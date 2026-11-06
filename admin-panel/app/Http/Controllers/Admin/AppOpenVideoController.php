<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppOpenVideo;
use App\Services\MediaStorage;
use App\Support\VideoOptimizer;
use Illuminate\Http\Request;

/** Admin: upload the video the restaurant or customer app plays when it opens. */
class AppOpenVideoController extends Controller
{
    public function index()
    {
        $videos = AppOpenVideo::withCount('views')->orderByDesc('sort_order')->orderByDesc('id')->get();

        return view('admin.app-open-videos.index', ['videos' => $videos]);
    }

    public function create()
    {
        return view('admin.app-open-videos.form');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['video_path'] = $this->storeVideo($request->file('video'));
        $data['is_active'] = $request->boolean('is_active');

        AppOpenVideo::create($data);

        $who = $data['audience'] === 'customer' ? 'Customers' : 'Restaurants';

        return redirect()->route('admin.app-open-videos.index')->with('success', "Video uploaded. {$who} will see it the next time they open the app.");
    }

    public function edit(AppOpenVideo $appOpenVideo)
    {
        return view('admin.app-open-videos.form', ['video' => $appOpenVideo]);
    }

    public function update(Request $request, AppOpenVideo $appOpenVideo)
    {
        $data = $this->validated($request, false);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('video')) {
            MediaStorage::delete($appOpenVideo->video_path);
            $data['video_path'] = $this->storeVideo($request->file('video'));
            // A new file is a new video: everyone gets to see it once.
            $appOpenVideo->views()->delete();
        }
        $appOpenVideo->update($data);

        return redirect()->route('admin.app-open-videos.index')->with('success', 'Video updated.');
    }

    public function destroy(AppOpenVideo $appOpenVideo)
    {
        MediaStorage::delete($appOpenVideo->video_path);
        $appOpenVideo->delete();

        return redirect()->route('admin.app-open-videos.index')->with('success', 'Video deleted.');
    }

    public function toggleStatus(AppOpenVideo $appOpenVideo)
    {
        $appOpenVideo->update(['is_active' => ! $appOpenVideo->is_active]);

        return back()->with('success', $appOpenVideo->is_active ? 'Video is now live.' : 'Video switched off.');
    }

    /** "Show it again to everyone" without uploading a new file. */
    public function resetViews(AppOpenVideo $appOpenVideo)
    {
        $appOpenVideo->views()->delete();

        return back()->with('success', 'Everyone will see this video once more.');
    }

    /** Stored as a phone-friendly, fast-start MP4 when ffmpeg is available (see VideoOptimizer). */
    private function storeVideo(\Illuminate\Http\UploadedFile $upload): string
    {
        $video = VideoOptimizer::optimize($upload);
        try {
            return MediaStorage::store($video, 'app-open-videos');
        } finally {
            if ($video !== $upload && VideoOptimizer::isTemporaryCopy($video, $upload)) {
                @unlink($video->getRealPath());
            }
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $v = $request->validate([
            'title' => 'required|string|max:150',
            'audience' => 'required|in:restaurant,customer',
            'video' => ($creating ? 'required' : 'nullable') . '|file|mimes:mp4,mov,m4v|max:51200',
            'skip_after_seconds' => 'nullable|integer|min:0|max:30',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        return [
            'title' => $v['title'],
            'audience' => $v['audience'],
            'skip_after_seconds' => (int) ($v['skip_after_seconds'] ?? 0),
            'starts_at' => $v['starts_at'] ?? null,
            'ends_at' => $v['ends_at'] ?? null,
            'sort_order' => (int) ($v['sort_order'] ?? 0),
        ];
    }
}
