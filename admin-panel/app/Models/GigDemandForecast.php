<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GigDemandForecast extends Model
{
    protected $fillable = [
        'area_id',
        'date',
        'hour',
        'historical_orders',
        'forecasted_orders',
        'recommended_capacity',
        'demand_score',
        'surge_multiplier',
        'signals',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'historical_orders' => 'integer',
        'forecasted_orders' => 'integer',
        'recommended_capacity' => 'integer',
        'demand_score' => 'decimal:2',
        'surge_multiplier' => 'decimal:2',
        'signals' => 'array',
    ];

    public function area()
    {
        return $this->belongsTo(DeliveryArea::class, 'area_id');
    }
}