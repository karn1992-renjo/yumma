<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdImpression extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'restaurant_ad_campaign_id',
        'restaurant_id',
        'user_id',
        'session_id',
        'surface',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RestaurantAdCampaign::class, 'restaurant_ad_campaign_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
