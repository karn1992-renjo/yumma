<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Console\Command;

class WhatsAppReminders extends Command
{
    protected $signature = 'whatsapp:reminders';

    protected $description = 'Send WhatsApp renewal reminders (Gold ending soon, franchise plan renewing).';

    public function handle(WhatsAppNotifier $notifier): int
    {
        $this->info('Queued ' . $notifier->sendReminders() . ' reminder(s).');

        return self::SUCCESS;
    }
}
