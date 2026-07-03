<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigIncentive extends Model
{
    protected $fillable = [
        'driver_gig_id', 'base_pay', 'order_incentive', 'active_time_incentive',
        'total_earned', 'orders_completed', 'active_minutes',
        'is_penalty_applied', 'penalty_amount', 'penalty_reason'
    ];
    
    protected $casts = [
        'orders_completed' => 'array',
        'is_penalty_applied' => 'boolean',
    ];
    
    public function gig()
    {
        return $this->belongsTo(DriverGig::class, 'driver_gig_id');
    }
}