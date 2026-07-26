<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScratchCardRewardLog extends Model
{
    protected $fillable = [
        'scratch_card_id',
        'promotion_id',
        'order_id',
        'user_id',
        'event_type',
        'reward_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function scratchCard(): BelongsTo
    {
        return $this->belongsTo(CustomerScratchCard::class, 'scratch_card_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
