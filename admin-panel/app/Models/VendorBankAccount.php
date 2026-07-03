<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'user_id',
        'account_holder_name',
        'account_number_encrypted',
        'account_number_last4',
        'ifsc_code_encrypted',
        'upi_id_encrypted',
        'bank_name',
        'gateway_contact_id',
        'gateway_fund_account_id',
        'stripe_account_id',
        'cashfree_beneficiary_id',
        'is_default',
        'is_verified',
        'verified_at',
        'meta',
    ];

    protected $casts = [
        'account_number_encrypted' => 'encrypted',
        'ifsc_code_encrypted' => 'encrypted',
        'upi_id_encrypted' => 'encrypted',
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForVendor($query, string $vendorType, int $vendorId)
    {
        return $query->where('vendor_type', $vendorType)->where('vendor_id', $vendorId);
    }

    public function getAccountNumberAttribute(): ?string
    {
        return $this->account_number_encrypted;
    }

    public function getIfscCodeAttribute(): ?string
    {
        return $this->ifsc_code_encrypted;
    }

    public function getUpiIdAttribute(): ?string
    {
        return $this->upi_id_encrypted;
    }
}
