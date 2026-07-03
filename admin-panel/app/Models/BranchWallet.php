<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchWallet extends Model
{
    protected $fillable = ['branch_id', 'balance', 'locked_balance', 'lifetime_earnings', 'lifetime_settled'];

    protected $casts = [
        'balance' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'lifetime_earnings' => 'decimal:2',
        'lifetime_settled' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BranchWalletTransaction::class);
    }
}
