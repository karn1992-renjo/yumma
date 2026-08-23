<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigIncentive extends Model
{
    protected $fillable = [
        'driver_gig_id',
        'driver_id',
        'base_pay',
        'order_incentive',
        'active_time_incentive',
        'fraud_status',
        'approval_status',
        'surge_amount',
        'surge_multiplier',
        'total_earned',
        'orders_completed',
        'active_minutes',
        'login_requirement_met',
        'order_requirement_met',
        'cancellation_requirement_met',
        'no_show',
        'delivered_orders_count',
        'rejected_orders_count',
        'cancelled_orders_count',
        'is_penalty_applied',
        'penalty_amount',
        'penalty_reason',
    ];
    
    protected $casts = [
        'orders_completed' => 'array',
        'is_penalty_applied' => 'boolean',
        'login_requirement_met' => 'boolean',
        'order_requirement_met' => 'boolean',
        'cancellation_requirement_met' => 'boolean',
        'no_show' => 'boolean',
        'active_minutes' => 'integer',
        'delivered_orders_count' => 'integer',
        'rejected_orders_count' => 'integer',
        'cancelled_orders_count' => 'integer',
        'total_earned' => 'decimal:2',
        'surge_multiplier' => 'decimal:2',
        'surge_amount' => 'decimal:2',
    ];
    
    public function gig()
    {
        return $this->belongsTo(DriverGig::class, 'driver_gig_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}