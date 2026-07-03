<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'restaurant_id', 'master_menu_item_id', 'item_source', 'category_id', 'cuisine_id', 'name', 'description', 'price',
        'discounted_price', 'images', 'is_veg', 'food_type', 'is_available', 'is_recommended',
        'is_bestseller', 'is_new', 'is_spicy', 'is_combo', 'availability_schedule', 'approval_status',
        'preparation_time', 'rating', 'total_orders', 'tags', 'variants', 'add_ons'
    ];
    
    protected $casts = [
        'images' => 'array',
        'tags' => 'array',
        'variants' => 'array',
        'add_ons' => 'array',
        'availability_schedule' => 'array',
        'is_veg' => 'boolean',
        'is_available' => 'boolean',
        'is_recommended' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_new' => 'boolean',
        'is_spicy' => 'boolean',
        'is_combo' => 'boolean',
        'price' => 'decimal:5',
        'discounted_price' => 'decimal:5',
    ];

    protected $appends = [
        'diet_label',
        'image',
        'image_url',
    ];
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cuisine(): BelongsTo
    {
        return $this->belongsTo(Cuisine::class);
    }

    public function masterMenuItem(): BelongsTo
    {
        return $this->belongsTo(MasterMenuItem::class);
    }
    
    public function getFinalPriceAttribute(): float
    {
        return (float) ($this->discounted_price ?? $this->price);
    }

    public function getDietLabelAttribute(): string
    {
        return match ($this->food_type ?: ($this->is_veg ? 'veg' : 'non_veg')) {
            'egg' => 'Egg',
            'non_veg' => 'Non-Veg',
            default => 'Veg',
        };
    }

    public function getImageAttribute()
    {
        return is_array($this->images) && count($this->images) ? $this->images[0] : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return MediaStorage::url($this->image);
    }

    public function getIsScheduledAvailableAttribute(): bool
    {
        if (!$this->is_available || ($this->approval_status && $this->approval_status !== 'approved')) {
            return false;
        }

        $schedule = $this->availability_schedule;
        if (!is_array($schedule) || empty($schedule)) {
            return true;
        }

        $now = now();
        $currentMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $today = strtolower($now->format('l'));

        foreach ($schedule as $slot) {
            if (!is_array($slot) || empty($slot['start']) || empty($slot['end'])) {
                continue;
            }

            $days = $slot['days'] ?? [];
            if (is_array($days) && count($days) && !in_array($today, array_map('strtolower', $days), true)) {
                continue;
            }

            $start = $this->timeToMinutes((string) $slot['start']);
            $end = $this->timeToMinutes((string) $slot['end']);
            if ($start === null || $end === null) {
                continue;
            }

            if ($start <= $end && $currentMinutes >= $start && $currentMinutes <= $end) {
                return true;
            }

            if ($start > $end && ($currentMinutes >= $start || $currentMinutes <= $end)) {
                return true;
            }
        }

        return false;
    }

    private function timeToMinutes(string $value): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}
