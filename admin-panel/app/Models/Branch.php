<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'logo',
        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_user_id',
        'country',
        'state',
        'city',
        'address',
        'gst_number',
        'pan_number',
        'trade_license',
        'status',
        'platform_commission_percent',
        'branch_share_percent',
        'admin_share_percent',
        'settlement_cycle',
        'bank_details',
        'notes',
    ];

    protected $casts = [
        'bank_details' => 'array',
        'platform_commission_percent' => 'decimal:2',
        'branch_share_percent' => 'decimal:2',
        'admin_share_percent' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(BranchWallet::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(BranchZone::class);
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(BranchSettlement::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(BranchPayout::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BranchAuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
