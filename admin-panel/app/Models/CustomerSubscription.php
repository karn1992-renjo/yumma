<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'orders_used_in_period',
        'auto_renew',
        'cancelled_at',
        'past_due_at',
        'gateway',
        'gateway_customer_id',
        'gateway_subscription_id',
        'gateway_order_id',
        'gateway_payment_method_label',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'past_due_at' => 'datetime',
        'orders_used_in_period' => 'integer',
        'auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Gold membership is pay-as-you-go (no recurring mandate, no webhook) --
     * nothing auto-flips `status` the moment a paid period ends, so "live"
     * must also check current_period_end/trial_ends_at directly rather than
     * trusting status alone. Mirrored by scopeLive() below for query-time
     * checks (GoldSubscriptionService::isActiveFor()/activeSubscriptionFor()).
     */
    public function isLive(): bool
    {
        if ($this->status === 'trialing') {
            return $this->trial_ends_at === null || now()->lte($this->trial_ends_at);
        }

        if (! in_array($this->status, ['active', 'past_due'], true)) {
            return false;
        }

        return $this->current_period_end === null || now()->lte($this->current_period_end);
    }

    public function scopeLive($query)
    {
        return $query->where(function ($outer) {
            $outer->where(function ($q) {
                $q->where('status', 'trialing')
                    ->where(function ($q2) {
                        $q2->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', now());
                    });
            })->orWhere(function ($q) {
                $q->whereIn('status', ['active', 'past_due'])
                    ->where(function ($q2) {
                        $q2->whereNull('current_period_end')->orWhere('current_period_end', '>=', now());
                    });
            });
        });
    }

    public function hasOrderCapacity(): bool
    {
        $limit = $this->plan?->order_limit;

        return $limit === null || $this->orders_used_in_period < $limit;
    }
}
