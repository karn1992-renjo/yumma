<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantLocationChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestaurantApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = RestaurantLocationChangeRequest::with(['restaurant', 'requester', 'reviewer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20);

        return view('admin.restaurant-approvals.index', compact('requests'));
    }

    public function approve(Request $request, RestaurantLocationChangeRequest $locationRequest)
    {
        if ($locationRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been reviewed.');
        }

        DB::transaction(function () use ($request, $locationRequest) {
            $locationRequest->restaurant->update([
                'latitude' => $locationRequest->requested_latitude,
                'longitude' => $locationRequest->requested_longitude,
            ]);

            $locationRequest->update([
                'status' => 'approved',
                'admin_notes' => $request->input('admin_notes'),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Restaurant location change approved.');
    }

    public function document(RestaurantLocationChangeRequest $locationRequest)
    {
        $path = $locationRequest->fssai_license_path
            ? preg_replace('#^public/#', '', $locationRequest->fssai_license_path)
            : null;

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function reject(Request $request, RestaurantLocationChangeRequest $locationRequest)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($locationRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'This request has already been reviewed.');
        }

        $locationRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Restaurant location change rejected.');
    }
}
