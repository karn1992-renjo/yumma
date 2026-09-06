<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Tax\TaxConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Replays the auto-posting hooks over a date range. Every poster is
 * idempotent, so this only fills gaps left by a missed hook -- it never
 * double-posts.
 */
class LedgerReconcile extends Command
{
    protected $signature = 'ledger:reconcile {--from=} {--to=}';

    protected $description = 'Backfill / reconcile the general ledger from orders, payouts and tax accruals.';

    public function handle(LedgerPostingService $ledger, TaxConfig $config): int
    {
        if (! $config->accountingEnabled()) {
            $this->warn('General ledger is disabled (accounting_enabled). Nothing to do.');

            return self::SUCCESS;
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : now()->subMonths(3)->startOfDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : now()->endOfDay();

        $this->info("Reconciling {$from->toDateString()} .. {$to->toDateString()}");

        $taxLedger = app(\App\Services\Tax\TaxLedgerService::class);
        Order::whereBetween('created_at', [$from, $to])->orderBy('id')->chunkById(200, function ($orders) use ($ledger, $taxLedger) {
            foreach ($orders as $o) {
                $ledger->postOrderPlaced($o);
                if ($o->status === 'delivered') {
                    $ledger->postOrderDelivered($o);
                    $taxLedger->recordGigCess($o);
                }
            }
        });

        TaxLedgerEntry::whereBetween('created_at', [$from, $to])->orderBy('id')->chunkById(300, function ($rows) use ($ledger) {
            foreach ($rows as $e) {
                $ledger->postTaxAccrual($e);
            }
        });

        Payout::whereBetween('created_at', [$from, $to])->orderBy('id')->chunkById(200, function ($payouts) use ($ledger) {
            foreach ($payouts as $p) {
                $ledger->postPayout($p);
            }
        });

        // COD deposits: driver cash that has fully reached the platform.
        Order::whereBetween('updated_at', [$from, $to])
            ->where('cod_reconciliation_status', 'deposited')
            ->where('cash_collected_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(300, function ($orders) use ($ledger) {
                foreach ($orders as $o) {
                    $ledger->postCodReconciled(
                        $o->id,
                        (float) ($o->cod_collected_from_driver_amount ?? $o->cash_collected_amount),
                        $o->cod_deposited_at ?? $o->cod_last_collected_at ?? $o->updated_at,
                        'COD deposit for order #' . ($o->order_number ?? $o->id)
                    );
                }
            });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
