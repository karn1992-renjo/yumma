<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AppSetting;
use App\Models\RestaurantStory;
use App\Services\MediaStorage;
use App\Services\StoriesAccessService;
use App\Support\VideoOptimizer;
use Illuminate\Http\Request;

/**
 * Restaurant-owner-facing story management (upload/list/delete). The
 * customer-facing read side (viewing a restaurant's stories, marking one
 * viewed) lives in Api\StoryViewController -- different auth context and
 * response shape, not worth forcing into one controller.
 */
class RestaurantStoryController extends Controller
{
    use ResolvesRestaurantContext;

    public function index(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $stories = RestaurantStory::where('restaurant_id', $restaurant->id)
            ->live()
            ->orderByDesc('posted_at')
            ->get();

        return response()->json([
            'success' => true,
            'can_post' => app(StoriesAccessService::class)->canPost($restaurant),
            'limits' => app(StoriesAccessService::class)->limits($restaurant),
            'can_link_menu' => app(StoriesAccessService::class)->canLinkMenu($restaurant),
            'data' => $stories->map(fn (RestaurantStory $story) => $this->formatStory($story)),
        ]);
    }

    /** The restaurant's available dishes, for the "link a menu item" picker. */
    public function menuItems()
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $items = \App\Models\MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->orderBy('name')->limit(300)->get()
            ->map(fn (\App\Models\MenuItem $i) => [
                'id' => $i->id, 'name' => $i->name, 'price' => round((float) $i->getFinalPriceAttribute(), 2), 'image_url' => $i->image_url, 'is_veg' => (bool) $i->is_veg,
            ])->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, StoriesAccessService $access)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        if (! $access->canPost($restaurant)) {
            return response()->json([
                'success' => false,
                'message' => 'Stories isn\'t included in your current plan. Subscribe to a plan with Stories, or buy the Stories add-on.',
            ], 403);
        }

        $limits = $access->limits($restaurant);
        if ($limits['left_today'] !== null && $limits['left_today'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => "You've reached today's limit of {$limits['per_day']} " . ($limits['per_day'] === 1 ? 'story' : 'stories') . ' on your plan. Try again tomorrow, or upgrade your plan.',
            ], 429);
        }

        $validated = $request->validate([
            'media_type' => 'required|in:image,video',
            'media' => $request->input('media_type') === 'video'
                ? 'required|file|mimes:mp4,mov,m4v|max:51200'
                : 'required|image|mimes:jpeg,png,jpg,webp|max:8192',
            'thumbnail' => $request->input('media_type') === 'video'
                ? 'required|image|mimes:jpeg,png,jpg,webp|max:2048'
                : 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'caption' => 'nullable|string|max:150',
            'duration_seconds' => 'nullable|integer|min:1|max:600',
            'menu_item_id' => 'nullable|integer',
            'cta_label' => 'nullable|string|max:24',
            'layout' => 'nullable|in:' . implode(',', RestaurantStory::LAYOUTS),
        ]);

        $menuItemId = null;
        if (! empty($validated['menu_item_id'])) {
            if (! $access->canLinkMenu($restaurant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Linking a menu item to a story isn\'t included in your plan. Upgrade to a plan that includes it.',
                ], 403);
            }
            $item = \App\Models\MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->find($validated['menu_item_id']);
            if (! $item) {
                return response()->json(['success' => false, 'message' => 'Choose an available menu item from your own menu.'], 422);
            }
            $menuItemId = $item->id;
        }

        $maxSeconds = (int) AppSetting::getValue('story_video_max_seconds', 60);
        if ($validated['media_type'] === 'video' && (int) ($validated['duration_seconds'] ?? 0) > $maxSeconds) {
            return response()->json([
                'success' => false,
                'message' => "Video stories can't be longer than {$maxSeconds} seconds.",
            ], 422);
        }

        $upload = $request->file('media');
        $media = $validated['media_type'] === 'video' ? VideoOptimizer::optimize($upload) : $upload;
        try {
            $mediaPath = MediaStorage::store($media, 'restaurant-stories');
        } finally {
            if ($media !== $upload && VideoOptimizer::isTemporaryCopy($media, $upload)) {
                @unlink($media->getRealPath());
            }
        }
        $thumbnailPath = $request->hasFile('thumbnail')
            ? MediaStorage::store($request->file('thumbnail'), 'restaurant-stories/thumbnails')
            : null;

        $story = RestaurantStory::create([
            'restaurant_id' => $restaurant->id,
            'menu_item_id' => $menuItemId,
            'cta_label' => $menuItemId ? (trim((string) ($validated['cta_label'] ?? '')) ?: 'Order now') : null,
            'layout' => $validated['layout'] ?? 'classic',
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'],
            'thumbnail_path' => $thumbnailPath,
            'caption' => $validated['caption'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'posted_at' => now(),
            'expires_at' => now()->addHours($limits['hours']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatStory($story),
        ], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $story = RestaurantStory::where('restaurant_id', $restaurant->id)->find($id);
        if (! $story) {
            return response()->json(['success' => false, 'message' => 'Story not found.'], 404);
        }

        MediaStorage::delete($story->media_path);
        MediaStorage::delete($story->thumbnail_path);
        $story->delete();

        return response()->json(['success' => true, 'message' => 'Story deleted.']);
    }

    private function formatStory(RestaurantStory $story): array
    {
        return [
            'id' => $story->id,
            'media_type' => $story->media_type,
            'media_url' => $story->mediaUrl(),
            'thumbnail_url' => $story->thumbnailUrl(),
            'caption' => $story->caption,
            'layout' => $story->layout ?: 'classic',
            'cta_label' => $story->menu_item_id ? ($story->cta_label ?: 'Order now') : null,
            'menu_item' => $story->menuItemCard(),
            'duration_seconds' => $story->duration_seconds,
            'posted_at' => $story->posted_at->toIso8601String(),
            'expires_at' => $story->expires_at->toIso8601String(),
            'view_count' => $story->view_count,
            'like_count' => (int) $story->like_count,
            'share_count' => (int) $story->share_count,
        ];
    }
}
