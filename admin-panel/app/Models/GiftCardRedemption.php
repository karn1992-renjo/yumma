<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardRedemption extends Model
{
    protected $fillable = [
        'gift_card_id',
        'user_id',
        'wallet_transaction_id',
        'amount',
        'redeemed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'redeemed_at' => 'datetime',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
