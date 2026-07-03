<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Exports\BranchCollectionExport;
use App\Models\AppSetting;
use App\Models\BranchSettlement;
use App\Models\BranchPayout;
use App\Models\BranchTicket;
use App\Models\BranchUser;
use App\Models\BranchWalletTransaction;
use App\Models\BranchZone;
use App\Models\Cuisine;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class BranchDashboardController extends Controller
{
    public function __construct(private BranchManagementService $branches)
    {
    }

    public function index(Request $request)
    {
        $branch = $this->currentBranch($request);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $orders = Order::with(['restaurant', 'driver'])
            ->where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('restaurant_id', $restaurantIds));
            })
            ->latest()
            ->limit(15)
            ->get();

        $branchOrders = $this->branchOrderQuery($branch, $restaurantIds);
        $creditedOrders = $this->creditedBranchOrderQuery($branch, $restaurantIds);

        $stats = [
            'orders' => (clone $branchOrders)->count(),
            'completed' => (clone $branchOrders)->where('status', 'delivered')->count(),
            'revenue' => (clone $branchOrders)->where('status', 'delivered')->sum('total'),
            'commission' => (clone $creditedOrders)->sum('branch_commission'),
            'wallet' => (float) ($branch->wallet?->balance ?? 0),
            'restaurants' => Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })->count(),
            'drivers' => User::role('delivery_partner')->where('branch_id', $branch->id)->count(),
            'zones' => $branch->zones()->count(),
        ];

        $dailyRevenue = Order::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('restaurant_id', $restaurantIds));
            })
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topRestaurants = Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->withCount('orders')
            ->withSum(['orders as revenue' => fn ($query) => $query->where('status', 'delivered')], 'total')
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $topDrivers = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $walletTransactions = BranchWalletTransaction::where('branch_id', $branch->id)
            ->latest()
            ->limit(5)
            ->get();

        $settlements = BranchSettlement::where('branch_id', $branch->id)
            ->latest()
            ->limit(5)
            ->get();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.dashboard', compact(
            'branch',
            'orders',
            'stats',
            'dailyRevenue',
            'topRestaurants',
            'topDrivers',
            'walletTransactions',
            'settlements',
            'capabilities'
        ));
    }

    public function orders(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $orders = $this->filteredBranchOrders($request, $branch, $restaurantIds)
            ->with(['restaurant', 'driver', 'customer'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.orders', compact('branch', 'orders', 'capabilities'));
    }

    public function exportOrders(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.export', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $rows = $this->filteredBranchOrders($request, $branch, $restaurantIds)
            ->with(['restaurant', 'driver'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->restaurant?->name,
                $order->customer_name,
                $order->customer_phone,
                $order->driver?->name ?? 'Unassigned',
                $order->status,
                $order->payment_status,
                (float) $order->total,
                $order->branch_commission_settled ? (float) $order->branch_commission : 0,
                $order->branch_commission_settled ? 'Credited' : 'Pending delivery',
                optional($order->created_at)->format('Y-m-d H:i:s'),
                optional($order->delivered_at)->format('Y-m-d H:i:s'),
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Order Number',
            'Restaurant',
            'Customer',
            'Customer Phone',
            'Driver',
            'Status',
            'Payment Status',
            'Total',
            'Credited Branch Commission',
            'Commission Status',
            'Created At',
            'Delivered At',
        ]), 'branch-orders-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function showOrder(Request $request, Order $order)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);

        $order->load(['restaurant', 'customer', 'driver', 'branch']);
        $availableDrivers = $this->availableBranchDrivers($branch, $order);
        $canAssignDriver = $this->branchCan($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']);

        return view('branch.orders-show', compact('branch', 'order', 'availableDrivers', 'canAssignDriver'));
    }

    public function assignOrderDriver(Request $request, Order $order, AutoAssignDriverService $autoAssignService)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);

        if ($order->driver_id) {
            return back()->withErrors(['driver_id' => 'This order already has a driver assigned.']);
        }

        if (in_array($order->status, ['delivered', 'cancelled', 'refunded'], true)) {
            return back()->withErrors(['driver_id' => 'Completed, cancelled, or refunded orders cannot be assigned to a driver.']);
        }

        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $driver = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->findOrFail($data['driver_id']);

        $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);
        if (!($eligibility['eligible'] ?? false)) {
            return back()->withErrors(['driver_id' => $eligibility['reason'] ?? 'Selected driver is not available for this order.']);
        }

        $order->forceFill([
            'branch_id' => $branch->id,
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
        ])->save();

        $this->branches->assignDriver($driver, $branch, $request->user());
        $this->branches->audit($branch, $request->user(), 'order.driver_assigned', $order, null, [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);

        return redirect()->route('branch.orders.show', $order)->with('success', 'Driver assigned to order.');
    }

    public function restaurants(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.view', ['manage_restaurants']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $restaurants = Restaurant::with('owner')
            ->withCount('orders')
            ->where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pincode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_open', $request->status === 'open'))
            ->when($request->filled('verification'), fn ($query) => $query->where('is_verified', $request->verification === 'verified'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.restaurants', compact('branch', 'restaurants', 'capabilities'));
    }

    public function createRestaurant(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.create', ['manage_restaurants']);

        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = $this->branchDeliveryAreas($branch);

        return view('branch.restaurants-create', compact('branch', 'cuisines', 'deliveryAreas'));
    }

    public function storeRestaurant(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.create', ['manage_restaurants']);

        $data = $this->validateRestaurant($request);
        $this->ensureInsideBranchZone($branch, $data, 'restaurant');

        $restaurant = DB::transaction(function () use ($request, $branch, $data) {
            $owner = User::create(array_merge([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'password' => Hash::make($data['owner_password']),
                'email_verified_at' => now(),
                'branch_id' => $branch->id,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'upi_id' => $data['upi_id'] ?? null,
                'stripe_account_id' => $data['stripe_account_id'] ?? null,
            ], $this->payoutProviderAccountAttributes($request)));
            $owner->assignRole('restaurant_owner');

            $slug = $this->uniqueRestaurantSlug($data['name']);
            $restaurant = Restaurant::create([
                'branch_id' => $branch->id,
                'owner_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'fssai_license_number' => $data['fssai_license_number'] ?? null,
                'description' => $data['description'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode'],
                'latitude' => $data['latitude'] ?? 0,
                'longitude' => $data['longitude'] ?? 0,
                'delivery_radius' => $data['delivery_radius'],
                'restaurant_type' => $data['restaurant_type'],
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'dining_charge' => $data['dining_charge'] ?? 0,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'commission_rate' => ($data['commission_calculation_type'] ?? 'global') === 'global'
                    ? null
                    : ($data['commission_rate'] ?? null),
                'commission_calculation_type' => $data['commission_calculation_type'] ?? 'global',
                'delivery_time' => $data['delivery_time'] ?? 30,
                'order_lead_time' => $data['order_lead_time'] ?? 0,
                'open_time' => $data['open_time'] ?? null,
                'close_time' => $data['close_time'] ?? null,
                'weekly_timings' => $this->weeklyTimingsFromFlatHours(null, $data['open_time'] ?? null, $data['close_time'] ?? null),
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'cuisine' => $data['cuisine'] ?? [],
                'is_open' => false,
                'is_verified' => false,
                'is_featured' => false,
            ]);

            $this->storeRestaurantImages($request, $restaurant);
            $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'branch_pending');

            return $restaurant;
        });

        return redirect()->route('branch.restaurants')->with('success', "Restaurant {$restaurant->name} created for this branch.");
    }

    public function showRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.view', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $restaurant->load(['owner', 'orders' => fn ($query) => $query->latest()->limit(10)]);
        $totalOrders = $restaurant->orders()->count();
        $totalRevenue = $restaurant->orders()->where('status', 'delivered')->sum('total');
        $totalMenuItems = $restaurant->menuItems()->count();
        $averageRating = $restaurant->reviews()->avg('rating') ?? 0;
        $capabilities = $this->branchCapabilities($request);

        return view('branch.restaurants-show', compact(
            'branch',
            'restaurant',
            'totalOrders',
            'totalRevenue',
            'totalMenuItems',
            'averageRating',
            'capabilities'
        ));
    }

    public function editRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $restaurant->load('owner');
        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = $this->branchDeliveryAreas($branch);

        return view('branch.restaurants-edit', compact('branch', 'restaurant', 'cuisines', 'deliveryAreas'));
    }

    public function updateRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $data = $this->validateRestaurant($request, $restaurant);
        $this->ensureInsideBranchZone($branch, $data, 'restaurant');

        if ($restaurant->name !== $data['name']) {
            $data['slug'] = $this->uniqueRestaurantSlug($data['name'], $restaurant->id);
        }

        $restaurant->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $restaurant->slug,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'fssai_license_number' => $data['fssai_license_number'] ?? null,
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'latitude' => $data['latitude'] ?? 0,
            'longitude' => $data['longitude'] ?? 0,
            'delivery_radius' => $data['delivery_radius'],
            'restaurant_type' => $data['restaurant_type'],
            'is_pure_veg' => $request->boolean('is_pure_veg'),
            'dining_charge' => $data['dining_charge'] ?? 0,
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'commission_rate' => ($data['commission_calculation_type'] ?? 'global') === 'global'
                ? null
                : ($data['commission_rate'] ?? null),
            'commission_calculation_type' => $data['commission_calculation_type'] ?? 'global',
            'delivery_time' => $data['delivery_time'] ?? 30,
            'order_lead_time' => $data['order_lead_time'] ?? 0,
            'open_time' => $data['open_time'] ?? null,
            'close_time' => $data['close_time'] ?? null,
            'weekly_timings' => $this->weeklyTimingsFromFlatHours($restaurant->weekly_timings, $data['open_time'] ?? null, $data['close_time'] ?? null),
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'cuisine' => $data['cuisine'] ?? [],
        ]);

        if ($restaurant->owner) {
            $ownerPayload = array_merge([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'upi_id' => $data['upi_id'] ?? null,
                'stripe_account_id' => $data['stripe_account_id'] ?? null,
                'branch_id' => $branch->id,
            ], $this->payoutProviderAccountAttributes($request));

            if (!empty($data['owner_password'])) {
                $ownerPayload['password'] = Hash::make($data['owner_password']);
            }

            $restaurant->owner->update($ownerPayload);
        }

        $this->storeRestaurantImages($request, $restaurant, true);
        $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'branch_pending');

        return redirect()->route('branch.restaurants')->with('success', "Restaurant {$restaurant->name} updated.");
    }

    public function approveRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $this->ensureInsideBranchZone($branch, $restaurant->only(['address', 'city', 'state', 'pincode', 'latitude', 'longitude']), 'restaurant');

        $restaurant->forceFill([
            'branch_id' => $branch->id,
            'is_verified' => true,
        ])->save();

        $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'approved');

        return back()->with('success', "{$restaurant->name} approved for this branch zone.");
    }

    public function drivers(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.view', ['manage_drivers']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $drivers = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->withCount([
                'orders',
                'orders as delivery_zone_orders_count' => fn ($query) => $restaurantIds->isNotEmpty()
                    ? $query->whereIn('restaurant_id', $restaurantIds)
                    : $query->whereRaw('1 = 0'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('vehicle_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.drivers', compact('branch', 'drivers', 'capabilities'));
    }

    public function createDriver(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.create', ['manage_drivers']);
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        return view('branch.drivers-create', compact('branch', 'globalMaxActiveOrders'));
    }

    public function storeDriver(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.create', ['manage_drivers']);

        $data = $this->validateDriver($request);
        $this->ensureInsideBranchZone($branch, $data, 'driver');

        $driver = User::create(array_merge([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_number' => $data['vehicle_number'],
            'license_number' => $data['license_number'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'max_active_orders' => $data['max_active_orders'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'upi_id' => $data['upi_id'] ?? null,
            'stripe_account_id' => $data['stripe_account_id'] ?? null,
        ], $this->payoutProviderAccountAttributes($request)));
        $driver->assignRole('delivery_partner');
        $this->branches->assignDriver($driver, $branch, $request->user());

        return redirect()->route('branch.drivers')->with('success', "Driver {$driver->name} created for this branch.");
    }

    public function editDriver(Request $request, User $driver)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.edit', ['manage_drivers']);
        abort_unless($driver->hasRole('delivery_partner') && (int) $driver->branch_id === (int) $branch->id, 404);

        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        return view('branch.drivers-edit', compact('branch', 'driver', 'globalMaxActiveOrders'));
    }

    public function updateDriver(Request $request, User $driver)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.edit', ['manage_drivers']);
        abort_unless($driver->hasRole('delivery_partner') && (int) $driver->branch_id === (int) $branch->id, 404);

        $data = $this->validateDriver($request, $driver);
        $this->ensureInsideBranchZone($branch, $data, 'driver');

        $payload = array_merge([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_active' => $request->boolean('is_active'),
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_number' => $data['vehicle_number'],
            'license_number' => $data['license_number'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'max_active_orders' => $data['max_active_orders'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'upi_id' => $data['upi_id'] ?? null,
            'stripe_account_id' => $data['stripe_account_id'] ?? null,
        ], $this->payoutProviderAccountAttributes($request));

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $driver->update($payload);
        $this->branches->assignDriver($driver, $branch, $request->user());

        return redirect()->route('branch.drivers')->with('success', "Driver {$driver->name} updated.");
    }

    public function zones(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.zones.view', ['manage_zones']);

        $zones = BranchZone::with('deliveryArea')->where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.zones', compact('branch', 'zones'));
    }

    public function wallet(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.wallet.export', ['view_wallet', 'view_earnings']);

        $wallet = $branch->wallet;
        $payouts = BranchPayout::where('branch_id', $branch->id)
            ->whereNull('branch_settlement_id')
            ->latest()
            ->limit(10)
            ->get();
        $transactions = BranchWalletTransaction::with('order')
            ->where('branch_id', $branch->id)
            ->latest()
            ->paginate(25);

        $capabilities = $this->branchCapabilities($request);

        return view('branch.wallet', compact('branch', 'wallet', 'transactions', 'payouts', 'capabilities'));
    }

    public function exportWallet(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']);

        $rows = BranchWalletTransaction::with('order')
            ->where('branch_id', $branch->id)
            ->latest()
            ->get()
            ->map(fn (BranchWalletTransaction $transaction) => [
                optional($transaction->created_at)->format('Y-m-d H:i:s'),
                $transaction->type,
                $transaction->order?->order_number,
                $transaction->description,
                (float) $transaction->amount,
                (float) $transaction->balance_after,
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Date',
            'Type',
            'Order',
            'Description',
            'Amount',
            'Balance After',
        ]), 'branch-wallet-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function requestWithdrawal(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.withdrawals.create', ['view_wallet', 'submit_settlement_requests']);
        $bank = $branch->bank_details ?? [];
        $hasBankAccount = !empty($bank['account_holder_name'])
            && !empty($bank['bank_name'])
            && !empty($bank['account_number'])
            && (!empty($bank['ifsc_code']) || !empty($bank['routing_code']));
        $hasAlternatePayout = !empty($bank['upi_id']) || !empty($bank['gateway_account_id']);

        if (! $hasBankAccount && ! $hasAlternatePayout) {
            return redirect()
                ->route('branch.settings')
                ->withErrors(['bank_details' => 'Add payout bank account, UPI, or gateway account details before requesting withdrawal.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payout = $this->branches->requestWithdrawal(
            $branch,
            (float) $data['amount'],
            $request->user(),
            $data['notes'] ?? null
        );

        return redirect()
            ->route('branch.wallet')
            ->with('success', 'Withdrawal request submitted: #' . $payout->id);
    }

    public function settlements(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settlements.view', ['submit_settlement_requests', 'view_wallet']);

        $settlements = BranchSettlement::where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.settlements', compact('branch', 'settlements'));
    }

    public function storeSettlement(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settlements.create', ['submit_settlement_requests']);

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $settlement = $this->branches->generateSettlement(
            $branch,
            $data['period_start'],
            $data['period_end'],
            $request->user()
        );

        return redirect()
            ->route('branch.settlements')
            ->with('success', 'Settlement request generated: ' . $settlement->settlement_number);
    }

    public function reports(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.view', ['view_reports']);

        $restaurantIds = $this->restaurantIdsForBranchZones($branch);
        $orders = $this->branchOrderQuery($branch, $restaurantIds);
        $creditedOrders = $this->creditedBranchOrderQuery($branch, $restaurantIds);

        $summary = [
            'orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'delivered')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'refunded_orders' => (clone $orders)->where('payment_status', 'refunded')->count(),
            'revenue' => (clone $orders)->where('status', 'delivered')->sum('total'),
            'branch_commission' => (clone $creditedOrders)->sum('branch_commission'),
            'wallet_balance' => (float) ($branch->wallet?->balance ?? 0),
        ];

        $topRestaurants = Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $peakHours = (clone $orders)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as orders_count')
            ->groupBy('hour')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.reports', compact('branch', 'summary', 'topRestaurants', 'peakHours', 'capabilities'));
    }

    public function exportReports(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.export', ['view_reports']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $rows = $this->creditedBranchOrderQuery($branch, $restaurantIds)
            ->with(['restaurant', 'driver'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->restaurant?->name,
                $order->driver?->name,
                (float) $order->total,
                (float) $order->branch_commission,
                optional($order->delivered_at ?: $order->updated_at)->format('Y-m-d H:i:s'),
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Order Number',
            'Restaurant',
            'Driver',
            'Delivered Revenue',
            'Credited Branch Commission',
            'Credited At',
        ]), 'branch-report-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function settings(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settings.view', ['manage_staff']);

        $staff = BranchUser::with('user')
            ->where('branch_id', $branch->id)
            ->latest()
            ->get();
        $permissionCatalog = $this->branchPermissionCatalog();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.settings', compact('branch', 'staff', 'permissionCatalog', 'capabilities'));
    }

    public function updateSettings(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settings.update', ['manage_staff']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', Rule::unique('branches', 'owner_email')->ignore($branch->id), Rule::unique('users', 'email')->ignore($branch->owner_user_id)],
            'owner_phone' => ['required', 'string', 'max:20', Rule::unique('branches', 'owner_phone')->ignore($branch->id), Rule::unique('users', 'phone')->ignore($branch->owner_user_id)],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gst_number' => ['nullable', 'string', 'max:100'],
            'pan_number' => ['nullable', 'string', 'max:100'],
            'trade_license' => ['nullable', 'string', 'max:100'],
            'bank_details.account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_details.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_details.account_number' => ['nullable', 'string', 'max:100'],
            'bank_details.ifsc_code' => ['nullable', 'string', 'max:50'],
            'bank_details.routing_code' => ['nullable', 'string', 'max:50'],
            'bank_details.upi_id' => ['nullable', 'string', 'max:100'],
            'bank_details.gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $branch->only(['name', 'owner_name', 'owner_email', 'owner_phone', 'bank_details']);
        $branch->update($data);
        if ($branch->owner) {
            $branch->owner->update([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
            ]);
        }
        $this->branches->audit($branch, $request->user(), 'settings.updated', $branch, $old, $branch->fresh()->toArray());

        return back()->with('success', 'Branch settings updated.');
    }

    public function storeStaff(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.staff.create', ['manage_staff']);

        $catalog = array_keys($this->branchPermissionCatalog());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in([BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($catalog)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $user->assignRole($data['role']);

        BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->branches->audit($branch, $request->user(), 'staff.created', $user, null, $user->toArray());

        return back()->with('success', 'Branch staff created.');
    }

    public function updateStaff(Request $request, BranchUser $staff)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.staff.edit', ['manage_staff']);
        abort_unless((int) $staff->branch_id === (int) $branch->id, 404);

        $catalog = array_keys($this->branchPermissionCatalog());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($staff->user_id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($staff->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in([BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($catalog)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $userPayload = [
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_active' => $request->boolean('is_active'),
        ];
        if (!empty($data['password'])) {
            $userPayload['password'] = Hash::make($data['password']);
        }

        $old = $staff->load('user')->toArray();
        $staff->user->update($userPayload);
        $staff->user->syncRoles([$data['role']]);
        $staff->update([
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->branches->audit($branch, $request->user(), 'staff.updated', $staff, $old, $staff->fresh('user')->toArray());

        return back()->with('success', 'Branch staff updated.');
    }

    public function tickets(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.view', ['manage_support_tickets', 'view_assigned_tasks']);

        $tickets = BranchTicket::where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.tickets', compact('branch', 'tickets'));
    }

    public function storeTicket(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.create', ['manage_support_tickets']);

        $data = $request->validate([
            'category' => ['required', Rule::in(['Settlement Issue', 'Payment Issue', 'Restaurant Issue', 'Driver Issue', 'Technical Issue'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        BranchTicket::create($data + [
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'ticket_number' => 'BTK' . now()->format('YmdHis') . random_int(100, 999),
            'status' => 'open',
            'priority' => 'normal',
        ]);

        return redirect()->route('branch.tickets')->with('success', 'Support ticket created.');
    }

    private function currentBranch(Request $request)
    {
        $branch = $this->branches->branchForUser($request->user());
        abort_unless($branch, 403);

        return $branch;
    }

    private function authorizeBranch(Request $request, string $permission, array $legacyPermissions = []): void
    {
        abort_unless($this->branchCan($request, $permission, $legacyPermissions), 403);
    }

    private function branchCapabilities(Request $request): array
    {
        return [
            'orders_view' => $this->branchCan($request, 'branch.orders.view', ['view_orders', 'manage_orders']),
            'orders_assign_driver' => $this->branchCan($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']),
            'orders_export' => $this->branchCan($request, 'branch.orders.export', ['view_orders', 'manage_orders']),
            'reports_view' => $this->branchCan($request, 'branch.reports.view', ['view_reports']),
            'reports_export' => $this->branchCan($request, 'branch.reports.export', ['view_reports']),
            'wallet_view' => $this->branchCan($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']),
            'wallet_export' => $this->branchCan($request, 'branch.wallet.export', ['view_wallet', 'view_earnings']),
            'withdrawals_create' => $this->branchCan($request, 'branch.withdrawals.create', ['view_wallet', 'submit_settlement_requests']),
            'settlements_view' => $this->branchCan($request, 'branch.settlements.view', ['submit_settlement_requests', 'view_wallet']),
            'restaurants_view' => $this->branchCan($request, 'branch.restaurants.view', ['manage_restaurants']),
            'restaurants_create' => $this->branchCan($request, 'branch.restaurants.create', ['manage_restaurants']),
            'restaurants_edit' => $this->branchCan($request, 'branch.restaurants.edit', ['manage_restaurants']),
            'drivers_view' => $this->branchCan($request, 'branch.drivers.view', ['manage_drivers']),
            'drivers_create' => $this->branchCan($request, 'branch.drivers.create', ['manage_drivers']),
            'drivers_edit' => $this->branchCan($request, 'branch.drivers.edit', ['manage_drivers']),
            'settings_view' => $this->branchCan($request, 'branch.settings.view', ['manage_staff']),
            'settings_update' => $this->branchCan($request, 'branch.settings.update', ['manage_staff']),
            'staff_create' => $this->branchCan($request, 'branch.staff.create', ['manage_staff']),
            'staff_edit' => $this->branchCan($request, 'branch.staff.edit', ['manage_staff']),
        ];
    }

    private function branchPermissionCatalog(): array
    {
        return [
            'branch.orders.view' => 'View Orders',
            'branch.orders.assign_driver' => 'Assign Order Drivers',
            'branch.orders.export' => 'Export Orders',
            'branch.restaurants.view' => 'View Restaurants',
            'branch.restaurants.create' => 'Create Restaurants',
            'branch.restaurants.edit' => 'Edit Restaurants',
            'branch.drivers.view' => 'View Drivers',
            'branch.drivers.create' => 'Create Drivers',
            'branch.drivers.edit' => 'Edit Drivers',
            'branch.zones.view' => 'View Territories',
            'branch.wallet.view' => 'View Wallet',
            'branch.wallet.export' => 'Export Wallet',
            'branch.withdrawals.create' => 'Request Withdrawals',
            'branch.settlements.view' => 'View Settlements',
            'branch.settlements.create' => 'Request Settlements',
            'branch.reports.view' => 'View Reports',
            'branch.reports.export' => 'Export Reports',
            'branch.settings.view' => 'View Settings',
            'branch.settings.update' => 'Update Settings',
            'branch.staff.create' => 'Create Staff',
            'branch.staff.edit' => 'Edit Staff',
            'branch.tickets.view' => 'View Support Tickets',
            'branch.tickets.create' => 'Create Support Tickets',
        ];
    }

    private function branchOrderQuery($branch, $restaurantIds)
    {
        return Order::where(function ($query) use ($branch, $restaurantIds) {
            $query->where('branch_id', $branch->id)
                ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('restaurant_id', $restaurantIds));
        });
    }

    private function creditedBranchOrderQuery($branch, $restaurantIds)
    {
        return $this->branchOrderQuery($branch, $restaurantIds)
            ->where('status', 'delivered')
            ->where('branch_commission_settled', true);
    }

    private function filteredBranchOrders(Request $request, $branch, $restaurantIds)
    {
        return $this->branchOrderQuery($branch, $restaurantIds)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            });
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

        $branch = $this->branches->branchForUser($user);
        $membership = $branch
            ? \App\Models\BranchUser::where('branch_id', $branch->id)->where('user_id', $user->id)->where('is_active', true)->first()
            : null;

        return $membership && count(array_intersect($permissions, $membership->permissions ?? [])) > 0;
    }

    private function restaurantIdsForBranchZones($branch)
    {
        $zones = $branch->zones()->with('deliveryArea')->where('is_active', true)->whereNotNull('delivery_area_id')->get();
        if ($zones->isEmpty()) {
            return collect();
        }

        return Restaurant::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'latitude', 'longitude'])
            ->filter(function (Restaurant $restaurant) use ($zones) {
                return $zones->contains(fn (BranchZone $zone) => $zone->deliveryArea?->containsPoint((float) $restaurant->latitude, (float) $restaurant->longitude));
            })
            ->pluck('id')
            ->values();
    }

    private function orderBelongsToBranch(Order $order, $branch): bool
    {
        if ((int) $order->branch_id === (int) $branch->id) {
            return true;
        }

        return $this->restaurantIdsForBranchZones($branch)->contains((int) $order->restaurant_id);
    }

    private function restaurantBelongsToBranch(Restaurant $restaurant, $branch): bool
    {
        if ((int) $restaurant->branch_id === (int) $branch->id) {
            return true;
        }

        return $this->restaurantIdsForBranchZones($branch)->contains((int) $restaurant->id);
    }

    private function availableBranchDrivers($branch, Order $order)
    {
        $autoAssignService = app(AutoAssignDriverService::class);

        return User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $driver) => (bool) ($autoAssignService->assignmentEligibility($driver, $order, $order->id)['eligible'] ?? false))
            ->values();
    }

    private function validateRestaurant(Request $request, ?Restaurant $restaurant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('restaurants', 'email')->ignore($restaurant?->id)],
            'phone' => ['required', 'string', 'max:20'],
            'fssai_license_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_radius' => ['required', 'numeric', 'min:0', 'max:100'],
            'restaurant_type' => ['required', Rule::in(Restaurant::validServiceTypes())],
            'is_pure_veg' => ['nullable', 'boolean'],
            'dining_charge' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'commission_calculation_type' => ['required', Rule::in(['global', 'percentage', 'fixed'])],
            'commission_rate' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('commission_calculation_type') !== 'global'),
                'numeric',
                'min:0',
                $request->input('commission_calculation_type') === 'percentage' ? 'max:100' : 'max:1000000',
            ],
            'delivery_time' => ['nullable', 'integer', 'min:1', 'max:240'],
            'order_lead_time' => ['nullable', 'integer', 'min:0', 'max:240'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'cuisine' => ['nullable', 'array'],
            'cuisine.*' => ['string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'owner_name' => [$restaurant ? 'nullable' : 'required', 'string', 'max:255'],
            'owner_email' => [$restaurant ? 'nullable' : 'required', 'email', Rule::unique('users', 'email')->ignore($restaurant?->owner_id)],
            'owner_phone' => [$restaurant ? 'nullable' : 'required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($restaurant?->owner_id)],
            'owner_password' => [$restaurant ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'routing_code' => ['nullable', 'string', 'max:32'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'stripe_account_id' => ['nullable', 'string', 'max:255'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validateDriver(Request $request, ?User $driver = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($driver?->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($driver?->id)],
            'password' => [$driver ? 'nullable' : 'required', 'string', 'min:6'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['required', 'string', 'max:50'],
            'license_number' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_active_orders' => ['nullable', 'integer', 'min:1', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'routing_code' => ['nullable', 'string', 'max:32'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'stripe_account_id' => ['nullable', 'string', 'max:255'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ensureInsideBranchZone($branch, array $data, string $type): void
    {
        $zones = $branch->zones()->with('deliveryArea')->where('is_active', true)->get();
        if ($zones->isEmpty()) {
            throw ValidationException::withMessages([
                'address' => 'Branch has no active delivery zone. Please ask admin to create or activate a branch zone first.',
            ]);
        }

        $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
        $address = Str::lower((string) ($data['address'] ?? ''));

        $matches = $zones->contains(function (BranchZone $zone) use ($data, $type, $latitude, $longitude, $address) {
            if ($zone->deliveryArea) {
                return $latitude !== null
                    && $longitude !== null
                    && $zone->deliveryArea->containsPoint($latitude, $longitude);
            }

            foreach (['state', 'city', 'pincode'] as $field) {
                if ($zone->{$field} && isset($data[$field]) && Str::lower((string) $zone->{$field}) !== Str::lower((string) $data[$field])) {
                    return false;
                }

                if ($zone->{$field} && $type === 'driver' && !Str::contains($address, Str::lower((string) $zone->{$field}))) {
                    return false;
                }
            }

            if ($zone->area && !Str::contains($address, Str::lower((string) $zone->area))) {
                return false;
            }

            if ($latitude !== null && $longitude !== null && is_array($zone->polygon) && count($zone->polygon) >= 3) {
                return $this->pointInPolygon($zone->polygon, $latitude, $longitude);
            }

            return true;
        });

        if (! $matches) {
            throw ValidationException::withMessages([
                'address' => ucfirst($type) . ' location is outside this branch delivery zone.',
            ]);
        }
    }

    private function pointInPolygon(array $polygon, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $current = $polygon[$i];
            $previous = $polygon[$j];
            $currentLat = (float) ($current['lat'] ?? $current['latitude'] ?? 0);
            $currentLng = (float) ($current['lng'] ?? $current['longitude'] ?? 0);
            $previousLat = (float) ($previous['lat'] ?? $previous['latitude'] ?? 0);
            $previousLng = (float) ($previous['lng'] ?? $previous['longitude'] ?? 0);

            if (($currentLat > $latitude) !== ($previousLat > $latitude)
                && $longitude < (($previousLng - $currentLng) * ($latitude - $currentLat)) / (($previousLat - $currentLat) ?: 0.000001) + $currentLng) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function uniqueRestaurantSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $base = $slug;
        $counter = 1;

        while (Restaurant::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function branchDeliveryAreas($branch)
    {
        return $branch->zones()
            ->with('deliveryArea')
            ->where('is_active', true)
            ->get()
            ->pluck('deliveryArea')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'area_type' => $area->area_type,
                'description' => $area->description,
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'radius_km' => $area->radius_km,
                'polygon_coordinates' => $area->polygon_coordinates,
            ]);
    }

    private function weeklyTimingsFromFlatHours(?array $existing, ?string $openTime, ?string $closeTime): array
    {
        $timings = $existing ?: Restaurant::getDefaultWeeklyTimings();

        if (!$openTime && !$closeTime) {
            return $timings;
        }

        foreach ($timings as $day => $timing) {
            $timings[$day] = array_merge(Restaurant::getDefaultDayTiming(), $timing, [
                'open_time' => $openTime ?: ($timing['open_time'] ?? '09:00'),
                'close_time' => $closeTime ?: ($timing['close_time'] ?? '22:00'),
            ]);
        }

        return $timings;
    }

    private function storeRestaurantImages(Request $request, Restaurant $restaurant, bool $replace = false): void
    {
        if ($request->hasFile('logo')) {
            if ($replace && $restaurant->logo_image) {
                Storage::disk('public')->delete($restaurant->logo_image);
            }
            $restaurant->update(['logo_image' => $request->file('logo')->store('restaurants/logos', 'public')]);
        }

        if ($request->hasFile('banner')) {
            if ($replace && $restaurant->banner_image) {
                Storage::disk('public')->delete($restaurant->banner_image);
            }
            $restaurant->update(['banner_image' => $request->file('banner')->store('restaurants/banners', 'public')]);
        }
    }

    private function payoutProviderAccountAttributes(Request $request): array
    {
        $provider = AppSetting::getValue('payout_gateway_provider', 'razorpay');
        $gatewayAccountId = $request->gateway_account_id;

        return [
            'gateway_account_id' => $gatewayAccountId,
            'mollie_organization_id' => $provider === 'mollie' ? $gatewayAccountId : null,
            'mercadopago_collector_id' => $provider === 'mercadopago' ? $gatewayAccountId : null,
        ];
    }
}
