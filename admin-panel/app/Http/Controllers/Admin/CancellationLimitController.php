<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderCancellationLimit;
use Illuminate\Http\Request;

class CancellationLimitController extends Controller
{
    public function index()
    {
        $restaurantLimit = OrderCancellationLimit::where('type', 'restaurant')->first();
        $customerLimit = OrderCancellationLimit::where('type', 'customer')->first();
        $driverLimit = OrderCancellationLimit::where('type', 'delivery_partner')->first();
        
        return view('admin.cancellation-limits.index', compact('restaurantLimit', 'customerLimit', 'driverLimit'));
    }
    
    public function update(Request $request)
    {
        $request->validate([
            'restaurant_warning' => 'required|numeric|min:0|max:100',
            'restaurant_penalty' => 'required|numeric|min:0|max:100',
            'restaurant_penalty_amount' => 'required|numeric|min:0',
            'restaurant_cancellation_window_minutes' => 'required|integer|min:0|max:1440',
            'restaurant_auto_disable' => 'boolean',
            'customer_cancellation_window_minutes' => 'required|integer|min:0|max:1440',
            'driver_warning' => 'required|numeric|min:0|max:100',
            'driver_penalty' => 'required|numeric|min:0|max:100',
            'driver_penalty_amount' => 'required|numeric|min:0',
            'driver_auto_disable' => 'boolean',
        ]);
        
        OrderCancellationLimit::updateOrCreate(
            ['type' => 'restaurant'],
            [
                'warning_threshold' => $request->restaurant_warning,
                'penalty_threshold' => $request->restaurant_penalty,
                'penalty_amount' => $request->restaurant_penalty_amount,
                'cancellation_window_minutes' => $request->restaurant_cancellation_window_minutes,
                'auto_disable' => $request->has('restaurant_auto_disable'),
            ]
        );

        OrderCancellationLimit::updateOrCreate(
            ['type' => 'customer'],
            [
                'warning_threshold' => 0,
                'penalty_threshold' => 0,
                'penalty_amount' => 0,
                'cancellation_window_minutes' => $request->customer_cancellation_window_minutes,
                'auto_disable' => false,
            ]
        );
        
        OrderCancellationLimit::updateOrCreate(
            ['type' => 'delivery_partner'],
            [
                'warning_threshold' => $request->driver_warning,
                'penalty_threshold' => $request->driver_penalty,
                'penalty_amount' => $request->driver_penalty_amount,
                'auto_disable' => $request->has('driver_auto_disable'),
            ]
        );
        
        return redirect()->back()->with('success', 'Cancellation limits updated successfully!');
    }
}
