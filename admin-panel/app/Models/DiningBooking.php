<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningBooking extends Model
{
    protected $fillable = [
        'restaurant_id', 'user_id', 'booking_number', 'booking_date',
        'booking_time', 'number_of_guests', 'celebration_type',
        'special_requests', 'status', 'booking_charge', 'payment_status',
        'payment_method', 'payment_id', 'gateway_order_id',
        'online_payment_verified_at', 'confirmed_at', 'cancelled_at',
        'cancellation_reason', 'rating', 'feedback'
    ];
    
    protected $casts = [
        'booking_date' => 'date',
        'booking_charge' => 'decimal:2',
        'online_payment_verified_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
    
    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($booking) {
            $booking->booking_number = 'DINE-' . strtoupper(uniqid());
        });
    }
    
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
