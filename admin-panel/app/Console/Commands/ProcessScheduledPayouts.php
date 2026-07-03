<?php

namespace App\Console\Commands;

use App\Services\PayoutScheduleService;
use Illuminate\Console\Command;

class ProcessScheduledPayouts extends Command
{
    protected $signature = 'payouts:process-scheduled {--gateway=}';
    protected $description = 'Process pending payouts using the active or selected gateway.';

    public function handle(PayoutScheduleService $service): int
    {
        $report = $service->processScheduled($this->option('gateway'));
        $this->info("Processed payouts. Success: {$report['success']}, Failed: {$report['failed']}.");
        return self::SUCCESS;
    }
}
