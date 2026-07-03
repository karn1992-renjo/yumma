<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebVisitTrack;
use Illuminate\Http\Request;

class WebVisitTrackController extends Controller
{
    public function index(Request $request)
    {
        $query = WebVisitTrack::with('user:id,name,email,phone')
            ->latest();

        if ($request->filled('panel')) {
            $query->where('panel', $request->panel);
        }

        if ($request->filled('country')) {
            $query->where('country_code', strtoupper($request->country));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $tracks = $query->paginate(25)->withQueryString();

        $todayQuery = WebVisitTrack::whereDate('created_at', today());
        $summary = [
            'today' => (clone $todayQuery)->count(),
            'unique_sessions' => (clone $todayQuery)->distinct('session_id')->count('session_id'),
            'with_location' => (clone $todayQuery)->whereNotNull('latitude')->whereNotNull('longitude')->count(),
            'countries' => (clone $todayQuery)->whereNotNull('country_code')->distinct('country_code')->count('country_code'),
        ];

        $panels = WebVisitTrack::query()
            ->select('panel')
            ->whereNotNull('panel')
            ->distinct()
            ->orderBy('panel')
            ->pluck('panel');

        return view('admin.web-tracking.index', compact('tracks', 'summary', 'panels'));
    }
}
