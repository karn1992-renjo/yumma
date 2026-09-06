<?php

namespace App\Console\Commands;

use App\Services\RestaurantBusinessReportService;
use Illuminate\Console\Command;

class SendRestaurantBusinessReports extends Command
{
    protected $signature = 'restaurants:business-reports {--frequency=}';
    protected $description = 'Send scheduled business reports to restaurant owners.';

    public function handle(RestaurantBusinessReportService $reports): int
    {
        $frequency = $this->option('frequency');
        $result = $reports->sendScheduled($frequency, filled($frequency));
        $this->info("{$result['message']} Sent: {$result['sent']}, Skipped: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
