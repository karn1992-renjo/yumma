<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterMenuItem extends Model
{
    protected $fillable = [
        'name',
        'category_name',
        'subcategory_name',
        'cuisine_id',
        'description',
        'food_type',
        'images',
        'preparation_time',
        'gst',
        'hsn_code',
        'variants',
        'add_ons',
        'is_active',
    ];

    protected $casts = [
        'images' => 'array',
        'variants' => 'array',
        'add_ons' => 'array',
        'gst' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function restaurantMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function cuisine()
    {
        return $this->belongsTo(Cuisine::class);
    }

    public function getImageAttribute(): ?string
    {
        return is_array($this->images) && count($this->images) ? $this->images[0] : null;
    }

    public function getDietLabelAttribute(): string
    {
        return match ($this->food_type) {
            'egg' => 'Egg',
            'non_veg' => 'Non-Veg',
            default => 'Veg',
        };
    }
}
