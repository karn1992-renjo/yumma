<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'ai_decision_id', 'agent_key', 'provider', 'model', 'context', 'input_tokens', 'output_tokens',
        'estimated_cost', 'latency_ms', 'status', 'error',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:6',
    ];
}
