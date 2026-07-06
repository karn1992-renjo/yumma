<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\AppSetting;
use App\Models\PayoutHistory;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PayoutCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function index()
    {
        $settings = CommissionSetting::whereIn('type', [
            CommissionSetting::RESTAURANT,
            CommissionSetting::DRIVER,
        ])->get();
        return view('admin.commissions.index', compact('settings'));
    }
    
    public function updateSettings(Request $request)
    {
        $request->validate([
            'restaurant_commission_rate' => 'required|numeric|min:0',
            'driver_commission_rate' => 'required|numeric|min:0',
            'restaurant_calculation_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'driver_calculation_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'gst_on_commission_rate' => 'required|numeric|min:0|max:100',
            'gateway_fee_rate' => 'required|numeric|min:0|max:100',
        ]);

        foreach (['restaurant', 'driver'] as $type) {
            if ($request->input($type . '_calculation_type') === 'percentage'
                && (float) $request->input($type . '_commission_rate') > 100) {
                return back()->withErrors([
                    $type . '_commission_rate' => 'Percentage commission cannot exceed 100.',
                ])->withInput();
            }
        }
        
        CommissionSetting::updateOrCreate(
            ['type' => 'restaurant'],
            ['name' => 'Restaurant Earning Commission', 'rate' => $request->restaurant_commission_rate, 'calculation_type' => $request->restaurant_calculation_type, 'is_active' => true]
        );
        
        CommissionSetting::updateOrCreate(
            ['type' => 'driver'],
            ['name' => 'Driver Earning Commission', 'rate' => $request->driver_commission_rate, 'calculation_type' => $request->driver_calculation_type, 'is_active' => true]
        );

        CommissionSetting::whereIn('type', ['admin', 'delivery_partner'])->delete();

        AppSetting::setValue('gst_rate', $request->gst_on_commission_rate);
        AppSetting::setValue('gateway_fee_rate', $request->gateway_fee_rate);
        
        return redirect()->back()->with('success', 'Commission settings updated successfully!');
    }
    
    public function payoutHistory(Request $request)
    {
        $query = PayoutHistory::with('payable');
        
        if ($request->type === 'restaurant') {
            $query->where('payable_type', Restaurant::class);
        } elseif ($request->type === 'driver') {
            $query->where('payable_type', User::class);
        }
        
        if ($request->restaurant_id) {
            $query->where('payable_type', Restaurant::class)
                  ->where('payable_id', $request->restaurant_id);
        }
        
        if ($request->period_type) {
            $query->where('period_type', $request->period_type);
        }
        
        if ($request->date_from) {
            $query->where('period_start', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->where('period_end', '<=', $request->date_to);
        }
        
        $payouts = $query->orderBy('created_at', 'desc')->paginate(20);
        $restaurants = Restaurant::orderBy('name')->get();
        
        return view('admin.commissions.payout-history', compact('payouts', 'restaurants'));
    }
    
    public function generatePayouts(Request $request, PayoutCalculationService $calculator)
    {
        $request->validate([
            'period_type' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        
        $batchId = 'COMM_' . now()->format('YmdHis') . '_' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4));
        $created = 0;

        foreach ($calculator->aggregateRestaurantPayouts($startDate, $endDate) as $row) {
            if ($calculator->createPayoutFromAggregate($row, $startDate, $endDate, $batchId)) {
                $created++;
            }
        }

        foreach ($calculator->aggregateDriverPayouts($startDate, $endDate) as $row) {
            if ($calculator->createPayoutFromAggregate($row, $startDate, $endDate, $batchId)) {
                $created++;
            }
        }

        return redirect()->route('admin.payouts.index')
            ->with('success', "Generated {$created} wallet-backed payouts in batch {$batchId}.");
    }
    
    public function markPayoutCompleted($id)
    {
        $payout = PayoutHistory::findOrFail($id);
        
        $payout->update([
            'status' => 'completed',
            'processed_at' => now(),
            'transaction_id' => 'TXN_' . strtoupper(uniqid())
        ]);
        
        return redirect()->back()->with('success', 'Payout marked as completed!');
    }
}
