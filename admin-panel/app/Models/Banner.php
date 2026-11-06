<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    public const DISPLAY_SURFACES = ['both', 'web', 'app'];

    public const DISPLAY_SURFACE_LABELS = [
        'both' => 'Web + Customer App',
        'web' => 'Web Only',
        'app' => 'Customer App Only',
    ];

    // redirect_type='app_screen' destinations -- named in-app screens with
    // no specific row to point at (unlike category/restaurant/menu_item).
    // Keys must match the customer app's named routes in main.dart, except
    // 'under_99' which has no named route and is special-cased in
    // _handleBannerTap() to push UnderPriceScreen directly.
    public const REDIRECT_SCREENS = [
        'home' => 'Home',
        'offers' => 'Offers',
        'search' => 'Search',
        'gold' => 'Gold Membership',
        'wallet' => 'Wallet',
        'notifications' => 'Notifications',
        'under_99' => 'Dishes Under ₹99',
    ];

    protected $fillable = [
        'title', 'description', 'cta_label', 'image', 'badge_image', 'link', 'redirect_type', 'redirect_category_id', 'redirect_restaurant_id',
        'redirect_menu_item_id', 'redirect_screen', 'display_order', 'is_active', 'start_date', 'end_date', 'banner_type',
        'display_surface', 'layout_mode', 'image_ratio', 'delivery_area_id'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'image_ratio' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function redirectCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'redirect_category_id');
    }

    public function redirectRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'redirect_restaurant_id');
    }

    public function redirectMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'redirect_menu_item_id');
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        
        if ($this->start_date && now()->lt($this->start_date)) return false;
        if ($this->end_date && now()->gt($this->end_date)) return false;
        
        return true;
    }

    public function scopeVisibleOnSurface($query, ?string $surface)
    {
        $surface = self::normalizeDisplaySurface($surface);

        if ($surface === null || $surface === 'both') {
            return $query;
        }

        return $query->where(function ($builder) use ($surface) {
            $builder->whereNull('display_surface')
                ->orWhere('display_surface', 'both')
                ->orWhere('display_surface', $surface);
        });
    }

    public static function normalizeDisplaySurface(?string $surface): ?string
    {
        $surface = strtolower(trim((string) $surface));

        return match ($surface) {
            'all', 'both', 'any' => 'both',
            'web', 'website', 'storefront' => 'web',
            'app', 'mobile', 'customer_app', 'customer-app', 'android', 'ios' => 'app',
            default => null,
        };
    }

    // delivery_area_id null means "show in every zone" -- a banner only
    // narrows to one zone once an admin explicitly sets it.
    public function scopeVisibleInZone($query, ?int $deliveryAreaId)
    {
        if ($deliveryAreaId === null) {
            return $query;
        }

        return $query->where(function ($builder) use ($deliveryAreaId) {
            $builder->whereNull('delivery_area_id')
                ->orWhere('delivery_area_id', $deliveryAreaId);
        });
    }
}