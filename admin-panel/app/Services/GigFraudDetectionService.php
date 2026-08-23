<?php

namespace App\Services;

use App\Models\DriverGigBooking;
use App\Models\GigFraudSignal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class GigFraudDetectionService
{
    public function scanBooking(DriverGigBooking $booking): array
    {
        $booking->loadMissing('gig');
        $signals = [];
        $gig = $booking->gig;
        if (! $gig || ! Schema::hasTable('gig_fraud_signals')) {
            return [];
        }

        $slotMinutes = $this->slotMinutes($gig);
        $onlineMinutes = (int) ($booking->online_minutes ?? 0);
        $delivered = (int) ($booking->delivered_orders_count ?? 0);
        $rejected = (int) ($booking->rejected_orders_count ?? 0);
        $cancelled = (int) ($booking->cancelled_orders_count ?? 0);

        if ($onlineMinutes > $slotMinutes + 20) {
            $signals[] = $this->upsert($booking, 'online_time_exceeds_slot', 'high', 85, [
                'online_minutes' => $onlineMinutes,
                'slot_minutes' => $slotMinutes,
            ]);
        }

        if ($booking->checked_in_at && $this->slotStart($gig) && $booking->checked_in_at->lt($this->slotStart($gig)->subMinutes(45))) {
            $signals[] = $this->upsert($booking, 'early_checkin_outside_window', 'medium', 55, [
                'checked_in_at' => $booking->checked_in_at->toIso8601String(),
            ]);
        }

        if ($delivered > max(8, ceil($slotMinutes / 8))) {
            $signals[] = $this->upsert($booking, 'unusually_high_delivery_count', 'medium', 60, [
                'delivered_orders_count' => $delivered,
                'slot_minutes' => $slotMinutes,
            ]);
        }

        if (($rejected + $cancelled) >= 3) {
            $signals[] = $this->upsert($booking, 'high_rejection_cancellation_rate', 'medium', 65, [
                'rejected_orders_count' => $rejected,
                'cancelled_orders_count' => $cancelled,
            ]);
        }

        if (($booking->no_show ?? false) === true) {
            $signals[] = $this->upsert($booking, 'no_show', 'high', 90, [
                'driver_gig_booking_id' => $booking->id,
            ]);
        }

        return array_filter($signals);
    }

    public function summaryForBooking(DriverGigBooking $booking): array
    {
        $signals = $this->scanBooking($booking);
        $score = collect($signals)->max('score') ?? 0;

        return [
            'score' => (int) $score,
            'status' => $score >= 80 ? 'review_required' : ($score >= 50 ? 'watch' : 'clear'),
            'signals' => collect($signals)->map(fn ($signal) => [
                'type' => $signal->signal_type,
                'severity' => $signal->severity,
                'score' => $signal->score,
            ])->values()->all(),
        ];
    }

    private function upsert(DriverGigBooking $booking, string $type, string $severity, int $score, array $evidence): GigFraudSignal
    {
        return GigFraudSignal::updateOrCreate(
            [
                'driver_gig_booking_id' => $booking->id,
                'signal_type' => $type,
            ],
            [
                'driver_gig_id' => $booking->driver_gig_id,
                'driver_id' => $booking->driver_id,
                'severity' => $severity,
                'score' => $score,
                'status' => 'open',
                'evidence' => $evidence,
            ]
        );
    }

    private function slotMinutes($gig): int
    {
        $start = $this->slotStart($gig);
        $end = $this->slotEnd($gig);

        return $start && $end ? max(0, $start->diffInMinutes($end, false)) : 0;
    }

    private function slotStart($gig): ?Carbon
    {
        return $this->slotDateTime($gig, 'start_time');
    }

    private function slotEnd($gig): ?Carbon
    {
        return $this->slotDateTime($gig, 'end_time');
    }

    private function slotDateTime($gig, string $attribute): ?Carbon
    {
        if (! $gig->date || ! $gig->{$attribute}) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $gig->date->format('Y-m-d') . ' ' . $gig->{$attribute}->format('H:i:s'),
            config('app.timezone')
        );
    }
}