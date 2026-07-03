<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChatMessage extends Model
{
    protected $fillable = [
        'order_id',
        'sender_id',
        'sender_role',
        'recipient_role',
        'message_type',
        'message',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'meta',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
