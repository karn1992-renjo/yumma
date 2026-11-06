<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppBroadcastService;
use Illuminate\Console\Command;

class WhatsAppBroadcasts extends Command
{
    protected $signature = 'whatsapp:broadcasts {--limit=200 : Max messages to send in this run}';

    protected $description = 'Send the next batch of running WhatsApp broadcasts.';

    public function handle(WhatsAppBroadcastService $broadcasts): int
    {
        $sent = $broadcasts->runBatch((int) $this->option('limit'));
        $this->info("Attempted {$sent} WhatsApp broadcast message(s).");

        return self::SUCCESS;
    }
}
