<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DeliveryChargeSetting;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class DeliveryChargeController extends Controller
{
    public function index()
    {
        $settings = DeliveryChargeSetting::first();
        $restaurants = Restaurant::orderBy('name')->get();
        $deliveryAreas = DeliveryArea::active()->orderBy('name')->get();
        
        return view('admin.delivery-charges.index', compact('settings', 'restaurants', 'deliveryAreas'));
    }
    
    public function update(Request $request)
    {
        if ($request->has('restaurant_min_orders')) {
            $validated = $request->validate([
                'restaurant_min_orders' => 'array',
                'restaurant_min_orders.*' => 'nullable|numeric|min:0',
            ]);

            foreach ($validated['restaurant_min_orders'] ?? [] as $restaurantId => $minOrder) {
                Restaurant::where('id', $restaurantId)->update([
                    'min_order_amount' => $minOrder === null || $minOrder === ''
                        ? null
                        : $minOrder,
                ]);
            }

            return redirect()->back()->with(
                'success',
                'Restaurant minimum order amounts updated successfully!'
            );
        }

        $request->validate([
            'charge_type' => 'required|in:fixed,per_km',
            'base_charge' => 'required|numeric|min:0',
            'per_km_charge' => 'required_if:charge_type,per_km|numeric|min:0',
            'platform_fee' => 'nullable|numeric|min:0',
            'free_delivery_threshold' => 'nullable|numeric|min:0',
            'free_delivery_global' => 'boolean',
            'free_delivery_days' => 'nullable|array',
            'free_delivery_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'free_delivery_area_ids' => 'nullable|array',
            'free_delivery_area_ids.*' => 'integer|exists:delivery_areas,id',
            'admin_contribution_percent' => 'required|numeric|min:0|max:100',
            'restaurant_contribution_percent' => 'required|numeric|min:0|max:100',
        ]);
        
        $settings = DeliveryChargeSetting::updateOrCreate(
            ['id' => 1],
            [
                'charge_type' => $request->charge_type,
                'base_charge' => $request->base_charge,
                'per_km_charge' => $request->per_km_charge ?? 0,
                'platform_fee' => $request->platform_fee ?? 0,
                'free_delivery_threshold' => $request->free_delivery_threshold,
                'free_delivery_global' => $request->has('free_delivery_global'),
                'free_delivery_days' => array_values($request->input('free_delivery_days', [])),
                'free_delivery_area_ids' => array_values($request->input('free_delivery_area_ids', [])),
                'admin_contribution_percent' => $request->admin_contribution_percent,
                'restaurant_contribution_percent' => $request->restaurant_contribution_percent,
            ]
        );

        return redirect()->back()->with('success', 'Delivery charges updated successfully!');
    }
}
