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
        'media_path',
        'media_type',
        'thumbnail_path',
        'caption',
        'duration_seconds',
        'posted_at',
        'expires_at',
        'view_count',
        'sort_order',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'expires_at' => 'datetime',
        'duration_seconds' => 'integer',
        'view_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
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
