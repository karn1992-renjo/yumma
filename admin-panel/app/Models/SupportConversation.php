<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_number',
        'order_id',
        'user_id',
        'requester_role',
        'restaurant_id',
        'category',
        'stage',
        'status',
        'priority',
        'assigned_to',
        'assigned_at',
        'resolved_at',
        'resolve_notes',
        'csat_rating',
        'csat_comment',
        'last_message_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_message_at' => 'datetime',
        'csat_rating' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(SupportMessage::class, 'conversation_id')->latestOfMany();
    }

    public function firstMessage()
    {
        return $this->hasOne(SupportMessage::class, 'conversation_id')->oldestOfMany();
    }

    public function scopeBotActive($query)
    {
        return $query->where('stage', 'bot');
    }

    public function scopeEscalated($query)
    {
        return $query->where('stage', 'human');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function scopeResolved($query)
    {
        return $query->where('stage', 'resolved');
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent')->open();
    }
}
