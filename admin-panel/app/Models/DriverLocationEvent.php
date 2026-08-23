<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocationEvent extends Model
{
    protected $fillable = [
        'driver_id', 'lat', 'lng', 'accuracy_meters', 'speed_mps', 'heading',
        'is_mock_location', 'device_id', 'attestation_status', 'risk_score',
        'risk_reasons', 'recorded_at',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'accuracy_meters' => 'decimal:2',
        'speed_mps' => 'decimal:2',
        'heading' => 'decimal:2',
        'is_mock_location' => 'boolean',
        'risk_score' => 'integer',
        'risk_reasons' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}