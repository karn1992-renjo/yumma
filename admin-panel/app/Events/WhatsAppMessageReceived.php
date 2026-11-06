<?php

namespace App\Events;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;

/** Fired for every inbound WhatsApp message (the chat bot listens to it from Phase 3). */
class WhatsAppMessageReceived
{
    public function __construct(
        public WhatsAppContact $contact,
        public WhatsAppMessage $message,
        public array $raw = [],
    ) {
    }
}
