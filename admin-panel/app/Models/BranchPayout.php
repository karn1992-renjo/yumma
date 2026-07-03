<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPayout extends Model
{
    protected $fillable = [
        'branch_id',
        'branch_settlement_id',
        'amount',
        'period_start',
        'period_end',
        'status',
        'transaction_reference',
        'approved_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(BranchSettlement::class, 'branch_settlement_id');
    }
}
