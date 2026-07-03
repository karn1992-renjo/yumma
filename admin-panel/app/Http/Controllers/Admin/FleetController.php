<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\AutoAssignDriverService;
use Illuminate\Support\Facades\Cache;

class FleetController extends Controller
{
    public function dashboard(Request $request)
    {
        $autoAssignService = app(AutoAssignDriverService::class);

        $drivers = User::role('delivery_partner')
            ->with('deliveryArea')
            ->withCount([
                'gigs as booked_gigs_count' => fn ($query) => $query->where('status', 'booked')->whereDate('date', '>=', today()),
                'orders as active_orders_count' => fn ($query) => $query->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'reached_pickup', 'picked_up', 'on_the_way']),
            ]);

        if ($request->filled('driver')) {
            $search = trim($request->input('driver'));
            $drivers->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('area_id')) {
            $drivers->where('delivery_area_id', $request->integer('area_id'));
        }

        $drivers = $drivers
            ->orderBy('name')
            ->get();

        $drivers->each(function ($driver) use ($autoAssignService) {
            $driver->effective_max_active_orders = $autoAssignService->maxActiveOrdersForDriver($driver);
        });

        $onlineDriverIds = $drivers
            ->filter(fn ($driver) => (bool) (Cache::get("driver_status_{$driver->id}")['is_online'] ?? false))
            ->pluck('id');

        $driverStatusFilter = $request->input('status');
        if ($driverStatusFilter === 'online') {
            $drivers = $drivers->filter(fn ($driver) => $onlineDriverIds->contains($driver->id))->values();
        } elseif ($driverStatusFilter === 'offline') {
            $drivers = $drivers->reject(fn ($driver) => $onlineDriverIds->contains($driver->id))->values();
        }

        $driverOnlineDurations = $drivers->mapWithKeys(function ($driver) {
            $status = Cache::get("driver_status_{$driver->id}", []);
            $startedAt = $status['online_started_at'] ?? null;

            $duration = null;
            if ($startedAt) {
                try {
                    $duration = now()->diffForHumans(Carbon::parse($startedAt), true, false, 2);
                } catch (\Throwable $e) {
                    $duration = null;
                }
            }

            return [
                $driver->id => [
                    'started_at' => $startedAt,
                    'duration' => $duration,
                ],
            ];
        });

        $stats = [
            'total_drivers' => $drivers->count(),
            'online_drivers' => $onlineDriverIds->count(),
            'booked_gigs_today' => DriverGig::whereDate('date', today())->where('status', 'booked')->count(),
            'available_gigs_today' => DriverGig::whereDate('date', today())->where('status', 'available')->count(),
            'active_deliveries' => Order::whereIn('status', ['picked_up', 'on_the_way'])->count(),
        ];

        $todayGigs = DriverGig::with(['driver', 'area'])
            ->whereDate('date', today())
            ->orderBy('start_time')
            ->get();

        $driverMarkers = $drivers
            ->map(function ($driver) use ($onlineDriverIds) {
                $location = Cache::get("driver_location_{$driver->id}");

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'area' => $driver->deliveryArea?->name,
                    'is_online' => $onlineDriverIds->contains($driver->id),
                    'lat' => $location['lat'] ?? $driver->latitude,
                    'lng' => $location['lng'] ?? $driver->longitude,
                    'updated_at' => isset($location['updated_at']) ? (string) $location['updated_at'] : null,
                    'active_orders_count' => $driver->active_orders_count,
                    'booked_gigs_count' => $driver->booked_gigs_count,
                ];
            })
            ->filter(fn ($driver) => !empty($driver['lat']) && !empty($driver['lng']))
            ->values();

        $areas = DeliveryArea::active()->orderBy('name')->get(['id', 'name']);
        $googleMapsApiKey = AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', ''));

        return view('admin.fleet.dashboard', compact(
            'drivers',
            'onlineDriverIds',
            'driverOnlineDurations',
            'stats',
            'todayGigs',
            'driverMarkers',
            'areas',
            'googleMapsApiKey'
        ));
    }
}
