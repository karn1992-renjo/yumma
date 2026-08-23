<?php

namespace App\Services;

use App\Models\DriverGig;
use App\Models\GigDemandForecast;
use App\Models\GigDispute;
use App\Models\GigFraudSignal;
use App\Models\GigPayoutApproval;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GigOperationsService
{
    public function snapshot(?Carbon $date = null): array
    {
        $date ??= today();

        $gigs = DriverGig::with('area')
            ->withCount(['activeBookings as active_bookings_count'])
            ->whereDate('date', $date->toDateString())
            ->get();

        $capacity = $gigs->sum(fn (DriverGig $gig) => (int) $gig->capacity);
        $booked = $gigs->sum(fn (DriverGig $gig) => (int) $gig->booked_count);
        $open = max(0, $capacity - $booked);

        return [
            'date' => $date->toDateString(),
            'capacity' => $capacity,
            'booked' => $booked,
            'open' => $open,
            'fill_rate' => $capacity > 0 ? round(($booked / $capacity) * 100, 1) : 0,
            'forecasted_orders' => $gigs->sum(fn (DriverGig $gig) => (int) ($gig->forecasted_orders ?? 0)),
            'recommended_capacity' => $gigs->sum(fn (DriverGig $gig) => (int) ($gig->recommended_capacity ?? 0)),
            'pending_payouts' => Schema::hasTable('gig_payout_approvals') ? GigPayoutApproval::where('status', 'pending')->count() : 0,
            'open_fraud_signals' => Schema::hasTable('gig_fraud_signals') ? GigFraudSignal::where('status', 'open')->count() : 0,
            'open_disputes' => Schema::hasTable('gig_disputes') ? GigDispute::where('status', 'open')->count() : 0,
            'areas' => $gigs->groupBy(fn (DriverGig $gig) => $gig->area?->name ?? 'Global')->map(function ($items, $areaName) {
                $areaCapacity = $items->sum(fn (DriverGig $gig) => (int) $gig->capacity);
                $areaBooked = $items->sum(fn (DriverGig $gig) => (int) $gig->booked_count);

                return [
                    'area' => $areaName,
                    'capacity' => $areaCapacity,
                    'booked' => $areaBooked,
                    'open' => max(0, $areaCapacity - $areaBooked),
                    'fill_rate' => $areaCapacity > 0 ? round(($areaBooked / $areaCapacity) * 100, 1) : 0,
                    'surge_multiplier' => round((float) $items->max('surge_multiplier'), 2),
                ];
            })->values()->all(),
        ];
    }

    public function controlRoom(?Carbon $date = null): array
    {
        $date ??= today();
        $snapshot = $this->snapshot($date);
        $snapshot['forecasts'] = Schema::hasTable('gig_demand_forecasts')
            ? GigDemandForecast::with('area')->whereDate('date', $date->toDateString())->orderBy('hour')->limit(200)->get()
            : [];
        $snapshot['fraud_signals'] = Schema::hasTable('gig_fraud_signals')
            ? GigFraudSignal::with(['driver', 'gig.area'])->where('status', 'open')->latest()->limit(50)->get()
            : [];
        $snapshot['payout_approvals'] = Schema::hasTable('gig_payout_approvals')
            ? GigPayoutApproval::with(['driver', 'gig.area', 'booking'])->where('status', 'pending')->latest()->limit(50)->get()
            : [];

        return $snapshot;
    }
}