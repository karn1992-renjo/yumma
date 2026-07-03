<?php

namespace App\Services;

use App\Models\DriverGig;
use App\Models\GigIncentive;
use App\Models\Order;

class GigIncentiveService
{
    protected $basePayPerHour = 50; // Default base pay per hour
    protected $orderIncentivePerOrder = 20; // Per order incentive
    protected $activeTimeIncentivePerHour = 10; // Per active hour
    
    public function calculateGigEarnings(DriverGig $gig)
    {
        // Get orders completed during this gig
        $orders = Order::where('driver_id', $gig->driver_id)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$gig->start_time, $gig->end_time])
            ->get();
            
        $hoursWorked = $gig->start_time->diffInMinutes($gig->end_time) / 60;
        $basePay = $hoursWorked * $this->basePayPerHour;
        $orderIncentive = $orders->count() * $this->orderIncentivePerOrder;
        $activeTimeIncentive = $hoursWorked * $this->activeTimeIncentivePerHour;
        
        $totalEarned = $basePay + $orderIncentive + $activeTimeIncentive;
        
        $incentive = GigIncentive::updateOrCreate(
            ['driver_gig_id' => $gig->id],
            [
                'base_pay' => $basePay,
                'order_incentive' => $orderIncentive,
                'active_time_incentive' => $activeTimeIncentive,
                'total_earned' => $totalEarned,
                'orders_completed' => $orders->pluck('id'),
                'active_minutes' => $hoursWorked * 60
            ]
        );
        
        return $incentive;
    }
    
    public function applyPenalty(DriverGig $gig, $reason, $amount = 50)
    {
        $incentive = GigIncentive::firstOrCreate(['driver_gig_id' => $gig->id]);
        
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
        // Check if driver actually served the gig
        $ordersCount = Order::where('driver_id', $gig->driver_id)
            ->whereBetween('created_at', [$gig->start_time, $gig->end_time])
            ->count();
            
        if ($ordersCount == 0 && $gig->status === 'booked') {
            // Driver booked but didn't serve any order
            $this->applyPenalty($gig, 'Gig booked but not served', 100);
            $gig->update(['status' => 'cancelled']);
            return false;
        }
        
        if ($gig->status === 'available' && $ordersCount > 0) {
            $gig->update(['status' => 'completed']);
            $this->calculateGigEarnings($gig);
        }
        
        return true;
    }
}