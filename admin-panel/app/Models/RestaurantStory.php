<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantStory extends Model
{
    protected $fillable = [
        'restaurant_id',
        'menu_item_id',
        'cta_label',
        'layout',
        'media_path',
        'media_type',
        'thumbnail_path',
        'caption',
        'duration_seconds',
        'posted_at',
        'expires_at',
        'view_count',
        'like_count',
        'share_count',
        'sort_order',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'expires_at' => 'datetime',
        'duration_seconds' => 'integer',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'share_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public const LAYOUTS = ['classic', 'spotlight', 'split', 'poster'];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * The dish linked to this story, for the app's card + button; null when none is linked or it can no longer be ordered.
     *
     * @return array<string, mixed>|null
     */
    public function menuItemCard(): ?array
    {
        $item = $this->menu_item_id ? $this->menuItem : null;
        if (! $item || ! $item->is_available) {
            return null;
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => round((float) $item->getFinalPriceAttribute(), 2),
            'image_url' => $item->image_url,
            'is_veg' => (bool) $item->is_veg,
        ];
    }

    public function views(): HasMany
    {
        return $this->hasMany(RestaurantStoryView::class);
    }

    public function scopeLive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function mediaUrl(): string
    {
        return MediaStorage::url($this->media_path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? MediaStorage::url($this->thumbnail_path) : null;
    }
}
