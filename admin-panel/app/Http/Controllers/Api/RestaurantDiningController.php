<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\FirebaseHelper;
use App\Models\DiningBooking;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RestaurantDiningController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function getRestaurantId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $selectedId = request()->input('restaurant_id');
        if ($selectedId && $selectedId !== 'all') {
            if ($user->restaurants()->whereKey((int) $selectedId)->exists()) {
                return (int) $selectedId;
            }

            $staffRestaurant = $user->restaurantStaff()->with('restaurant')->first()?->restaurant;
            if ($staffRestaurant && (int) $staffRestaurant->id === (int) $selectedId) {
                return (int) $selectedId;
            }

            return null;
        }

        return $user->activeRestaurant()?->id;
    }

    /**
     * Get dining bookings for restaurant owner's restaurant
     */
    public function getDiningBookings(Request $request)
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $query = DiningBooking::where('restaurant_id', $restaurantId);

        // Filter by status if provided
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->where('booking_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->where('booking_date', '<=', $request->to_date);
        }

        // Filter by booking_date
        if ($request->has('date') && !empty($request->date)) {
            $query->where('booking_date', $request->date);
        }

        $bookings = $query->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc')
            ->with(['user', 'restaurant'])
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * Get single booking details
     */
    public function getBookingDetails($id)
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $booking = DiningBooking::where('restaurant_id', $restaurantId)
            ->with(['user', 'restaurant'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $booking]);
    }

    /**
     * Confirm a pending dining booking
     */
    public function confirmBooking($id)
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $booking = DiningBooking::where('restaurant_id', $restaurantId)
            ->where('status', 'pending')
            ->findOrFail($id);

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->notifyCustomer(
            $booking->fresh(['user', 'restaurant']),
            'Dining booking confirmed',
            "Your table booking at {$booking->restaurant?->name} has been confirmed.",
            'DINING_BOOKING_CONFIRMED'
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed successfully',
            'data' => $booking
        ]);
    }

    /**
     * Reject a pending dining booking
     */
    public function rejectBooking($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $booking = DiningBooking::where('restaurant_id', $restaurantId)
            ->where('status', 'pending')
            ->findOrFail($id);

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->reason,
        ]);

        $this->notifyCustomer(
            $booking->fresh(['user', 'restaurant']),
            'Dining booking rejected',
            "Your table booking at {$booking->restaurant?->name} was rejected: {$request->reason}",
            'DINING_BOOKING_REJECTED'
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking rejected',
            'data' => $booking
        ]);
    }

    /**
     * Mark booking as completed
     */
    public function completeBooking($id)
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $booking = DiningBooking::where('restaurant_id', $restaurantId)
            ->where('status', 'confirmed')
            ->findOrFail($id);

        $booking->update([
            'status' => 'completed',
        ]);

        $this->notifyCustomer(
            $booking->fresh(['user', 'restaurant']),
            'How was your dining experience?',
            "Please rate your visit to {$booking->restaurant?->name}.",
            'DINING_BOOKING_COMPLETED'
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking marked as completed',
            'data' => $booking
        ]);
    }

    /**
     * Get dining statistics for restaurant
     */
    public function getDiningStats(Request $request)
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $fromDate = $request->from_date ?? now()->subDays(30);
        $toDate = $request->to_date ?? now();

        $stats = [
            'total_bookings' => DiningBooking::where('restaurant_id', $restaurantId)
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->count(),
            'confirmed_bookings' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'confirmed')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->count(),
            'pending_bookings' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'pending')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->count(),
            'completed_bookings' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->count(),
            'cancelled_bookings' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'cancelled')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->count(),
            'total_guests' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->sum('number_of_guests'),
            'average_rating' => DiningBooking::where('restaurant_id', $restaurantId)
                ->whereNotNull('rating')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->avg('rating'),
            'total_revenue' => DiningBooking::where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('booking_date', [$fromDate, $toDate])
                ->sum('booking_charge'),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Get upcoming bookings (next 30 days)
     */
    public function getUpcomingBookings()
    {
        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $bookings = DiningBooking::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('booking_date', '>=', now()->toDateString())
            ->orderBy('booking_date', 'asc')
            ->orderBy('booking_time', 'asc')
            ->with(['user'])
            ->take(10)
            ->get();

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * Update dining settings for restaurant
     */
    public function updateDiningSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dining_charge' => 'required|numeric|min:0',
            'max_guests_per_booking' => 'nullable|integer|min:1|max:100',
            'min_advance_booking_hours' => 'nullable|integer|min:0',
            'max_advance_booking_days' => 'nullable|integer|min:1',
            'accepts_dining' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $restaurantId = $this->getRestaurantId();

        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'No restaurant found'], 404);
        }

        $restaurant = Restaurant::findOrFail($restaurantId);

        $updatedData = [];
        
        if ($request->has('dining_charge')) {
            $updatedData['dining_charge'] = $request->dining_charge;
        }

        if ($request->has('accepts_dining')) {
            $currentType = $restaurant->restaurant_type ?? 'delivery';
            if ($request->accepts_dining && !in_array($currentType, ['dining', 'both'])) {
                $updatedData['restaurant_type'] = 'both';
            } elseif (!$request->accepts_dining && $currentType === 'both') {
                $updatedData['restaurant_type'] = 'delivery';
            } elseif (!$request->accepts_dining && $currentType === 'dining') {
                $updatedData['restaurant_type'] = 'delivery';
            }
        }

        // Store other settings in a meta field or settings column
        if ($request->has('max_guests_per_booking') || $request->has('min_advance_booking_hours') || $request->has('max_advance_booking_days')) {
            $settings = is_array($restaurant->dining_settings)
                ? $restaurant->dining_settings
                : json_decode($restaurant->dining_settings ?? '{}', true);
            if (!is_array($settings)) {
                $settings = [];
            }
            
            if ($request->has('max_guests_per_booking')) {
                $settings['max_guests_per_booking'] = $request->max_guests_per_booking;
            }
            if ($request->has('min_advance_booking_hours')) {
                $settings['min_advance_booking_hours'] = $request->min_advance_booking_hours;
            }
            if ($request->has('max_advance_booking_days')) {
                $settings['max_advance_booking_days'] = $request->max_advance_booking_days;
            }
            
            $updatedData['dining_settings'] = $settings;
        }

        $restaurant->update($updatedData);

        return response()->json([
            'success' => true,
            'message' => 'Dining settings updated successfully',
            'data' => $restaurant
        ]);
    }

    private function notifyCustomer(DiningBooking $booking, string $title, string $body, string $type): void
    {
        try {
            if (!$booking->user?->fcm_token) {
                return;
            }

            (new FirebaseHelper())->sendToDevice(
                $booking->user->fcm_token,
                $title,
                $body,
                [
                    'type' => $type,
                    'booking_id' => (string) $booking->id,
                    'booking_number' => (string) $booking->booking_number,
                    'restaurant_id' => (string) $booking->restaurant_id,
                    'status' => (string) $booking->status,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Dining booking customer notification failed: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
                'type' => $type,
            ]);
        }
    }
}
