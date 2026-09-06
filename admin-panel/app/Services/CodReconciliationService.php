<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CodReconciliationService
{
    public const DEPOSIT_REFERENCE_TYPE = 'driver_cod_deposit';
    public const EXCESS_REFERENCE_TYPE = 'driver_cod_excess_credit';

    /**
     * SQL expression for the cash a driver is still holding on an order.
     *
     * `cash_collected_amount` is only populated by the driver OTP-delivery flow;
     * COD orders marked delivered from the admin panel / other paths leave it
     * NULL even though the driver physically took the cash. Fall back to the
     * order `total` in that case so those balances still show up.
     */
    public const CASH_IN_HAND_EXPR =
        '(COALESCE(NULLIF(cash_collected_amount, 0), total) - COALESCE(cod_collected_from_driver_amount, 0))';

    public function pendingOrdersQuery(
        ?int $branchId = null,
        ?int $driverId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Builder {
        return Order::query()
            ->where('status', 'delivered')
            ->where(function ($query) {
                $query->where('delivery_payment_mode', 'cod')
                    ->orWhere('payment_method', 'cod');
            })
            ->whereRaw(self::CASH_IN_HAND_EXPR . ' > 0')
            ->whereNull('cod_deposited_at')
            ->whereNotNull('driver_id')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->when($dateFrom, fn ($query) => $query->whereDate('cash_collected_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('cash_collected_at', '<=', $dateTo));
    }

    public function driverSummaries(?int $branchId = null, ?string $search = null): Collection
    {
        // Cash-in-hand belongs to the DRIVER, not the branch — a driver holds the
        // money regardless of which branch's order it came from (and many COD
        // orders carry a NULL branch_id). So the per-driver total is computed
        // unscoped; the branch filter below only narrows the roster that's shown.
        $totals = $this->pendingOrdersQuery()
            ->selectRaw('driver_id, COUNT(*) as pending_orders')
            ->selectRaw('SUM(' . self::CASH_IN_HAND_EXPR . ') as pending_amount')
            ->selectRaw('MIN(cash_collected_at) as oldest_collected_at')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        // List every delivery partner (optionally scoped by branch / search) so
        // admins can see the whole roster across all zones and branches -- not
        // only the handful who happen to be holding undeposited cash right now.
        // When a branch is picked, still keep any driver who is holding COD cash
        // even if their profile has no branch set, so money is never hidden.
        $cashHolderIds = $totals->keys()->all();

        return User::role('delivery_partner')
            ->when($branchId, fn ($query) => $query->where(function ($q) use ($branchId, $cashHolderIds) {
                $q->where('branch_id', $branchId);
                if (! empty($cashHolderIds)) {
                    $q->orWhereIn('id', $cashHolderIds);
                }
            }))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->with('branch')
            ->orderBy('name')
            ->get()
            ->map(function (User $driver) use ($totals) {
                $summary = $totals->get($driver->id);
                $driver->pending_orders = (int) ($summary->pending_orders ?? 0);
                $driver->pending_amount = (float) ($summary->pending_amount ?? 0);
                $driver->oldest_collected_at = $summary->oldest_collected_at ?? null;

                return $driver;
            })
            // Stable sort (PHP 8.2): drivers holding cash float to the top by
            // amount, everyone else stays in alphabetical order below them.
            ->sortByDesc('pending_amount')
            ->values();
    }

    public function totals(?int $branchId = null): array
    {
        $row = $this->pendingOrdersQuery($branchId)
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COUNT(DISTINCT driver_id) as drivers')
            ->selectRaw('SUM(' . self::CASH_IN_HAND_EXPR . ') as amount')
            ->first();

        return [
            'orders' => (int) ($row->orders ?? 0),
            'drivers' => (int) ($row->drivers ?? 0),
            'amount' => (float) ($row->amount ?? 0),
        ];
    }

    public function driverBalance(User $driver, ?int $branchId = null): float
    {
        return round((float) $this->pendingOrdersQuery($branchId, $driver->id)
            ->selectRaw('SUM(' . self::CASH_IN_HAND_EXPR . ') as amount')
            ->value('amount'), 2);
    }

    public function collectFromDriver(
        User $driver,
        float $collectedAmount,
        ?User $actor,
        ?string $reference = null,
        ?int $branchId = null
    ): array {
        $collectedAmount = round(max(0, $collectedAmount), 2);

        return DB::transaction(function () use ($driver, $collectedAmount, $actor, $reference, $branchId) {
            $orders = $this->pendingOrdersQuery($branchId, $driver->id)
                ->orderBy('cash_collected_at')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $balanceBefore = round((float) $orders->sum(function (Order $order) {
                return $this->pendingOrderAmount($order);
            }), 2);

            $wallet = $this->walletFor($driver);
            $remaining = $collectedAmount;
            $appliedAmount = 0.0;
            $settledCount = 0;
            $touchedOrders = 0;

            foreach ($orders as $order) {
                if ($remaining <= 0) {
                    break;
                }

                $pendingAmount = $this->pendingOrderAmount($order);
                if ($pendingAmount <= 0) {
                    continue;
                }

                $applied = round(min($pendingAmount, $remaining), 2);
                if ($applied <= 0) {
                    continue;
                }

                $this->creditWallet(
                    $wallet,
                    $driver,
                    $applied,
                    self::DEPOSIT_REFERENCE_TYPE,
                    $order->id,
                    'COD cash collected from driver for order #' . ($order->order_number ?? $order->id),
                    $actor,
                    array_filter([
                        'source' => 'cod_reconciliation',
                        'reference' => $reference,
                        'branch_id' => $branchId,
                        'partial' => $applied < $pendingAmount,
                    ], fn ($value) => $value !== null)
                );

                $newCollected = round((float) ($order->cod_collected_from_driver_amount ?? 0) + $applied, 2);
                $isFullyCollected = $newCollected + 0.00001 >= round((float) $order->cash_collected_amount, 2);

                $order->forceFill([
                    'cod_collected_from_driver_amount' => min($newCollected, round((float) $order->cash_collected_amount, 2)),
                    'cod_last_collected_at' => now(),
                    'cod_reconciliation_status' => $isFullyCollected ? 'deposited' : 'partial',
                    'cod_deposited_at' => $isFullyCollected ? now() : null,
                ])->save();

                $remaining = round($remaining - $applied, 2);
                $appliedAmount = round($appliedAmount + $applied, 2);
                $touchedOrders++;
                if ($isFullyCollected) {
                    $settledCount++;
                    try {
                        $codAmount = round((float) $order->cash_collected_amount, 2);
                        $codMemo = 'COD deposit for order #' . ($order->order_number ?? $order->id);
                        $entry = app(\App\Services\Accounting\LedgerPostingService::class)->postCodReconciled($order->id, $codAmount, now(), $codMemo);
                        \App\Services\Integration\LedgerEventEmitter::journal($entry);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Ledger postCodReconciled failed', [
                            'order_id' => $order->id, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $excessAmount = round(max(0, $remaining), 2);
            if ($excessAmount > 0) {
                $this->creditWallet(
                    $wallet,
                    $driver,
                    $excessAmount,
                    self::EXCESS_REFERENCE_TYPE,
                    null,
                    'Extra COD cash collected beyond pending driver balance',
                    $actor,
                    array_filter([
                        'source' => 'cod_excess_credit',
                        'reference' => $reference,
                        'branch_id' => $branchId,
                        'cod_balance_before' => $balanceBefore,
                    ], fn ($value) => $value !== null)
                );
            }

            return [
                'drivers' => 1,
                'orders' => $settledCount,
                'touched_orders' => $touchedOrders,
                'amount' => $collectedAmount,
                'cod_applied_amount' => $appliedAmount,
                'excess_credit_amount' => $excessAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => max(0, round($balanceBefore - $appliedAmount, 2)),
            ];
        });
    }

    public function settleForDriver(User $driver, ?User $actor, ?string $reference = null, ?int $branchId = null): array
    {
        $balance = $this->driverBalance($driver, $branchId);

        if ($balance <= 0) {
            return ['drivers' => 0, 'orders' => 0, 'touched_orders' => 0, 'amount' => 0.0, 'cod_applied_amount' => 0.0, 'excess_credit_amount' => 0.0];
        }

        return $this->collectFromDriver($driver, $balance, $actor, $reference, $branchId);
    }

    /**
     * @param  iterable<User>  $drivers
     */
    public function settleForDrivers(iterable $drivers, ?User $actor, ?string $reference = null, ?int $branchId = null): array
    {
        $driversSettled = 0;
        $ordersSettled = 0;
        $ordersTouched = 0;
        $amountCollected = 0.0;
        $appliedAmount = 0.0;
        $excessAmount = 0.0;

        foreach ($drivers as $driver) {
            $result = $this->settleForDriver($driver, $actor, $reference, $branchId);
            if (($result['cod_applied_amount'] ?? 0) > 0 || ($result['excess_credit_amount'] ?? 0) > 0) {
                $driversSettled++;
            }
            $ordersSettled += (int) ($result['orders'] ?? 0);
            $ordersTouched += (int) ($result['touched_orders'] ?? 0);
            $amountCollected += (float) ($result['amount'] ?? 0);
            $appliedAmount += (float) ($result['cod_applied_amount'] ?? 0);
            $excessAmount += (float) ($result['excess_credit_amount'] ?? 0);
        }

        return [
            'drivers' => $driversSettled,
            'orders' => $ordersSettled,
            'touched_orders' => $ordersTouched,
            'amount' => round($amountCollected, 2),
            'cod_applied_amount' => round($appliedAmount, 2),
            'excess_credit_amount' => round($excessAmount, 2),
        ];
    }

    public function historyQuery(
        ?int $branchId = null,
        ?int $driverId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Builder {
        $query = WalletTransaction::query()
            ->whereIn('reference_type', [self::DEPOSIT_REFERENCE_TYPE, self::EXCESS_REFERENCE_TYPE])
            ->with(['user.branch', 'creator'])
            ->when($driverId, fn ($q) => $q->where('user_id', $driverId))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo));

        if ($branchId) {
            $driverIds = User::role('delivery_partner')->where('branch_id', $branchId)->pluck('id');
            $query->whereIn('user_id', $driverIds);
        }

        return $query->latest();
    }

    private function pendingOrderAmount(Order $order): float
    {
        // Mirror self::CASH_IN_HAND_EXPR: fall back to the order total when the
        // driver flow never recorded cash_collected_amount.
        $collected = (float) $order->cash_collected_amount;
        if ($collected <= 0) {
            $collected = (float) $order->total;
        }

        return round(max(0, $collected - (float) ($order->cod_collected_from_driver_amount ?? 0)), 2);
    }

    private function walletFor(User $driver): Wallet
    {
        return Wallet::where('user_id', $driver->id)->lockForUpdate()->first()
            ?: Wallet::create([
                'user_id' => $driver->id,
                'balance' => 0,
                'locked_balance' => 0,
                'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                'is_active' => true,
            ]);
    }

    private function creditWallet(
        Wallet $wallet,
        User $driver,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        ?User $actor,
        array $meta = []
    ): void {
        $wallet->increment('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $driver->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $actor?->id,
            'meta' => $meta,
        ]);
    }
}
