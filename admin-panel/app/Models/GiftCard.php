<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    protected $fillable = [
        'code',
        'title',
        'amount',
        'max_redemptions',
        'redeemed_count',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'max_redemptions' => 'integer',
        'redeemed_count' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    public function getIsRedeemableAttribute(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
