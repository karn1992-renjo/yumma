<?php
// app/Http/Controllers/Admin/DeliveryAreaController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use Illuminate\Http\Request;

class DeliveryAreaController extends Controller
{
    private function polygonCenter(array $coordinates): array
    {
        $validPoints = collect($coordinates)
            ->filter(fn ($point) => is_array($point)
                && isset($point['lat'], $point['lng'])
                && is_numeric($point['lat'])
                && is_numeric($point['lng']))
            ->map(fn ($point) => [
                'lat' => max(-90, min(90, (float) $point['lat'])),
                'lng' => max(-180, min(180, (float) $point['lng'])),
            ])
            ->values();

        if ($validPoints->isEmpty()) {
            return ['lat' => 0.0, 'lng' => 0.0];
        }

        return [
            'lat' => round($validPoints->avg('lat'), 8),
            'lng' => round($validPoints->avg('lng'), 8),
        ];
    }

    private function normalizePolygonCoordinates($coordinates): array
    {
        return collect($coordinates)
            ->filter(fn ($point) => is_array($point)
                && isset($point['lat'], $point['lng'])
                && is_numeric($point['lat'])
                && is_numeric($point['lng']))
            ->map(fn ($point) => [
                'lat' => max(-90, min(90, (float) $point['lat'])),
                'lng' => max(-180, min(180, (float) $point['lng'])),
            ])
            ->values()
            ->all();
    }

    public function index()
    {
        $deliveryAreas = DeliveryArea::orderBy('name')->paginate(20);
        return view('admin.delivery-areas.index', compact('deliveryAreas'));
    }
    
    public function create()
    {
        return view('admin.delivery-areas.create');
    }
    
    public function store(Request $request)
    {
        if ($request->area_type === 'polygon') {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'area_type' => 'required|in:circle,polygon',
                'max_daily_bookings' => 'required|integer|min:0',
                'is_active' => 'sometimes|boolean',
                'polygon_coordinates' => 'required',
            ]);
            
            $coordinates = json_decode($request->polygon_coordinates, true);
            $coordinates = $this->normalizePolygonCoordinates($coordinates ?: []);
            if (count($coordinates) < 3) {
                return back()->withInput()->withErrors([
                    'polygon_coordinates' => 'Please draw a polygon with at least 3 points.'
                ]);
            }
            $center = $this->polygonCenter($coordinates);
            
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'area_type' => 'polygon',
                'max_daily_bookings' => $validated['max_daily_bookings'],
                'is_active' => $request->boolean('is_active'),
                'polygon_coordinates' => json_encode($coordinates),
                'latitude' => $center['lat'],
                'longitude' => $center['lng'],
                'radius_km' => 0,
            ];
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'area_type' => 'required|in:circle,polygon',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'required|numeric|min:0.1|max:200',
                'max_daily_bookings' => 'required|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ]);
            
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'area_type' => 'circle',
                'max_daily_bookings' => $validated['max_daily_bookings'],
                'is_active' => $request->boolean('is_active'),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'radius_km' => $validated['radius_km'],
                'polygon_coordinates' => null,
            ];
        }
        
        DeliveryArea::create($data);
        
        return redirect()->route('admin.delivery-areas.index')
            ->with('success', 'Delivery area created successfully.');
    }
    
    public function edit(DeliveryArea $deliveryArea)
    {
        return view('admin.delivery-areas.edit', compact('deliveryArea'));
    }
    
    public function update(Request $request, DeliveryArea $deliveryArea)
    {
        if ($request->area_type === 'polygon') {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'area_type' => 'required|in:circle,polygon',
                'max_daily_bookings' => 'required|integer|min:0',
                'is_active' => 'sometimes|boolean',
                'polygon_coordinates' => 'required',
            ]);
            
            $coordinates = json_decode($request->polygon_coordinates, true);
            $coordinates = $this->normalizePolygonCoordinates($coordinates ?: []);
            if (count($coordinates) < 3) {
                return back()->withInput()->withErrors([
                    'polygon_coordinates' => 'Please draw a polygon with at least 3 points.'
                ]);
            }
            $center = $this->polygonCenter($coordinates);
            
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'area_type' => 'polygon',
                'max_daily_bookings' => $validated['max_daily_bookings'],
                'is_active' => $request->boolean('is_active'),
                'polygon_coordinates' => json_encode($coordinates),
                'latitude' => $center['lat'],
                'longitude' => $center['lng'],
                'radius_km' => 0,
            ];
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'area_type' => 'required|in:circle,polygon',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'required|numeric|min:0.1|max:200',
                'max_daily_bookings' => 'required|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ]);
            
            $data = [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'area_type' => 'circle',
                'max_daily_bookings' => $validated['max_daily_bookings'],
                'is_active' => $request->boolean('is_active'),
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'radius_km' => $validated['radius_km'],
                'polygon_coordinates' => null,
            ];
        }
        
        $deliveryArea->update($data);
        
        return redirect()->route('admin.delivery-areas.index')
            ->with('success', 'Delivery area updated successfully.');
    }
    
    public function destroy(DeliveryArea $deliveryArea)
    {
        $deliveryArea->delete();
        
        return redirect()->route('admin.delivery-areas.index')
            ->with('success', 'Delivery area deleted successfully.');
    }
}
