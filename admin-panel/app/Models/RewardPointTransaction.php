<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardPointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'promotion_id',
        'scratch_card_id',
        'order_id',
        'type',
        'points',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'meta',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function scratchCard(): BelongsTo
    {
        return $this->belongsTo(CustomerScratchCard::class, 'scratch_card_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
