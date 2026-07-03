<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name', 'type', 'target_audience', 'target_location',
        'discount_details', 'image_url', 'link_url', 'start_date',
        'end_date', 'is_active', 'impressions', 'clicks'
    ];
    
    protected $casts = [
        'discount_details' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today());
    }
    
    public function recordClick()
    {
        $this->increment('clicks');
    }
    
    public function recordImpression()
    {
        $this->increment('impressions');
    }
}