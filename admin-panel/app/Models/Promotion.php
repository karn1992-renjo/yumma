<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'restaurant_id',
        'branch_id',
        'title',
        'slug',
        'description',
        'promotion_type',
        'application_mode',
        'status',
        'priority',
        'is_exclusive',
        'funding_type',
        'platform_share_percent',
        'restaurant_share_percent',
        'partner_name',
        'total_budget',
        'daily_budget',
        'per_restaurant_budget',
        'budget_used',
        'image_path',
        'starts_at',
        'ends_at',
        'starts_time',
        'ends_time',
        'schedule',
        'targets',
        'conditions',
        'rewards',
        'stacking',
        'fraud_rules',
        'visibility',
        'total_usage_limit',
        'per_user_usage_limit',
        'used_count',
        'migrated_from_type',
        'migrated_from_id',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'restaurant_id' => 'integer',
        'branch_id' => 'integer',
        'priority' => 'integer',
        'is_exclusive' => 'boolean',
        'platform_share_percent' => 'decimal:2',
        'restaurant_share_percent' => 'decimal:2',
        'total_budget' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'per_restaurant_budget' => 'decimal:2',
        'budget_used' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'schedule' => 'array',
        'targets' => 'array',
        'conditions' => 'array',
        'rewards' => 'array',
        'stacking' => 'array',
        'fraud_rules' => 'array',
        'visibility' => 'array',
        'total_usage_limit' => 'integer',
        'per_user_usage_limit' => 'integer',
        'used_count' => 'integer',
    ];

    public function couponCodes(): HasMany
    {
        return $this->hasMany(PromotionCouponCode::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function targetRows(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function conditionRows(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function rewardRows(): HasMany
    {
        return $this->hasMany(PromotionReward::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PromotionLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $builder) {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function getImageUrlAttribute(): ?string
    {
        return MediaStorage::url($this->image_path);
    }

    public function isCouponBased(): bool
    {
        return $this->application_mode === 'coupon';
    }

    public function getCodeAttribute(): ?string
    {
        return $this->relationLoaded('couponCodes')
            ? $this->couponCodes->first()?->code
            : $this->couponCodes()->active()->value('code');
    }

    public function getMinimumOrderAmountAttribute(): ?float
    {
        $minimum = data_get($this->conditions, 'min_order_amount');

        return $minimum !== null ? (float) $minimum : null;
    }

    public function getDiscountTypeAttribute(): ?string
    {
        return data_get($this->rewards, 'type');
    }

    public function getDiscountValueAttribute(): ?float
    {
        $value = data_get($this->rewards, 'value');

        return $value !== null ? (float) $value : null;
    }

    public function toPublicPayload(): array
    {
        $reward = $this->rewards ?? [];
        $imageUrl = $this->image_url;
        $bannerUrl = MediaStorage::url(data_get($this->visibility, 'banner_image')) ?: $imageUrl;
        $thumbnailUrl = MediaStorage::url(data_get($this->visibility, 'thumbnail')) ?: $imageUrl;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'promotion_type' => $this->promotion_type,
            'application_mode' => $this->application_mode,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'restaurant_id' => $this->restaurant_id,
            'priority' => $this->priority,
            'is_exclusive' => $this->is_exclusive,
            'image' => $imageUrl ?: $bannerUrl ?: $thumbnailUrl,
            'image_url' => $imageUrl ?: $bannerUrl ?: $thumbnailUrl,
            'promo_image' => $imageUrl ?: $bannerUrl ?: $thumbnailUrl,
            'banner_image' => $bannerUrl,
            'thumbnail' => $thumbnailUrl,
            'thumbnail_url' => $thumbnailUrl,
            'discount_type' => $reward['type'] ?? null,
            'discount_value' => $reward['value'] ?? null,
            'max_discount' => $reward['max_discount'] ?? null,
            'max_discount_amount' => $reward['max_discount'] ?? null,
            'min_order_value' => data_get($this->conditions, 'min_order_amount'),
            'min_order_amount' => data_get($this->conditions, 'min_order_amount'),
            'valid_from' => $this->starts_at,
            'valid_to' => $this->ends_at,
            'start_date' => $this->starts_at,
            'end_date' => $this->ends_at,
            'status' => $this->status,
            'is_active' => $this->status === self::STATUS_ACTIVE,
            'migrated_from_type' => $this->migrated_from_type,
            'migrated_from_id' => $this->migrated_from_id,
            'legacy_id' => $this->migrated_from_id,
            'targets' => $this->targets,
            'conditions' => $this->conditions,
            'rewards' => $this->rewards,
            'visibility' => $this->visibility,
        ];
    }

    public function resolvedFundingType(): string
    {
        $type = strtolower((string) ($this->funding_type ?: ''));

        if (in_array($type, ['platform', 'restaurant', 'shared', 'bank_partner'], true)) {
            return $type;
        }

        return in_array($this->owner_type, ['restaurant', 'branch'], true) ? 'restaurant' : 'platform';
    }

    public function budgetRemaining(): ?float
    {
        $budget = $this->total_budget ?? data_get($this->visibility ?? [], 'campaign_budget');
        if ($budget === null || $budget === '') {
            return null;
        }

        return max(0, round((float) $budget - (float) ($this->budget_used ?? 0), 2));
    }
}
