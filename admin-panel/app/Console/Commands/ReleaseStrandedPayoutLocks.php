<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Models\WalletTransaction;
use App\Services\PayoutSettlementService;
use Illuminate\Console\Command;

/**
 * Mops up wallet funds that stayed in `locked_balance` after a payout ended in
 * `failed` / `cancelled` without its reserve being reversed (legacy null-source
 * payouts, or the pre-patch early-exit branches in BulkPayoutService).
 *
 *   php artisan payouts:release-stranded-locks --since=90 [--dry-run]
 */
class ReleaseStrandedPayoutLocks extends Command
{
    protected $signature = 'payouts:release-stranded-locks {--since=30 : Look back this many days} {--dry-run}';

    protected $description = 'Return still-locked wallet funds from failed/cancelled payouts to spendable balance.';

    public function handle(PayoutSettlementService $settlement): int
    {
        $since = now()->subDays((int) $this->option('since'));
        $dry = (bool) $this->option('dry-run');

        $payouts = Payout::with(['restaurant.owner', 'driver'])
            ->whereIn('status', ['failed', 'cancelled'])
            ->where('updated_at', '>=', $since)
            ->orderBy('id')
            ->get();

        $released = 0;
        $skipped = 0;

        foreach ($payouts as $payout) {
            // Already reversed? (releaseLockedFundsIfNeeded writes a matching credit)
            $alreadyReversed = WalletTransaction::where('reference_type', 'payout')
                ->where('reference_id', $payout->id)
                ->where('type', 'credit')
                ->exists();

            if ($alreadyReversed) {
                $skipped++;
                continue;
            }

            $payee = $payout->driver_id
                ? $payout->driver
                : $payout->restaurant?->owner;

            $locked = $payee
                ? (float) optional($payee->wallet)->locked_balance
                : 0.0;

            if (! $payee || $locked <= 0) {
                $skipped++;
                continue;
            }

            $amount = min($locked, (float) $payout->amount);
            $this->line(sprintf(
                '%s payout #%d (%s) — release ₹%s from user #%d',
                $dry ? '[dry-run]' : 'RELEASE',
                $payout->id,
                $payout->status,
                number_format($amount, 2),
                $payee->id
            ));

            if (! $dry) {
                $settlement->releaseLockedFundsIfNeeded($payout, true);
                $released++;
            }
        }

        $this->info("Done. released={$released} skipped={$skipped} scanned={$payouts->count()}");

        return self::SUCCESS;
    }
}
