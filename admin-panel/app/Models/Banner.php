<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    protected $fillable = [
        'title', 'description', 'image', 'link', 'redirect_type', 'redirect_category_id', 'redirect_restaurant_id',
        'redirect_menu_item_id', 'display_order', 'is_active', 'start_date', 'end_date', 'banner_type',
        'layout_mode', 'image_ratio'
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
}
