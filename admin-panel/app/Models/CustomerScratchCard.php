<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerScratchCard extends Model
{
    protected $fillable = [
        'promotion_id',
        'promotion_usage_id',
        'order_id',
        'user_id',
        'restaurant_id',
        'title',
        'status',
        'reward_pool',
        'reward',
        'metadata',
        'wallet_transaction_id',
        'issued_at',
        'viewed_at',
        'scratched_at',
        'revealed_at',
        'reward_generated_at',
        'reward_credited_at',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'reward_pool' => 'array',
        'reward' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'viewed_at' => 'datetime',
        'scratched_at' => 'datetime',
        'revealed_at' => 'datetime',
        'reward_generated_at' => 'datetime',
        'reward_credited_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function promotionUsage(): BelongsTo
    {
        return $this->belongsTo(PromotionUsage::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ScratchCardRewardLog::class, 'scratch_card_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'scratch_card_id');
    }

    public function isRevealed(): bool
    {
        return in_array($this->status, ['revealed', 'scratched', 'reward_generated', 'reward_credited', 'redeemed'], true)
            && $this->revealed_at !== null;
    }
}
