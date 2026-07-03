<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiningBooking;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class DiningBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = DiningBooking::with(['restaurant', 'user'])
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('restaurant_id'), fn ($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('booking_date', $request->date))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($nested) use ($search) {
                    $nested->where('booking_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', "%{$search}%"));
                });
            });

        $bookings = $query->latest('booking_date')->latest('booking_time')->paginate(20)->withQueryString();
        $restaurants = Restaurant::whereIn('restaurant_type', ['dining', 'both'])->orderBy('name')->get(['id', 'name']);
        $statusCounts = DiningBooking::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.dining-bookings.index', compact('bookings', 'restaurants', 'statusCounts'));
    }

    public function updateStatus(Request $request, DiningBooking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $payload = ['status' => $validated['status']];
        if ($validated['status'] === 'confirmed' && !$booking->confirmed_at) {
            $payload['confirmed_at'] = now();
        }
        if ($validated['status'] === 'cancelled') {
            $payload['cancelled_at'] = now();
            $payload['cancellation_reason'] = $validated['cancellation_reason'] ?? 'Updated by admin';
        }

        $booking->update($payload);

        return back()->with('success', 'Dining booking updated.');
    }
}
