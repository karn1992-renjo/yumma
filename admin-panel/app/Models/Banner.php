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

    protected $fillable = [
        'title', 'description', 'cta_label', 'image', 'badge_image', 'link', 'redirect_type', 'redirect_category_id', 'redirect_restaurant_id',
        'redirect_menu_item_id', 'display_order', 'is_active', 'start_date', 'end_date', 'banner_type',
        'display_surface', 'layout_mode', 'image_ratio'
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
}