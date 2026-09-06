<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOnboardingIncentive;
use App\Models\User;
use App\Models\AppSetting;
use App\Models\CommissionSetting;
use App\Models\DeliveryChargeSetting;
use App\Models\Payout;
use App\Models\PayoutSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutCalculationService
{
    protected $commissionRate;
    protected $gstRate;
    protected $gatewayFeeRate;
    
    public function __construct()
    {
        $this->commissionRate = $this->normalizePercentage($this->globalRestaurantCommissionRate());
        $this->gstRate = $this->normalizePercentage($this->adminCommissionGstRate());
        $this->gatewayFeeRate = $this->normalizePercentage(AppSetting::getValue('gateway_fee_rate', 2));
    }

    protected function globalRestaurantCommissionRate(): float
    {
        $setting = CommissionSetting::where('type', CommissionSetting::RESTAURANT)->first();

        if ($setting) {
            return $setting->is_active ? (float) $setting->rate : 0.0;
        }

        return 15.0;
    }

    protected function adminCommissionGstRate(): float
    {
        $rate = AppSetting::getValue('gst_rate');

        return is_numeric($rate) ? (float) $rate : 0.0;
    }

    protected function normalizePercentage($value)
    {
        if (!is_numeric($value)) {
            $value = preg_replace('/[^0-9.\-]/', '', (string) $value);
        }

        $value = (float) $value;

        return $value / 100;
    }
    
    public function calculateRestaurantEarning(Order $order)
    {
        $commissionBase = max(0, (float) $order->subtotal);
        $commissionDefinition = $this->restaurantCommissionDefinition($order);
        $platformCommission = $this->restaurantCommissionAmount($order, $commissionBase);
        $gstOnCommission = $platformCommission * $this->gstRate;
        $gatewayFee = $this->isOnlinePayment($order)
            ? $order->total * $this->gatewayFeeRate
            : 0;
        $restaurantDeliverySubsidy = max(0, (float) ($order->restaurant_delivery_subsidy ?? 0));
        $restaurantPromotionLiability = max(0, (float) ($order->promotion_restaurant_liability ?? 0));
        // Long-distance charge (Settings -> Delivery Radius) is borne by the restaurant.
        $longDistanceCharge = max(0, (float) ($order->long_distance_charge ?? 0));

        $restaurantEarning = $commissionBase - $platformCommission - $gstOnCommission - $gatewayFee - $restaurantDeliverySubsidy - $restaurantPromotionLiability - $longDistanceCharge;

        return [
            'subtotal' => round($commissionBase, 2),
            'commission_type' => $commissionDefinition['type'],
            'commission_value' => $commissionDefinition['value'],
            'platform_commission' => round($platformCommission, 2),
            'gst_on_commission' => round($gstOnCommission, 2),
            'payment_gateway_fee' => round($gatewayFee, 2),
            'restaurant_delivery_subsidy' => round($restaurantDeliverySubsidy, 2),
            'promotion_restaurant_liability' => round($restaurantPromotionLiability, 2),
            'long_distance_charge' => round($longDistanceCharge, 2),
            'restaurant_earning' => max(0, round($restaurantEarning, 2))
        ];
    }
    
    public function calculateDriverEarning(Order $order)
    {
        $customerDeliveryFee = (float) $order->delivery_fee;
        $chargeableDeliveryFee = $this->chargeableDeliveryFee($order);
        $freeDeliveryContribution = $this->freeDeliveryContribution($order, $chargeableDeliveryFee);
        $multipleOrderBonus = $this->calculateMultipleOrderBonus($order);
        // The long-distance charge the restaurant was billed flows to the
        // driver who covers the distance.
        $longDistanceCharge = max(0, (float) ($order->long_distance_charge ?? 0));

        // Driver commission applies to the whole per-order earning (delivery
        // fee + long-distance charge + multi-order bonus). Tips are handled
        // separately and never commissioned.
        $grossEarning = $chargeableDeliveryFee + $longDistanceCharge + $multipleOrderBonus;
        $driverCommission = CommissionSetting::calculate(CommissionSetting::DRIVER, $grossEarning);
        $finalEarning = $grossEarning - $driverCommission;

        return [
            'delivery_fee' => $customerDeliveryFee,
            'delivery_base' => round($chargeableDeliveryFee, 2),
            'chargeable_delivery_fee' => round($chargeableDeliveryFee, 2),
            'free_delivery_contribution' => round($freeDeliveryContribution, 2),
            'long_distance_charge' => round($longDistanceCharge, 2),
            'gross_earning' => round($grossEarning, 2),
            'driver_commission' => round($driverCommission, 2),
            'driver_commission_type' => CommissionSetting::getCalculationType(CommissionSetting::DRIVER),
            'driver_commission_value' => (float) CommissionSetting::getRate(CommissionSetting::DRIVER),
            'multiple_order_bonus' => round($multipleOrderBonus, 2),
            'driver_earning' => max(0, round($finalEarning, 2))
        ];
    }

    public function aggregateRestaurantPayouts($startDate, $endDate, bool $includeEarlierUnpaid = false): array
    {
        return Order::with('restaurant.owner')
            ->where('status', 'delivered')
            ->where('payout_processed', true)
            ->whereNull('restaurant_payout_id')
            ->when(! $includeEarlierUnpaid, fn ($query) => $query->whereDate('delivered_at', '>=', $startDate))
            ->whereDate('delivered_at', '<=', $endDate)
            ->get()
            ->groupBy('restaurant_id')
            ->map(function ($orders, $restaurantId) {
                $restaurant = $orders->first()->restaurant;
                $gross = (float) $orders->sum('subtotal');
                $commission = (float) $orders->sum('platform_commission');
                $gst = (float) $orders->sum('gst_on_commission');
                $gatewayFee = (float) $orders->sum('payment_gateway_fee');
                $delivery = (float) $orders->sum('delivery_fee');
                $adminDeliverySubsidy = (float) $orders->sum('admin_delivery_subsidy');
                $restaurantDeliverySubsidy = (float) $orders->sum('restaurant_delivery_subsidy');
                $longDistanceCharge = (float) $orders->sum('long_distance_charge');
                $net = (float) $orders->sum('restaurant_earning');

                return [
                    'restaurant_id' => (int) $restaurantId,
                    'vendor_type' => 'restaurant',
                    'vendor_id' => (int) $restaurantId,
                    'user_id' => $restaurant?->owner_id,
                    'gross_amount' => round($gross, 2),
                    'platform_commission' => round($commission, 2),
                    'gst_on_commission' => round($gst, 2),
                    'payment_gateway_fee' => round($gatewayFee, 2),
                    'delivery_fee' => round($delivery, 2),
                    'admin_delivery_subsidy' => round($adminDeliverySubsidy, 2),
                    'restaurant_delivery_subsidy' => round($restaurantDeliverySubsidy, 2),
                    'amount' => round($net, 2),
                    'order_ids' => $orders->pluck('id')->values()->all(),
                    'breakdown' => [
                        'order_count' => $orders->count(),
                        'long_distance_charge' => round($longDistanceCharge, 2),
                        'commission_rules' => $orders->map(fn ($order) => [
                            'type' => $order->restaurant_commission_type,
                            'value' => (float) $order->restaurant_commission_value,
                        ])->unique(fn ($rule) => $rule['type'] . ':' . $rule['value'])->values()->all(),
                    ],
                ];
            })
            ->filter(fn ($row) => $row['amount'] >= $this->minimumPayoutAmount())
            ->values()
            ->all();
    }

    public function aggregateDriverPayouts($startDate, $endDate, bool $includeEarlierUnpaid = false): array
    {
        $rows = Order::with('driver')
            ->where('status', 'delivered')
            ->where('payout_processed', true)
            ->whereNotNull('driver_id')
            ->whereNull('driver_payout_id')
            ->when(! $includeEarlierUnpaid, fn ($query) => $query->whereDate('delivered_at', '>=', $startDate))
            ->whereDate('delivered_at', '<=', $endDate)
            ->get()
            ->groupBy('driver_id')
            ->map(function ($orders, $driverId) {
                $gross = (float) $orders->sum('driver_delivery_base');
                $driverCommission = (float) $orders->sum(fn ($order) =>
                    (float) $order->admin_delivery_commission + (float) $order->driver_deduction
                );
                $adminDeliverySubsidy = (float) $orders->sum('admin_delivery_subsidy');
                $batchBonus = (float) $orders->sum('batch_bonus');
                $longDistanceCharge = (float) $orders->sum('long_distance_charge');
                $net = (float) $orders->sum('driver_earning');

                return [
                    'driver_id' => (int) $driverId,
                    'vendor_type' => 'driver',
                    'vendor_id' => (int) $driverId,
                    'user_id' => (int) $driverId,
                    'gross_amount' => round($gross, 2),
                    'platform_commission' => round($driverCommission, 2),
                    'delivery_fee' => round($gross, 2),
                    'admin_delivery_subsidy' => round($adminDeliverySubsidy, 2),
                    'restaurant_delivery_subsidy' => 0,
                    'admin_delivery_commission' => 0,
                    'driver_deduction' => round($driverCommission, 2),
                    'batch_bonus' => round($batchBonus, 2),
                    'amount' => round($net, 2),
                    'order_ids' => $orders->pluck('id')->values()->all(),
                    'breakdown' => [
                        'order_count' => $orders->count(),
                        'long_distance_charge' => round($longDistanceCharge, 2),
                        'driver_commission_rules' => $orders->map(fn ($order) => [
                            'type' => $order->driver_deduction_type,
                            'value' => (float) $order->driver_deduction_value,
                        ])->unique(fn ($rule) => $rule['type'] . ':' . $rule['value'])->values()->all(),
                    ],
                ];
            })
            ->keyBy('driver_id');

        RestaurantOnboardingIncentive::with('onboarding')
            ->where('status', RestaurantOnboardingIncentive::STATUS_EARNED)
            ->whereNull('payout_id')
            ->when(! $includeEarlierUnpaid, fn ($query) => $query->whereDate('earned_at', '>=', $startDate))
            ->whereDate('earned_at', '<=', $endDate)
            ->get()
            ->groupBy('driver_id')
            ->each(function ($incentives, $driverId) use ($rows) {
                $amount = round((float) $incentives->sum('amount'), 2);
                $row = $rows->get((int) $driverId, [
                    'driver_id' => (int) $driverId,
                    'vendor_type' => 'driver',
                    'vendor_id' => (int) $driverId,
                    'user_id' => (int) $driverId,
                    'gross_amount' => 0,
                    'platform_commission' => 0,
                    'delivery_fee' => 0,
                    'admin_delivery_subsidy' => 0,
                    'restaurant_delivery_subsidy' => 0,
                    'admin_delivery_commission' => 0,
                    'driver_deduction' => 0,
                    'batch_bonus' => 0,
                    'amount' => 0,
                    'order_ids' => [],
                    'breakdown' => [
                        'order_count' => 0,
                        'driver_commission_rules' => [],
                    ],
                ]);

                $row['gross_amount'] = round((float) ($row['gross_amount'] ?? 0) + $amount, 2);
                $row['amount'] = round((float) ($row['amount'] ?? 0) + $amount, 2);
                $row['breakdown']['restaurant_onboarding_incentive_amount'] = $amount;
                $row['breakdown']['onboarding_incentive_count'] = $incentives->count();
                $row['breakdown']['onboarding_incentive_ids'] = $incentives->pluck('id')->values()->all();
                $row['breakdown']['onboarding_application_numbers'] = $incentives
                    ->pluck('onboarding.application_number')
                    ->filter()
                    ->values()
                    ->all();

                $rows->put((int) $driverId, $row);
            });

        return $rows
            ->filter(fn ($row) => $row['amount'] >= $this->minimumPayoutAmount())
            ->values()
            ->all();
    }
    public function createPayoutFromAggregate(array $row, $startDate, $endDate, ?string $batchId = null): ?Payout
    {
        $orderIds = array_values(array_unique(array_map('intval', $row['order_ids'] ?? [])));
        $incentiveIds = array_values(array_unique(array_map('intval', $row['breakdown']['onboarding_incentive_ids'] ?? $row['onboarding_incentive_ids'] ?? [])));

        if ($orderIds === [] && $incentiveIds === []) {
            return null;
        }

        return DB::transaction(function () use ($row, $startDate, $endDate, $batchId, $orderIds, $incentiveIds) {
            $isRestaurantPayout = ! empty($row['restaurant_id']);
            $payoutColumn = $isRestaurantPayout ? 'restaurant_payout_id' : 'driver_payout_id';
            $claimableIds = [];

            if ($orderIds !== []) {
                $claimableIds = Order::whereIn('id', $orderIds)
                    ->whereNull($payoutColumn)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();
            }

            $claimableIncentiveIds = [];
            $incentiveOnboardingIds = [];
            $incentiveAmount = 0.0;

            if (! $isRestaurantPayout && $incentiveIds !== []) {
                $incentives = RestaurantOnboardingIncentive::whereIn('id', $incentiveIds)
                    ->where('driver_id', (int) ($row['driver_id'] ?? 0))
                    ->where('status', RestaurantOnboardingIncentive::STATUS_EARNED)
                    ->whereNull('payout_id')
                    ->lockForUpdate()
                    ->get();

                $claimableIncentiveIds = $incentives->pluck('id')->values()->all();
                $incentiveOnboardingIds = $incentives->pluck('restaurant_onboarding_id')->values()->all();
                $incentiveAmount = round((float) $incentives->sum('amount'), 2);
            }

            if ($claimableIds === [] && $claimableIncentiveIds === []) {
                return null;
            }

            if ($orderIds !== [] && count($claimableIds) !== count($orderIds)) {
                $row = $this->recalculateAggregateForClaimedOrders($row, $claimableIds, $isRestaurantPayout);
            }

            if (! $isRestaurantPayout && ($incentiveIds !== [] || isset($row['breakdown']['restaurant_onboarding_incentive_amount']))) {
                $originalIncentiveAmount = (float) ($row['breakdown']['restaurant_onboarding_incentive_amount'] ?? 0);
                $orderAmount = max(0, (float) ($row['amount'] ?? 0) - $originalIncentiveAmount);
                $row['amount'] = round($orderAmount + $incentiveAmount, 2);
                $row['gross_amount'] = round((float) ($row['gross_amount'] ?? 0) - $originalIncentiveAmount + $incentiveAmount, 2);
                $row['breakdown']['restaurant_onboarding_incentive_amount'] = $incentiveAmount;
                $row['breakdown']['onboarding_incentive_count'] = count($claimableIncentiveIds);
                $row['breakdown']['onboarding_incentive_ids'] = $claimableIncentiveIds;
            }

            $orderIds = $claimableIds;
            $amount = max(0, (float) $row['amount']);
            if ($amount < $this->minimumPayoutAmount()) {
                return null;
            }

            // --- Income-tax TDS / GST TCS at settlement (toggle-gated) ------
            $taxDeductions = $this->resolveSettlementTaxes($row, $orderIds, $isRestaurantPayout, $amount);
            $taxTotal = round(($taxDeductions['tds']?->amount ?? 0) + ($taxDeductions['tcs']?->amount ?? 0), 2);

            $deduction = round((float) ($row['deduction_amount'] ?? 0) + $taxTotal, 2);
            $netAmount = max(0, $amount - $deduction);
            $wallet = Wallet::where('user_id', $row['user_id'] ?? 0)->lockForUpdate()->first();
            if (! $wallet || (float) $wallet->balance < $netAmount) {
                Log::warning('Payout skipped: vendor income wallet is under-funded.', [
                    'vendor_type' => $row['vendor_type'] ?? null,
                    'vendor_id' => $row['vendor_id'] ?? null,
                    'user_id' => $row['user_id'] ?? null,
                    'required' => round($netAmount, 2),
                    'wallet_balance' => $wallet ? round((float) $wallet->balance, 2) : null,
                    'wallet_locked' => $wallet ? round((float) $wallet->locked_balance, 2) : null,
                    'order_ids' => $orderIds,
                    'batch_id' => $batchId,
                ]);

                return null;
            }

            $payout = Payout::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'batch_id' => $batchId,
                'restaurant_id' => $row['restaurant_id'] ?? null,
                'driver_id' => $row['driver_id'] ?? null,
                'vendor_type' => $row['vendor_type'],
                'vendor_id' => $row['vendor_id'],
                'gross_amount' => $row['gross_amount'] ?? $amount,
                'platform_commission' => $row['platform_commission'] ?? 0,
                'gst_on_commission' => $row['gst_on_commission'] ?? 0,
                'payment_gateway_fee' => $row['payment_gateway_fee'] ?? 0,
                'delivery_fee' => $row['delivery_fee'] ?? 0,
                'admin_delivery_subsidy' => $row['admin_delivery_subsidy'] ?? 0,
                'restaurant_delivery_subsidy' => $row['restaurant_delivery_subsidy'] ?? 0,
                'admin_delivery_commission' => $row['admin_delivery_commission'] ?? 0,
                'driver_deduction' => $row['driver_deduction'] ?? 0,
                'batch_bonus' => $row['batch_bonus'] ?? 0,
                'order_ids' => $orderIds,
                'breakdown' => $row['breakdown'] ?? [],
                'amount' => $netAmount,
                'deduction_amount' => $deduction,
                'net_amount' => $netAmount,
                'pre_tax_amount' => round($amount - (float) ($row['deduction_amount'] ?? 0), 2),
                'tds_amount' => $taxDeductions['tds']?->amount ?? null,
                'tds_section' => $taxDeductions['tds']?->applies() ? $taxDeductions['tds']->section : null,
                'tcs_amount' => $taxDeductions['tcs']?->amount ?? null,
                'currency' => AppSetting::getValue('currency_code', 'INR'),
                'status' => 'pending',
                'period_start' => $startDate,
                'period_end' => $endDate,
                'gateway' => PayoutSetting::activeGateway(),
                'idempotency_key' => 'scheduled_' . (string) \Illuminate\Support\Str::uuid(),
                'source' => 'scheduled',
                'created_by' => auth()->id(),
            ]);

            $wallet->decrement('balance', $netAmount);
            $wallet->increment('locked_balance', $netAmount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'debit',
                'amount' => $netAmount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'payout',
                'reference_id' => $payout->id,
                'description' => 'Scheduled payout reserved',
                'created_by' => auth()->id(),
                'meta' => ['batch_id' => $batchId, 'source' => 'scheduled_payout'],
            ]);

            if ($orderIds !== []) {
                Order::whereIn('id', $orderIds)->update([
                    $payoutColumn => $payout->id,
                    'payout_status' => 'Payout Released',
                    'payout_released_at' => now(),
                ]);
            }

            $this->commitSettlementTaxes($payout, $taxDeductions);

            try {
                $entry = app(\App\Services\Accounting\LedgerPostingService::class)->postPayout($payout);
                \App\Services\Integration\LedgerEventEmitter::journal($entry, \App\Services\Integration\LedgerEventEmitter::payoutMirror($payout->fresh(['restaurant', 'driver'])));
            } catch (\Throwable $e) {
                Log::warning('Payout journal failed.', ['payout_id' => $payout->id, 'message' => $e->getMessage()]);
            }

            if ($claimableIncentiveIds !== []) {
                RestaurantOnboardingIncentive::whereIn('id', $claimableIncentiveIds)->update([
                    'payout_id' => $payout->id,
                    'status' => RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                ]);

                \App\Models\RestaurantOnboarding::whereIn('id', $incentiveOnboardingIds)->update([
                    'incentive_status' => RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                ]);

                \App\Models\RestaurantOnboarding::with('driver')
                    ->whereIn('id', $incentiveOnboardingIds)
                    ->get()
                    ->each(fn ($onboarding) => app(RestaurantOnboardingNotificationService::class)->driver(
                        $onboarding,
                        'Onboarding incentive included in payout',
                        'Your restaurant onboarding incentive has been added to a scheduled payout.',
                        'restaurant_onboarding_payout_included'
                    ));
            }

            return $payout;
        });
    }

    /**
     * Sec 194-O / 194-C TDS + Sec 52 TCS for a settlement row. Returns
     * ['tds' => ?TaxDeduction, 'tcs' => ?TaxDeduction, 'party' => ?TaxEntity].
     * Every branch is a no-op when its toggle is off.
     */
    private function resolveSettlementTaxes(array $row, array $orderIds, bool $isRestaurantPayout, float $amount): array
    {
        $config = new \App\Services\Tax\TaxConfig();
        $out = ['tds' => null, 'tcs' => null, 'party' => null];

        if (! $config->gstEnabled()) {
            return $out;
        }

        $resolver = new \App\Services\Tax\TaxEntityResolver();
        $tds = new \App\Services\Tax\TdsService($config);
        $fy = $config->fy(now());

        if ($isRestaurantPayout) {
            $restaurant = Restaurant::find((int) $row['restaurant_id']);
            if (! $restaurant) {
                return $out;
            }
            $party = $resolver->restaurantEntity($restaurant);
            $out['party'] = $party;

            // 194-O base = gross food sales ex-GST = sum(orders.subtotal).
            $grossExGst = round((float) ($row['gross_amount'] ?? 0), 2);
            $out['tds'] = $tds->restaurant194O($party, $grossExGst, $fy);

            $orders = Order::whereIn('id', $orderIds)->get(['id', 'tax_breakdown']);
            $out['tcs'] = (new \App\Services\Tax\TcsService($config))->forRestaurant($party, $orders);

            return $out;
        }

        $driver = User::find((int) ($row['driver_id'] ?? 0));
        if (! $driver) {
            return $out;
        }
        $party = $resolver->driverEntity($driver);
        $out['party'] = $party;
        $out['tds'] = $tds->driver194C($party, $amount, $fy);

        return $out;
    }

    private function commitSettlementTaxes(Payout $payout, array $taxDeductions): void
    {
        $party = $taxDeductions['party'] ?? null;
        if (! $party) {
            return;
        }

        $ledger = new \App\Services\Tax\TaxLedgerService();

        foreach (['tds', 'tcs'] as $key) {
            /** @var \App\Services\Tax\TaxDeduction|null $d */
            $d = $taxDeductions[$key] ?? null;
            if (! $d) {
                continue;
            }

            $kind = match ($d->section) {
                '194O' => \App\Models\TaxLedgerEntry::KIND_TDS_194O,
                '194C' => \App\Models\TaxLedgerEntry::KIND_TDS_194C,
                default => \App\Models\TaxLedgerEntry::KIND_TCS,
            };

            // TDS rows are written even at ₹0 so the deductee's YTD gross
            // advances (thresholds); TCS only when there is an amount.
            if ($kind === \App\Models\TaxLedgerEntry::KIND_TCS && $d->amount <= 0) {
                continue;
            }

            $ledger->recordPayoutDeduction(
                $payout,
                $kind,
                $party,
                $d->section,
                $d->taxableValue,
                $d->rate,
                $d->amount,
                ['reason' => $d->reason],
                $kind === \App\Models\TaxLedgerEntry::KIND_TCS ? null : $d->grossForYtd,
            );
        }
    }

    private function recalculateAggregateForClaimedOrders(array $row, array $orderIds, bool $isRestaurantPayout): array
    {
        $orders = Order::whereIn('id', $orderIds)->get();

        if ($isRestaurantPayout) {
            $row['gross_amount'] = round((float) $orders->sum('subtotal'), 2);
            $row['platform_commission'] = round((float) $orders->sum('platform_commission'), 2);
            $row['gst_on_commission'] = round((float) $orders->sum('gst_on_commission'), 2);
            $row['payment_gateway_fee'] = round((float) $orders->sum('payment_gateway_fee'), 2);
            $row['delivery_fee'] = round((float) $orders->sum('delivery_fee'), 2);
            $row['admin_delivery_subsidy'] = round((float) $orders->sum('admin_delivery_subsidy'), 2);
            $row['restaurant_delivery_subsidy'] = round((float) $orders->sum('restaurant_delivery_subsidy'), 2);
            $row['amount'] = round((float) $orders->sum('restaurant_earning'), 2);
        } else {
            $gross = (float) $orders->sum('driver_delivery_base');
            $driverCommission = (float) $orders->sum(fn ($order) =>
                (float) $order->admin_delivery_commission + (float) $order->driver_deduction
            );
            $batchBonus = (float) $orders->sum('batch_bonus');
            $adminDeliverySubsidy = (float) $orders->sum('admin_delivery_subsidy');

            $row['gross_amount'] = round($gross, 2);
            $row['platform_commission'] = round($driverCommission, 2);
            $row['delivery_fee'] = round($gross, 2);
            $row['admin_delivery_subsidy'] = round($adminDeliverySubsidy, 2);
            $row['restaurant_delivery_subsidy'] = 0;
            $row['admin_delivery_commission'] = 0;
            $row['driver_deduction'] = round($driverCommission, 2);
            $row['batch_bonus'] = round($batchBonus, 2);
            $row['amount'] = round((float) $orders->sum('driver_earning'), 2);
        }

        $row['order_ids'] = array_values($orderIds);
        $row['breakdown']['order_count'] = count($orderIds);
        $row['breakdown']['duplicate_orders_ignored'] = true;

        return $row;
    }

    public function minimumPayoutAmount(): float
    {
        return (float) (PayoutSetting::where('is_active', true)->value('minimum_payout_amount')
            ?: AppSetting::getValue('minimum_payout_amount', 100));
    }

    /**
     * Credits only the driver's leg of a failed-delivery order. The restaurant's
     * leg is deferred until the flash-resale offer resolves (resold or returned) --
     * see finalizeRestaurantEarningForFailedDelivery(). `payout_processed` is
     * deliberately left false here so the scheduled batch-payout pipeline (which
     * filters on it) doesn't touch this order until fully resolved.
     */
    public function creditDriverEarningOnly(Order $order)
    {
        if ($order->status !== 'delivery_failed' || $order->driver_earning_processed_at) {
            return false;
        }

        DB::beginTransaction();

        try {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'delivery_failed' || $order->driver_earning_processed_at) {
                DB::rollBack();
                return false;
            }

            $driverEarningData = $this->calculateDriverEarning($order);

            $order->update([
                'driver_earning' => $driverEarningData['driver_earning'],
                'driver_delivery_base' => $driverEarningData['delivery_base'],
                'driver_deduction' => $driverEarningData['driver_commission'],
                'driver_deduction_type' => $driverEarningData['driver_commission_type'],
                'driver_deduction_value' => $driverEarningData['driver_commission_value'],
                'batch_bonus' => $driverEarningData['multiple_order_bonus'],
                'driver_earning_processed_at' => now(),
            ]);

            if ($order->driver_id) {
                $driver = User::find($order->driver_id);
                if ($driver) {
                    $driver->update([
                        'total_earned' => ($driver->total_earned ?? 0) + $driverEarningData['driver_earning'],
                        'pending_payout' => ($driver->pending_payout ?? 0) + $driverEarningData['driver_earning'],
                    ]);
                    $this->creditIncomeWallet(
                        $driver,
                        $driverEarningData['driver_earning'],
                        'driver_order_earning',
                        $order->id,
                        'Driver earning for order #' . ($order->order_number ?? $order->id)
                    );
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Driver earning processing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Credits the restaurant's leg once a failed delivery's flash-resale offer
     * resolves. $outcome is 'resold' (restaurant gets its normal full earning --
     * the platform absorbs the resale discount, per policy) or 'returned' (the
     * food physically went back to the restaurant, so it gets a flat 50% of
     * subtotal instead of the normal commission-based earning). A resale order
     * itself (one with original_order_id set) never owes the restaurant a second
     * payout here -- that was already settled against the original order.
     */
    public function finalizeRestaurantEarningForFailedDelivery(Order $order, string $outcome)
    {
        if ($order->restaurant_earning_processed_at) {
            return false;
        }

        DB::beginTransaction();

        try {
            $order = Order::with(['restaurant', 'branch'])->lockForUpdate()->findOrFail($order->id);
            if ($order->restaurant_earning_processed_at) {
                DB::rollBack();
                return false;
            }

            if ($order->original_order_id) {
                $order->update([
                    'restaurant_earning_processed_at' => now(),
                    'payout_processed' => true,
                    'payout_processed_at' => now(),
                ]);
                DB::commit();
                return true;
            }

            if ($outcome === 'returned') {
                $restaurantEarning = round((float) $order->subtotal * 0.5, 2);
                $order->update([
                    'restaurant_earning' => $restaurantEarning,
                    'restaurant_earning_processed_at' => now(),
                    'payout_processed' => true,
                    'payout_processed_at' => now(),
                ]);
            } else {
                $restaurantEarningData = $this->calculateRestaurantEarning($order);
                $restaurantEarning = (float) $restaurantEarningData['restaurant_earning'];
                $restaurantCommission = (float) $restaurantEarningData['platform_commission'];
                $branchShare = $this->branchCommissionShare($order, $restaurantCommission);
                $adminRestaurantShare = round($restaurantCommission - $branchShare, 2);
                $adminDeliverySubsidy = max(0, (float) ($order->admin_delivery_subsidy ?? 0));

                $order->update([
                    'platform_commission' => $restaurantEarningData['platform_commission'],
                    'gst_on_commission' => $restaurantEarningData['gst_on_commission'],
                    'payment_gateway_fee' => $restaurantEarningData['payment_gateway_fee'],
                    'restaurant_commission_type' => $restaurantEarningData['commission_type'],
                    'restaurant_commission_value' => $restaurantEarningData['commission_value'],
                    'restaurant_earning' => $restaurantEarning,
                    'branch_commission' => $branchShare,
                    'admin_commission' => round(
                        $adminRestaurantShare
                        + (float) ($order->driver_deduction ?? 0)
                        + (float) ($order->platform_fee ?? 0)
                        - $adminDeliverySubsidy,
                        2
                    ),
                    'restaurant_earning_processed_at' => now(),
                    'payout_processed' => true,
                    'payout_processed_at' => now(),
                ]);
            }

            $restaurant = Restaurant::find($order->restaurant_id);
            if ($restaurant && $restaurant->owner) {
                $restaurant->owner->update([
                    'total_earned' => ($restaurant->owner->total_earned ?? 0) + $restaurantEarning,
                    'pending_payout' => ($restaurant->owner->pending_payout ?? 0) + $restaurantEarning,
                ]);
                $this->creditIncomeWallet(
                    $restaurant->owner,
                    $restaurantEarning,
                    'restaurant_order_earning',
                    $order->id,
                    'Restaurant earning for order #' . ($order->order_number ?? $order->id)
                );
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Restaurant earning finalization failed: ' . $e->getMessage());
            return false;
        }
    }

    public function processOrderEarnings(Order $order)
    {
        if ($order->status !== 'delivered' || $order->payout_processed) {
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            $order = Order::with(['restaurant', 'branch'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'delivered' || $order->payout_processed) {
                DB::rollBack();
                return false;
            }

            // A resale order (original_order_id set) never owes the restaurant a
            // second payout -- that was already settled against the original
            // failed order via finalizeRestaurantEarningForFailedDelivery().
            $isResaleOrder = (bool) $order->original_order_id;

            $restaurantEarningData = $this->calculateRestaurantEarning($order);
            $driverEarningData = $this->calculateDriverEarning($order);
            $restaurantCommission = (float) $restaurantEarningData['platform_commission'];
            $branchShare = $this->branchCommissionShare($order, $restaurantCommission);
            $adminRestaurantShare = round($restaurantCommission - $branchShare, 2);
            $adminDeliverySubsidy = max(0, (float) ($order->admin_delivery_subsidy ?? 0));
            $restaurantEarningForRecord = $isResaleOrder ? 0.0 : (float) $restaurantEarningData['restaurant_earning'];

            $order->update([
                'platform_commission' => $isResaleOrder ? 0 : $restaurantEarningData['platform_commission'],
                'gst_on_commission' => $isResaleOrder ? 0 : $restaurantEarningData['gst_on_commission'],
                'payment_gateway_fee' => $isResaleOrder ? 0 : $restaurantEarningData['payment_gateway_fee'],
                'restaurant_commission_type' => $restaurantEarningData['commission_type'],
                'restaurant_commission_value' => $restaurantEarningData['commission_value'],
                'restaurant_earning' => $restaurantEarningForRecord,
                'driver_earning' => $driverEarningData['driver_earning'],
                'driver_delivery_base' => $driverEarningData['delivery_base'],
                'admin_delivery_commission' => 0,
                'admin_delivery_commission_type' => null,
                'admin_delivery_commission_value' => 0,
                'driver_deduction' => $driverEarningData['driver_commission'],
                'driver_deduction_type' => $driverEarningData['driver_commission_type'],
                'driver_deduction_value' => $driverEarningData['driver_commission_value'],
                'batch_bonus' => $driverEarningData['multiple_order_bonus'],
                'branch_commission' => $branchShare,
                'admin_commission' => round(
                    $adminRestaurantShare
                    + (float) $driverEarningData['driver_commission']
                    + (float) ($order->platform_fee ?? 0)
                    - $adminDeliverySubsidy,
                    2
                ),
                'payout_processed' => true,
                'payout_processed_at' => now()
            ]);
            
            $restaurant = Restaurant::find($order->restaurant_id);
            if (! $isResaleOrder && $restaurant && $restaurant->owner) {
                $restaurant->owner->update([
                    'total_earned' => ($restaurant->owner->total_earned ?? 0) + $restaurantEarningForRecord,
                    'pending_payout' => ($restaurant->owner->pending_payout ?? 0) + $restaurantEarningForRecord
                ]);
                $this->creditIncomeWallet(
                    $restaurant->owner,
                    $restaurantEarningForRecord,
                    'restaurant_order_earning',
                    $order->id,
                    'Restaurant earning for order #' . ($order->order_number ?? $order->id)
                );
            }

            if ($order->driver_id) {
                $driver = User::find($order->driver_id);
                if ($driver) {
                    $driver->update([
                        'total_earned' => ($driver->total_earned ?? 0) + $driverEarningData['driver_earning'],
                        'pending_payout' => ($driver->pending_payout ?? 0) + $driverEarningData['driver_earning']
                    ]);
                    $this->creditIncomeWallet(
                        $driver,
                        $driverEarningData['driver_earning'],
                        'driver_order_earning',
                        $order->id,
                        'Driver earning for order #' . ($order->order_number ?? $order->id)
                    );
                }
            }
            
            app(RestaurantOnboardingService::class)->markFirstSuccessfulOrder($order->restaurant, $order->driver);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Order earnings processing failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function isOnlinePayment(Order $order): bool
    {
        $method = strtolower((string) ($order->delivery_payment_mode ?: $order->payment_method));

        return $method !== '' && ! in_array($method, ['cash', 'cod', 'cash_on_delivery', 'wallet'], true);
    }

    protected function restaurantCommissionAmount(Order $order, float $base): float
    {
        $restaurantRate = $order->restaurant?->commission_rate;
        $type = $order->restaurant?->commission_calculation_type;

        if ($type === 'global' || $restaurantRate === null || $restaurantRate === '') {
            return CommissionSetting::calculate('restaurant', $base, $this->commissionRate * 100);
        }

        $type = $type ?: CommissionSetting::TYPE_PERCENTAGE;
        $amount = $type === CommissionSetting::TYPE_FIXED
            ? (float) $restaurantRate
            : $base * $this->normalizePercentage($restaurantRate);

        return round(min($base, max(0, $amount)), 2);
    }

    protected function restaurantCommissionDefinition(Order $order): array
    {
        $type = $order->restaurant?->commission_calculation_type;
        $value = $order->restaurant?->commission_rate;

        if ($type === 'global' || $value === null || $value === '') {
            return [
                'type' => CommissionSetting::getCalculationType('restaurant'),
                'value' => (float) (CommissionSetting::getRate('restaurant') ?: $this->commissionRate * 100),
            ];
        }

        return [
            'type' => $type ?: CommissionSetting::TYPE_PERCENTAGE,
            'value' => (float) $value,
        ];
    }

    protected function branchCommissionShare(Order $order, float $restaurantCommission): float
    {
        if (! $order->branch_id) {
            return 0.0;
        }

        $branchShareRate = (float) ($order->branch?->branch_share_percent ?? 70);

        return round($restaurantCommission * $this->normalizePercentage($branchShareRate), 2);
    }

    protected function creditIncomeWallet(User $user, float $amount, string $referenceType, int $referenceId, string $description): void
    {
        if ($amount <= 0) {
            return;
        }

        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?: Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'locked_balance' => 0,
                'currency' => 'INR',
                'is_active' => true,
            ]);

        $exists = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();

        if ($exists) {
            return;
        }

        $wallet->increment('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'meta' => ['source' => 'income'],
        ]);
    }

    protected function calculateMultipleOrderBonus(Order $order): float
    {
        if (!$order->driver_id || !$order->restaurant || !$order->restaurant->latitude || !$order->restaurant->longitude || !$order->delivery_lat || !$order->delivery_lng) {
            return 0.0;
        }

        if (! empty($order->route_batch_id)) {
            $batchCount = Order::where('driver_id', $order->driver_id)
                ->where('status', 'delivered')
                ->where('route_batch_id', $order->route_batch_id)
                ->count();

            return $this->bonusForBatchCount($batchCount);
        }

        $routeRadius = max(0.5, (float) AppSetting::getValue('driver_route_match_radius_km', 3));
        $batchCount = Order::with('restaurant')
            ->where('driver_id', $order->driver_id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', optional($order->delivered_at)->toDateString() ?? today())
            ->where('id', '!=', $order->id)
            ->get()
            ->filter(function (Order $routeOrder) use ($order, $routeRadius) {
                if (!$routeOrder->restaurant || !$routeOrder->restaurant->latitude || !$routeOrder->restaurant->longitude || !$routeOrder->delivery_lat || !$routeOrder->delivery_lng) {
                    return false;
                }

                return $this->distanceKm(
                    $order->restaurant->latitude,
                    $order->restaurant->longitude,
                    $routeOrder->restaurant->latitude,
                    $routeOrder->restaurant->longitude
                ) <= $routeRadius
                    && $this->distanceKm(
                        $order->delivery_lat,
                        $order->delivery_lng,
                        $routeOrder->delivery_lat,
                        $routeOrder->delivery_lng
                    ) <= $routeRadius;
            })
            ->count() + 1;

        return $this->bonusForBatchCount($batchCount);
    }

    protected function chargeableDeliveryFee(Order $order): float
    {
        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return 0.0;
        }

        if ((float) ($order->original_delivery_fee ?? 0) > 0) {
            return (float) $order->original_delivery_fee;
        }

        if ((float) $order->delivery_fee > 0) {
            return (float) $order->delivery_fee;
        }

        $distance = null;
        if ($order->restaurant && $order->restaurant->latitude && $order->restaurant->longitude && $order->delivery_lat && $order->delivery_lng) {
            $distance = $this->distanceKm(
                $order->restaurant->latitude,
                $order->restaurant->longitude,
                $order->delivery_lat,
                $order->delivery_lng
            );
        }

        return round((float) DeliveryChargeSetting::getDeliveryCharge($distance), 2);
    }

    protected function freeDeliveryContribution(Order $order, float $chargeableDeliveryFee): float
    {
        if ((float) $order->delivery_fee > 0 || $chargeableDeliveryFee <= 0) {
            return 0.0;
        }

        if ((float) ($order->delivery_discount ?? 0) > 0) {
            return min($chargeableDeliveryFee, (float) $order->delivery_discount);
        }

        $setting = DeliveryChargeSetting::first();
        if (!$setting) {
            return 0.0;
        }

        $threshold = DeliveryChargeSetting::getFreeDeliveryThreshold(
            $order->restaurant_id,
            $order->delivery_lat,
            $order->delivery_lng
        );
        if ($threshold !== null && (float) $order->subtotal < (float) $threshold) {
            return 0.0;
        }

        if ($threshold === null) {
            return 0.0;
        }

        $contributionPercent = (float) $setting->admin_contribution_percent
            + (float) $setting->restaurant_contribution_percent;

        if ($contributionPercent <= 0) {
            return 0.0;
        }

        return round($chargeableDeliveryFee * min($contributionPercent, 100) / 100, 2);
    }

    protected function bonusForBatchCount(int $batchCount): float
    {
        if ($batchCount < 2) {
            return 0.0;
        }

        if ($batchCount === 2) {
            return (float) AppSetting::getValue('multiple_order_bonus_two_orders', 0);
        }

        return (float) AppSetting::getValue('multiple_order_bonus_three_plus_orders', 0)
            + max(0, $batchCount - 3) * (float) AppSetting::getValue('multiple_order_bonus_extra_order', 0);
    }

    protected function distanceKm($lat1, $lon1, $lat2, $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = max(-1, min(1, $dist));

        return rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
    }
}

