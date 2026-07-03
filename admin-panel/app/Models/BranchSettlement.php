<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchSettlement extends Model
{
    protected $fillable = [
        'branch_id',
        'settlement_number',
        'period_start',
        'period_end',
        'gross_orders',
        'platform_commission',
        'branch_commission',
        'admin_commission',
        'adjustments',
        'amount',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'gross_orders' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'branch_commission' => 'decimal:2',
        'admin_commission' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(BranchPayout::class);
    }
}
