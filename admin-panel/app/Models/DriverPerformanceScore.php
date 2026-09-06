<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPerformanceScore extends Model
{
    protected $fillable = ['driver_id', 'score_date', 'score', 'classification', 'factors', 'raw_metrics'];

    protected $casts = [
        'score_date' => 'date',
        'score' => 'decimal:2',
        'factors' => 'array',
        'raw_metrics' => 'array',
    ];
}
