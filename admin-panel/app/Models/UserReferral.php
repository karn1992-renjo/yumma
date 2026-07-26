<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReferral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'referral_code',
        'status',
        'qualified_order_id',
        'promotion_id',
        'referrer_reward_redemption_id',
        'referred_reward_redemption_id',
        'bonus_type',
        'amount',
        'points',
        'qualified_at',
        'credited_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'points' => 'integer',
        'qualified_at' => 'datetime',
        'credited_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function qualifiedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'qualified_order_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
