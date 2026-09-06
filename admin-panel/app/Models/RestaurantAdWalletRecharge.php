<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantAdWalletRecharge extends Model
{
    protected $fillable = [
        'restaurant_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'meta',
    ];

    protected $casts = [
        'amount' => 'float',
        'meta' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
