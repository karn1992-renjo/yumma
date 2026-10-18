<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantStory;
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
    public function show(Request $request, Restaurant $restaurant)
    {
        if ($restaurant->isHiddenFromDiscovery()) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $userId = $request->user()?->id;
        $stories = RestaurantStory::where('restaurant_id', $restaurant->id)
            ->live()
            ->orderBy('posted_at')
            ->get();

        $seenStoryIds = $userId
            ? RestaurantStoryView::where('user_id', $userId)
                ->whereIn('restaurant_story_id', $stories->pluck('id'))
                ->pluck('restaurant_story_id')
                ->all()
            : [];

        return response()->json([
            'success' => true,
            'data' => $stories->map(fn (RestaurantStory $story) => [
                'id' => $story->id,
                'media_type' => $story->media_type,
                'media_url' => $story->mediaUrl(),
                'thumbnail_url' => $story->thumbnailUrl(),
                'caption' => $story->caption,
                'duration_seconds' => $story->duration_seconds,
                'posted_at' => $story->posted_at->toIso8601String(),
                'seen' => in_array($story->id, $seenStoryIds, true),
            ]),
        ]);
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
