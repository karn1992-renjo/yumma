<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionSettlementLedger extends Model
{
    protected $fillable = [
        'promotion_id',
        'promotion_usage_id',
        'order_id',
        'restaurant_id',
        'user_id',
        'coupon_code',
        'funding_type',
        'partner_name',
        'discount_amount',
        'cashback_amount',
        'gift_voucher_amount',
        'gross_liability_amount',
        'platform_liability_amount',
        'restaurant_liability_amount',
        'partner_liability_amount',
        'settlement_status',
        'line_payload',
        'funding_breakdown',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'cashback_amount' => 'decimal:2',
        'gift_voucher_amount' => 'decimal:2',
        'gross_liability_amount' => 'decimal:2',
        'platform_liability_amount' => 'decimal:2',
        'restaurant_liability_amount' => 'decimal:2',
        'partner_liability_amount' => 'decimal:2',
        'line_payload' => 'array',
        'funding_breakdown' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(PromotionUsage::class, 'promotion_usage_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
