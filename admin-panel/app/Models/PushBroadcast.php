<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushBroadcast extends Model
{
    protected $fillable = [
        'title',
        'body',
        'audience_type',
        'audience_roles',
        'deep_link',
        'data_payload',
        'status',
        'recipients_count',
        'token_count',
        'delivered_count',
        'failed_count',
        'failure_reason',
        'sent_by',
        'sent_at',
        'source',
        'role_group',
        'notification_type',
        'campaign_type',
        'image_status',
        'ai_decision_id',
    ];

    protected $casts = [
        'audience_roles' => 'array',
        'data_payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
