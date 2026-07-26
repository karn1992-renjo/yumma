<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionLog extends Model
{
    protected $fillable = [
        'promotion_id',
        'user_id',
        'restaurant_id',
        'event_type',
        'coupon_code',
        'context',
        'result',
    ];

    protected $casts = [
        'context' => 'array',
        'result' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
