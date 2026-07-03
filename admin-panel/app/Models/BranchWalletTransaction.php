<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchWalletTransaction extends Model
{
    protected $fillable = [
        'branch_wallet_id',
        'branch_id',
        'order_id',
        'settlement_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(BranchWallet::class, 'branch_wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
