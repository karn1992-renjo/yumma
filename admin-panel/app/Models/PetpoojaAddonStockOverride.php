<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetpoojaAddonStockOverride extends Model
{
    protected $fillable = [
        'restaurant_id',
        'petpooja_addon_id',
        'in_stock',
        'unavailable_until',
    ];

    protected $casts = [
        'in_stock' => 'boolean',
        'unavailable_until' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
