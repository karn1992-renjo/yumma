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
        if ($this->option('auto')) {
            $setting = PayoutSetting::where('is_active', true)->first();
            if (! $setting?->auto_generate_enabled || ! $service->shouldRunToday($setting)) {
                $this->info('Automatic payout generation is not due.');
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
        $result = $service->generateDuePayouts(
            $this->option('type'),
            $start,
            $end,
            (bool) $this->option('auto')
        );
        $this->info("Generated {$result['created']} payouts in batch {$result['batch_id']}.");
        return self::SUCCESS;
    }
}
