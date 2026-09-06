<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Services\Tax\TaxConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts balanced double-entry journals for the platform's money events.
 * Every poster is idempotent on (source_type, source_id, kind) and a no-op
 * until the general ledger is switched on (accounting_enabled).
 */
class LedgerPostingService
{
    private array $accountCache = [];

    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    public function enabled(): bool
    {
        return $this->config->accountingEnabled();
    }

    /* ================= primitive ================= */

    /**
     * @param  array<int,array{account:string,debit?:float,credit?:float,party_type?:string,party_id?:int,memo?:string}>  $lines
     */
    public function post(array $lines, string $narration, Carbon|string|null $date = null, array $source = [], bool $isManual = false): ?JournalEntry
    {
        if (! $this->enabled() && ! $isManual) {
            return null;
        }

        $lines = array_values(array_filter($lines, fn ($l) => round((float) ($l['debit'] ?? 0) + (float) ($l['credit'] ?? 0), 2) > 0));
        if (count($lines) < 2) {
            return null;
        }

        $debit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);
        if ($debit !== $credit) {
            Log::warning('Ledger post rejected: unbalanced.', compact('narration', 'debit', 'credit'));

            return null;
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?: now());
        $period = $date->format('Y-m');
        if (AccountingPeriod::isClosed($period)) {
            Log::info('Ledger post skipped: period closed.', compact('period', 'narration'));

            return null;
        }

        $sourceType = $source['type'] ?? null;
        $sourceId = $source['id'] ?? null;
        $kind = $source['kind'] ?? ($isManual ? 'manual' : null);

