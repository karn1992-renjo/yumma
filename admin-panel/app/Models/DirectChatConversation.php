<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectChatConversation extends Model
{
    protected $fillable = [
        'title',
        'order_id',
        'context_type',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'direct_chat_participants', 'conversation_id', 'user_id')
            ->withPivot(['last_read_at', 'muted_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DirectChatMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(DirectChatMessage::class, 'conversation_id')->latestOfMany();
    }
}
