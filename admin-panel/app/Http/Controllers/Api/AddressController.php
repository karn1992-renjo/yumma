<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppSetting;
use App\Models\Restaurant;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index()
    {
        $restaurantId = request()->integer('restaurant_id');
        $restaurant = $restaurantId ? Restaurant::find($restaurantId) : null;

        $addresses = Auth::user()
            ->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Address $address) use ($restaurant) {
                if (! $restaurant || ! $restaurant->latitude || ! $restaurant->longitude || ! $address->latitude || ! $address->longitude) {
                    $address->setAttribute('distance_km', null);
                    $address->setAttribute('is_deliverable', true);
                    $address->setAttribute('delivery_status_label', 'DELIVERS TO');

                    return $address;
                }

                $distance = $this->calculateDistance(
                    (float) $restaurant->latitude,
                    (float) $restaurant->longitude,
                    (float) $address->latitude,
                    (float) $address->longitude
                );

                $radius = (float) ($restaurant->delivery_radius ?: 0);
                $isDeliverable = $radius <= 0 ? true : $distance <= $radius;

                $address->setAttribute('distance_km', round($distance, 1));
                $address->setAttribute('is_deliverable', $isDeliverable);
                $address->setAttribute(
                    'delivery_status_label',
                    $isDeliverable ? 'DELIVERS TO' : 'DOES NOT DELIVER TO'
                );

                return $address;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if (preg_match('/[A-Za-z]/', $data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422);
        }

        $data['phone'] = PhoneNumber::normalize(
            $data['phone'],
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
        if ($data['phone'] === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422);
        }

        $data['name'] = $data['name'] ?? 'Home';
        $data['is_default'] = Auth::user()->addresses()->count() == 0;

        $address = Auth::user()->addresses()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'address' => $address,
        ]);
    }

    public function update(Request $request, $id)
    {
        $address = Auth::user()->addresses()->findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if (preg_match('/[A-Za-z]/', $data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422);
        }

        $data['phone'] = PhoneNumber::normalize(
            $data['phone'],
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
        if ($data['phone'] === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422);
        }

        $data['name'] = $data['name'] ?? $address->name;
        $address->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'address' => $address,
        ]);
    }

    public function destroy($id)
    {
        $address = Auth::user()->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $newDefault = Auth::user()->addresses()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    public function setDefault($id)
    {
        Auth::user()->addresses()->update(['is_default' => false]);
        $address = Auth::user()->addresses()->findOrFail($id);
        $address->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default address updated!',
        ]);
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = max(-1, min(1, $dist));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        return $miles * 1.609344;
    }
}
