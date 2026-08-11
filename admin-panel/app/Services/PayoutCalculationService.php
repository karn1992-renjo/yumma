<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
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
        
        $restaurantEarning = $commissionBase - $platformCommission - $gstOnCommission - $gatewayFee - $restaurantDeliverySubsidy - $restaurantPromotionLiability;
        
        return [
            'subtotal' => round($commissionBase, 2),
            'commission_type' => $commissionDefinition['type'],
            'commission_value' => $commissionDefinition['value'],
            'platform_commission' => round($platformCommission, 2),
            'gst_on_commission' => round($gstOnCommission, 2),
            'payment_gateway_fee' => round($gatewayFee, 2),
            'restaurant_delivery_subsidy' => round($restaurantDeliverySubsidy, 2),
            'promotion_restaurant_liability' => round($restaurantPromotionLiability, 2),
            'restaurant_earning' => max(0, round($restaurantEarning, 2))
        ];
    }
    
    public function calculateDriverEarning(Order $order)
    {
        $customerDeliveryFee = (float) $order->delivery_fee;
        $chargeableDeliveryFee = $this->chargeableDeliveryFee($order);
        $freeDeliveryContribution = $this->freeDeliveryContribution($order, $chargeableDeliveryFee);
        $driverEarning = $chargeableDeliveryFee;
        $driverCommission = CommissionSetting::calculate(CommissionSetting::DRIVER, $driverEarning);
        $multipleOrderBonus = $this->calculateMultipleOrderBonus($order);
        $finalEarning = $driverEarning - $driverCommission + $multipleOrderBonus;
        
        return [
            'delivery_fee' => $customerDeliveryFee,
            'delivery_base' => round($driverEarning, 2),
            'chargeable_delivery_fee' => round($chargeableDeliveryFee, 2),
            'free_delivery_contribution' => round($freeDeliveryContribution, 2),
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
        return Order::with('driver')
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
                        'driver_commission_rules' => $orders->map(fn ($order) => [
                            'type' => $order->driver_deduction_type,
                            'value' => (float) $order->driver_deduction_value,
                        ])->unique(fn ($rule) => $rule['type'] . ':' . $rule['value'])->values()->all(),
                    ],
                ];
            })
            ->filter(fn ($row) => $row['amount'] >= $this->minimumPayoutAmount())
            ->values()
            ->all();
    }

    public function createPayoutFromAggregate(array $row, $startDate, $endDate, ?string $batchId = null): ?Payout
    {
        $orderIds = array_values(array_unique(array_map('intval', $row['order_ids'] ?? [])));
        if ($orderIds === []) {
            return null;
        }

        return DB::transaction(function () use ($row, $startDate, $endDate, $batchId, $orderIds) {
            $isRestaurantPayout = ! empty($row['restaurant_id']);
            $payoutColumn = $isRestaurantPayout ? 'restaurant_payout_id' : 'driver_payout_id';
            $claimableIds = Order::whereIn('id', $orderIds)
                ->whereNull($payoutColumn)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($claimableIds === []) {
                return null;
            }

            if (count($claimableIds) !== count($orderIds)) {
                $row = $this->recalculateAggregateForClaimedOrders($row, $claimableIds, $isRestaurantPayout);
                $orderIds = $claimableIds;
            }

            $amount = max(0, (float) $row['amount']);
            if ($amount < $this->minimumPayoutAmount()) {
                return null;
            }

            $deduction = (float) ($row['deduction_amount'] ?? 0);
            $netAmount = max(0, $amount - $deduction);
            $wallet = Wallet::where('user_id', $row['user_id'] ?? 0)->lockForUpdate()->first();
            if (! $wallet || (float) $wallet->balance < $netAmount) {
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
            'currency' => AppSetting::getValue('currency_code', 'INR'),
            'status' => 'pending',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'gateway' => PayoutSetting::activeGateway(),
            'idempotency_key' => 'scheduled_' . (string) \Illuminate\Support\Str::uuid(),
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

            Order::whereIn('id', $orderIds)->update([
                $payoutColumn => $payout->id,
                'payout_status' => 'Payout Released',
                'payout_released_at' => now(),
            ]);

            return $payout;
        });
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

            $restaurantEarningData = $this->calculateRestaurantEarning($order);
            $driverEarningData = $this->calculateDriverEarning($order);
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
                'restaurant_earning' => $restaurantEarningData['restaurant_earning'],
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
            if ($restaurant && $restaurant->owner) {
                $restaurant->owner->update([
                    'total_earned' => ($restaurant->owner->total_earned ?? 0) + $restaurantEarningData['restaurant_earning'],
                    'pending_payout' => ($restaurant->owner->pending_payout ?? 0) + $restaurantEarningData['restaurant_earning']
                ]);
                $this->creditIncomeWallet(
                    $restaurant->owner,
                    $restaurantEarningData['restaurant_earning'],
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

