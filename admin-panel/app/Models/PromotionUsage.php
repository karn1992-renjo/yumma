<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    protected $table = 'promotion_usage';

    protected $fillable = [
        'promotion_id',
        'promotion_coupon_code_id',
        'order_id',
        'user_id',
        'restaurant_id',
        'branch_id',
        'source_type',
        'source_id',
        'coupon_code',
        'discount_amount',
        'cashback_amount',
        'gross_liability_amount',
        'platform_liability_amount',
        'restaurant_liability_amount',
        'partner_liability_amount',
        'funding_breakdown',
        'discount_lines',
        'context',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'cashback_amount' => 'decimal:2',
        'gross_liability_amount' => 'decimal:2',
        'platform_liability_amount' => 'decimal:2',
        'restaurant_liability_amount' => 'decimal:2',
        'partner_liability_amount' => 'decimal:2',
        'funding_breakdown' => 'array',
        'discount_lines' => 'array',
        'context' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

