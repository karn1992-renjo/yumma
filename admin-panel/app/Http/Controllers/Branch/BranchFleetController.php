<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The branch-panel counterpart of Admin\FleetController — a live driver map
 * scoped to only this branch's OWN mapped drivers (`users.branch_id`),
 * instead of every delivery partner on the platform. Reuses the exact same
 * cache keys (`driver_status_{id}` / `driver_location_{id}`, written by
 * Api\DriverController::updateLocation()) and the same Google-Maps-JS +
 * 10s-poll frontend pattern as the admin Fleet dashboard, so a branch owner
 * sees the same kind of live map admin does -- just filtered to their own
 * fleet.
 */
class BranchFleetController extends Controller
{
    public function __construct(private readonly BranchManagementService $branches)
    {
    }

    public function dashboard(Request $request)
    {
        $branch = $this->currentBranch($request);
        // Live tracking is a default part of Fleet, not a separately-grantable
        // permission -- anyone who can see the Fleet page (branch.gigs.view)
        // sees the live map on it too.
        $this->authorizeBranch($request, 'branch.gigs.view');

        $autoAssignService = app(AutoAssignDriverService::class);
        $drivers = $this->driverQuery($branch, $request)->get();

        $drivers->each(function ($driver) use ($autoAssignService) {
            $driver->effective_max_active_orders = $autoAssignService->maxActiveOrdersForDriver($driver);
        });

        $onlineDriverIds = $this->onlineDriverIds($drivers);
        $drivers = $this->filterByStatus($drivers, $request, $onlineDriverIds);

        $driverMarkers = $this->driverMarkersFor($drivers, $onlineDriverIds);

        $stats = [
            'total_drivers' => $drivers->count(),
            'online_drivers' => $onlineDriverIds->count(),
            'active_deliveries' => $drivers->sum('active_orders_count'),
        ];

        $googleMapsApiKey = AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', ''));

        return view('branch.fleet-map', compact('branch', 'drivers', 'onlineDriverIds', 'stats', 'driverMarkers', 'googleMapsApiKey'));
    }

    public function markers(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.view');

        $autoAssignService = app(AutoAssignDriverService::class);
        $drivers = $this->driverQuery($branch, $request)->get();

        $drivers->each(function ($driver) use ($autoAssignService) {
            $driver->effective_max_active_orders = $autoAssignService->maxActiveOrdersForDriver($driver);
        });

        $onlineDriverIds = $this->onlineDriverIds($drivers);
        $drivers = $this->filterByStatus($drivers, $request, $onlineDriverIds);

        return response()->json([
            'success' => true,
            'data' => $this->driverMarkersFor($drivers, $onlineDriverIds),
        ]);
    }

    private function driverQuery(Branch $branch, Request $request)
    {
        $query = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->with('deliveryArea')
            ->withCount([
                'orders as active_orders_count' => fn ($q) => $q->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'reached_pickup', 'picked_up', 'on_the_way']),
            ]);

        if ($request->filled('driver')) {
            $search = trim((string) $request->input('driver'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('name');
    }

    private function onlineDriverIds($drivers)
    {
        return $drivers
            ->filter(fn ($driver) => (bool) (Cache::get("driver_status_{$driver->id}")['is_online'] ?? false))
            ->pluck('id');
    }

    private function filterByStatus($drivers, Request $request, $onlineDriverIds)
    {
        $status = $request->input('status');
        if ($status === 'online') {
            return $drivers->filter(fn ($driver) => $onlineDriverIds->contains($driver->id))->values();
        }
        if ($status === 'offline') {
            return $drivers->reject(fn ($driver) => $onlineDriverIds->contains($driver->id))->values();
        }

        return $drivers;
    }

    private function driverMarkersFor($drivers, $onlineDriverIds)
    {
        return $drivers
            ->map(function ($driver) use ($onlineDriverIds) {
                $location = Cache::get("driver_location_{$driver->id}", []);
                $lat = is_array($location) ? ($location['lat'] ?? null) : null;
                $lng = is_array($location) ? ($location['lng'] ?? null) : null;

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone,
                    'area' => $driver->deliveryArea?->name,
                    'is_online' => $onlineDriverIds->contains($driver->id),
                    'lat' => $lat ?? $driver->latitude,
                    'lng' => $lng ?? $driver->longitude,
                    'updated_at' => is_array($location) && isset($location['updated_at']) ? (string) $location['updated_at'] : null,
                    'active_orders_count' => $driver->active_orders_count,
                ];
            })
            ->filter(fn ($driver) => ! empty($driver['lat']) && ! empty($driver['lng']))
            ->values();
    }

    private function currentBranch(Request $request): Branch
    {
        $branch = $this->branches->branchForUser($request->user());
        abort_unless($branch, 403);

        return $branch;
    }

    private function authorizeBranch(Request $request, string $permission, array $legacyPermissions = ['manage_drivers']): void
    {
        abort_unless($this->branchCan($request, $permission, $legacyPermissions), 403);
    }

    private function branchCan(Request $request, string $permission, array $legacyPermissions = []): bool
    {
        $user = $request->user();
        $permissions = array_merge([$permission], $legacyPermissions);

        foreach ($permissions as $candidate) {
            try {
                if ($user->can($candidate)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // Real Spatie role permissions on branch_owner/manager/staff use
        // manage_branch_* naming (seeded once, platform-wide); the dotted
        // branch.*.* keys and the flat legacy keys checked above are never
        // actually granted via Spatie -- they only ever land in this
        // BranchUser.permissions JSON column (BranchManagementService::
        // permissionsFor()). Skipping this fallback -- as an earlier draft
        // of this method did -- 403s every real branch owner/manager/staff.
        $branch = $this->currentBranch($request);
        $membership = \App\Models\BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $membership && count(array_intersect($permissions, $membership->permissions ?? [])) > 0;
    }
}
