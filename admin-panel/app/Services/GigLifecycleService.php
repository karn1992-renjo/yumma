<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AppDatabaseNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GigLifecycleService
{
    public function activeBookingForDriver(int $driverId, ?int $areaId = null): ?DriverGigBooking
    {
        $now = now();

        $query = DriverGigBooking::with('gig.area')
            ->where('driver_id', $driverId)
            ->where('status', 'booked')
            ->whereHas('gig', function ($query) use ($now, $areaId) {
                $query->whereDate('date', today())
                    ->whereIn('status', ['available', 'booked'])
                    ->whereTime('start_time', '<=', $now->copy()->addMinutes(30)->format('H:i:s'))
                    ->whereTime('end_time', '>=', $now->format('H:i:s'))
                    ->when($areaId, function ($areaQuery) use ($areaId) {
                        $areaQuery->where(function ($builder) use ($areaId) {
                            $builder->where('area_id', $areaId)->orWhereNull('area_id');
                        });
                    });
            });

        if (Schema::hasColumn('driver_gig_bookings', 'checked_in_at')) {
            $query->orderByDesc('checked_in_at');
        }

        return $query->latest('id')->first();
    }

    public function openBookingForDriver(int $driverId): ?DriverGigBooking
    {
        if (! Schema::hasColumn('driver_gig_bookings', 'checked_in_at') || ! Schema::hasColumn('driver_gig_bookings', 'checked_out_at')) {
            return null;
        }

        return DriverGigBooking::with('gig.area')
            ->where('driver_id', $driverId)
            ->where('status', 'booked')
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereHas('gig', fn ($query) => $query->whereDate('date', today()))
            ->latest('checked_in_at')
            ->first();
    }

    public function checkIn(int $driverId): ?DriverGigBooking
    {
        $booking = $this->activeBookingForDriver($driverId);
        if (! $booking || ! Schema::hasColumn('driver_gig_bookings', 'checked_in_at')) {
            return $booking;
        }

        if (! $booking->checked_in_at) {
            $booking->forceFill([
                'checked_in_at' => now(),
                'no_show' => false,
            ])->save();
        }

        return $booking->fresh('gig.area');
    }

    public function checkOutOpenBooking(int $driverId): ?DriverGigBooking
    {
        $booking = $this->openBookingForDriver($driverId);
        if (! $booking || ! Schema::hasColumn('driver_gig_bookings', 'checked_out_at')) {
            return $booking;
        }

        $slotEnd = $this->slotDateTime($booking->gig, 'end_time') ?? now();
        $checkedOutAt = now()->lessThan($slotEnd) ? now() : $slotEnd;
        $booking->forceFill(['checked_out_at' => $checkedOutAt])->save();

        return $this->refreshBookingMetrics($booking->fresh('gig'));
    }

    public function recordRejection(int $driverId): void
    {
        $booking = $this->activeBookingForDriver($driverId) ?: $this->openBookingForDriver($driverId);
        if (! $booking || ! Schema::hasColumn('driver_gig_bookings', 'rejected_orders_count')) {
            return;
        }

        $booking->increment('rejected_orders_count');
        $this->refreshBookingMetrics($booking->fresh('gig'));
    }

    public function finalizeDueGigs(): int
    {
        $count = 0;

        DriverGig::whereIn('status', ['available', 'booked'])
            ->where('end_time', '<', now())
            ->with('bookings.driver')
            ->get()
            ->each(function (DriverGig $gig) use (&$count) {
                DB::transaction(function () use ($gig, &$count) {
                    $gig->update(['status' => 'completed']);

                    $gig->bookings()
                        ->where('status', 'booked')
                        ->get()
                        ->each(function (DriverGigBooking $booking) use ($gig, &$count) {
                            $booking = $this->refreshBookingMetrics($booking->loadMissing('gig'));
                            $noShow = ! $booking->checked_in_at;

                            $updates = [
                                'status' => 'completed',
                                'completed_at' => now(),
                            ];

                            if (Schema::hasColumn('driver_gig_bookings', 'no_show')) {
                                $updates['no_show'] = $noShow;
                            }

                            $booking->forceFill($updates)->save();
                            app(GigFraudDetectionService::class)->scanBooking($booking->fresh('gig'));

                            $this->creditGigIncentive($gig->fresh(), $booking->fresh('gig'));
                            $count++;
                        });
                });
            });

        app(GigOperationsBroadcastService::class)->broadcast();

        return $count;
    }

    public function sendUpcomingReminders(): int
    {
        if (! Schema::hasColumn('driver_gig_bookings', 'reminder_sent_at')) {
            return 0;
        }

        $sent = 0;
        $now = now();

        DriverGigBooking::with(['gig.area', 'driver'])
            ->where('status', 'booked')
            ->whereNull('reminder_sent_at')
            ->whereHas('gig', function ($query) use ($now) {
                $query->whereDate('date', $now->toDateString())
                    ->whereTime('start_time', '>=', $now->format('H:i:s'))
                    ->whereTime('start_time', '<=', $now->copy()->addMinutes(30)->format('H:i:s'));
            })
            ->limit(100)
            ->get()
            ->each(function (DriverGigBooking $booking) use (&$sent) {
                $this->notifyDriver($booking, 'Gig starts soon', 'Your delivery gig starts in the next 30 minutes.');
                $booking->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });

        app(GigOperationsBroadcastService::class)->broadcast();

        return $sent;
    }

    public function heatmap(?Carbon $date = null): array
    {
        $date ??= today();

        return DriverGig::with('area')
            ->withCount(['bookings as booked_slots_count' => fn ($query) => $query->whereIn('status', ['booked', 'completed'])])
            ->whereDate('date', $date->toDateString())
            ->get()
            ->groupBy(fn (DriverGig $gig) => $gig->area?->name ?? 'Global')
            ->map(function ($gigs, string $areaName) {
                return $gigs->map(function (DriverGig $gig) use ($areaName) {
                    $hour = $this->slotDateTime($gig, 'start_time')?->format('H:00') ?? '00:00';
                    $capacity = max(1, (int) $gig->capacity);
                    $booked = (int) ($gig->booked_slots_count ?? 0);

                    return [
                        'area_id' => $gig->area_id,
                        'area_name' => $areaName,
                        'hour' => $hour,
                        'capacity' => $capacity,
                        'booked' => $booked,
                        'available' => max(0, $capacity - $booked),
                        'fill_rate' => round(($booked / $capacity) * 100, 1),
                    ];
                });
            })
            ->flatten(1)
            ->values()
            ->all();
    }

    public function resolveDeliveryAreaId(?float $latitude, ?float $longitude): ?int
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return DeliveryArea::active()
            ->get()
            ->first(fn (DeliveryArea $area) => $area->containsPoint($latitude, $longitude))?->id;
    }

    public function refreshBookingMetrics(DriverGigBooking $booking): DriverGigBooking
    {
        $gig = $booking->gig ?: DriverGig::find($booking->driver_gig_id);
        if (! $gig) {
            return $booking;
        }

        $start = $this->slotDateTime($gig, 'start_time');
        $end = $this->slotDateTime($gig, 'end_time');
        if (! $start || ! $end) {
            return $booking;
        }

        $orders = Order::where('driver_id', $booking->driver_id)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$start, $end])
            ->get();

        $onlineMinutes = (int) ($booking->online_minutes ?? 0);
        if ($booking->checked_in_at) {
            $checkout = $booking->checked_out_at ?: (now()->lessThan($end) ? now() : $end);
            $onlineMinutes = max($onlineMinutes, max(0, $booking->checked_in_at->diffInMinutes($checkout, false)));
        }

        $updates = [
            'online_minutes' => $onlineMinutes,
            'delivered_orders_count' => $orders->count(),
            'metrics' => [
                'orders_completed' => $orders->pluck('id')->values()->all(),
                'slot_start' => $start->toIso8601String(),
                'slot_end' => $end->toIso8601String(),
            ],
        ];

        foreach (array_keys($updates) as $column) {
            if (! Schema::hasColumn('driver_gig_bookings', $column)) {
                unset($updates[$column]);
            }
        }

        if ($updates) {
            $booking->forceFill($updates)->save();
        }

        return $booking->fresh('gig');
    }

    public function creditGigIncentive(DriverGig $gig, DriverGigBooking $booking): void
    {
        if (Schema::hasColumn('driver_gig_bookings', 'incentive_paid_at') && $booking->incentive_paid_at) {
            return;
        }

        $incentive = app(GigIncentiveService::class)->calculateGigEarnings($gig, $booking->driver_id);
        if (! $incentive) {
            return;
        }

        $amount = (float) ($incentive->total_earned ?? 0);
        if ($amount <= 0) {
            if (Schema::hasColumn('driver_gig_bookings', 'incentive_paid_at')) {
                $booking->forceFill(['incentive_paid_at' => now()])->save();
            }
            return;
        }

        app(GigPayoutApprovalService::class)->stageOrRelease($gig, $booking, $incentive);
    }

    private function notifyDriver(DriverGigBooking $booking, string $title, string $body): void
    {
        $driver = $booking->driver ?: User::find($booking->driver_id);
        if (! $driver) {
            return;
        }

        $driver->notify(new AppDatabaseNotification($title, $body, [
            'type' => 'gig_reminder',
            'driver_gig_id' => $booking->driver_gig_id,
            'driver_gig_booking_id' => $booking->id,
            'deeplink' => '/driver/gigs',
        ]));
    }

    private function slotDateTime(DriverGig $gig, string $attribute): ?Carbon
    {
        $date = $gig->date;
        $time = $gig->{$attribute};

        if (! $date || ! $time) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
            config('app.timezone')
        );
    }
}