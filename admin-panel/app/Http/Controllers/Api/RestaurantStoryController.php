<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AppSetting;
use App\Models\RestaurantStory;
use App\Services\MediaStorage;
use App\Services\StoriesAccessService;
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
            'data' => $stories->map(fn (RestaurantStory $story) => $this->formatStory($story)),
        ]);
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
        ]);

        $maxSeconds = (int) AppSetting::getValue('story_video_max_seconds', 60);
        if ($validated['media_type'] === 'video' && (int) ($validated['duration_seconds'] ?? 0) > $maxSeconds) {
            return response()->json([
                'success' => false,
                'message' => "Video stories can't be longer than {$maxSeconds} seconds.",
            ], 422);
        }

        $mediaPath = MediaStorage::store($request->file('media'), 'restaurant-stories');
        $thumbnailPath = $request->hasFile('thumbnail')
            ? MediaStorage::store($request->file('thumbnail'), 'restaurant-stories/thumbnails')
            : null;

        $story = RestaurantStory::create([
            'restaurant_id' => $restaurant->id,
            'media_path' => $mediaPath,
            'media_type' => $validated['media_type'],
            'thumbnail_path' => $thumbnailPath,
            'caption' => $validated['caption'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'posted_at' => now(),
            'expires_at' => now()->addHours(24),
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
            'duration_seconds' => $story->duration_seconds,
            'posted_at' => $story->posted_at->toIso8601String(),
            'expires_at' => $story->expires_at->toIso8601String(),
            'view_count' => $story->view_count,
        ];
    }
}
