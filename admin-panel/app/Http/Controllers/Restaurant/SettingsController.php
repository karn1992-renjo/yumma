<?php
// app/Http/Controllers/Restaurant/SettingsController.php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    /**
     * Show restaurant profile settings.
     */
    public function index()
    {
        $restaurant = $this->getCurrentRestaurant();

        return view('restaurant.settings.index', compact('restaurant'));
    }

    /**
     * Update restaurant profile settings.
     */
    public function update(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'pincode' => 'nullable|string|max:20',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'address' => 'required|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($restaurant->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . $restaurant->id;
        }

        $restaurant->update($validated);

        return redirect()->route('restaurant.settings.index')
            ->with('success', 'Restaurant settings updated successfully!');
    }

    /**
     * Show day-wise timing settings
     */
    public function timing()
    {
        $restaurant = $this->getCurrentRestaurant();
        
        // Initialize weekly timings if not set
        if (!$restaurant->weekly_timings) {
            $restaurant->weekly_timings = Restaurant::getDefaultWeeklyTimings();
        }
        
        $days = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
        
        $timezone = $restaurant->timezone ?? 'Asia/Kolkata';
        
        return view('restaurant.settings.timing', compact('restaurant', 'days', 'timezone'));
    }

    /**
     * Update day-wise timing settings
     */
    public function updateTiming(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        $validated = $request->validate([
            'timezone' => 'required|string|timezone',
            'auto_accept_orders' => 'boolean',
            'order_processing_type' => 'required|in:after_restaurant_accept,only_if_driver_available',
            'timings' => 'required|array',
            'timings.*.is_open' => 'boolean',
            'timings.*.open_time' => 'nullable|date_format:H:i',
            'timings.*.close_time' => 'nullable|date_format:H:i',
            'timings.*.break_start' => 'nullable|date_format:H:i',
            'timings.*.break_end' => 'nullable|date_format:H:i',
        ]);
        
        // Prepare weekly timings
        $weeklyTimings = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $dayData = $request->input("timings.{$day}", []);
            $isOpen = isset($dayData['is_open']) ? (bool)$dayData['is_open'] : false;

            if ($isOpen && (empty($dayData['open_time']) || empty($dayData['close_time']))) {
                return back()
                    ->withInput()
                    ->withErrors(["timings.{$day}.open_time" => ucfirst($day) . ' requires opening and closing times.']);
            }

            if ((!empty($dayData['break_start']) && empty($dayData['break_end'])) || (empty($dayData['break_start']) && !empty($dayData['break_end']))) {
                return back()
                    ->withInput()
                    ->withErrors(["timings.{$day}.break_start" => ucfirst($day) . ' requires both break start and break end times.']);
            }
            
            $weeklyTimings[$day] = [
                'is_open' => $isOpen,
                'open_time' => $dayData['open_time'] ?? '09:00',
                'close_time' => $dayData['close_time'] ?? '22:00',
                'break_start' => $dayData['break_start'] ?? null,
                'break_end' => $dayData['break_end'] ?? null,
            ];
        }
        
        // Update restaurant
        $restaurant->update([
            'weekly_timings' => $weeklyTimings,
            'timezone' => $validated['timezone'],
            'auto_accept_orders' => $request->has('auto_accept_orders'),
        ]);
        
        // Update order processing type session
        if ($request->order_processing_type) {
            session(['order_processing_type' => $request->order_processing_type]);
        }
        
        // Update is_open status based on current time if needed
        $restaurant->is_open = $restaurant->shouldBeOpenNow();
        $restaurant->save();
        
        return redirect()->route('restaurant.settings.timing')
            ->with('success', 'Weekly timing settings updated successfully!');
    }

    /**
     * Copy timing from one day to another
     */
    public function copyTimings(Request $request)
    {
        $request->validate([
            'from_day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'to_days' => 'required|array',
            'to_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);
        
        $restaurant = $this->getCurrentRestaurant();
        $weeklyTimings = $restaurant->weekly_timings ?? Restaurant::getDefaultWeeklyTimings();
        
        $sourceTimings = $weeklyTimings[$request->from_day] ?? null;
        
        if (!$sourceTimings) {
            return response()->json(['success' => false, 'message' => 'Source day not found'], 404);
        }
        
        foreach ($request->to_days as $toDay) {
            $weeklyTimings[$toDay] = $sourceTimings;
        }
        
        $restaurant->update(['weekly_timings' => $weeklyTimings]);
        
        return response()->json([
            'success' => true,
            'message' => 'Timings copied successfully'
        ]);
    }

    /**
     * Apply same timing for all weekdays (Monday-Friday)
     */
    public function applyWeekdayTimings(Request $request)
    {
        $request->validate([
            'open_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'apply_to_weekend' => 'boolean',
        ]);
        
        $restaurant = $this->getCurrentRestaurant();
        $weeklyTimings = $restaurant->weekly_timings ?? Restaurant::getDefaultWeeklyTimings();
        
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $daysToApply = $weekdays;
        
        if ($request->apply_to_weekend) {
            $daysToApply = array_merge($daysToApply, ['saturday', 'sunday']);
        }
        
        $timingData = [
            'is_open' => true,
            'open_time' => $request->open_time,
            'close_time' => $request->close_time,
            'break_start' => $request->break_start,
            'break_end' => $request->break_end,
        ];
        
        foreach ($daysToApply as $day) {
            $weeklyTimings[$day] = $timingData;
        }
        
        $restaurant->update(['weekly_timings' => $weeklyTimings]);
        
        return response()->json([
            'success' => true,
            'message' => 'Weekday timings applied successfully'
        ]);
    }

    /**
     * Reset weekly timings to the default schedule.
     */
    public function resetTimings()
    {
        $restaurant = $this->getCurrentRestaurant();

        $restaurant->update([
            'weekly_timings' => Restaurant::getDefaultWeeklyTimings(),
            'is_open' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Weekly timings reset successfully'
        ]);
    }

    public function goOffline(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        $request->validate([
            'reason' => 'required|string',
            'sub_reason' => 'nullable|string',
        ]);
        
        $restaurant->update([
            'is_open' => false,
            'offline_reason' => [
                'reason' => $request->reason,
                'sub_reason' => $request->sub_reason,
                'set_at' => now()
            ]
        ]);
        
        return response()->json(['success' => true]);
    }

    public function goOnline(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        // Check if restaurant should be open based on day-wise timing
        if (!$restaurant->isOpenNow()) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant is closed as per your weekly timing settings for the current day and time.'
            ], 400);
        }
        
        $restaurant->update([
            'is_open' => true,
            'offline_reason' => null
        ]);
        
        return response()->json(['success' => true]);
    }

    protected function getCurrentRestaurant()
    {
        $user = Auth::user();
        
        if ($user->current_restaurant_id) {
            return $user->restaurants()->findOrFail($user->current_restaurant_id);
        }
        
        return $user->restaurants()->firstOrFail();
    }
}
