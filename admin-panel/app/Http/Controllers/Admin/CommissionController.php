<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Models\AppSetting;
use App\Models\Restaurant;
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
            'gst_on_commission_rate' => 'nullable|numeric|min:0|max:100',
            'gateway_fee_rate' => 'required|numeric|min:0|max:100',
            'delivery_failure_wait_minutes' => 'nullable|numeric|min:1|max:60',
            'resale_discount_percent' => 'nullable|numeric|min:0|max:90',
            'resale_window_minutes' => 'nullable|numeric|min:1|max:60',
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

        // Keep the gst_rate key intact if the field isn't submitted (it is now
        // surfaced read-only on Settings -> Tax & Charges while GST invoicing is on).
        if ($request->filled('gst_on_commission_rate') || $request->input('gst_on_commission_rate') === '0') {
            AppSetting::setValue('gst_rate', $request->input('gst_on_commission_rate'));
        }
        AppSetting::setValue('gateway_fee_rate', $request->gateway_fee_rate);
        AppSetting::setValue('delivery_failure_wait_minutes', $request->input('delivery_failure_wait_minutes', 5));
        AppSetting::setValue('resale_discount_percent', $request->input('resale_discount_percent', 30));
        AppSetting::setValue('resale_window_minutes', $request->input('resale_window_minutes', 8));

        return redirect()->back()->with('success', 'Commission settings updated successfully!');
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
}
