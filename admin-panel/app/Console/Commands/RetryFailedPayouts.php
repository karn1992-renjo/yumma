<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Services\BulkPayoutService;
use App\Services\PayoutSettlementService;
use Illuminate\Console\Command;

class RetryFailedPayouts extends Command
{
    protected $signature = 'payouts:retry-failed {--max-retries=3}';
    protected $description = 'Retry failed payouts with exponential backoff.';

    public function handle(
        BulkPayoutService $bulkPayoutService,
        PayoutSettlementService $settlementService
    ): int
    {
        $maxRetries = (int) $this->option('max-retries');
        $payouts = Payout::where('status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->get();

        $reserved = $payouts->filter(function (Payout $payout) use ($settlementService) {
            if (! $settlementService->reserveFundsForRetry($payout)) {
                $payout->update([
                    'failure_reason' => 'Insufficient wallet balance for automatic retry.',
                    'next_retry_at' => now()->addHour(),
                ]);
                return false;
            }

            $payout->update(['status' => 'pending', 'failure_reason' => null, 'next_retry_at' => null]);
            return true;
        });

        $report = $bulkPayoutService->process($reserved);
        $this->info("Retry complete. Success: {$report['success']}, Failed: {$report['failed']}.");
        return self::SUCCESS;
    }
}
