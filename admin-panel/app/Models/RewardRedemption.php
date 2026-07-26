<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'promotion_id',
        'scratch_card_id',
        'order_id',
        'promotion_coupon_code_id',
        'wallet_transaction_id',
        'gift_card_id',
        'reward_type',
        'status',
        'amount',
        'points',
        'code',
        'settlement_key',
        'payload',
        'issued_at',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'points' => 'integer',
        'payload' => 'array',
        'issued_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function scratchCard(): BelongsTo
    {
        return $this->belongsTo(CustomerScratchCard::class, 'scratch_card_id');
    }

    public function couponCode(): BelongsTo
    {
        return $this->belongsTo(PromotionCouponCode::class, 'promotion_coupon_code_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
