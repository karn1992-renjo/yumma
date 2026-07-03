<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'restaurant_id', 'name', 'image', 'display_order', 'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}