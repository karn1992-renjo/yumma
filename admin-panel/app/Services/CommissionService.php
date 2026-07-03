<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\CommissionSetting;
use App\Models\PayoutHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(private readonly PayoutCalculationService $payoutCalculation)
    {
    }

    /**
     * Calculate restaurant commission for an order
     */
    public function calculateRestaurantCommission($order)
    {
        $earning = $this->payoutCalculation->calculateRestaurantEarning($order);
        $commissionRate = $order->restaurant?->commission_rate ?? CommissionSetting::getRate('restaurant');
        $calculationType = $order->restaurant?->commission_calculation_type;
        if ($calculationType === 'global' || $commissionRate === null) {
            $commissionRate = CommissionSetting::getRate('restaurant');
            $calculationType = CommissionSetting::getCalculationType('restaurant');
        }
        $totalDeduction = (float) $earning['platform_commission']
            + (float) $earning['gst_on_commission']
            + (float) $earning['payment_gateway_fee'];
        
        return [
            'commission_rate' => $commissionRate,
            'calculation_type' => $calculationType ?: CommissionSetting::TYPE_PERCENTAGE,
            'commission_amount' => $earning['platform_commission'],
            'commission_base' => $earning['subtotal'],
            'gst_on_commission' => $earning['gst_on_commission'],
            'payment_gateway_fee' => $earning['payment_gateway_fee'],
            'platform_fee' => (float) ($order->platform_fee ?? 0),
            'total_deduction' => round($totalDeduction, 2),
        ];
    }
    
    /**
     * Calculate driver commission for an order
     */
    public function calculateDriverCommission($order)
    {
        $earning = $this->payoutCalculation->calculateDriverEarning($order);
        
        return [
            'delivery_fee' => $earning['delivery_fee'],
            'admin_commission' => $earning['admin_delivery_commission'],
            'driver_deduction' => $earning['driver_deduction'],
            'batch_bonus' => $earning['multiple_order_bonus'],
            'driver_earning' => $earning['driver_earning'],
        ];
    }
    
    /**
     * Process order earnings after delivery
     */
    public function processOrderEarnings(Order $order)
    {
        return $this->payoutCalculation->processOrderEarnings($order);
    }
    
    /**
     * Generate payout history for a payable entity
     */
    public function generatePayoutHistory($payable, $periodType, $startDate, $endDate)
    {
        $payout = PayoutHistory::create([
            'payable_type' => get_class($payable),
            'payable_id' => $payable->id,
            'period_type' => $periodType,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'amount' => 0,
            'status' => 'pending'
        ]);
        
        return $payout;
    }
    
    /**
     * Calculate restaurant payout for a period
     */
    public function calculateRestaurantPayout($restaurantId, $startDate, $endDate, $periodType)
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
            
        $earnings = $orders->map(fn ($order) => $this->payoutCalculation->calculateRestaurantEarning($order));
        $totalRevenue = $earnings->sum('subtotal');
        $commissionRate = $restaurant->commission_rate ?? CommissionSetting::getRate('restaurant');
        $calculationType = $restaurant->commission_calculation_type;
        if ($calculationType === 'global' || $restaurant->commission_rate === null) {
            $commissionRate = CommissionSetting::getRate('restaurant');
            $calculationType = CommissionSetting::getCalculationType('restaurant');
        }
        $commission = $earnings->sum('platform_commission');
        $gstOnCommission = $earnings->sum('gst_on_commission');
        $gatewayFee = $earnings->sum('payment_gateway_fee');
        $payoutAmount = $earnings->sum('restaurant_earning');
        
        $payout = PayoutHistory::updateOrCreate(
            [
                'payable_type' => Restaurant::class,
                'payable_id' => $restaurantId,
                'period_type' => $periodType,
                'period_start' => $startDate,
                'period_end' => $endDate,
            ],
            [
                'amount' => max(0, $payoutAmount),
                'breakdown' => [
                    'total_revenue' => $totalRevenue,
                    'commission_rate' => $commissionRate,
                    'calculation_type' => $calculationType ?: CommissionSetting::TYPE_PERCENTAGE,
                    'commission_amount' => $commission,
                    'gst_on_commission' => $gstOnCommission,
                    'payment_gateway_fee' => $gatewayFee,
                    'order_count' => $orders->count(),
                    'orders' => $orders->pluck('id')
                ]
            ]
        );
        
        return $payout;
    }
    
    /**
     * Calculate driver payout for a period
     */
    public function calculateDriverPayout($driverId, $startDate, $endDate, $periodType)
    {
        $driver = User::findOrFail($driverId);
        
        $orders = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
            
        $earnings = $orders->map(fn ($order) => $this->payoutCalculation->calculateDriverEarning($order));
        $totalEarnings = $earnings->sum('delivery_fee');
        $adminDeliveryCommission = $earnings->sum('admin_delivery_commission');
        $driverDeduction = $earnings->sum('driver_deduction');
        $batchBonus = $earnings->sum('multiple_order_bonus');
        $commission = $adminDeliveryCommission + $driverDeduction;
        $payoutAmount = $earnings->sum('driver_earning');
        
        $payout = PayoutHistory::updateOrCreate(
            [
                'payable_type' => User::class,
                'payable_id' => $driverId,
                'period_type' => $periodType,
                'period_start' => $startDate,
                'period_end' => $endDate,
            ],
            [
                'amount' => max(0, $payoutAmount),
                'breakdown' => [
                    'total_earnings' => $totalEarnings,
                    'commission_rate' => CommissionSetting::getRate('delivery_partner'),
                    'calculation_type' => CommissionSetting::getCalculationType('delivery_partner'),
                    'commission_amount' => $commission,
                    'admin_delivery_commission' => $adminDeliveryCommission,
                    'driver_deduction' => $driverDeduction,
                    'batch_bonus' => $batchBonus,
                    'order_count' => $orders->count(),
                    'orders' => $orders->pluck('id')
                ]
            ]
        );
        
        return $payout;
    }
    
    /**
     * Get pending payouts
     */
    public function getPendingPayouts($type = null)
    {
        $query = PayoutHistory::where('status', 'pending');
        
        if ($type === 'restaurant') {
            $query->where('payable_type', Restaurant::class);
        } elseif ($type === 'driver') {
            $query->where('payable_type', User::class);
        }
        
        return $query->get();
    }
    
    /**
     * Process a payout
     */
    public function processPayout(PayoutHistory $payout, $transactionId = null)
    {
        DB::beginTransaction();
        
        try {
            $payout->update([
                'status' => 'completed',
                'processed_at' => now(),
                'transaction_id' => $transactionId ?? 'TXN_' . strtoupper(uniqid())
            ]);
            
            DB::commit();
            
            return ['success' => true, 'message' => 'Payout processed successfully'];
            
        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get total pending amount
     */
    public function getTotalPendingAmount()
    {
        return [
            'restaurant' => PayoutHistory::where('status', 'pending')
                ->where('payable_type', Restaurant::class)
                ->sum('amount'),
            'driver' => PayoutHistory::where('status', 'pending')
                ->where('payable_type', User::class)
                ->sum('amount'),
            'total' => PayoutHistory::where('status', 'pending')->sum('amount')
        ];
    }
    
    /**
     * Get payout summary for dashboard
     */
    public function getPayoutSummary($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();
        
        return [
            'total_payouts' => PayoutHistory::whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
            'completed_payouts' => PayoutHistory::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'pending_payouts' => PayoutHistory::where('status', 'pending')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'restaurant_payouts' => PayoutHistory::where('payable_type', Restaurant::class)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'driver_payouts' => PayoutHistory::where('payable_type', User::class)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount')
        ];
    }
}
