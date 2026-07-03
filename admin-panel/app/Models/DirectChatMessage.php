<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'message_type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
