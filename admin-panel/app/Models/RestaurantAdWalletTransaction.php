<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantAdWalletTransaction extends Model
{
    protected $fillable = [
        'restaurant_ad_wallet_id',
        'restaurant_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RestaurantAdWallet::class, 'restaurant_ad_wallet_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
