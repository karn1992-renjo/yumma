<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'contact_id', 'direction', 'provider', 'phone', 'type', 'template', 'body', 'payload',
        'provider_message_id', 'status', 'error', 'event', 'related_type', 'related_id',
    ];

    protected $casts = ['payload' => 'array'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
