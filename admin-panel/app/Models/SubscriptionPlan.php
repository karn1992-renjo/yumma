<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'audience',
        'scope',
        'name',
        'slug',
        'billing_period',
        'validity_days',
        'price',
        'compare_at_price',
        'currency',
        'order_limit',
        'menu_item_limit',
        'trial_days',
        'includes_stories',
        'is_active',
        'sort_order',
        'description',
        'razorpay_plan_id',
        'cashfree_plan_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'validity_days' => 'integer',
        'order_limit' => 'integer',
        'menu_item_limit' => 'integer',
        'trial_days' => 'integer',
        'includes_stories' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(RestaurantSubscription::class);
    }

    public function isUnlimitedOrders(): bool
    {
        return $this->order_limit === null;
    }

    public function isUnlimitedMenuItems(): bool
    {
        return $this->menu_item_limit === null;
    }

    public function hasTrial(): bool
    {
        return (int) $this->trial_days > 0;
    }

    /**
     * Normally derives the next period's end from billing_period alone.
     * validity_days (when set) overrides it -- lets an admin offer a
     * custom-length pass (e.g. 7 or 45 days) instead of being limited to
     * monthly/quarterly/yearly. Shared by SubscriptionService and
     * GoldSubscriptionService so both billing audiences get the override
     * for free.
     */
    public function nextPeriodEndFrom(\Carbon\Carbon $from): \Carbon\Carbon
    {
        if ($this->validity_days !== null && $this->validity_days > 0) {
            return $from->copy()->addDays($this->validity_days);
        }

        return match ($this->billing_period) {
            'quarterly' => $from->copy()->addMonths(3),
            'yearly' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
