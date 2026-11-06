<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppBroadcastTemplate extends Model
{
    protected $table = 'whatsapp_broadcast_templates';

    protected $fillable = ['name', 'template_name', 'body', 'variables', 'language', 'provider_template_id', 'status', 'notes', 'created_by'];

    protected $casts = ['variables' => 'array'];

    public function usable(): bool
    {
        return $this->status === 'approved' && filled($this->provider_template_id);
    }
}
