<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppOpenVideo;
use App\Models\AppOpenVideoView;
use Illuminate\Http\Request;

/** The video an app plays on opening: the first current video this person has not seen yet. */
class AppOpenVideoController extends Controller
{
    public function show(Request $request)
    {
        return $this->playable('restaurant', $request->user()->id);
    }

    /**
     * Customer app. Open to guests too (the app plays it before anyone signs in). It plays on every app open, so
     * nothing is filtered by "seen".
     */
    public function showForCustomer(Request $request)
    {
        return $this->playable('customer', null);
    }

    private function playable(string $audience, ?int $userId)
    {
        $seen = $userId ? AppOpenVideoView::where('user_id', $userId)->pluck('app_open_video_id') : collect();

        $video = AppOpenVideo::current($audience)->whereNotIn('id', $seen)->orderBy('sort_order')->orderBy('id')->first();

        return response()->json([
            'success' => true,
            'data' => $video ? [
                'id' => $video->id,
                'key' => $video->versionKey(),
                'title' => $video->title,
                'url' => $video->url(),
                'skip_after_seconds' => $video->skip_after_seconds,
            ] : null,
        ]);
    }

    /** Watched to the end or skipped: either way it never plays again for this person. */
    public function viewed(Request $request, AppOpenVideo $video)
    {
        AppOpenVideoView::firstOrCreate(
            ['app_open_video_id' => $video->id, 'user_id' => $request->user()->id],
            ['skipped' => $request->boolean('skipped'), 'viewed_at' => now()]
        );

        return response()->json(['success' => true]);
    }
}
