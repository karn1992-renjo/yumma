<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    public const TYPES = [
        'banner_carousel' => 'Banner Carousel',
        'hero_banner' => 'Hero Banner',
        'restaurant_grid' => 'Restaurant Grid',
        'cuisine_grid' => 'Cuisine Grid',
        'custom_section' => 'Custom Section',
        'featured_restaurants' => 'Featured Restaurants',
        'recommended_for_you' => 'Recommended For You',
        'nearby_restaurants' => 'Nearby Restaurants',
        'popular_restaurants' => 'Popular Restaurants',
        'new_arrivals' => 'New Arrivals',
        'trending_near_you' => 'Trending Near You',
        'popular_dishes' => 'Popular Dishes',
        'admin_offers' => 'Admin Offers',
        'shop_by_brand' => 'Shop By Brand',
    ];

    public const SOURCES = [
        'auto' => 'Automatic',
        'manual' => 'Manual selection',
    ];

    public const RESTAURANT_SCOPES = [
        'featured' => 'Featured restaurants',
        'top_rated' => 'Top rated restaurants',
        'latest' => 'Newest restaurants',
        'most_ordered' => 'Most ordered restaurants',
        'pure_veg' => 'Pure veg restaurants',
        'open_now' => 'Open now restaurants',
    ];

    protected $fillable = [
        'title',
        'subtitle',
        'section_type',
        'data_source',
        'configuration',
        'display_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'display_order' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
