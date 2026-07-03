<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTicketMessage extends Model
{
    protected $fillable = ['branch_ticket_id', 'user_id', 'message', 'attachments'];

    protected $casts = ['attachments' => 'array'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(BranchTicket::class, 'branch_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
