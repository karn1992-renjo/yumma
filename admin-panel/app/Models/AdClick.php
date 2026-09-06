<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdClick extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'restaurant_ad_campaign_id',
        'restaurant_id',
        'user_id',
        'session_id',
        'surface',
        'price_paid',
        'rank_at_serve',
        'competitor_rank_used',
        'restaurant_ad_wallet_transaction_id',
        'is_billed',
        'ip_address',
    ];

    protected $casts = [
        'price_paid' => 'float',
        'rank_at_serve' => 'float',
        'competitor_rank_used' => 'float',
        'is_billed' => 'boolean',
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

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(RestaurantAdWalletTransaction::class, 'restaurant_ad_wallet_transaction_id');
    }
}
