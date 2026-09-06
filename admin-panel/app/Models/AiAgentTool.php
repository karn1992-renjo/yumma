<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentTool extends Model
{
    protected $fillable = ['ai_agent_id', 'tool_key', 'tool_type', 'risk_level', 'requires_approval', 'is_enabled', 'schema'];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_enabled' => 'boolean',
        'schema' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
