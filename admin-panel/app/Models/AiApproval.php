<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiApproval extends Model
{
    protected $fillable = [
        'ai_decision_id', 'ai_action_id', 'status', 'approver_type', 'restaurant_id', 'requested_payload', 'modified_payload',
        'admin_note', 'reviewed_by', 'reviewed_at', 'last_reminded_at',
    ];

    protected $casts = [
        'requested_payload' => 'array',
        'modified_payload' => 'array',
        'reviewed_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'restaurant_id' => 'integer',
    ];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AiDecision::class, 'ai_decision_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AiAction::class, 'ai_action_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
