<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantAdCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_BUDGET_EXHAUSTED = 'budget_exhausted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ENDED = 'ended';

    private const PLACEMENT_SURFACE_ALIASES = [
        'search' => ['search', 'search_results'],
        'home' => ['home', 'home_recommendations'],
        'home_featured' => ['home_featured', 'home_recommendations'],
        'home_brand' => ['home_brand', 'home_recommendations'],
        'legacy_home' => ['legacy_home', 'home_recommendations'],
        'legacy_featured' => ['legacy_featured', 'home_recommendations'],
        'nearby' => ['nearby', 'restaurant_listing'],
        'restaurant_listing' => ['restaurant_listing', 'nearby'],
        'cuisine' => ['cuisine', 'cuisine_pages'],
        'cuisine_pages' => ['cuisine_pages', 'cuisine'],
        'deals' => ['deals', 'deals_offers'],
        'deals_offers' => ['deals_offers', 'deals'],
        'sponsored_items' => ['sponsored_items'],
    ];

    protected $fillable = [
        'restaurant_id',
        'name',
        'status',
        'max_cpc',
        'daily_budget',
        'total_budget',
        'spent_total',
        'spent_today',
        'starts_at',
        'ends_at',
        'targeting',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'admin_review_note',
        'rejected_reason',
        'paused_reason',
    ];

    protected $casts = [
        'max_cpc' => 'float',
        'daily_budget' => 'float',
        'total_budget' => 'float',
        'spent_total' => 'float',
        'spent_today' => 'float',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'targeting' => 'array',
        'admin_reviewed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class);
    }

    public function fraudSignals(): HasMany
    {
        return $this->hasMany(AdFraudSignal::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', today())
            ->where(function (Builder $builder) {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', today());
            });
    }

    public function allowsPlacement(?string $surface): bool
    {
        $targeting = is_array($this->targeting) ? $this->targeting : [];

        if (($targeting['automatic_placement'] ?? true) === true) {
            return true;
        }

        $placements = collect($targeting['placements'] ?? [])
            ->map(fn ($placement) => self::normalizePlacement((string) $placement))
            ->filter()
            ->values();

        if ($placements->isEmpty()) {
            return false;
        }

        $surfaceKey = self::normalizePlacement((string) $surface);
        $allowedSurfaceKeys = self::PLACEMENT_SURFACE_ALIASES[$surfaceKey] ?? [$surfaceKey];

        return $placements->intersect($allowedSurfaceKeys)->isNotEmpty();
    }

    private static function normalizePlacement(string $value): string
    {
        return strtolower(str_replace(['-', ' '], '_', trim($value)));
    }
    public function budgetRemaining(): ?float
    {
        if ($this->total_budget === null) {
            return null;
        }

        return max(0, round((float) $this->total_budget - (float) $this->spent_total, 2));
    }

    /**
     * Real-time billed spend for today, computed from the click ledger rather
     * than the spent_today column (which is only ever incremented, never
     * reset, so it can't be trusted for a "today" figure).
     */
    public function spentToday(): float
    {
        return (float) $this->clicks()
            ->where('is_billed', true)
            ->whereDate('created_at', today())
            ->sum('price_paid');
    }

    public function wallet(): ?RestaurantAdWallet
    {
        return RestaurantAdWallet::where('restaurant_id', $this->restaurant_id)->first();
    }

    /**
     * Whether the restaurant's ad wallet currently has enough balance to
     * cover at least one click at this campaign's bid. Used to gate
     * submit/approve so a campaign can't go live while it can never win an
     * auction slot.
     */
    public function hasFundedWallet(): bool
    {
        $wallet = $this->wallet();

        return $wallet !== null && $wallet->is_active && (float) $wallet->balance >= (float) $this->max_cpc;
    }
}
