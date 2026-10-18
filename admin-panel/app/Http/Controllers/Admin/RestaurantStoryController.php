<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantStory;
use App\Services\MediaStorage;
use Illuminate\Http\Request;

/**
 * Admin moderation: view every live story across all restaurants + delete
 * (takedown). No create/edit here -- stories are only ever posted by
 * restaurant owners via the app.
 */
class RestaurantStoryController extends Controller
{
    public function index(Request $request)
    {
        $stories = RestaurantStory::with('restaurant:id,name')
            ->live()
            ->orderByDesc('posted_at')
            ->paginate(30);

        return view('admin.restaurant-stories.index', compact('stories'));
    }

    public function destroy(RestaurantStory $restaurantStory)
    {
        MediaStorage::delete($restaurantStory->media_path);
        MediaStorage::delete($restaurantStory->thumbnail_path);
        $restaurantStory->delete();

        return redirect()->route('admin.restaurant-stories.index')
            ->with('success', 'Story removed.');
    }
}
