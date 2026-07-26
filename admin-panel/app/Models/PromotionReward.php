<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionReward extends Model
{
    protected $fillable = ['promotion_id', 'reward_type', 'value', 'max_discount', 'payload'];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
