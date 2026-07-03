<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketReply extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachment',
        'is_admin_reply',
        'is_system_message',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_admin_reply' => 'boolean',
        'is_system_message' => 'boolean',
    ];
    
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function scopeAdminReplies($query)
    {
        return $query->where('is_admin_reply', true);
    }
    
    public function scopeUserReplies($query)
    {
        return $query->where('is_admin_reply', false);
    }
}