<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverGig extends Model
{
    protected $appends = [
        'duration',
        'estimated_earning',
    ];

    protected $fillable = [
        'title',
        'description',
        'driver_id',
        'area_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'earnings',
        'orders_count',
        'base_pay',
        'order_incentive',
        'login_incentive',
        'min_orders_required',
        'min_login_minutes',
        'max_cancellations_allowed',
        'terms_conditions',
        'booked_at',
    ];
    
    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'earnings' => 'integer',
        'orders_count' => 'integer',
        'base_pay' => 'decimal:2',
        'order_incentive' => 'decimal:2',
        'login_incentive' => 'decimal:2',
        'min_orders_required' => 'integer',
        'min_login_minutes' => 'integer',
        'max_cancellations_allowed' => 'integer',
        'booked_at' => 'datetime',
    ];
    
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
    
    public function area(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }
    
    public function getDurationAttribute(): string
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getEstimatedEarningAttribute(): float
    {
        return round(
            (float) $this->base_pay
            + ((float) $this->order_incentive * (int) $this->min_orders_required)
            + (float) $this->login_incentive,
            2
        );
    }
}
