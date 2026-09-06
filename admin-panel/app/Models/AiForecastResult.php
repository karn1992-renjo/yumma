<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecastResult extends Model
{
    protected $fillable = ['ai_forecast_id', 'actual_orders', 'actual_available_drivers', 'accuracy', 'outcome'];

    protected $casts = [
        'accuracy' => 'decimal:4',
        'outcome' => 'array',
    ];
}
