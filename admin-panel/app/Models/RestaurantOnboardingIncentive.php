<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantOnboardingIncentive extends Model
{
    public const TYPE = 'restaurant_onboarding_incentive';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EARNED = 'earned';
    public const STATUS_INCLUDED_IN_PAYOUT = 'included_in_payout';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'restaurant_onboarding_id',
        'driver_id',
        'restaurant_id',
        'amount',
        'earning_type',
        'wallet_transaction_id',
        'payout_id',
        'status',
        'earned_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'earned_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(RestaurantOnboarding::class, 'restaurant_onboarding_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
