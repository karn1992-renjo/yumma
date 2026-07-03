<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'restaurant_id',
        'user_id',
        'requester_role',
        'assigned_to',
        'ticket_number',
        'subject',
        'category',
        'priority',
        'description',
        'attachment',
        'status',
        'resolved_at',
        'assigned_at',
        'resolve_notes',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];
    
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function latestReply()
    {
        return $this->hasOne(SupportTicketReply::class, 'ticket_id')->latestOfMany();
    }
    
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }
    
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
    
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
    
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent')->open();
    }
}
