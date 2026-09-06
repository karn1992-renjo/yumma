<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAlert extends Model
{
    protected $fillable = ['ai_decision_id', 'agent_key', 'severity', 'title', 'message', 'status', 'context', 'resolved_at'];

    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];
}
