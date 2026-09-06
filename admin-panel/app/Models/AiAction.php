<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAction extends Model
{
    protected $fillable = [
        'ai_decision_id', 'action_key', 'risk_level', 'status', 'idempotency_key', 'parameters',
        'policy_result', 'result', 'executed_by', 'executed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'policy_result' => 'array',
        'result' => 'array',
        'executed_at' => 'datetime',
    ];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AiDecision::class, 'ai_decision_id');
    }
}
