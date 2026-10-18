<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Rules\UniqueUserContactForRole;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use App\Services\CodReconciliationService;
use App\Services\DeliveryAreaResolver;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DriverController extends Controller
{
    public function __construct(
        private readonly DeliveryAreaResolver $deliveryAreaResolver,
        private readonly BranchManagementService $branches
    ) {}

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

    /**
     * Maps the same document fields collected during driver-app partner
     * application onboarding (see Api\PartnerApplicationController) onto
     * the admin create/edit forms, so admin can upload or replace them too.
     * Only returns keys for files actually present in this request -- an
     * update() call with no new file chosen must leave the existing path
     * untouched, not null it out.
     */
    private function driverDocumentUploads(Request $request): array
    {
        $fields = [
            'profile_photo' => ['profile_photo_path', 'driver_documents/profile_photos'],
            'vehicle_image' => ['vehicle_image', 'driver_documents/vehicle_images'],
            'license_document' => ['license_document', 'driver_documents/licenses'],
            'aadhar_card' => ['aadhar_card', 'driver_documents/aadhar'],
            'pan_card' => ['pan_card', 'driver_documents/pan'],
            'vehicle_rc' => ['vehicle_rc', 'driver_documents/vehicle_rc'],
            'insurance_document' => ['insurance_document', 'driver_documents/insurance'],
        ];

        $uploads = [];
        foreach ($fields as $input => [$column, $directory]) {
            if ($request->hasFile($input)) {
                $uploads[$column] = MediaStorage::store($request->file($input), $directory);
            }
        }

        return $uploads;
    }

    public function index(Request $request)
    {
        $query = User::role('delivery_partner');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
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
        $branches = Branch::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.drivers.create', compact('globalMaxActiveOrders', 'deliveryAreas', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', UniqueUserContactForRole::email('delivery_partner')],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone('delivery_partner')],
            'password' => 'required|string|min:6',
            'vehicle_type' => 'required|string',
            'vehicle_number' => 'required|string',
            'vehicle_model' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|in:petrol,electric,manual',
            'license_number' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other',
            'city' => 'nullable|string|max:120',
            'landmark' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'vehicle_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'aadhar_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pan_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'vehicle_rc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'insurance_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'background_location_enabled' => 'nullable|boolean',
            'notification_permission_enabled' => 'nullable|boolean',
            'earning_mode' => 'nullable|in:commission,salary',
            'monthly_salary' => 'nullable|numeric|min:0|required_if:earning_mode,salary',
            'salary_effective_from' => 'nullable|date',
            'branch_id' => 'nullable|exists:branches,id',
            'salary_payer' => 'nullable|in:admin,branch',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'delivery_area_id' => 'nullable|exists:delivery_areas,id',
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
            'vehicle_model' => $request->vehicle_model,
            'fuel_type' => $request->fuel_type,
            'license_number' => $request->license_number,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'earning_mode' => $request->earning_mode ?: 'commission',
            'monthly_salary' => $request->monthly_salary,
            'salary_effective_from' => $request->salary_effective_from,
            'salary_payer' => $request->salary_payer,
            'address' => $request->address,
            'city' => $request->city,
            'landmark' => $request->landmark,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            // A manually picked zone always wins -- only fall back to
            // auto-matching by coordinates when the admin left it blank.
            'delivery_area_id' => $request->delivery_area_id
                ?: $this->resolveDeliveryAreaId($request->latitude, $request->longitude),
            'max_active_orders' => $request->max_active_orders,
            'background_location_enabled' => $request->boolean('background_location_enabled'),
            'notification_permission_enabled' => $request->boolean('notification_permission_enabled'),
            'account_holder_name' => $request->account_holder_name,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
            'routing_code' => $request->routing_code ?: $request->ifsc_code,
            'upi_id' => $request->upi_id,
            'stripe_account_id' => $request->stripe_account_id,
        ], $this->payoutProviderAccountAttributes($request), $this->driverDocumentUploads($request)));

        $user->assignRole(Role::findOrCreate('delivery_partner', 'web'));

        if ($request->branch_id) {
            $branch = Branch::find($request->branch_id);
            if ($branch) {
                $this->branches->assignDriver($user, $branch, $request->user());
            }
        }

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

        $pendingCodQuery = app(CodReconciliationService::class)
            ->pendingOrdersQuery(null, $driver->id);
        $pendingCodOrders = (clone $pendingCodQuery)
            ->latest('cash_collected_at')
            ->get(['id', 'order_number', 'cash_collected_amount', 'cash_collected_at', 'total', 'cod_collected_from_driver_amount']);
        $pendingCodAmount = round((float) (clone $pendingCodQuery)
            ->selectRaw('COALESCE(SUM(' . CodReconciliationService::CASH_IN_HAND_EXPR . '), 0) as amount')
            ->value('amount'), 2);

        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        return view('admin.drivers.show', compact(
            'driver',
            'wallet',
            'orders',
            'walletTransactions',
            'globalMaxActiveOrders',
            'pendingCodOrders',
            'pendingCodAmount'
        ));
    }

    public function edit($id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);
        $deliveryAreas = DeliveryArea::query()->active()->orderBy('name')->get();
        $branches = Branch::query()->where('status', 'active')->orderBy('name')->get();

        return view('admin.drivers.edit', compact('driver', 'globalMaxActiveOrders', 'deliveryAreas', 'branches'));
    }

    public function update(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', UniqueUserContactForRole::email('delivery_partner', $driver->id)],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone('delivery_partner', $driver->id)],
            'vehicle_type' => 'required|string',
            'vehicle_number' => 'required|string',
            'vehicle_model' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|in:petrol,electric,manual',
            'license_number' => 'required|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other',
            'city' => 'nullable|string|max:120',
            'landmark' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'vehicle_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'aadhar_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pan_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'vehicle_rc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'insurance_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'background_location_enabled' => 'nullable|boolean',
            'notification_permission_enabled' => 'nullable|boolean',
            'earning_mode' => 'required|in:commission,salary',
            'monthly_salary' => 'nullable|numeric|min:0|required_if:earning_mode,salary',
            'salary_effective_from' => 'nullable|date',
            'branch_id' => 'nullable|exists:branches,id',
            'salary_payer' => 'nullable|in:admin,branch',
            'pan' => 'nullable|string|max:15',
            'tax_deductee_type' => 'nullable|in:individual,company',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'delivery_area_id' => 'nullable|exists:delivery_areas,id',
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
        // A manually picked zone always wins -- only fall back to
        // auto-matching by coordinates when the admin left it blank.
        $validated['delivery_area_id'] = $request->delivery_area_id
            ?: $this->resolveDeliveryAreaId($request->latitude, $request->longitude);
        $validated['background_location_enabled'] = $request->boolean('background_location_enabled');
        $validated['notification_permission_enabled'] = $request->boolean('notification_permission_enabled');

        $newBranchId = $validated['branch_id'] ?? null;
        unset($validated['branch_id']); // applied via BranchManagementService below, not mass-assignment

        // The file inputs are validated above for error messages, but their
        // raw UploadedFile values can't go into $driver->update() directly --
        // swap them out for resolved storage paths (only when a new file was
        // actually chosen, so leaving a field blank keeps the existing file).
        foreach (['profile_photo', 'vehicle_image', 'license_document', 'aadhar_card', 'pan_card', 'vehicle_rc', 'insurance_document'] as $fileField) {
            unset($validated[$fileField]);
        }

        $validated = array_merge(
            $validated,
            $this->payoutProviderAccountAttributes($request),
            $this->driverDocumentUploads($request)
        );
        $driver->update($validated);

        if ($newBranchId != $driver->branch_id) {
            if ($newBranchId) {
                $branch = Branch::find($newBranchId);
                if ($branch) {
                    $this->branches->assignDriver($driver, $branch, $request->user());
                }
            } else {
                $this->branches->unassignDriver($driver, $request->user());
            }
        }

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
        $driver->update(['is_active' => ! $driver->is_active]);

        return redirect()->route('admin.drivers.index')->with('success', 'Driver status updated!');
    }

    /**
     * Admin-only suspension, distinct from the plain `is_active` toggle
     * above (which a branch owner can also flip for their own drivers) —
     * this one locks the driver out of login entirely, records a reason,
     * and can only be lifted by an admin action or the auto-expiry
     * scheduler for a time-limited suspension.
     */
    public function suspend(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);

        $validated = $request->validate([
            'suspension_reason' => 'nullable|string|max:1000',
            'suspension_type' => 'required|in:limited,permanent',
            'suspended_until' => 'required_if:suspension_type,limited|nullable|date|after:now',
        ]);

        $until = $validated['suspension_type'] === 'limited'
            ? \Carbon\Carbon::parse($validated['suspended_until'])
            : null;

        $driver->suspend($validated['suspension_reason'] ?? null, $until, $request->user()->id);

        return redirect()->back()->with('success', "Driver {$driver->name} has been suspended.");
    }

    public function reactivate(Request $request, $id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);
        $driver->liftSuspension();

        return redirect()->back()->with('success', "Driver {$driver->name} has been reactivated.");
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
            'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
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

    public function collectCash($id)
    {
        $driver = User::role('delivery_partner')->findOrFail($id);

        $settled = app(CodReconciliationService::class)
            ->settleForDriver($driver, auth()->user());

        $ordersCount = (int) ($settled['orders'] ?? 0);
        $amount = (float) ($settled['amount'] ?? 0);

        if ($ordersCount === 0 && $amount <= 0) {
            return redirect()->route('admin.drivers.show', $driver->id)
                ->with('info', 'There is no unsettled COD cash for this driver.');
        }

        return redirect()->route('admin.drivers.show', $driver->id)->with(
            'success',
            'Collected ' . AppSetting::sanitizedCurrencySymbol()
                . number_format($amount, AppSetting::currencyDecimals())
                . ' from ' . $ordersCount . ' COD order(s).'
        );
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

        $currencySymbol = AppSetting::sanitizedCurrencySymbol();

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
