<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuisine extends Model
{
    protected $table = 'cuisines';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'is_active',
        'display_order',
        'popular'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'popular' => 'boolean',
        'display_order' => 'integer',
    ];
    
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($cuisine) {
            $cuisine->slug = \Illuminate\Support\Str::slug($cuisine->name);
        });
        
        static::updating(function ($cuisine) {
            $cuisine->slug = \Illuminate\Support\Str::slug($cuisine->name);
        });
    }
    
    /**
     * Get restaurants with this cuisine
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'cuisine_id');
    }
    
    /**
     * Scope for active cuisines
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for popular cuisines
     */
    public function scopePopular($query)
    {
        return $query->where('popular', true);
    }
    
    /**
     * Scope for ordered cuisines
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc')->orderBy('name', 'asc');
    }
    
    /**
     * Get icon HTML
     */
    public function getIconHtmlAttribute()
    {
        if ($this->icon) {
            return '<i class="' . $this->icon . '"></i>';
        }
        return '<i class="fas fa-utensils"></i>';
    }
    
    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->is_active) {
            return '<span class="badge bg-success">Active</span>';
        }
        return '<span class="badge bg-danger">Inactive</span>';
    }
    
    /**
     * Get popular badge
     */
    public function getPopularBadgeAttribute()
    {
        if ($this->popular) {
            return '<span class="badge bg-warning">Popular</span>';
        }
        return '<span class="badge bg-secondary">Normal</span>';
    }
}