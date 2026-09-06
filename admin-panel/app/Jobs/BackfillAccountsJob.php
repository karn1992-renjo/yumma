<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Services\Integration\LedgerEventEmitter;
use App\Services\Integration\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One-shot replay of everything already in admin/'s books to the Accounts/ app:
 * every journal entry (with its balanced lines + an order/payout mirror where
 * the entry has one) and every tax-ledger row. Safe to run repeatedly — the
 * Accounts side dedupes journals on source and tax rows on id.
 *
 * Queued from Settings -> Integrations -> "Sync existing data".
 */
class BackfillAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public ?string $since = null,   // optional 'Y-m-d' lower bound on entry date
        public ?int $requestedBy = null,
    ) {
    }

    public function handle(): void
    {
        if (! WebhookDispatcher::enabled('accounts')) {
            Log::warning('BackfillAccountsJob skipped — accounts integration is off.');

            return;
        }

        $journals = 0;
        $taxRows = 0;

        JournalEntry::query()
            ->with('lines.account')
            ->when($this->since, fn ($q) => $q->whereDate('date', '>=', $this->since))
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$journals) {
                foreach ($chunk as $entry) {
                    LedgerEventEmitter::journal($entry, $this->mirrorFor($entry));
                    $journals++;
                }
            });

        TaxLedgerEntry::query()
            ->when($this->since, fn ($q) => $q->whereDate('created_at', '>=', $this->since))
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$taxRows) {
                foreach ($chunk as $row) {
                    LedgerEventEmitter::taxAccrued($row);
                    $taxRows++;
                }
            });

        $summary = [
            'at' => now()->toIso8601String(),
            'journals' => $journals,
            'tax_rows' => $taxRows,
            'since' => $this->since,
            'by' => $this->requestedBy,
        ];
        AppSetting::setValue('integration_accounts_last_sync', json_encode($summary));
        Log::info('BackfillAccountsJob queued deliveries', $summary);
    }

    /** Attach the order / payout snapshot the GST read-models need. */
    private function mirrorFor(JournalEntry $entry): array
    {
        try {
            if ($entry->source_type === Order::class && $entry->source_id) {
                $order = Order::find($entry->source_id);

                return $order ? LedgerEventEmitter::orderMirror($order) : [];
            }
            if ($entry->source_type === Payout::class && $entry->source_id) {
                $payout = Payout::with('restaurant', 'driver')->find($entry->source_id);

                return $payout ? LedgerEventEmitter::payoutMirror($payout) : [];
            }
        } catch (\Throwable $e) {
            Log::warning('BackfillAccountsJob mirror failed for entry ' . $entry->id . ': ' . $e->getMessage());
        }

        return [];
    }
}
