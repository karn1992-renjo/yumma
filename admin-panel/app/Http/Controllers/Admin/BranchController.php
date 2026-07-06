<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchAuditLog;
use App\Models\BranchPayout;
use App\Models\BranchSettlement;
use App\Models\BranchTicket;
use App\Models\BranchUser;
use App\Models\BranchWalletTransaction;
use App\Models\BranchZone;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\BranchManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function __construct(private BranchManagementService $branches)
    {
    }

    public function index(Request $request)
    {
        $query = Branch::query()->with(['owner', 'wallet'])->withCount(['restaurants', 'orders', 'zones']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $branches = $query->latest()->paginate(20)->withQueryString();
        $stats = [
            'total' => Branch::count(),
            'active' => Branch::where('status', 'active')->count(),
            'restaurants' => Restaurant::whereNotNull('branch_id')->count(),
            'wallet_balance' => DB::table('branch_wallets')->sum('balance'),
        ];

        return view('admin.branches.index', compact('branches', 'stats'));
    }

    public function create()
    {
        return view('admin.branches.create', $this->branchZoneFormData());
    }

    public function store(Request $request)
    {
        $conflict = $this->existingBranchUserConflict($request);

        if ($conflict) {
            return back()
                ->withErrors([$conflict['field'] => $conflict['message']])
                ->withInput();
        }

        $data = $this->validatedBranch($request);
        $data['platform_commission_percent'] = 0;
        $data['admin_share_percent'] = 100 - (float) $data['branch_share_percent'];
        $deliveryAreaIds = $data['delivery_area_ids'];
        unset($data['delivery_area_ids']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('branches', 'public');
        }

        $branch = $this->branches->createBranch($data, $request->user());
        $this->syncBranchDeliveryAreas($branch, $deliveryAreaIds, $request->user());

        if ($request->filled('manager_email')) {
            $manager = User::firstOrCreate(
                ['email' => $request->manager_email],
                [
                    'name' => $request->manager_name ?: $branch->name . ' Manager',
                    'phone' => $request->manager_phone,
                    'password' => Hash::make($request->manager_password ?: str()->random(16)),
                    'is_active' => true,
                    'branch_id' => $branch->id,
                ]
            );
            $manager->assignRole(BranchManagementService::MANAGER_ROLE);
            BranchUser::updateOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $manager->id],
                ['role' => BranchManagementService::MANAGER_ROLE, 'permissions' => $this->branches->permissionsFor(BranchManagementService::MANAGER_ROLE), 'is_active' => true]
            );
        }

        return redirect()->route('admin.branches.show', $branch)->with('success', 'Branch created with owner login, wallet, and permissions.');
    }

    public function show(Branch $branch)
    {
        $branch->load(['owner', 'wallet', 'zones']);
        $orders = $branch->orders()->latest()->limit(10)->get();
        $analytics = $this->analytics($branch);

        return view('admin.branches.show', compact('branch', 'orders', 'analytics'));
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.edit', array_merge(
            ['branch' => $branch],
            $this->branchZoneFormData($branch)
        ));
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validatedBranch($request, $branch);
        $data['platform_commission_percent'] = 0;
        $data['admin_share_percent'] = 100 - (float) $data['branch_share_percent'];
        $deliveryAreaIds = $data['delivery_area_ids'] ?? [];
        unset($data['delivery_area_ids']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('branches', 'public');
        }

        $old = $branch->toArray();
        $branch->update($data);
        $this->branches->audit($branch, $request->user(), 'branch.updated', $branch, $old, $branch->fresh()->toArray());
        $this->syncBranchDeliveryAreas($branch, $deliveryAreaIds, $request->user());

        return redirect()->route('admin.branches.show', $branch)->with('success', 'Branch updated.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        $blocking = array_filter($this->branches->canDeactivate($branch));

        if ($blocking) {
            return back()->with('error', 'Branch cannot be deleted while active orders, settlements, refunds, or tickets are pending.');
        }

        DB::transaction(function () use ($branch, $request) {
            $old = $branch->toArray();

            Restaurant::where('branch_id', $branch->id)->update(['branch_id' => null]);
            User::where('branch_id', $branch->id)->update(['branch_id' => null]);
            Order::where('branch_id', $branch->id)->update(['branch_id' => null]);

            if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'branch_id')) {
                DB::table('addresses')->where('branch_id', $branch->id)->update(['branch_id' => null]);
            }

            if (Schema::hasTable('branch_transfer_history')) {
                DB::table('branch_transfer_history')
                    ->where('from_branch_id', $branch->id)
                    ->update(['from_branch_id' => null]);
            }

            $this->branches->audit(null, $request->user(), 'branch.deleted', $branch, $old, ['deleted_from_database' => true]);
            $branch->forceDelete();
        });

        return redirect()->route('admin.branches.index')->with('success', 'Branch deleted from database.');
    }

    public function users(Request $request)
    {
        $branchId = $request->integer('branch_id');
        $users = BranchUser::with(['branch', 'user.roles'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $branches = Branch::orderBy('name')->get();
        $permissionCatalog = $this->branchPermissionCatalog();

        return view('admin.branches.users', compact('users', 'branches', 'permissionCatalog'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in([BranchManagementService::OWNER_ROLE, BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys($this->branchPermissionCatalog()))],
        ]);

        $this->branches->ensureBranchRoles();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password'] ?: str()->random(16)),
            'branch_id' => $data['branch_id'],
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);

        BranchUser::create([
            'branch_id' => $data['branch_id'],
            'user_id' => $user->id,
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => true,
        ]);

        return back()->with('success', 'Branch user created.');
    }

    public function updateUser(Request $request, BranchUser $membership)
    {
        $this->branches->ensureBranchRoles();

        $data = $request->validate([
            'role' => ['required', Rule::in([BranchManagementService::OWNER_ROLE, BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys($this->branchPermissionCatalog()))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $membership->update([
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if ($membership->user) {
            $membership->user->syncRoles([$data['role']]);
            $membership->user->forceFill([
                'branch_id' => $membership->branch_id,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ])->save();
        }

        return back()->with('success', 'Branch user permissions updated.');
    }

    public function wallets(Request $request)
    {
        $branches = Branch::with('wallet')->orderBy('name')->paginate(20);
        $transactions = BranchWalletTransaction::with(['branch', 'order'])->latest()->limit(40)->get();

        return view('admin.branches.wallets', compact('branches', 'transactions'));
    }

    public function settlements(Request $request)
    {
        $settlements = BranchSettlement::with('branch')->latest()->paginate(20);
        $branches = Branch::where('status', 'active')->orderBy('name')->get();

        return view('admin.branches.settlements', compact('settlements', 'branches'));
    }

    public function generateSettlement(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $settlement = $this->branches->generateSettlement(Branch::findOrFail($data['branch_id']), $data['period_start'], $data['period_end'], $request->user());

        return back()->with('success', 'Settlement generated: ' . $settlement->settlement_number);
    }

    public function approveSettlement(Request $request, BranchSettlement $settlement)
    {
        $this->branches->approveSettlement($settlement, $request->user());

        return back()->with('success', 'Settlement approved and payout created.');
    }

    public function payouts()
    {
        $payouts = BranchPayout::with(['branch', 'settlement'])->latest()->paginate(20);

        return view('admin.branches.payouts', compact('payouts'));
    }

    public function markPayoutPaid(Request $request, BranchPayout $payout)
    {
        $data = $request->validate(['transaction_reference' => ['nullable', 'string', 'max:255']]);
        $this->branches->markPayoutPaid($payout, $request->user(), $data['transaction_reference'] ?? null);

        return back()->with('success', 'Payout marked paid and branch wallet debited.');
    }

    public function zones()
    {
        $zones = BranchZone::with(['branch', 'deliveryArea'])->latest()->paginate(25);
        $branches = Branch::where('status', 'active')->orderBy('name')->get();
        $deliveryAreas = DeliveryArea::query()
            ->orderBy('name')
            ->get();
        $assignedDeliveryAreas = BranchZone::with('branch')
            ->whereNotNull('delivery_area_id')
            ->get()
            ->keyBy('delivery_area_id');

        return view('admin.branches.zones', compact('zones', 'branches', 'deliveryAreas', 'assignedDeliveryAreas'));
    }

    public function storeZone(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'delivery_area_ids' => ['required', 'array', 'min:1'],
            'delivery_area_ids.*' => ['integer', 'exists:delivery_areas,id'],
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        $deliveryAreas = DeliveryArea::whereIn('id', $data['delivery_area_ids'])->get();

        DB::transaction(function () use ($branch, $deliveryAreas, $request) {
            foreach ($deliveryAreas as $deliveryArea) {
                $zone = BranchZone::firstOrNew(['delivery_area_id' => $deliveryArea->id]);
                $oldValues = $zone->exists ? $zone->toArray() : null;

                $zone->fill([
                    'branch_id' => $branch->id,
                    'name' => $deliveryArea->name,
                    'country' => null,
                    'state' => null,
                    'city' => null,
                    'area' => $deliveryArea->description,
                    'pincode' => null,
                    'polygon' => $deliveryArea->area_type === 'polygon' ? $deliveryArea->polygon_coordinates : null,
                    'is_active' => $deliveryArea->is_active,
                ]);
                $zone->save();

                $action = $oldValues && (int) ($oldValues['branch_id'] ?? 0) !== (int) $branch->id
                    ? 'delivery_zone.reassigned'
                    : 'delivery_zone.assigned';

                $this->branches->audit($branch, $request->user(), $action, $zone, $oldValues, $zone->toArray());
            }
        });

        return back()->with('success', 'Delivery zone assignment updated for branch. Already assigned zones were moved to the selected branch.');
    }

    public function reports(Request $request)
    {
        $branches = Branch::orderBy('name')->get();
        $branch = $request->filled('branch_id') ? Branch::find($request->branch_id) : null;
        $analytics = $branch ? $this->analytics($branch) : null;
        $global = [
            'orders' => Order::whereNotNull('branch_id')->count(),
            'revenue' => Order::whereNotNull('branch_id')->where('status', 'delivered')->sum('total'),
            'branch_commission' => Order::whereNotNull('branch_id')->sum('branch_commission'),
            'admin_commission' => Order::whereNotNull('branch_id')->sum('admin_commission'),
        ];

        return view('admin.branches.reports', compact('branches', 'branch', 'analytics', 'global'));
    }

    public function auditLogs()
    {
        $logs = BranchAuditLog::with(['branch', 'user'])->latest()->paginate(30);

        return view('admin.branches.audit-logs', compact('logs'));
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['restaurant', 'driver', 'zone'])],
            'id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'exists:branches,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->branches->transferEntity($data['type'], (int) $data['id'], Branch::findOrFail($data['to_branch_id']), $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Branch transfer completed. Historical orders remain unchanged.');
    }

    public function tickets()
    {
        $tickets = BranchTicket::with(['branch', 'user'])->latest()->paginate(25);
        $branches = Branch::where('status', 'active')->orderBy('name')->get();

        return view('admin.branches.tickets', compact('tickets', 'branches'));
    }

    public function storeTicket(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'category' => ['required', Rule::in(['Settlement Issue', 'Payment Issue', 'Restaurant Issue', 'Driver Issue', 'Technical Issue'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'string', 'max:30'],
        ]);

        BranchTicket::create($data + [
            'user_id' => $request->user()->id,
            'ticket_number' => 'BTK' . now()->format('YmdHis') . random_int(100, 999),
            'status' => 'open',
        ]);

        return back()->with('success', 'Branch support ticket created.');
    }

    private function validatedBranch(Request $request, ?Branch $branch = null): array
    {
        $deliveryAreaRules = $branch
            ? ['nullable', 'array']
            : ['required', 'array', 'min:1'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('branches', 'owner_email')->ignore($branch)],
            'owner_phone' => ['required', 'string', 'max:30', Rule::unique('branches', 'owner_phone')->ignore($branch)],
            'owner_password' => [$branch ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'country' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'trade_license' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'branch_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'settlement_cycle' => ['required', Rule::in(['daily', 'weekly', 'biweekly', 'monthly'])],
            'bank_details.account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_details.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_details.account_number' => ['nullable', 'string', 'max:100'],
            'bank_details.ifsc_code' => ['nullable', 'string', 'max:50'],
            'bank_details.upi_id' => ['nullable', 'string', 'max:100'],
            'delivery_area_ids' => $deliveryAreaRules,
            'delivery_area_ids.*' => ['integer', 'exists:delivery_areas,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function branchZoneFormData(?Branch $branch = null): array
    {
        $deliveryAreas = DeliveryArea::query()
            ->orderBy('name')
            ->get();
        $assignedDeliveryAreas = BranchZone::with('branch')
            ->whereNotNull('delivery_area_id')
            ->get()
            ->keyBy('delivery_area_id');
        $selectedDeliveryAreaIds = $branch
            ? $branch->zones()->whereNotNull('delivery_area_id')->pluck('delivery_area_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return compact('deliveryAreas', 'assignedDeliveryAreas', 'selectedDeliveryAreaIds');
    }

    private function syncBranchDeliveryAreas(Branch $branch, array $deliveryAreaIds, ?User $actor): void
    {
        $deliveryAreaIds = collect($deliveryAreaIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($branch, $deliveryAreaIds, $actor) {
            $removedZones = BranchZone::query()
                ->where('branch_id', $branch->id)
                ->whereNotNull('delivery_area_id')
                ->when($deliveryAreaIds, fn ($query) => $query->whereNotIn('delivery_area_id', $deliveryAreaIds))
                ->get();

            foreach ($removedZones as $zone) {
                $oldValues = $zone->toArray();
                $zone->delete();
                $this->branches->audit($branch, $actor, 'delivery_zone.unassigned', $zone, $oldValues, null);
            }

            $deliveryAreas = DeliveryArea::whereIn('id', $deliveryAreaIds)->get();

            foreach ($deliveryAreas as $deliveryArea) {
                $zone = BranchZone::firstOrNew(['delivery_area_id' => $deliveryArea->id]);
                $oldValues = $zone->exists ? $zone->toArray() : null;

                $zone->fill([
                    'branch_id' => $branch->id,
                    'name' => $deliveryArea->name,
                    'country' => null,
                    'state' => null,
                    'city' => null,
                    'area' => $deliveryArea->description,
                    'pincode' => null,
                    'polygon' => $deliveryArea->area_type === 'polygon' ? $deliveryArea->polygon_coordinates : null,
                    'is_active' => $deliveryArea->is_active,
                ]);
                $zone->save();

                $action = $oldValues && (int) ($oldValues['branch_id'] ?? 0) !== (int) $branch->id
                    ? 'delivery_zone.reassigned'
                    : 'delivery_zone.assigned';

                $this->branches->audit($branch, $actor, $action, $zone, $oldValues, $zone->toArray());
            }
        });
    }

    private function existingBranchUserConflict(Request $request): ?array
    {
        $checks = [
            'owner_email' => ['column' => 'email', 'label' => 'owner email'],
            'owner_phone' => ['column' => 'phone', 'label' => 'owner phone number'],
            'manager_email' => ['column' => 'email', 'label' => 'manager email'],
            'manager_phone' => ['column' => 'phone', 'label' => 'manager phone number'],
        ];

        foreach ($checks as $field => $config) {
            $value = trim((string) $request->input($field));

            if ($value === '') {
                continue;
            }

            $user = User::where($config['column'], $value)->with('roles')->first();

            if (! $user) {
                continue;
            }

            $role = $user->roles->pluck('name')->map(fn ($name) => str_replace('_', ' ', $name))->join(', ') ?: 'no role';

            return [
                'field' => $field,
                'message' => ucfirst($config['label']) . " is already used by {$user->name} ({$role}). Delete that user role/account first, then create the branch.",
            ];
        }

        return null;
    }

    private function analytics(Branch $branch): array
    {
        $orders = $branch->orders();

        return [
            'total_orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'delivered')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'refunded_orders' => (clone $orders)->where('payment_status', 'refunded')->count(),
            'revenue' => (clone $orders)->where('status', 'delivered')->sum('total'),
            'commission_earned' => (clone $orders)->sum('branch_commission'),
            'wallet_balance' => (float) ($branch->wallet?->balance ?? 0),
            'settlement_due' => (float) ($branch->wallet?->balance ?? 0),
            'driver_count' => User::role('delivery_partner')->where('branch_id', $branch->id)->count(),
            'restaurant_count' => $branch->restaurants()->count(),
            'average_delivery_time' => (clone $orders)->whereNotNull('delivered_at')->avg(DB::raw('TIMESTAMPDIFF(MINUTE, created_at, delivered_at)')),
            'top_restaurants' => Restaurant::where('branch_id', $branch->id)->withCount('orders')->orderByDesc('orders_count')->limit(5)->get(),
            'top_drivers' => User::role('delivery_partner')->where('branch_id', $branch->id)->withCount('orders')->orderByDesc('orders_count')->limit(5)->get(),
            'peak_hours' => (clone $orders)->selectRaw('HOUR(created_at) as hour, COUNT(*) as orders_count')->groupBy('hour')->orderByDesc('orders_count')->limit(5)->get(),
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
}
