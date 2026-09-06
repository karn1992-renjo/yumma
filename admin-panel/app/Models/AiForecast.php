<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecast extends Model
{
    protected $fillable = [
        'area_id', 'forecast_type', 'window_start', 'window_end', 'window_minutes', 'expected_orders',
        'lower_bound', 'upper_bound', 'confidence', 'required_drivers', 'available_drivers', 'shortage', 'signals',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'confidence' => 'decimal:4',
        'signals' => 'array',
    ];
}
