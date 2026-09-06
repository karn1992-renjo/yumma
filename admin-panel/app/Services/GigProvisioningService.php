<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\DriverGig;
use Carbon\Carbon;

/**
 * Shared gig create/modify/close path used by the AI tool layer
 * (App\Services\Ai\AiToolRegistry). Mirrors the overlap and area
 * daily-booking-limit guards App\Http\Controllers\Admin\GigController
 * enforces for human-admin edits, so AI-proposed gig changes can't
 * create overlapping slots or exceed a zone's booking cap.
 */
class GigProvisioningService
{
    public const VALID_STATUSES = ['available', 'booked', 'completed', 'cancelled'];

    public function createGig(array $data): array
    {
        if (empty($data['area_id'])) {
            return ['success' => false, 'error' => 'area_id is required to create a gig slot.'];
        }

        $gigDate = Carbon::parse($data['date'])->format('Y-m-d');
        if ($gigDate < Carbon::today()->format('Y-m-d')) {
            return ['success' => false, 'error' => "Gig date {$gigDate} is in the past."];
        }

        $startTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate.' '.$data['start_time']);
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate.' '.$data['end_time']);

        if ($this->overlaps($data['area_id'], $gigDate, $startTime, $endTime)) {
            return ['success' => false, 'error' => 'A gig slot already exists for this area and time range.'];
        }

        $gig = DriverGig::create([
            'title' => filled($data['title'] ?? null) ? $data['title'] : $this->defaultTitle($startTime),
            'description' => $data['description'] ?? 'Created by AI management system.',
            'driver_id' => null,
            'area_id' => $data['area_id'],
            'capacity' => $data['capacity'],
            'date' => $gigDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'available',
            'base_pay' => $data['base_pay'] ?? 0,
            'order_incentive' => $data['order_incentive'] ?? 0,
            'login_incentive' => $data['login_incentive'] ?? 0,
            'min_orders_required' => $data['min_orders_required'] ?? 0,
            'min_login_minutes' => $data['min_login_minutes'] ?? 0,
            'max_cancellations_allowed' => $data['max_cancellations_allowed'] ?? 0,
            'terms_conditions' => $data['terms_conditions'] ?? 'AI generated gig slot. Admin-configured guardrails apply.',
            'auto_pricing_enabled' => $data['auto_pricing_enabled'] ?? true,
            'surge_multiplier' => $data['surge_multiplier'] ?? 1,
            'demand_score' => $data['demand_score'] ?? 0,
            'forecasted_orders' => $data['forecasted_orders'] ?? 0,
            'recommended_capacity' => $data['recommended_capacity'] ?? null,
        ]);

        return ['success' => true, 'gig_id' => $gig->id, 'gig' => $gig->fresh()];
    }

    public function modifyGig(DriverGig $gig, array $data): array
    {
        $allowed = collect($data)->only([
            'title', 'description', 'capacity', 'status', 'base_pay', 'order_incentive',
            'login_incentive', 'min_orders_required', 'min_login_minutes', 'terms_conditions',
        ])->toArray();

        if (array_key_exists('status', $allowed) && ! in_array($allowed['status'], self::VALID_STATUSES, true)) {
            return ['success' => false, 'error' => "Invalid gig status [{$allowed['status']}]."];
        }

        if (array_key_exists('capacity', $allowed)) {
            $bookedCount = $gig->activeBookings()->count();
            if ((int) $allowed['capacity'] < $bookedCount) {
                return ['success' => false, 'error' => "Capacity cannot be less than the {$bookedCount} active bookings already on this gig."];
            }
        }

        if (($allowed['status'] ?? null) === 'booked' && ! $this->withinAreaBookingLimit($gig)) {
            return ['success' => false, 'error' => 'Cannot book this gig. The delivery area has reached its daily bookings limit.'];
        }

        $gig->update($allowed);

        if (array_key_exists('status', $allowed)) {
            $this->syncBookingsForTerminalStatus($gig, $allowed['status']);
        }

        return ['success' => true, 'gig_id' => $gig->id, 'gig' => $gig->fresh()];
    }

    public function closeGig(DriverGig $gig, ?string $reason = null): array
    {
        return $this->modifyGig($gig, [
            'status' => 'cancelled',
            'terms_conditions' => trim(($gig->terms_conditions ?? '')."\nClosed by AI: ".($reason ?: 'capacity no longer required.')),
        ]);
    }

    /**
     * Period-based fallback name for a gig slot when no title was proposed
     * (or a caller left it blank) -- keeps AI-created slots distinguishable
     * from each other instead of every one reading "AI recommended delivery
     * slot". Callers (chat/scheduled AI prompts) are asked to propose a more
     * specific title themselves; this is only the safety-net default.
     */
    private function defaultTitle(Carbon $startTime): string
    {
        $hour = (int) $startTime->format('G');

        return match (true) {
            $hour >= 5 && $hour < 11 => 'Breakfast Slot',
            $hour >= 11 && $hour < 15 => 'Lunch Slot',
            $hour >= 15 && $hour < 18 => 'Afternoon Slot',
            $hour >= 18 && $hour < 22 => 'Dinner Slot',
            default => 'Late Night Slot',
        };
    }

    private function overlaps($areaId, string $date, Carbon $startTime, Carbon $endTime, ?int $ignoreId = null): bool
    {
        return DriverGig::where('area_id', $areaId)
            ->whereDate('date', $date)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    private function withinAreaBookingLimit(DriverGig $gig): bool
    {
        if (! $gig->area_id) {
            return true;
        }

        $area = DeliveryArea::find($gig->area_id);
        if (! $area || ! $area->max_daily_bookings) {
            return true;
        }

        $bookedCount = DriverGig::where('area_id', $gig->area_id)
            ->whereDate('date', $gig->date)
            ->where('id', '!=', $gig->id)
            ->withCount(['activeBookings as active_bookings_count'])
            ->get()
            ->sum('booked_count');

        return $bookedCount < $area->max_daily_bookings;
    }

    private function syncBookingsForTerminalStatus(DriverGig $gig, string $status): void
    {
        if (! in_array($status, ['completed', 'cancelled'], true)) {
            return;
        }

        $timestampColumn = $status === 'completed' ? 'completed_at' : 'cancelled_at';

        $gig->bookings()
            ->where('status', 'booked')
            ->update([
                'status' => $status,
                $timestampColumn => now(),
                'updated_at' => now(),
            ]);
    }
}
