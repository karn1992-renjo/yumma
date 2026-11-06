<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DeliveryChargeSetting;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryChargeController extends Controller
{
    public function index()
    {
        $settings = DeliveryChargeSetting::query()->oldest('id')->first();
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

        $validated = $request->validate([
            'charge_type' => 'required|in:fixed,per_km',
            'base_charge' => 'required|numeric|min:0',
            'per_km_charge' => 'required_if:charge_type,per_km|numeric|min:0',
            'platform_fee' => 'nullable|numeric|min:0',
            'order_acceptance_timeout_seconds' => 'required|integer|min:30|max:600',
            'zone_free_delivery' => 'nullable|array',
            'zone_free_delivery.*.enabled' => 'nullable|boolean',
            'zone_free_delivery.*.threshold' => 'nullable|numeric|min:0',
            'admin_contribution_percent' => 'required|numeric|min:0|max:100',
            'restaurant_contribution_percent' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated['zone_free_delivery'] ?? [] as $areaId => $freeDelivery) {
            if (filter_var($freeDelivery['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && (($freeDelivery['threshold'] ?? null) === null || ($freeDelivery['threshold'] ?? '') === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "zone_free_delivery.$areaId.threshold" => 'Enter the free delivery amount for enabled zones.',
                ]);
            }
        }

        $contributionTotal = round(
            (float) $validated['admin_contribution_percent']
            + (float) $validated['restaurant_contribution_percent'],
            2
        );
        if ($contributionTotal !== 100.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'admin_contribution_percent' => 'Admin and restaurant contribution must total 100%.',
                'restaurant_contribution_percent' => 'Admin and restaurant contribution must total 100%.',
            ]);
        }
        
        DB::transaction(function () use ($request, $validated) {
            $settings = DeliveryChargeSetting::query()->oldest('id')->lockForUpdate()->first()
                ?? new DeliveryChargeSetting();

            $settings->fill([
                'charge_type' => $validated['charge_type'],
                'base_charge' => $validated['base_charge'],
                'per_km_charge' => $validated['per_km_charge'] ?? 0,
                'platform_fee' => $validated['platform_fee'] ?? 0,
                'order_acceptance_timeout_seconds' => $validated['order_acceptance_timeout_seconds'],
                'free_delivery_threshold' => null,
                'free_delivery_global' => false,
                'free_delivery_days' => [],
                'free_delivery_area_ids' => [],
                'admin_contribution_percent' => $validated['admin_contribution_percent'],
                'restaurant_contribution_percent' => $validated['restaurant_contribution_percent'],
            ])->save();

            DeliveryChargeSetting::query()->whereKeyNot($settings->getKey())->delete();

            foreach ($validated['zone_free_delivery'] ?? [] as $areaId => $freeDelivery) {
                DeliveryArea::whereKey($areaId)->update([
                    'free_delivery_enabled' => filter_var($freeDelivery['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'free_delivery_threshold' => ($freeDelivery['threshold'] ?? null) === null || ($freeDelivery['threshold'] ?? '') === ''
                        ? null
                        : $freeDelivery['threshold'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Delivery charges updated successfully!');
    }
}
