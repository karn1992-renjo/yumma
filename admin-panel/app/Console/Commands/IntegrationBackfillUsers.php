<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Observers\UserIntegrationObserver;
use Illuminate\Console\Command;

class IntegrationBackfillUsers extends Command
{
    protected $signature = 'integration:backfill-users';

    protected $description = 'Push every accountant / hr_manager / employee user to the standalone apps.';

    public function handle(UserIntegrationObserver $observer): int
    {
        $count = 0;
        User::role(['accountant', 'hr_manager', 'employee'])->chunkById(200, function ($users) use ($observer, &$count) {
            foreach ($users as $user) {
                $observer->sync($user);
                $count++;
            }
        });

        $this->info("Queued identity sync for {$count} user(s).");

        return self::SUCCESS;
    }
}
