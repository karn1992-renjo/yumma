<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdFraudSignal extends Model
{
    protected $fillable = [
        'restaurant_ad_campaign_id',
        'ad_click_id',
        'signal_type',
        'severity',
        'score',
        'status',
        'evidence',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'evidence' => 'array',
        'score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RestaurantAdCampaign::class, 'restaurant_ad_campaign_id');
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(AdClick::class, 'ad_click_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
