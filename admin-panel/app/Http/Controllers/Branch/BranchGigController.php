<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchAuditLog;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\GigPayoutApproval;
use App\Models\User;
use App\Services\BranchManagementService;
use App\Services\GigPayoutApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The branch-panel counterpart of Admin\GigController — lets a branch create
 * and manage its OWN gig/shift slots for its OWN drivers, instead of relying
 * on platform admin. A branch-created gig is invisible to other branches'
 * drivers unless explicitly opened to the general pool (open_to_pool); an
 * admin-created platform-wide gig (branch_id null) is untouched by any of
 * this and keeps working exactly as it does today.
 */
class BranchGigController extends Controller
{
    public function __construct(private readonly BranchManagementService $branches)
    {
    }

    public function index(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.view');

        $validated = $request->validate(['date' => 'nullable|date']);
        $selectedDate = !empty($validated['date']) ? Carbon::parse($validated['date'])->format('Y-m-d') : null;

        $availableGigs = $this->gigStatusQuery($branch, 'available', $selectedDate)->get();
        $bookedGigs = $this->gigStatusQuery($branch, 'booked', $selectedDate)->get();
        $completedGigs = $this->gigStatusQuery($branch, 'completed', $selectedDate)->limit(50)->get();
        $cancelledGigs = $this->gigStatusQuery($branch, 'cancelled', $selectedDate)->limit(30)->get();
        $stats = $this->gigStats($branch);
        $capabilities = $this->branchGigCapabilities($request);
        $googleMapsApiKey = \App\Models\AppSetting::getValue('google_maps_api_key', \App\Models\AppSetting::getValue('google_maps_key', ''));

        return view('branch.gigs.index', compact(
            'branch', 'availableGigs', 'bookedGigs', 'completedGigs', 'cancelledGigs', 'stats', 'selectedDate', 'capabilities', 'googleMapsApiKey'
        ));
    }

    public function create(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.create');

        $deliveryAreas = DeliveryArea::whereIn('id', $branch->deliveryAreaIds())->where('is_active', true)->orderBy('name')->get();
        if ($deliveryAreas->isEmpty()) {
            return redirect()->route('branch.gigs.index')
                ->with('error', 'Your branch has no active delivery zone yet. Ask admin to create or activate one first.');
        }

        return view('branch.gigs.create', compact('branch', 'deliveryAreas'));
    }

