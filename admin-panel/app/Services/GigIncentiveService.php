<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Models\GigIncentive;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class GigIncentiveService
{
    public function calculateGigEarnings(DriverGig $gig, ?int $driverId = null)
    {
        $driverId ??= $gig->driver_id;
        if (! $driverId) {
            return null;
        }

        $startTime = $this->slotDateTime($gig, 'start_time') ?: $gig->start_time;
        $endTime = $this->slotDateTime($gig, 'end_time') ?: $gig->end_time;

        $orders = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startTime, $endTime])
            ->get();

        $booking = DriverGigBooking::where('driver_gig_id', $gig->id)
            ->where('driver_id', $driverId)
            ->latest('id')
            ->first();

        $ordersCompleted = $orders->count();
        $activeMinutes = $booking && Schema::hasColumn('driver_gig_bookings', 'online_minutes')
            ? (int) $booking->online_minutes
            : max(0, $startTime->diffInMinutes($endTime, false));
        $rejectedOrders = $booking && Schema::hasColumn('driver_gig_bookings', 'rejected_orders_count')
            ? (int) $booking->rejected_orders_count
            : 0;
        $cancelledOrders = $booking && Schema::hasColumn('driver_gig_bookings', 'cancelled_orders_count')
            ? (int) $booking->cancelled_orders_count
            : 0;
        $noShow = $booking && Schema::hasColumn('driver_gig_bookings', 'no_show')
            ? (bool) $booking->no_show
            : false;

        $maxCancellations = (int) $gig->max_cancellations_allowed;
        $loginRequirementMet = (int) $gig->min_login_minutes <= 0 || $activeMinutes >= (int) $gig->min_login_minutes;
        $orderRequirementMet = (int) $gig->min_orders_required <= 0 || $ordersCompleted >= (int) $gig->min_orders_required;
        $cancellationRequirementMet = ($rejectedOrders + $cancelledOrders) <= $maxCancellations;
        $eligible = ! $noShow && $loginRequirementMet && $cancellationRequirementMet;

        $basePay = $eligible ? (float) $gig->base_pay : 0.0;
        $orderIncentive = ($eligible && $orderRequirementMet)
            ? $ordersCompleted * (float) $gig->order_incentive
            : 0.0;
        $activeTimeIncentive = $eligible ? (float) $gig->login_incentive : 0.0;
        $surgeMultiplier = ((bool) AppSetting::getValue('gig_dynamic_incentives_enabled', true) && Schema::hasColumn('driver_gigs', 'surge_multiplier'))
            ? max(1.0, (float) ($gig->surge_multiplier ?? 1))
            : 1.0;
        $subtotalBeforeSurge = $basePay + $orderIncentive + $activeTimeIncentive;
        $surgeAmount = $eligible ? max(0, ($subtotalBeforeSurge * $surgeMultiplier) - $subtotalBeforeSurge) : 0.0;
        $penaltyAmount = 0.0;
        $penaltyReason = null;

        if ($noShow) {
            $penaltyAmount = (float) AppSetting::getValue('gig_no_show_penalty_amount', 0);
            $penaltyReason = 'Gig no-show';
        } elseif (! $cancellationRequirementMet) {
            $penaltyAmount = (float) AppSetting::getValue('gig_cancellation_penalty_amount', 0);
            $penaltyReason = 'Gig cancellation limit exceeded';
        }

        $totalEarned = max(0, $subtotalBeforeSurge + $surgeAmount - $penaltyAmount);

        $values = [
            'base_pay' => round($basePay, 2),
            'order_incentive' => round($orderIncentive, 2),
            'active_time_incentive' => round($activeTimeIncentive, 2),
            'surge_multiplier' => round($surgeMultiplier, 2),
            'surge_amount' => round($surgeAmount, 2),
            'total_earned' => round($totalEarned, 2),
            'orders_completed' => $orders->pluck('id')->values()->all(),
            'active_minutes' => $activeMinutes,
            'login_requirement_met' => $loginRequirementMet,
            'order_requirement_met' => $orderRequirementMet,
            'cancellation_requirement_met' => $cancellationRequirementMet,
            'no_show' => $noShow,
            'delivered_orders_count' => $ordersCompleted,
            'rejected_orders_count' => $rejectedOrders,
            'cancelled_orders_count' => $cancelledOrders,
            'is_penalty_applied' => $penaltyAmount > 0 || $noShow || ! $cancellationRequirementMet,
            'penalty_amount' => round($penaltyAmount, 2),
            'penalty_reason' => $penaltyReason,
        ];

        foreach (array_keys($values) as $column) {
            if (! in_array($column, ['base_pay', 'order_incentive', 'active_time_incentive', 'surge_multiplier', 'surge_amount', 'total_earned', 'orders_completed', 'active_minutes', 'is_penalty_applied', 'penalty_amount', 'penalty_reason'], true)
                && ! Schema::hasColumn('gig_incentives', $column)) {
                unset($values[$column]);
            }
        }

        return GigIncentive::updateOrCreate(
            [
                'driver_gig_id' => $gig->id,
                'driver_id' => $driverId,
            ],
            $values
        );
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
            'total_earned' => max(0, (float) $incentive->total_earned - (float) $amount),
        ]);
        
        return $incentive;
    }
    
    public function checkGigServed(DriverGig $gig)
    {
        $driverId = $gig->driver_id;
        if (! $driverId) {
            return true;
        }

        $incentive = $this->calculateGigEarnings($gig, $driverId);
        if (! $incentive || (float) $incentive->total_earned <= 0) {
            $this->applyPenalty($gig, $incentive?->penalty_reason ?: 'Gig requirements not met', 0, $driverId);
            return false;
        }
        
        return true;
    }

    private function slotDateTime(DriverGig $gig, string $attribute)
    {
        $date = $gig->date;
        $time = $gig->{$attribute};

        if (! $date || ! $time) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
            config('app.timezone')
        );
    }
}