        return DB::transaction(function () use ($lines, $narration, $date, $period, $sourceType, $sourceId, $kind, $isManual, $debit) {
            if ($sourceType && $sourceId && $kind) {
                $existing = JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->where('kind', $kind)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $fy = $this->config->fy($date);
            $entry = JournalEntry::create([
                'entry_no' => $this->nextEntryNo($fy),
                'date' => $date->toDateString(),
                'narration' => $narration,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'kind' => $kind,
                'fy' => $fy,
                'period' => $period,
                'status' => 'posted',
                'is_manual' => $isManual,
                'posted_by' => auth()->id(),
            ]);

            foreach ($lines as $l) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $this->accountId($l['account']),
                    'debit' => round((float) ($l['debit'] ?? 0), 2),
                    'credit' => round((float) ($l['credit'] ?? 0), 2),
                    'party_type' => $l['party_type'] ?? null,
                    'party_id' => $l['party_id'] ?? null,
                    'memo' => $l['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    public function postManualJournal(array $lines, string $narration, string $date): ?JournalEntry
    {
        return $this->post($lines, $narration, $date, [], true);
    }

    /* ================= typed posters ================= */

    /** Order confirmed: cash owed in, revenue deferred, output tax accrues. */
    public function postOrderPlaced(Order $order): ?JournalEntry
    {
        $order->loadMissing('restaurant');
        $subtotal = (float) $order->subtotal;
        $delivery = (float) $order->delivery_fee;
        $platform = (float) $order->platform_fee;
        $total = (float) $order->total;
        if ($total <= 0) {
            return null;
        }

        $receivable = $order->isCashOnDelivery() ? '1100' : '1110';

        // Whole customer receipt sits in deferred revenue; the tax-accrual
        // hook reclassifies the 9(5) / service GST out of deferred into the
        // payable, and delivery recognises the rest as income.
        return $this->post([
            ['account' => $receivable, 'debit' => $total, 'memo' => 'Order ' . $order->order_number],
            ['account' => '2200', 'credit' => $total, 'memo' => 'Deferred order revenue'],
        ], 'Order placed ' . $order->order_number, $order->created_at, [
            'type' => Order::class, 'id' => $order->id, 'kind' => 'order_placed',
        ]);
    }

    /**
     * Order delivered: move the remaining deferred revenue (customer receipt
     * less the GST already reclassified to payables) into what we owe the
     * restaurant + driver, with the platform's take as the balancing figure.
     * Always balanced by construction.
     */
    public function postOrderDelivered(Order $order): ?JournalEntry
    {
        $earn = app(\App\Services\PayoutCalculationService::class);
        $r = $earn->calculateRestaurantEarning($order);
        $restaurantNet = round((float) ($r['restaurant_earning'] ?? 0), 2);
        $commGst = round((float) ($r['gst_on_commission'] ?? 0), 2);
        $driverNet = round($order->driver_id ? (float) ($earn->calculateDriverEarning($order)['driver_earning'] ?? 0) : 0, 2);

        // What still sits in deferred for this order after the tax-accrual hook.
        $ecoGst = (float) $order->eco_gst_food;
        $serviceGst = (float) $order->service_gst;
        $deferredRemaining = round((float) $order->total - $ecoGst - $serviceGst, 2);
        if ($deferredRemaining <= 0) {
            return null;
        }

        $platformTake = round($deferredRemaining - $restaurantNet - $driverNet - $commGst, 2);

        $lines = [
            ['account' => '2200', 'debit' => $deferredRemaining, 'memo' => 'Recognise ' . $order->order_number],
            ['account' => '2000', 'credit' => $restaurantNet, 'party_type' => \App\Models\Restaurant::class, 'party_id' => $order->restaurant_id, 'memo' => 'Owed to restaurant'],
            ['account' => '2320', 'credit' => $commGst, 'memo' => 'GST on commission (payable)'],
        ];
        if ($order->driver_id) {
            $lines[] = ['account' => '2010', 'credit' => $driverNet, 'party_type' => \App\Models\User::class, 'party_id' => $order->driver_id, 'memo' => 'Owed to driver'];
        }
        // Platform's net take (commission + platform fee + delivery margin −
        // gateway fee − subsidies); one line, split later if needed.
        $lines[] = $platformTake >= 0
            ? ['account' => '4000', 'credit' => $platformTake, 'memo' => 'Platform net revenue']
            : ['account' => '5020', 'debit' => -$platformTake, 'memo' => 'Net platform cost on order'];

        return $this->post($lines, 'Order delivered ' . $order->order_number, $order->delivered_at ?: now(), [
            'type' => Order::class, 'id' => $order->id, 'kind' => 'order_delivered',
        ]);
    }

    /** A GST / TDS / TCS / cess accrual row -> its payable + contra. */
    public function postTaxAccrual(TaxLedgerEntry $e): ?JournalEntry
    {
        $amount = round((float) $e->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        [$payable, $contra, $contraSide] = match ($e->kind) {
            // 9(5) / service GST: reclassify out of the customer's deferred
            // revenue into the payable (postOrderPlaced put the whole receipt
            // in 2200).
            TaxLedgerEntry::KIND_GST_9_5 => ['2300', '2200', 'debit'],
            TaxLedgerEntry::KIND_GST_SERVICE => ['2310', '2200', 'debit'],
            // Commission GST is booked by postOrderDelivered against 2320;
            // TDS / TCS withholding is booked by postPayout at settlement.
            TaxLedgerEntry::KIND_GST_COMMISSION,
            TaxLedgerEntry::KIND_TCS,
            TaxLedgerEntry::KIND_TDS_194O,
            TaxLedgerEntry::KIND_TDS_194C => [null, null, null],
            'gig_cess' => ['2360', $this->config->gigCessBorneBy() === 'driver' ? '2010' : '5020', 'debit'],
            default => [null, null, null],
        };
        if (! $payable) {
            return null;
        }

        return $this->post([
            ['account' => $contra, $contraSide => $amount, 'memo' => $e->kind],
            ['account' => $payable, 'credit' => $amount, 'memo' => $e->kind . ' payable'],
        ], 'Tax accrual ' . $e->kind, $e->created_at, [
            'type' => TaxLedgerEntry::class, 'id' => $e->id, 'kind' => 'tax_' . $e->kind,
        ]);
    }

    /** A settlement payout: clear the vendor payable, move cash out. */
    public function postPayout(Payout $payout): ?JournalEntry
    {
        $gross = round((float) ($payout->pre_tax_amount ?? $payout->gross_amount ?? $payout->net_amount), 2);
        $net = round((float) $payout->net_amount, 2);
        $tds = round((float) ($payout->tds_amount ?? 0), 2);
        $tcs = round((float) ($payout->tcs_amount ?? 0), 2);
        $payableAcct = $payout->restaurant_id ? '2000' : '2010';
        $tdsAcct = $payout->restaurant_id ? '2340' : '2350';

        $lines = [
            ['account' => $payableAcct, 'debit' => $net + $tds + $tcs, 'party_type' => $payout->restaurant_id ? \App\Models\Restaurant::class : \App\Models\User::class, 'party_id' => $payout->restaurant_id ?: $payout->driver_id, 'memo' => 'Settle payout ' . $payout->uuid],
            ['account' => '1000', 'credit' => $net, 'memo' => 'Bank payout'],
        ];
        if ($tds > 0) {
            $lines[] = ['account' => $tdsAcct, 'credit' => $tds, 'memo' => 'TDS withheld'];
        }
        if ($tcs > 0) {
            $lines[] = ['account' => '2330', 'credit' => $tcs, 'memo' => 'TCS withheld'];
        }

        return $this->post($lines, 'Payout ' . $payout->uuid, $payout->created_at, [
            'type' => Payout::class, 'id' => $payout->id, 'kind' => 'payout',
        ]);
    }

    public function postCodReconciled(int $refId, float $amount, Carbon|string $date, string $memo = 'COD deposit'): ?JournalEntry
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        return $this->post([
            ['account' => '1000', 'debit' => $amount, 'memo' => $memo],
            ['account' => '1100', 'credit' => $amount, 'memo' => $memo],
        ], $memo, $date, ['type' => 'cod_reconciliation', 'id' => $refId, 'kind' => 'cod_reconciled']);
    }

    public function postRefund(Order $order, float $amount, string $to = 'wallet'): ?JournalEntry
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        return $this->post([
            ['account' => '5030', 'debit' => $amount, 'memo' => 'Refund order ' . $order->order_number],
            ['account' => $to === 'bank' ? '1000' : '2100', 'credit' => $amount, 'memo' => 'Refund to ' . $to],
        ], 'Refund ' . $order->order_number, now(), [
            'type' => Order::class, 'id' => $order->id, 'kind' => 'refund',
        ]);
    }

    public function postWalletRecharge(int $refId, float $amount, Carbon|string $date): ?JournalEntry
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        return $this->post([
            ['account' => '1000', 'debit' => $amount, 'memo' => 'Customer wallet top-up'],
            ['account' => '2100', 'credit' => $amount, 'memo' => 'Customer wallet liability'],
        ], 'Wallet recharge', $date, ['type' => 'wallet_recharge', 'id' => $refId, 'kind' => 'wallet_recharge']);
    }

    /* ================= helpers ================= */

    private function accountId(string $code): int
    {
        return $this->accountCache[$code] ??= (int) (ChartOfAccount::byCode($code)?->id
            ?? ChartOfAccount::byCode('1900')?->id
            ?? ChartOfAccount::query()->value('id'));
    }

    private function nextEntryNo(string $fy): string
    {
        $n = JournalEntry::where('fy', $fy)->count() + 1;

        return 'JV/' . $fy . '/' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
