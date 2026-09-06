<?php

namespace App\Console\Commands;

use App\Services\PayoutScheduleService;
use App\Models\PayoutSetting;
use Illuminate\Console\Command;

class GeneratePayouts extends Command
{
    protected $signature = 'payouts:generate {--period-start=} {--period-end=} {--type=all} {--auto}';
    protected $description = 'Generate restaurant and driver payouts for a date range.';

    public function handle(PayoutScheduleService $service): int
    {
        $setting = null;
        $dueTypes = [$this->option('type')];          // manual run: all | restaurant | driver
        if ($this->option('auto')) {
            $setting = PayoutSetting::where('is_active', true)->first();
            if (! $setting?->auto_generate_enabled) {
                $this->info('Automatic payout generation is disabled.');
                return self::SUCCESS;
            }
            $dueTypes = $service->dueVendorTypesToday($setting);
            if (empty($dueTypes)) {
                $this->info('Automatic payout generation is not due for any vendor type today.');
                return self::SUCCESS;
            }
        }

        $end = $this->option('period-end') ?: now()->subDay()->endOfDay();
        $start = $this->option('period-start');
        if (! $start) {
            $endDate = \Carbon\Carbon::parse($end);
            $start = match ($setting?->schedule_frequency) {
                'weekly' => $endDate->copy()->subDays(6)->startOfDay(),
                'biweekly' => $endDate->copy()->subDays(13)->startOfDay(),
                'monthly' => $endDate->copy()->startOfMonth(),
                default => $endDate->copy()->startOfDay(),
            };
        }
        $total = 0;
        $batches = [];
        foreach ($dueTypes as $type) {
            $result = $service->generateDuePayouts($type, $start, $end, (bool) $this->option('auto'));
            $total += $result['created'];
            $batches[] = $result['batch_id'];
        }
        $this->info("Generated {$total} payouts (" . implode(', ', $batches) . ").");
        return self::SUCCESS;
    }
}
