<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDecision extends Model
{
    protected $fillable = [
        'agent_key', 'decision_type', 'trigger', 'provider', 'model', 'risk_level', 'confidence',
        'input_snapshot', 'reason_summary', 'proposed_action', 'expected_result', 'expected_financial_impact',
        'policy_status', 'requires_approval', 'auto_executed', 'execution_status', 'execution_result',
        'actual_result', 'roi',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'input_snapshot' => 'array',
        'proposed_action' => 'array',
        'expected_result' => 'array',
        'expected_financial_impact' => 'decimal:2',
        'requires_approval' => 'boolean',
        'auto_executed' => 'boolean',
        'execution_result' => 'array',
        'actual_result' => 'array',
        'roi' => 'decimal:4',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(AiAction::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AiApproval::class);
    }
}
