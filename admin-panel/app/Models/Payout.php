<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'restaurant_id', 'driver_id', 'amount', 'status', 'transaction_id',
        'period_start', 'period_end', 'processed_at', 'gateway',
        'gateway_response', 'failure_reason', 'deduction_amount',
        'deduction_reason', 'deduction_revoked_at', 'deduction_revoke_reason',
        'uuid', 'batch_id', 'vendor_type', 'vendor_id', 'gross_amount',
        'platform_commission', 'delivery_fee', 'net_amount', 'currency',
        'gst_on_commission', 'payment_gateway_fee', 'admin_delivery_commission',
        'driver_deduction', 'batch_bonus', 'order_ids', 'breakdown',
        'gateway_reference_id', 'gateway_status', 'idempotency_key',
        'vendor_bank_account_id', 'retry_count', 'next_retry_at',
        'created_by', 'processed_by'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'processed_at' => 'datetime',
        'deduction_revoked_at' => 'datetime',
        'gateway_response' => 'array',
        'next_retry_at' => 'datetime',
        'gross_amount' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'gst_on_commission' => 'decimal:2',
        'payment_gateway_fee' => 'decimal:2',
        'admin_delivery_commission' => 'decimal:2',
        'driver_deduction' => 'decimal:2',
        'batch_bonus' => 'decimal:2',
        'order_ids' => 'array',
        'breakdown' => 'array',
    ];
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(VendorBankAccount::class, 'vendor_bank_account_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PayoutAuditLog::class);
    }

    public function failedAttempts(): HasMany
    {
        return $this->hasMany(FailedPayout::class);
    }
    
    public function getTypeAttribute(): string
    {
        return $this->restaurant_id ? 'restaurant' : 'driver';
    }
}