    public function store(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.create');

        $validated = $this->validateGig($request, $branch);
        $gigDate = Carbon::parse($validated['date'])->format('Y-m-d');
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['start_time']);
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['end_time']);

        if ($this->gigOverlaps($validated['area_id'], $gigDate, $startTime, $endTime)) {
            return redirect()->back()->withInput()->with('error', 'A gig slot already exists for this area and time range.');
        }

        DriverGig::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'driver_id' => null,
            'area_id' => $validated['area_id'],
            'branch_id' => $branch->id,
            'open_to_pool' => $request->boolean('open_to_pool'),
            'capacity' => $validated['capacity'],
            'date' => $gigDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'available',
            'base_pay' => $validated['base_pay'] ?? 0,
            'order_incentive' => $validated['order_incentive'] ?? 0,
            'login_incentive' => $validated['login_incentive'] ?? 0,
            'min_orders_required' => $validated['min_orders_required'] ?? 0,
            'min_login_minutes' => $validated['min_login_minutes'] ?? 0,
            'max_cancellations_allowed' => $validated['max_cancellations_allowed'] ?? 0,
            'terms_conditions' => $validated['terms_conditions'] ?? null,
            'auto_pricing_enabled' => $request->boolean('auto_pricing_enabled', true),
            'surge_multiplier' => $validated['surge_multiplier'] ?? 1,
        ]);

        return redirect()->route('branch.gigs.index')->with('success', 'Gig slot created successfully.');
    }

    public function edit(Request $request, DriverGig $gig)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.edit');
        $this->ensureOwnedByBranch($gig, $branch);

        $deliveryAreas = DeliveryArea::whereIn('id', $branch->deliveryAreaIds())->where('is_active', true)->orderBy('name')->get();

        return view('branch.gigs.edit', compact('branch', 'gig', 'deliveryAreas'));
    }

    public function update(Request $request, DriverGig $gig)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.edit');
        $this->ensureOwnedByBranch($gig, $branch);

        $validated = $this->validateGig($request, $branch, ['status' => 'required|in:available,booked,completed,cancelled']);
        $gigDate = Carbon::parse($validated['date'])->format('Y-m-d');
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['start_time']);
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['end_time']);

        if ($this->gigOverlaps($validated['area_id'], $gigDate, $startTime, $endTime, $gig->id)) {
            return redirect()->back()->withInput()->with('error', 'Another gig slot already exists for this area and time range.');
        }

        $bookedCount = $gig->activeBookings()->count();
        if ((int) $validated['capacity'] < $bookedCount) {
            return redirect()->back()->withInput()->with('error', "Capacity cannot be less than the {$bookedCount} active bookings already on this gig.");
        }

        $gig->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'area_id' => $validated['area_id'],
            'open_to_pool' => $request->boolean('open_to_pool'),
            'capacity' => $validated['capacity'],
            'date' => $gigDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $validated['status'],
            'base_pay' => $validated['base_pay'] ?? 0,
            'order_incentive' => $validated['order_incentive'] ?? 0,
            'login_incentive' => $validated['login_incentive'] ?? 0,
            'min_orders_required' => $validated['min_orders_required'] ?? 0,
            'min_login_minutes' => $validated['min_login_minutes'] ?? 0,
            'max_cancellations_allowed' => $validated['max_cancellations_allowed'] ?? 0,
            'terms_conditions' => $validated['terms_conditions'] ?? null,
            'auto_pricing_enabled' => $request->boolean('auto_pricing_enabled', true),
            'surge_multiplier' => $validated['surge_multiplier'] ?? 1,
        ]);

        $this->syncBookingsForTerminalStatus($gig, $validated['status']);

        return redirect()->route('branch.gigs.index')->with('success', 'Gig slot updated successfully.');
    }

    public function destroy(Request $request, DriverGig $gig)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.edit');
        $this->ensureOwnedByBranch($gig, $branch);

        $gig->delete();

        return redirect()->route('branch.gigs.index')->with('success', 'Gig deleted successfully.');
    }

    public function updateStatus(Request $request, DriverGig $gig)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.edit');
        $this->ensureOwnedByBranch($gig, $branch);

        $request->validate(['status' => 'required|in:available,booked,completed,cancelled']);

        $oldStatus = $gig->status;
        $gig->update(['status' => $request->status]);
        $this->syncBookingsForTerminalStatus($gig, $request->status);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'old_status' => $oldStatus, 'new_status' => $request->status]);
        }

        return redirect()->back()->with('success', 'Gig status updated successfully.');
    }

    public function bulkCreate(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.create');

        $validated = $this->validateGig($request, $branch, [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ], skip: ['date']);

        $dates = [];
        $currentDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        $created = 0;
        $skipped = 0;
        foreach ($dates as $date) {
            $slotStart = Carbon::parse($date . ' ' . $validated['start_time']);
            $slotEnd = Carbon::parse($date . ' ' . $validated['end_time']);

            if ($this->gigOverlaps($validated['area_id'], $date, $slotStart, $slotEnd)) {
                $skipped++;
                continue;
            }

            DriverGig::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'area_id' => $validated['area_id'],
                'branch_id' => $branch->id,
                'open_to_pool' => $request->boolean('open_to_pool'),
                'capacity' => $validated['capacity'],
                'date' => $date,
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'status' => 'available',
                'base_pay' => $validated['base_pay'] ?? 0,
                'order_incentive' => $validated['order_incentive'] ?? 0,
                'login_incentive' => $validated['login_incentive'] ?? 0,
                'min_orders_required' => $validated['min_orders_required'] ?? 0,
                'min_login_minutes' => $validated['min_login_minutes'] ?? 0,
                'max_cancellations_allowed' => $validated['max_cancellations_allowed'] ?? 0,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'auto_pricing_enabled' => $request->boolean('auto_pricing_enabled', true),
                'surge_multiplier' => $validated['surge_multiplier'] ?? 1,
            ]);
            $created++;
        }

        return redirect()->route('branch.gigs.index')
            ->with('success', "{$created} gig slots created." . ($skipped > 0 ? " {$skipped} skipped due to conflicts." : ''));
    }

    public function payoutApprovals(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.payouts', ['manage_drivers', 'view_wallet']);

        $status = in_array($request->input('status'), ['pending', 'approved', 'rejected'], true) ? $request->input('status') : 'pending';

        $approvals = GigPayoutApproval::with(['driver', 'gig.area', 'booking'])
            ->whereHas('gig', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('branch.gigs.payout-approvals', compact('branch', 'approvals', 'status'));
    }

    public function approvePayout(Request $request, GigPayoutApproval $approval, GigPayoutApprovalService $approvalService)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.gigs.payouts', ['manage_drivers', 'view_wallet']);

        if ((int) $approval->gig?->branch_id !== (int) $branch->id) {
            abort(403, 'This payout approval does not belong to your branch.');
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $approvalService->approve($approval, $request->user()->id, $validated['note'] ?? null);
        } else {
            $approvalService->reject($approval, $request->user()->id, $validated['note'] ?? null);
        }
        $approval->forceFill(['approved_via' => 'branch'])->save();

        BranchAuditLog::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'action' => 'gig_payout_' . $validated['action'],
            'entity_type' => GigPayoutApproval::class,
            'entity_id' => $approval->id,
            'new_values' => ['status' => $validated['action'] === 'approve' ? 'approved' : 'rejected', 'note' => $validated['note'] ?? null],
            'ip_address' => $request->ip(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Payout ' . $validated['action'] . 'd successfully.');
    }

    private function validateGig(Request $request, Branch $branch, array $extra = [], array $skip = []): array
    {
        $rules = array_merge([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'area_id' => 'required|exists:delivery_areas,id',
            'capacity' => 'required|integer|min:1',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'base_pay' => 'nullable|numeric|min:0',
            'order_incentive' => 'nullable|numeric|min:0',
            'login_incentive' => 'nullable|numeric|min:0',
            'min_orders_required' => 'nullable|integer|min:0',
            'min_login_minutes' => 'nullable|integer|min:0',
            'max_cancellations_allowed' => 'nullable|integer|min:0',
            'terms_conditions' => 'nullable|string|max:5000',
            'auto_pricing_enabled' => 'nullable|boolean',
            'surge_multiplier' => 'nullable|numeric|min:1|max:10',
            'open_to_pool' => 'nullable|boolean',
        ], $extra);

        foreach ($skip as $field) {
            unset($rules[$field]);
        }

        $validated = $request->validate($rules);

        if (! $branch->deliveryAreaIds()->contains((int) $validated['area_id'])) {
            throw ValidationException::withMessages([
                'area_id' => 'That delivery area does not belong to your branch.',
            ]);
        }

        return $validated;
    }

    private function ensureOwnedByBranch(DriverGig $gig, Branch $branch): void
    {
        abort_unless((int) $gig->branch_id === (int) $branch->id, 403, 'This gig does not belong to your branch.');
    }

    /**
     * A gig belongs to this branch's fleet view if it was created BY the
     * branch (branch_id matches) OR it's a platform/admin gig (branch_id
     * null) scoped to a delivery area inside this branch's own zone
     * territory — admin's own Gig management page has no branch selector at
     * all, it only ever sets area_id, so a zone-targeted admin gig would
     * otherwise be invisible to the branch that actually covers that zone.
     */
    private function branchGigScope(Branch $branch)
    {
        $areaIds = $branch->deliveryAreaIds();

        return function ($query) use ($branch, $areaIds) {
            $query->where('branch_id', $branch->id)
                ->when($areaIds->isNotEmpty(), fn ($q) => $q->orWhere(
                    fn ($q2) => $q2->whereNull('branch_id')->whereIn('area_id', $areaIds)
                ));
        };
    }

    private function gigStatusQuery(Branch $branch, string $status, ?string $selectedDate)
    {
        return DriverGig::with(['driver', 'area', 'bookings.driver'])
            ->withCount(['activeBookings as active_bookings_count'])
            ->where($this->branchGigScope($branch))
            ->where('status', $status)
            ->when(
                $selectedDate,
                fn ($query) => $query->whereDate('date', $selectedDate),
                fn ($query) => in_array($status, ['available', 'booked'], true)
                    ? $query->whereDate('date', '>=', today())
                    : $query->whereDate('date', '>=', today()->subDays(7))
            )
            ->orderBy('date', in_array($status, ['completed', 'cancelled'], true) ? 'desc' : 'asc')
            ->orderBy('start_time');
    }

    private function gigStats(Branch $branch): array
    {
        $base = DriverGig::where($this->branchGigScope($branch));

        return [
            'total_today' => (clone $base)->whereDate('date', today())->count(),
            'active_gigs' => (clone $base)->whereIn('status', ['available', 'booked'])->whereDate('date', today())->count(),
            'completed_today' => (clone $base)->whereDate('date', today())->where('status', 'completed')->count(),
            'available_today' => (clone $base)->whereDate('date', today())->where('status', 'available')->count(),
            'pending_payouts' => GigPayoutApproval::whereHas('gig', fn ($q) => $q->where('branch_id', $branch->id))->where('status', 'pending')->count(),
        ];
    }

    private function gigOverlaps($areaId, string $date, Carbon $startTime, Carbon $endTime, ?int $ignoreId = null): bool
    {
        return DriverGig::where('area_id', $areaId)
            ->whereDate('date', $date)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    private function syncBookingsForTerminalStatus(DriverGig $gig, string $status): void
    {
        if (! in_array($status, ['completed', 'cancelled'], true)) {
            return;
        }

        $timestampColumn = $status === 'completed' ? 'completed_at' : 'cancelled_at';

        $gig->bookings()->where('status', 'booked')->update([
            'status' => $status,
            $timestampColumn => now(),
            'updated_at' => now(),
        ]);
    }

    private function currentBranch(Request $request): Branch
    {
        $branch = $this->branches->branchForUser($request->user());
        abort_unless($branch, 403);

        return $branch;
    }

    /**
     * $legacyPermissions matters as much as $permission: a branch owner's
     * BranchUser row was seeded with broad flags like 'manage_drivers' (see
     * BranchManagementService::permissionsFor), never the newer dotted
     * 'branch.xxx.yyy' keys — those only exist so a branch owner can grant a
     * STAFF member finer-grained access via branch.settings. Every check here
     * must accept the legacy flag too, or owners lock themselves out.
     */
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

        $branch = $this->currentBranch($request);
        $membership = \App\Models\BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return $membership && count(array_intersect($permissions, $membership->permissions ?? [])) > 0;
    }

    private function branchGigCapabilities(Request $request): array
    {
        return [
            'gigs_create' => $this->branchCan($request, 'branch.gigs.create', ['manage_drivers']),
            'gigs_edit' => $this->branchCan($request, 'branch.gigs.edit', ['manage_drivers']),
            'gigs_payouts' => $this->branchCan($request, 'branch.gigs.payouts', ['manage_drivers', 'view_wallet']),
        ];
    }
}
