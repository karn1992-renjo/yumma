<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantSubscription extends Model
{
    protected $fillable = [
        'restaurant_id',
        'subscription_plan_id',
        'scope',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'orders_used_in_period',
        'auto_renew',
        'cancelled_at',
        'gateway',
        'gateway_customer_id',
        'gateway_subscription_id',
        'gateway_payment_method_label',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'orders_used_in_period' => 'integer',
        'auto_renew' => 'boolean',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true);
    }

    public function hasOrderCapacity(): bool
    {
        $limit = $this->plan?->order_limit;

        return $limit === null || $this->orders_used_in_period < $limit;
    }

    public function hasMenuItemCapacity(int $currentCount): bool
    {
        $limit = $this->plan?->menu_item_limit;

        return $limit === null || $currentCount < $limit;
    }
}
