<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDeviceAttestation extends Model
{
    protected $fillable = [
        'driver_id', 'device_id', 'platform', 'provider', 'status', 'risk_score', 'claims', 'verified_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'claims' => 'array',
        'verified_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}