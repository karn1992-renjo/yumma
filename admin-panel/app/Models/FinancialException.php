<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialException extends Model
{
    protected $fillable = [
        'ai_decision_id', 'exception_type', 'severity', 'status', 'expected_amount', 'actual_amount',
        'variance_amount', 'context', 'assigned_to', 'resolved_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];
}
