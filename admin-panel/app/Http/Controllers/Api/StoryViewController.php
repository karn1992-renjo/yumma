<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantStory;
use App\Models\RestaurantStoryLike;
use App\Models\RestaurantStoryView;
use Illuminate\Http\Request;

/**
 * Customer-facing read side of Stories: fetching a restaurant's current
 * story reel for the full-screen viewer, and marking a segment viewed (the
 * same action that drives the home rail's seen/unseen ring -- see
 * HomeSectionService's 'restaurant_stories' section).
 */
class StoryViewController extends Controller
{
    public function show(Request $request, string $restaurant)
    {
        // The home rail only carries the numeric id; links elsewhere use the slug.
        $restaurant = Restaurant::query()
            ->where(ctype_digit($restaurant) ? 'id' : 'slug', $restaurant)
            ->firstOrFail();

        if ($restaurant->isHiddenFromDiscovery()) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $userId = $request->user('sanctum')?->id;
        $stories = RestaurantStory::where('restaurant_id', $restaurant->id)
            ->with('menuItem')
            ->live()
            ->orderBy('posted_at')
            ->get();

        $seenStoryIds = $userId
            ? RestaurantStoryView::where('user_id', $userId)
                ->whereIn('restaurant_story_id', $stories->pluck('id'))
                ->pluck('restaurant_story_id')
                ->all()
            : [];

        $likedStoryIds = $userId
            ? RestaurantStoryLike::where('user_id', $userId)
                ->whereIn('restaurant_story_id', $stories->pluck('id'))
                ->pluck('restaurant_story_id')
                ->all()
            : [];

        return response()->json([
            'success' => true,
            'data' => $stories->map(fn (RestaurantStory $story) => [
                'liked' => in_array($story->id, $likedStoryIds, true),
                'like_count' => (int) $story->like_count,
                'share_count' => (int) $story->share_count,
                'restaurant_slug' => $restaurant->slug,
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
                'seen' => in_array($story->id, $seenStoryIds, true),
            ]),
        ]);
    }

    public function like(Request $request, RestaurantStory $story)
    {
        $like = RestaurantStoryLike::firstOrCreate(
            ['restaurant_story_id' => $story->id, 'user_id' => $request->user()->id],
            ['liked_at' => now()]
        );

        if ($like->wasRecentlyCreated) {
            $story->increment('like_count');
        }

        return response()->json(['success' => true, 'liked' => true, 'like_count' => (int) $story->fresh()->like_count]);
    }

    public function unlike(Request $request, RestaurantStory $story)
    {
        $removed = RestaurantStoryLike::where('restaurant_story_id', $story->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($removed) {
            $story->decrement('like_count');
        }

        return response()->json(['success' => true, 'liked' => false, 'like_count' => max(0, (int) $story->fresh()->like_count)]);
    }

    /** Counts a share. Failures are harmless -- the share itself already happened. */
    public function shared(RestaurantStory $story)
    {
        $story->increment('share_count');

        return response()->json(['success' => true]);
    }

    public function markViewed(Request $request, RestaurantStory $story)
    {
        $userId = $request->user()->id;

        $view = RestaurantStoryView::firstOrCreate(
            ['restaurant_story_id' => $story->id, 'user_id' => $userId],
            ['viewed_at' => now()]
        );

        if ($view->wasRecentlyCreated) {
            $story->increment('view_count');
        }

        return response()->json(['success' => true]);
    }
}
