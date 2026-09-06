<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceAiSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'provider',
        'fallback_provider',
        'fallback_used',
        'started_at',
        'ended_at',
        'active_seconds',
        'input_audio_seconds',
        'output_audio_seconds',
        'tool_calls_count',
        'order_id',
        'status',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'fallback_used' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'input_audio_seconds' => 'integer',
            'output_audio_seconds' => 'integer',
            'tool_calls_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
