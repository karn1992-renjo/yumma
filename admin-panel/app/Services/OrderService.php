<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\DeliveryChargeSetting;
use App\Models\TaxSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $kilometers = $miles * 1.609344;
        
        return $kilometers;
    }
    
    public function calculateDeliveryFee($distance, $restaurant)
    {
        return DeliveryChargeSetting::getDeliveryCharge($distance);
    }
    
    public function calculateTax($subtotal, $deliveryFee = 0)
    {
        return round((float) TaxSetting::calculateTax($subtotal, $deliveryFee), 2);
    }
    
    public function findNearestDriver($restaurantLat, $restaurantLng)
    {
        return User::role('delivery_partner')
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
    }
    
    public function createOrder($data)
    {
        DB::beginTransaction();
        
        try {
            $restaurant = Restaurant::find($data['restaurant_id']);
            
            // Calculate distance
            $distance = $this->calculateDistance(
                $restaurant->latitude, $restaurant->longitude,
                $data['delivery_lat'], $data['delivery_lng']
            );
            
            // Calculate delivery fee
            $deliveryFee = $this->calculateDeliveryFee($distance, $restaurant);
            
            // Calculate subtotal
            $subtotal = 0;
            $items = [];
            
            foreach ($data['items'] as $item) {
                $menuItem = MenuItem::find($item['id']);
                $itemTotal = $menuItem->getFinalPriceAttribute() * $item['quantity'];
                $subtotal += $itemTotal;
                
                $items[] = [
                    'id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $menuItem->getFinalPriceAttribute(),
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal
                ];
            }
            
            $tax = $this->calculateTax($subtotal, $deliveryFee);
            $platformFee = DeliveryChargeSetting::getPlatformFee();
            $discount = max(0, (float) ($data['discount'] ?? 0));
            $total = max(0, $subtotal + $deliveryFee + $platformFee + $tax - $discount);
            
            // Find nearest driver
            $driver = $this->findNearestDriver($restaurant->latitude, $restaurant->longitude);
            
            // Create order
            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'restaurant_id' => $restaurant->id,
                'driver_id' => $driver->id ?? null,
                'items' => $items,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'status' => 'pending',
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'delivery_address' => $data['delivery_address'],
                'delivery_lat' => $data['delivery_lat'],
                'delivery_lng' => $data['delivery_lng'],
            ]);
            
            DB::commit();
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
