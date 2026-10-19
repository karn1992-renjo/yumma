<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplashCampaign extends Model
{
    public const PLATFORMS = ['android', 'ios', 'both'];
    public const TAP_ACTIONS = ['none', 'home', 'offers', 'restaurant', 'gold'];

    protected $fillable = [
        'title', 'headline', 'subtitle', 'image_path', 'logo_path',
        'background_color', 'platform', 'is_active', 'start_at', 'end_at',
        'display_duration_seconds', 'tap_action', 'tap_restaurant_id', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'display_duration_seconds' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tapRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'tap_restaurant_id');
    }

    public function isCurrentlyEligible(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_at && now()->lt($this->start_at)) return false;
        if ($this->end_at && now()->gt($this->end_at)) return false;

        return true;
    }

    public function scopeEligibleForPlatform($query, string $platform)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->where(function ($q) use ($platform) {
                $q->where('platform', 'both')->orWhere('platform', $platform);
            });
    }

    public function getVersionAttribute(): string
    {
        return (string) ($this->updated_at?->timestamp ?? $this->id);
    }
}
