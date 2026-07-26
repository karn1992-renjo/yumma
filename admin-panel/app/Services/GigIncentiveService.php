<?php

namespace App\Services;

use App\Models\DriverGig;
use App\Models\GigIncentive;
use App\Models\Order;

class GigIncentiveService
{
    public function calculateGigEarnings(DriverGig $gig, ?int $driverId = null)
    {
        $driverId ??= $gig->driver_id;
        if (! $driverId) {
            return null;
        }

        $startTime = $gig->start_time;
        $endTime = $gig->end_time;

        $orders = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startTime, $endTime])
            ->get();

        $activeMinutes = max(0, $startTime->diffInMinutes($endTime, false));
        $ordersCompleted = $orders->count();
        $loginRequirementMet = (int) $gig->min_login_minutes <= 0
            || $activeMinutes >= (int) $gig->min_login_minutes;
        $orderRequirementMet = (int) $gig->min_orders_required <= 0
            || $ordersCompleted >= (int) $gig->min_orders_required;

        $basePay = $loginRequirementMet ? (float) $gig->base_pay : 0.0;
        $orderIncentive = $orderRequirementMet
            ? $ordersCompleted * (float) $gig->order_incentive
            : 0.0;
        $activeTimeIncentive = $loginRequirementMet ? (float) $gig->login_incentive : 0.0;
        $totalEarned = $basePay + $orderIncentive + $activeTimeIncentive;
        
        $incentive = GigIncentive::updateOrCreate(
            [
                'driver_gig_id' => $gig->id,
                'driver_id' => $driverId,
            ],
            [
                'base_pay' => round($basePay, 2),
                'order_incentive' => round($orderIncentive, 2),
                'active_time_incentive' => round($activeTimeIncentive, 2),
                'total_earned' => round($totalEarned, 2),
                'orders_completed' => $orders->pluck('id'),
                'active_minutes' => $activeMinutes
            ]
        );
        
        return $incentive;
    }
    
    public function applyPenalty(DriverGig $gig, $reason, $amount = 50, ?int $driverId = null)
    {
        $driverId ??= $gig->driver_id;
        if (! $driverId) {
            return null;
        }

        $incentive = GigIncentive::firstOrCreate([
            'driver_gig_id' => $gig->id,
            'driver_id' => $driverId,
        ]);
        
        $incentive->update([
            'is_penalty_applied' => true,
            'penalty_amount' => $amount,
            'penalty_reason' => $reason,
            'total_earned' => max(0, $incentive->total_earned - $amount)
        ]);
        
        return $incentive;
    }
    
    public function checkGigServed(DriverGig $gig)
    {
        $driverId = $gig->driver_id;
        if (! $driverId) {
            return true;
        }

        // Check if driver actually served the gig
        $ordersCount = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$gig->start_time, $gig->end_time])
            ->count();
            
        if ($ordersCount == 0 && $gig->status === 'booked') {
            // Driver booked but didn't serve any order
            $this->applyPenalty($gig, 'Gig booked but not served', 100, $driverId);
            $gig->update(['status' => 'cancelled']);
            return false;
        }
        
        if ($gig->status === 'available' && $ordersCount > 0) {
            $gig->update(['status' => 'completed']);
            $this->calculateGigEarnings($gig, $driverId);
        }
        
        return true;
    }
}
