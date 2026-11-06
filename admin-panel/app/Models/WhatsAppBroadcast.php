<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppBroadcast extends Model
{
    protected $table = 'whatsapp_broadcasts';

    protected $fillable = ['title', 'template', 'params', 'header_image', 'roles', 'audience', 'audience_days', 'status', 'total', 'sent', 'failed', 'created_by', 'started_at', 'finished_at'];

    protected $casts = ['params' => 'array', 'roles' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppBroadcastRecipient::class, 'broadcast_id');
    }
}
