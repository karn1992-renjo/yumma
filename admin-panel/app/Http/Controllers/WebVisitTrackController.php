<?php

namespace App\Http\Controllers;

use App\Models\WebVisitTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WebVisitTrackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:40'],
            'panel' => ['nullable', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:2048'],
            'path' => ['nullable', 'string', 'max:1024'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:120'],
            'local_time' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $sessionId = $validated['session_id'] ?? $request->session()->getId();
        $path = $validated['path'] ?? parse_url($validated['url'], PHP_URL_PATH) ?? '/';
        $cacheKey = 'web_visit_track:' . sha1($sessionId . '|' . $path . '|' . ($validated['panel'] ?? 'web'));

        if (Cache::has($cacheKey)) {
            if (isset($validated['latitude'], $validated['longitude'])) {
                $track = WebVisitTrack::query()
                    ->where('session_id', $sessionId)
                    ->where('path', $path)
                    ->where('panel', $validated['panel'] ?? 'web')
                    ->latest()
                    ->first();

                $track?->update([
                        'latitude' => $validated['latitude'],
                        'longitude' => $validated['longitude'],
                        'location_accuracy' => $validated['location_accuracy'] ?? null,
                        'metadata' => array_merge($track->metadata ?? [], $validated['metadata'] ?? []),
                    ]);
            }

            return response()->json(['success' => true, 'tracked' => false]);
        }

        Cache::put($cacheKey, true, now()->addMinutes(10));

        WebVisitTrack::create([
            ...$validated,
            'session_id' => $sessionId,
            'path' => $path,
            'source' => $validated['source'] ?? 'web',
            'user_id' => optional($request->user())->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'local_time' => isset($validated['local_time'])
                ? Carbon::parse($validated['local_time'])
                : null,
        ]);

        return response()->json(['success' => true, 'tracked' => true]);
    }
}
