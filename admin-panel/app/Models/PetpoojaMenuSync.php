<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetpoojaMenuSync extends Model
{
    protected $fillable = [
        'restaurant_id',
        'source',
        'raw_payload',
        'item_count',
        'category_count',
        'status',
        'error',
        'received_at',
        'applied_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
