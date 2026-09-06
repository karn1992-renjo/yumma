<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCallMapping extends Model
{
    protected $fillable = [
        'order_id',
        'call_masking_pool_id',
        'exophone',
        'customer_id',
        'customer_phone_snapshot',
        'restaurant_id',
        'restaurant_phone_snapshot',
        'driver_id',
        'driver_phone_snapshot',
        'status',
        'expires_at',
        'released_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(CallMaskingPool::class, 'call_masking_pool_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
