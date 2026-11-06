<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppContact extends Model
{
    protected $table = 'whatsapp_contacts';

    protected $fillable = ['phone', 'user_id', 'profile_name', 'channel', 'opted_in', 'opted_out_at', 'last_inbound_at', 'bot_state', 'bot_context'];

    protected $casts = [
        'opted_in' => 'boolean',
        'opted_out_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'bot_context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'contact_id');
    }

    public function lastMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WhatsAppMessage::class, 'contact_id')->latestOfMany();
    }

    /** Free-form (non-template) messages are only allowed within 24h of the customer's last message. */
    public function inServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }
}
