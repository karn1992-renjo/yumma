<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GigController extends Controller
{
    public function index(Request $request)
    {
        $availableGigs = DriverGig::with(['driver', 'area'])
            ->where('status', 'available')
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        $bookedGigs = DriverGig::with(['driver', 'area'])
            ->where('status', 'booked')
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        $completedGigs = DriverGig::with(['driver', 'area'])
            ->where('status', 'completed')
            ->whereDate('date', '>=', today()->subDays(7))
            ->orderBy('date', 'desc')
            ->orderBy('start_time')
            ->limit(50)
            ->get();
            
        $cancelledGigs = DriverGig::with(['driver', 'area'])
            ->where('status', 'cancelled')
            ->whereDate('date', '>=', today()->subDays(7))
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();
            
        // Stats for dashboard
        $stats = [
            'total_today' => DriverGig::whereDate('date', today())->count(),
            'active_gigs' => DriverGig::whereIn('status', ['available', 'booked'])
                ->whereDate('date', today())
                ->count(),
            'completed_today' => DriverGig::whereDate('date', today())
                ->where('status', 'completed')
                ->count(),
            'available_today' => DriverGig::whereDate('date', today())
                ->where('status', 'available')
                ->count(),
            'globally_open' => DriverGig::whereNull('driver_id')
                ->where('status', 'available')
                ->whereDate('date', '>=', today())
                ->count(),
        ];
        
        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.gigs.index', compact('availableGigs', 'bookedGigs', 'completedGigs', 'cancelledGigs', 'deliveryAreas', 'stats'));
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
        ]);

        $gigDate = Carbon::parse($validated['date'])->format('Y-m-d');
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['start_time']);
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['end_time']);

        $existingGig = DriverGig::where('area_id', $validated['area_id'])
            ->whereDate('date', $gigDate)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($inner) use ($startTime, $endTime) {
                        $inner->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();

        if ($existingGig) {
            return redirect()->back()->withInput()->with('error', 'A gig slot already exists for this area and time range.');
        }

        DriverGig::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'driver_id' => null,
            'area_id' => $validated['area_id'],
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
        ]);
        
        if ($request->status === 'booked' && ! $this->checkAreaBookingLimit($gig, $request->area_id)) {
            return redirect()->back()->with('error', 'Cannot book this gig. The selected delivery area has reached its daily bookings limit.');
        }

        $gigDate = Carbon::parse($validated['date'])->format('Y-m-d');
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['start_time']);
        $endTime = Carbon::createFromFormat('Y-m-d H:i', $gigDate . ' ' . $validated['end_time']);

        $gig->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'area_id' => $validated['area_id'],
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
        ]);
        
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

            $existingGig = DriverGig::where('area_id', $request->area_id)
                ->whereDate('date', $date)
                ->where(function ($q) use ($slotStart, $slotEnd) {
                    $q->whereBetween('start_time', [$slotStart, $slotEnd])
                      ->orWhereBetween('end_time', [$slotStart, $slotEnd])
                      ->orWhere(function ($inner) use ($slotStart, $slotEnd) {
                          $inner->where('start_time', '<=', $slotStart)
                              ->where('end_time', '>=', $slotEnd);
                      });
                })
                ->exists();

            if (!$existingGig) {
                DriverGig::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'driver_id' => null,
                    'area_id' => $request->area_id,
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
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }
        
        return redirect()->route('admin.gigs.index')
            ->with('success', "{$created} global gig slots created successfully! " . ($skipped > 0 ? "{$skipped} skipped due to conflicts." : ""));
    }
    
    public function getCalendarEvents()
    {
        $gigs = DriverGig::with(['driver', 'area'])
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
                'driver' => $gig->driver?->name,
                'status' => $gig->status
            ];
        }
        
        return response()->json($events);
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
            ->where('status', 'booked')
            ->when($gig->exists, function ($query) use ($gig) {
                return $query->where('id', '!=', $gig->id);
            })
            ->count();

        return $bookedCount < $area->max_daily_bookings;
    }
}
