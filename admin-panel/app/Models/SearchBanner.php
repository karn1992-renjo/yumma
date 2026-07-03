<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchBanner extends Model
{
    protected $table = 'search_banners';
    
    protected $fillable = [
        'title', 'image', 'link', 'is_active', 'start_date', 'end_date', 'position'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];
    
    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        
        if ($this->start_date && now()->lt($this->start_date)) return false;
        if ($this->end_date && now()->gt($this->end_date)) return false;
        
        return true;
    }
}
