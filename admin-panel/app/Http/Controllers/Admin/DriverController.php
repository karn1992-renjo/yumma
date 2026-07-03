<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\User;
use App\Models\Order;
use App\Models\DriverGig;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AutoAssignDriverService;
use App\Services\DeliveryAreaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DriverController extends Controller
{
    public function __construct(
        private readonly DeliveryAreaResolver $deliveryAreaResolver
    ) {
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

    public function index(Request $request)
    {
        $query = User::role('delivery_partner');
        
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }
        
        $drivers = $query->latest()->paginate(20);
        
        $autoAssignService = app(AutoAssignDriverService::class);
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        foreach ($drivers as $driver) {
            $driver->total_orders = Order::where('driver_id', $driver->id)->count();
            $driver->total_earnings = Order::where('driver_id', $driver->id)
                ->where('status', 'delivered')
                ->sum('delivery_fee');
            $driver->active_orders_count = $autoAssignService->activeOrderCountForDriver($driver);
            $driver->effective_max_active_orders = $autoAssignService->maxActiveOrdersForDriver($driver);
        }
        
        return view('admin.drivers.index', compact('drivers', 'globalMaxActiveOrders'));
    }
    
    public function create()
    {
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);
        $deliveryAreas = DeliveryArea::query()->active()->orderBy('name')->get();

        return view('admin.drivers.create', compact('globalMaxActiveOrders', 'deliveryAreas'));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6',
            'vehicle_type' => 'required|string',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_active_orders' => 'nullable|integer|min:1|max:50',
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'ifsc_code' => 'nullable|string|max:32',
            'routing_code' => 'nullable|string|max:32',
            'upi_id' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
            'gateway_account_id' => 'nullable|string|max:255',
        ]);
        
        $user = User::create(array_merge([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_active' => true,
            'vehicle_type' => $request->vehicle_type,
            'vehicle_number' => $request->vehicle_number,
            'license_number' => $request->license_number,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'delivery_area_id' => $this->resolveDeliveryAreaId($request->latitude, $request->longitude),
            'max_active_orders' => $request->max_active_orders,
            'account_holder_name' => $request->account_holder_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
            'routing_code' => $request->routing_code ?: $request->ifsc_code,
            'upi_id' => $request->upi_id,
            'stripe_account_id' => $request->stripe_account_id,
        ], $this->payoutProviderAccountAttributes($request)));
        
        $user->assignRole(Role::findOrCreate('delivery_partner', 'web'));
        
        return redirect()->route('admin.drivers.index')->with('success', 'Driver added successfully!');
    }
    
    public function show($id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        
        $driver->total_orders = Order::where('driver_id', $driver->id)->count();
        $driver->total_earnings = Order::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->sum('delivery_fee');
        
        $autoAssignService = app(AutoAssignDriverService::class);
        $driver->active_orders_count = $autoAssignService->activeOrderCountForDriver($driver);
        $driver->effective_max_active_orders = $autoAssignService->maxActiveOrdersForDriver($driver);
        
        // Load wallet information
        $wallet = $driver->wallet()->first() ?? new Wallet(['balance' => 0, 'locked_balance' => 0]);
        
        // Load recent orders with restaurant details
        $orders = Order::with('restaurant')->where('driver_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
        
        // Load wallet transactions
        $walletTransactions = WalletTransaction::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
        
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);
        
        return view('admin.drivers.show', compact('driver', 'wallet', 'orders', 'walletTransactions', 'globalMaxActiveOrders'));
    }
    
    public function edit($id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);
        $deliveryAreas = DeliveryArea::query()->active()->orderBy('name')->get();

        return view('admin.drivers.edit', compact('driver', 'globalMaxActiveOrders', 'deliveryAreas'));
    }
    
    public function update(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $driver->id,
            'phone' => 'required|string|max:20|unique:users,phone,' . $driver->id,
            'vehicle_type' => 'required|string',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_active_orders' => 'nullable|integer|min:1|max:50',
            'is_active' => 'boolean',
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:64',
            'ifsc_code' => 'nullable|string|max:32',
            'routing_code' => 'nullable|string|max:32',
            'upi_id' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
            'gateway_account_id' => 'nullable|string|max:255',
        ]);
        
        if ($request->password) {
            $validated['password'] = Hash::make($request->password);
        }

        $validated['ifsc_code'] = $request->routing_code ?: $request->ifsc_code;
        $validated['routing_code'] = $request->routing_code ?: $request->ifsc_code;
        $validated['delivery_area_id'] = $this->resolveDeliveryAreaId(
            $request->latitude,
            $request->longitude
        );

        $validated = array_merge($validated, $this->payoutProviderAccountAttributes($request));
        $driver->update($validated);
        
        return redirect()->route('admin.drivers.index')->with('success', 'Driver updated successfully!');
    }
    
    public function destroy($id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        $driver->delete();
        
        return redirect()->route('admin.drivers.index')->with('success', 'Driver deleted successfully!');
    }

    public function toggleStatus(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        $driver->update(['is_active' => !$driver->is_active]);
        
        return redirect()->route('admin.drivers.index')->with('success', 'Driver status updated!');
    }

    public function topupWallet(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);
        
        $wallet = $driver->wallet()->firstOrCreate(['user_id' => $driver->id], [
            'balance' => 0,
            'locked_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        
        // Update wallet balance
        $newBalance = $wallet->balance + $validated['amount'];
        $wallet->update(['balance' => $newBalance]);
        
        // Create transaction record
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $driver->id,
            'type' => 'topup',
            'amount' => $validated['amount'],
            'balance_after' => $newBalance,
            'reference_type' => 'admin_topup',
            'reference_id' => auth()->id(),
            'description' => $validated['description'] ?? 'Admin wallet top-up',
            'created_by' => auth()->id(),
        ]);
        
        return redirect()->route('admin.drivers.show', $driver->id)->with('success', 'Wallet topped up successfully!');
    }

    public function walletTransactions(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        
        $query = WalletTransaction::where('user_id', $driver->id);
        
        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }
        
        // Filter by date range
        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        // Filter by amount range
        if ($request->min_amount) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->max_amount) {
            $query->where('amount', '<=', $request->max_amount);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')->paginate(15);
        $wallet = $driver->wallet()->first() ?? new Wallet(['balance' => 0, 'locked_balance' => 0]);

        $summary = (clone $query)
            ->selectRaw("SUM(CASE WHEN type IN ('credit','refund','topup') THEN amount ELSE 0 END) as total_credit")
            ->selectRaw("SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as total_debit")
            ->first();

        $totalCredit = $summary->total_credit ?? 0;
        $totalDebit = $summary->total_debit ?? 0;
        $netChange = $totalCredit - $totalDebit;
        $creditDebitRatio = $totalDebit > 0 ? round($totalCredit / $totalDebit, 2) : null;

        return view('admin.drivers.wallet-transactions', compact('driver', 'transactions', 'wallet', 'totalCredit', 'totalDebit', 'netChange', 'creditDebitRatio'));
    }

    public function ordersHistory(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        
        $query = Order::where('driver_id', $driver->id);
        
        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range
        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        // Filter by delivery fee range
        if ($request->min_fee) {
            $query->where('delivery_fee', '>=', $request->min_fee);
        }
        if ($request->max_fee) {
            $query->where('delivery_fee', '<=', $request->max_fee);
        }
        
        $orders = $query->with('restaurant')->orderBy('created_at', 'desc')->paginate(15);
        
        $stats = [
            'total_orders' => Order::where('driver_id', $driver->id)->count(),
            'total_earnings' => Order::where('driver_id', $driver->id)
                ->where('status', 'delivered')
                ->sum('delivery_fee'),
            'cancelled_orders' => Order::where('driver_id', $driver->id)
                ->where('status', 'cancelled')
                ->count(),
        ];
        
        return view('admin.drivers.orders-history', compact('driver', 'orders', 'stats'));
    }

    public function orderDetails(Request $request, $driverId, $orderId)
    {
        $driver = User::role('delivery_partner')->findOrFail($driverId);
        $order = Order::with('restaurant')->where('id', $orderId)
            ->where('driver_id', $driver->id)
            ->firstOrFail();
        
        $currencySymbol = AppSetting::getValue('currency_symbol', '?');
        
        return view('admin.drivers.order-details', compact('driver', 'order', 'currencySymbol'));
    }

    private function resolveDeliveryAreaId($latitude, $longitude): ?int
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        $area = $this->deliveryAreaResolver->resolve((float) $latitude, (float) $longitude);

        return $area?->id;
    }
}
