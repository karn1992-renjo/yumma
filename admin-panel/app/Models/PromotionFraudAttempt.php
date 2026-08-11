<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionFraudAttempt extends Model
{
    protected $fillable = [
        'promotion_id',
        'user_id',
        'restaurant_id',
        'coupon_code',
        'rule_key',
        'reason_code',
        'reason',
        'device_id',
        'address_hash',
        'payment_instrument_hash',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}