<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantAdWallet extends Model
{
    protected $fillable = [
        'restaurant_id',
        'balance',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'float',
        'is_active' => 'boolean',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RestaurantAdWalletTransaction::class);
    }
}
