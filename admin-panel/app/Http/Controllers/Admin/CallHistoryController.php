<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Illuminate\Http\Request;

class CallHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'in:dial_in,click_to_call'],
            'status' => ['nullable', 'string', 'max:32'],
            'provider' => ['nullable', 'in:exotel,raw_fallback'],
        ]);

        $logs = CallLog::query()
            ->with('order:id,order_number')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"));
            })
            ->when($filters['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn ($query, $provider) => $query->where('provider_used', $provider))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => CallLog::count(),
            'today' => CallLog::whereDate('created_at', today())->count(),
            'raw_fallback' => CallLog::where('provider_used', 'raw_fallback')->count(),
        ];

        return view('admin.call-history.index', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }
}
