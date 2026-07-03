<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Twilio\Rest\Client;

class TwilioCallService
{
    protected $client;
    
    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        
        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }
    
    public function callRestaurant(Restaurant $restaurant, Order $order)
    {
        if (!$this->client) {
            return false;
        }
        
        $twilioNumber = config('services.twilio.phone_number');
        
        try {
            $call = $this->client->calls->create(
                $restaurant->phone,
                $twilioNumber,
                [
                    'twiml' => $this->generateRestaurantTwiML($order)
                ]
            );
            
            return $call->sid;
        } catch (\Exception $e) {
            \Log::error("Twilio call failed for restaurant {$restaurant->id}: " . $e->getMessage());
            return false;
        }
    }
    
    public function callDriver(User $driver, Order $order)
    {
        if (!$this->client || !$driver->phone) {
            return false;
        }
        
        $twilioNumber = config('services.twilio.phone_number');
        
        try {
            $call = $this->client->calls->create(
                $driver->phone,
                $twilioNumber,
                [
                    'twiml' => $this->generateDriverTwiML($order)
                ]
            );
            
            return $call->sid;
        } catch (\Exception $e) {
            \Log::error("Twilio call failed for driver {$driver->id}: " . $e->getMessage());
            return false;
        }
    }
    
    protected function generateRestaurantTwiML(Order $order)
    {
        return "<Response>
            <Say voice='alice'>
                Hello! You have a new order from FoodFlow.
                Order number is {$order->order_number}.
                Total amount is INR {$order->total}.
                Please check your dashboard to confirm the order.
            </Say>
            <Hangup/>
        </Response>";
    }
    
    protected function generateDriverTwiML(Order $order)
    {
        return "<Response>
            <Say voice='alice'>
                Hello! You have been assigned a new delivery order.
                Order number is {$order->order_number}.
                Pickup from {$order->restaurant->name}.
                Please check your app for details.
            </Say>
            <Hangup/>
        </Response>";
    }
}