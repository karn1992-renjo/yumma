<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Services\GigPayoutApprovalService;
use App\Services\GigOperationsService;
use App\Services\GigOperationsBroadcastService;
use App\Services\GigExternalSignalService;
use App\Services\GigDemandForecastService;
use App\Models\GigDemandForecast;
use App\Models\GigPayoutApproval;
use App\Models\GigFraudSignal;
use App\Models\GigDispute;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\GigLifecycleService;
use Carbon\Carbon;

class GigController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);
        $selectedDate = !empty($validated['date'])
            ? Carbon::parse($validated['date'])->format('Y-m-d')
            : null;

        $availableGigs = $this->gigStatusQuery('available', $selectedDate)->get();
        $bookedGigs = $this->gigStatusQuery('booked', $selectedDate)->get();
        $completedGigs = $this->gigStatusQuery('completed', $selectedDate)->limit(50)->get();
        $cancelledGigs = $this->gigStatusQuery('cancelled', $selectedDate)->limit(30)->get();
        $stats = $this->gigStats();

        return view('admin.gigs.index', compact('availableGigs', 'bookedGigs', 'completedGigs', 'cancelledGigs', 'stats', 'selectedDate'));
    }

    public function analytics(Request $request, GigLifecycleService $gigLifecycleService, GigOperationsService $gigOperationsService)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $selectedDate = !empty($validated['date'])
            ? Carbon::parse($validated['date'])->format('Y-m-d')
            : today()->toDateString();
        $date = Carbon::parse($selectedDate);
        $operations = $gigOperationsService->controlRoom($date);
        $heatmap = $gigLifecycleService->heatmap($date);
        $stats = $this->gigStats();
        $forecastSettings = [
            'gig_forecast_lookback_days' => (int) AppSetting::getValue('gig_forecast_lookback_days', 28),
            'gig_ml_forecast_lookback_days' => (int) AppSetting::getValue('gig_ml_forecast_lookback_days', 56),
            'gig_target_orders_per_driver_per_hour' => (int) AppSetting::getValue('gig_target_orders_per_driver_per_hour', 3),
        ];

        return view('admin.gigs.analytics', compact('operations', 'heatmap', 'stats', 'selectedDate', 'forecastSettings'));
    }

    public function bulk()
    {
        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();

        return view('admin.gigs.bulk', compact('deliveryAreas'));
    }

    public function payoutApprovals(Request $request)
    {
        $status = $request->input('status', 'pending');
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'pending';

        $approvals = GigPayoutApproval::with(['driver', 'gig.area', 'booking'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.gigs.payout-approvals', compact('approvals', 'status', 'allowedStatuses'));
    }

    public function fraudSignals(Request $request)
    {
        $status = $request->input('status', 'open');
        $allowedStatuses = ['open', 'cleared', 'confirmed', 'ignored'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'open';

        $signals = GigFraudSignal::with(['driver', 'gig.area', 'booking'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.gigs.fraud-signals', compact('signals', 'status', 'allowedStatuses'));
    }

    public function disputes(Request $request)
    {
        $status = $request->input('status', 'open');
        $allowedStatuses = ['open', 'resolved', 'dismissed'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'open';

        $disputes = GigDispute::with(['driver', 'gig.area', 'booking', 'incentive'])
            ->where('status', $status)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.gigs.disputes', compact('disputes', 'status', 'allowedStatuses'));
    }

    public function create()
    {
        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();
        return view('admin.gigs.create', compact('deliveryAreas'));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
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
            'demand_score' => 'nullable|numeric|min:0|max:100',
            'forecasted_orders' => 'nullable|integer|min:0',
            'recommended_capacity' => 'nullable|integer|min:1',
        ]);

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
            'demand_score' => $validated['demand_score'] ?? 0,
            'forecasted_orders' => $validated['forecasted_orders'] ?? 0,
            'recommended_capacity' => $validated['recommended_capacity'] ?? null,
        ]);
        
        return redirect()->route('admin.gigs.index')
            ->with('success', 'Global gig slot created successfully.');
    }
    
    public function edit(DriverGig $gig)
    {
        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();
        return view('admin.gigs.edit', compact('gig', 'deliveryAreas'));
    }
    
    public function update(Request $request, DriverGig $gig)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'area_id' => 'required|exists:delivery_areas,id',
            'capacity' => 'required|integer|min:1',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:available,booked,completed,cancelled',
            'base_pay' => 'nullable|numeric|min:0',
            'order_incentive' => 'nullable|numeric|min:0',
            'login_incentive' => 'nullable|numeric|min:0',
            'min_orders_required' => 'nullable|integer|min:0',
            'min_login_minutes' => 'nullable|integer|min:0',
            'max_cancellations_allowed' => 'nullable|integer|min:0',
            'terms_conditions' => 'nullable|string|max:5000',
            'auto_pricing_enabled' => 'nullable|boolean',
            'surge_multiplier' => 'nullable|numeric|min:1|max:10',
            'demand_score' => 'nullable|numeric|min:0|max:100',
            'forecasted_orders' => 'nullable|integer|min:0',
            'recommended_capacity' => 'nullable|integer|min:1',
        ]);
        
        if ($request->status === 'booked' && ! $this->checkAreaBookingLimit($gig, $request->area_id)) {
            return redirect()->back()->with('error', 'Cannot book this gig. The selected delivery area has reached its daily bookings limit.');
        }

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
            'demand_score' => $validated['demand_score'] ?? 0,
            'forecasted_orders' => $validated['forecasted_orders'] ?? 0,
            'recommended_capacity' => $validated['recommended_capacity'] ?? null,
        ]);

        $this->syncBookingsForTerminalStatus($gig, $validated['status']);
        
        return redirect()->route('admin.gigs.index')
            ->with('success', 'Gig slot updated successfully.');
    }
    
    public function destroy(DriverGig $gig)
    {
        $gig->delete();
        
        return redirect()->route('admin.gigs.index')
            ->with('success', 'Gig deleted successfully!');
    }
    
    public function updateStatus(Request $request, DriverGig $gig)
    {
        $request->validate([
            'status' => 'required|in:available,booked,completed,cancelled'
        ]);
        
        if ($request->status === 'booked' && ! $this->checkAreaBookingLimit($gig, $gig->area_id)) {
            return response()->json(['success' => false, 'message' => 'Area booking limit reached for this date.']);
        }

        $oldStatus = $gig->status;
        $gig->update(['status' => $request->status]);
        $this->syncBookingsForTerminalStatus($gig, $request->status);
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Gig status updated successfully',
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ]);
        }
        
        return redirect()->back()->with('success', 'Gig status updated successfully!');
    }
    
    public function book(DriverGig $gig)
    {
        if ($gig->status !== 'available') {
            return response()->json(['success' => false, 'message' => 'Gig is not available!']);
        }

        if (! $this->checkAreaBookingLimit($gig, $gig->area_id)) {
            return response()->json(['success' => false, 'message' => 'Cannot book gig because the area booking limit has been reached for this date.']);
        }
        
        $gig->update(['status' => 'booked']);
        
        return response()->json(['success' => true]);
    }
    
    public function bulkCreate(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'area_id' => 'required|exists:delivery_areas,id',
            'capacity' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
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
            'demand_score' => 'nullable|numeric|min:0|max:100',
            'forecasted_orders' => 'nullable|integer|min:0',
            'recommended_capacity' => 'nullable|integer|min:1',
        ]);
        
        $dates = [];
        $currentDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        
        while ($currentDate <= $endDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }
        
        $created = 0;
        $skipped = 0;
        
        foreach ($dates as $date) {
            $slotStart = Carbon::parse($date . ' ' . $request->start_time);
            $slotEnd = Carbon::parse($date . ' ' . $request->end_time);

            if (! $this->gigOverlaps($request->area_id, $date, $slotStart, $slotEnd)) {
                DriverGig::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'driver_id' => null,
                    'area_id' => $request->area_id,
                    'capacity' => $request->capacity,
                    'date' => $date,
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                    'status' => 'available',
                    'base_pay' => $request->base_pay ?? 0,
                    'order_incentive' => $request->order_incentive ?? 0,
                    'login_incentive' => $request->login_incentive ?? 0,
                    'min_orders_required' => $request->min_orders_required ?? 0,
                    'min_login_minutes' => $request->min_login_minutes ?? 0,
                    'max_cancellations_allowed' => $request->max_cancellations_allowed ?? 0,
                    'terms_conditions' => $request->terms_conditions,
                    'auto_pricing_enabled' => $request->boolean('auto_pricing_enabled', true),
                    'surge_multiplier' => $request->surge_multiplier ?? 1,
                    'demand_score' => $request->demand_score ?? 0,
                    'forecasted_orders' => $request->forecasted_orders ?? 0,
                    'recommended_capacity' => $request->recommended_capacity ?: null,
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }
        
        return redirect()->route('admin.gigs.index')
            ->with('success', "{$created} global gig slots created successfully! " . ($skipped > 0 ? "{$skipped} skipped due to conflicts." : ""));
    }
    
    public function operations(Request $request, GigOperationsService $gigOperationsService)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : today();

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('admin.gigs.analytics', ['date' => $date->toDateString()]);
        }

        return response()->json([
            'success' => true,
            'data' => $gigOperationsService->controlRoom($date),
        ]);
    }
    public function forecast(Request $request, GigDemandForecastService $forecastService)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : today()->addDay();

        $data = $forecastService->forecastDay($date);

        app(GigOperationsBroadcastService::class)->broadcast();

        if (! $request->expectsJson()) {
            return redirect()->route('admin.gigs.analytics', ['date' => $date->toDateString()])
                ->with('success', count($data) . ' forecast cells refreshed.');
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Looks up the already-computed forecast for a single area/date/hour so
     * the gig create/edit forms can pre-fill capacity/surge/demand fields
     * instead of admins guessing them by hand. Returns found=false (not an
     * error) when no forecast row exists yet for that slot -- forecasts are
     * only computed hourly for today/tomorrow, so far-future or just-created
     * area slots won't have one until the next scheduled run.
     */
    public function forecastLookup(Request $request)
    {
        $validated = $request->validate([
            'area_id' => 'nullable|exists:delivery_areas,id',
            'date' => 'required|date',
            'hour' => 'required|integer|min:0|max:23',
        ]);

        $query = GigDemandForecast::whereDate('date', Carbon::parse($validated['date'])->toDateString())
            ->where('hour', $validated['hour']);

        if (! empty($validated['area_id'])) {
            $query->where('area_id', $validated['area_id']);
        } else {
            $query->whereNull('area_id');
        }

        $forecast = $query->first();

        return response()->json([
            'success' => true,
            'found' => (bool) $forecast,
            'data' => $forecast ? [
                'forecasted_orders' => $forecast->forecasted_orders,
                'recommended_capacity' => $forecast->recommended_capacity,
                'demand_score' => $forecast->demand_score,
                'surge_multiplier' => $forecast->surge_multiplier,
            ] : null,
        ]);
    }

    /**
     * Tuning knobs for GigDemandForecastService/GigMlForecastService --
     * intentionally a small standalone form rather than folded into the
     * general settings page, since that page's shared multi-section form
     * has a validation trap where one unrelated required field silently
     * blocks saving everything else on it.
     */
    public function updateForecastSettings(Request $request)
    {
        $validated = $request->validate([
            'gig_forecast_lookback_days' => 'required|integer|min:7|max:180',
            'gig_ml_forecast_lookback_days' => 'required|integer|min:7|max:365',
            'gig_target_orders_per_driver_per_hour' => 'required|integer|min:1|max:50',
            'redirect_date' => 'nullable|date',
        ]);

        foreach (['gig_forecast_lookback_days', 'gig_ml_forecast_lookback_days', 'gig_target_orders_per_driver_per_hour'] as $key) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $validated[$key], 'type' => 'number']);
        }

        Cache::forget('app_settings');

        return redirect()->route('admin.gigs.analytics', ['date' => $validated['redirect_date'] ?? today()->toDateString()])
            ->with('success', 'Forecast tuning settings updated.');
    }

    public function approvePayout(Request $request, GigPayoutApproval $approval, GigPayoutApprovalService $approvalService)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validated['action'] === 'approve') {
            $approvalService->approve($approval, auth()->id(), $validated['note'] ?? null);
        } else {
            $approvalService->reject($approval, auth()->id(), $validated['note'] ?? null);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Payout ' . $validated['action'] . 'd successfully.');
    }

    public function resolveFraudSignal(Request $request, GigFraudSignal $signal)
    {
        $validated = $request->validate([
            'status' => 'required|in:cleared,confirmed,ignored',
        ]);

        $signal->forceFill([
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Fraud signal marked as ' . $validated['status'] . '.');
    }

    public function resolveDispute(Request $request, GigDispute $dispute)
    {
        $validated = $request->validate([
            'status' => 'required|in:resolved,dismissed',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $dispute->forceFill([
            'status' => $validated['status'],
            'resolution_note' => $validated['resolution_note'] ?? null,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Dispute marked as ' . $validated['status'] . '.');
    }
    public function ingestSignal(Request $request, GigExternalSignalService $signalService)
    {
        $validated = $request->validate([
            'area_id' => 'nullable|exists:delivery_areas,id',
            'date' => 'required|date',
            'hour' => 'required|integer|min:0|max:23',
            'source' => 'required|string|max:40',
            'score' => 'required|numeric|min:-100|max:100',
            'payload' => 'nullable|array',
        ]);

        $signal = $signalService->ingest($validated);
        app(GigOperationsBroadcastService::class)->broadcast();

        return response()->json([
            'success' => true,
            'data' => $signal,
        ], 201);
    }
    public function heatmap(Request $request, GigLifecycleService $gigLifecycleService)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : today();

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('admin.gigs.analytics', ['date' => $date->toDateString()]);
        }

        return response()->json([
            'success' => true,
            'data' => $gigLifecycleService->heatmap($date),
        ]);
    }
    public function getCalendarEvents()
    {
        $gigs = DriverGig::with(['driver', 'area', 'bookings.driver'])
            ->withCount(['activeBookings as active_bookings_count'])
            ->whereIn('status', ['available', 'booked'])
            ->whereDate('date', '>=', today()->subDays(7))
            ->whereDate('date', '<=', today()->addDays(30))
            ->get();
            
        $events = [];
        foreach ($gigs as $gig) {
            $statusColor = [
                'available' => '#28a745',
                'booked' => '#007bff',
                'completed' => '#17a2b8',
                'cancelled' => '#dc3545'
            ];
            
            $events[] = [
                'id' => $gig->id,
                'title' => ($gig->title ?: ($gig->area?->name ?? 'Gig Slot')) . ' - ' . ucfirst($gig->status),
                'start' => $gig->date . 'T' . Carbon::parse($gig->start_time)->format('H:i:s'),
                'end' => $gig->date . 'T' . Carbon::parse($gig->end_time)->format('H:i:s'),
                'color' => $statusColor[$gig->status],
                'driver' => $gig->bookings
                    ->whereIn('status', ['booked', 'completed'])
                    ->map(fn ($booking) => $booking->driver?->name)
                    ->filter()
                    ->values()
                    ->join(', '),
                'status' => $gig->status,
                'booked_count' => $gig->booked_count,
                'capacity' => $gig->capacity,
            ];
        }
        
        return response()->json($events);
    }

    protected function gigStatusQuery(string $status, ?string $selectedDate)
    {
        return DriverGig::with(['driver', 'area', 'bookings.driver'])
            ->withCount(['activeBookings as active_bookings_count'])
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

    protected function gigStats(): array
    {
        return [
            'total_today' => DriverGig::whereDate('date', today())->count(),
            'active_gigs' => DriverGig::whereIn('status', ['available', 'booked'])->whereDate('date', today())->count(),
            'completed_today' => DriverGig::whereDate('date', today())->where('status', 'completed')->count(),
            'available_today' => DriverGig::whereDate('date', today())->where('status', 'available')->count(),
            'globally_open' => DriverGig::withCount(['activeBookings as active_bookings_count'])
                ->whereIn('status', ['available', 'booked'])
                ->whereDate('date', '>=', today())
                ->get()
                ->filter(fn (DriverGig $gig) => $gig->available_seats > 0)
                ->count(),
        ];
    }
    protected function gigOverlaps($areaId, string $date, Carbon $startTime, Carbon $endTime, ?int $ignoreId = null): bool
    {
        return DriverGig::where('area_id', $areaId)
            ->whereDate('date', $date)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }
    protected function checkAreaBookingLimit(DriverGig $gig, $areaId): bool
    {
        if (!$areaId) {
            return true;
        }

        $area = DeliveryArea::find($areaId);
        if (!$area || !$area->max_daily_bookings) {
            return true;
        }

        $bookedCount = DriverGig::where('area_id', $areaId)
            ->whereDate('date', $gig->date)
            ->when($gig->exists, function ($query) use ($gig) {
                return $query->where('id', '!=', $gig->id);
            })
            ->withCount(['activeBookings as active_bookings_count'])
            ->get()
            ->sum('booked_count');

        return $bookedCount < $area->max_daily_bookings;
    }

    protected function syncBookingsForTerminalStatus(DriverGig $gig, string $status): void
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
