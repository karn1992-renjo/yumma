<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\FailedPayout;
use App\Models\Payout;
use App\Models\PayoutSetting;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BulkPayoutService;
use App\Services\PayoutCalculationService;
use App\Services\PayoutGatewayService;
use App\Services\PayoutSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Payout::with(['restaurant.owner', 'driver']);

        if ($request->type === 'restaurant') {
            $query->whereNotNull('restaurant_id');
        } elseif ($request->type === 'driver') {
            $query->whereNotNull('driver_id');
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('gateway')) $query->where('gateway', $request->gateway);
        if ($request->filled('restaurant_id')) $query->where('restaurant_id', $request->restaurant_id);
        if ($request->filled('date_from')) $query->whereDate('period_start', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('period_end', '<=', $request->date_to);

        $payouts = $query->latest()->paginate(20);
        $restaurants = Restaurant::orderBy('name')->get();
        $pendingRestaurantAmount = Payout::where('status', 'pending')->whereNotNull('restaurant_id')->sum('amount');
        $pendingDriverAmount = Payout::where('status', 'pending')->whereNotNull('driver_id')->sum('amount');
        $payoutFrequency = AppSetting::getValue('payout_frequency', 'weekly');
        $payoutDay = AppSetting::getValue('payout_day', 'monday');
        $totalProcessed = Payout::whereIn('status', ['completed', 'processed'])->sum('amount');
        $failedCount = Payout::where('status', 'failed')->count();
        $activeGateway = PayoutSetting::activeGateway();
        $platformBalance = Wallet::sum('balance');

        return view('admin.payouts.index', compact(
            'payouts',
            'restaurants',
            'pendingRestaurantAmount',
            'pendingDriverAmount',
            'payoutFrequency',
            'payoutDay',
            'totalProcessed',
            'failedCount',
            'activeGateway',
            'platformBalance'
        ));
    }

    public function data(Request $request)
    {
        return response()->json(Payout::with(['restaurant.owner', 'driver'])->latest()->paginate($request->integer('per_page', 20)));
    }

    public function create()
    {
        $restaurants = Restaurant::orderBy('name')->get();
        $drivers = User::role('delivery_partner')->where('is_active', true)->get();
        return view('admin.payouts.create', compact('restaurants', 'drivers'));
    }

    public function store(Request $request, PayoutSettlementService $settlementService)
    {
        $request->validate([
            'type' => 'required|in:restaurant,driver',
            'amount' => 'required|numeric|min:1',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $data = [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => $request->amount,
            'net_amount' => $request->amount,
            'gross_amount' => $request->amount,
            'currency' => AppSetting::getValue('currency_code', 'INR'),
            'gateway' => PayoutSetting::activeGateway(),
            'status' => 'pending',
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'created_by' => auth()->id(),
        ];

        if ($request->type === 'restaurant') {
            $request->validate(['restaurant_id' => 'required|exists:restaurants,id']);
            $data['restaurant_id'] = $request->restaurant_id;
            $data['vendor_type'] = 'restaurant';
            $data['vendor_id'] = $request->restaurant_id;
        } else {
            $request->validate(['driver_id' => 'required|exists:users,id']);
            $data['driver_id'] = $request->driver_id;
            $data['vendor_type'] = 'driver';
            $data['vendor_id'] = $request->driver_id;
        }

        DB::transaction(function () use ($data, $settlementService) {
            $data['idempotency_key'] = 'manual_admin_' . (string) \Illuminate\Support\Str::uuid();
            $payout = Payout::create($data);

            if (! $settlementService->reserveFunds($payout, (float) $payout->amount, 'Admin payout reserved')) {
                throw ValidationException::withMessages([
                    'amount' => 'The vendor wallet does not have enough available balance.',
                ]);
            }
        });
        return redirect()->route('admin.payouts.index')->with('success', 'Payout created successfully!');
    }

    public function process(Payout $payout, PayoutGatewayService $gatewayService, PayoutSettlementService $settlementService)
    {
        if ($payout->status !== 'pending') {
            return $this->payoutResponse(false, 'This payout has already been processed!');
        }

        $provider = $payout->gateway ?: PayoutSetting::activeGateway();
        if (!$gatewayService->supportsAutomatedProcessing($provider)) {
            return $this->payoutResponse(false, $gatewayService->unsupportedAutomationMessage($provider));
        }

        try {
            if (! $settlementService->ensureFundsReserved(
                $payout->loadMissing(['restaurant.owner', 'driver'])
            )) {
                return $this->payoutResponse(false, 'Insufficient wallet balance to reserve this payout.');
            }

            $gatewayResult = $gatewayService->process($payout->loadMissing(['restaurant.owner', 'driver']));
        } catch (\Throwable $e) {
            $settlementService->releaseLockedFundsIfNeeded($payout->loadMissing(['restaurant.owner', 'driver']), true);
            $payout->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'retry_count' => (int) $payout->retry_count + 1,
                'next_retry_at' => now()->addMinutes(2 ** min((int) $payout->retry_count + 1, 8)),
            ]);
            return $this->payoutResponse(false, 'Payout failed: ' . $e->getMessage());
        }

        $status = $settlementService->settleFromGatewayResult(
            $payout->loadMissing(['restaurant.owner', 'driver']),
            $gatewayResult,
            auth()->id()
        );

        if ($status === 'failed') {
            return $this->payoutResponse(false, $payout->fresh()->failure_reason ?: 'The gateway rejected this payout.');
        }

        return $this->payoutResponse(true, $status === 'completed'
            ? 'Payout processed successfully!'
            : 'Payout submitted to the gateway and is awaiting final confirmation.');
    }

    public function bulkProcess(Request $request, BulkPayoutService $bulkPayoutService)
    {
        $validated = $request->validate([
            'payout_ids' => 'required|array',
            'payout_ids.*' => 'integer|exists:payouts,id',
        ]);

        return response()->json(['success' => true, 'report' => $bulkPayoutService->process($validated['payout_ids'])]);
    }

    public function retry(
        Payout $payout,
        BulkPayoutService $bulkPayoutService,
        PayoutSettlementService $settlementService
    )
    {
        if ($payout->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Only failed payouts can be retried.',
            ], 422);
        }

        if (! $settlementService->reserveFundsForRetry($payout)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance to retry this payout.',
            ], 422);
        }

        $payout->update(['status' => 'pending', 'failure_reason' => null, 'next_retry_at' => null]);
        return response()->json(['success' => true, 'report' => $bulkPayoutService->process([$payout])]);
    }

    public function status(
        Payout $payout,
        PayoutGatewayService $gatewayService,
        PayoutSettlementService $settlementService
    )
    {
        $status = $gatewayService->checkStatus($payout);
        $localStatus = $settlementService->settleFromStatusPayload($payout, $status, $payout->gateway);

        return response()->json([
            'success' => true,
            'data' => $status,
            'local_status' => $localStatus,
        ]);
    }

    public function show(Payout $payout)
    {
        $payout->loadMissing(['restaurant.owner', 'driver', 'bankAccount', 'auditLogs', 'failedAttempts']);
        return response()->json([
            'success' => true,
            'payout' => array_merge($payout->toArray(), [
                'recipient_name' => $payout->restaurant->name ?? $payout->driver->name ?? 'N/A',
                'order_ids' => $payout->order_ids ?: ($payout->gateway_response['order_ids'] ?? []),
            ]),
        ]);
    }

    public function export()
    {
        $rows = Payout::with(['restaurant', 'driver'])->latest()->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Batch ID', 'Type', 'Vendor', 'Order IDs', 'Gross Amount',
                'Platform Commission Charged to Restaurant', 'GST on Platform Commission', 'Online Payment Gateway Fee',
                'Delivery Settlement Base', 'Admin Delivery Commission', 'Driver Deduction', 'Batch Bonus',
                'Additional Deduction', 'Net Payout', 'Currency', 'Gateway', 'Status',
                'Commission Rules', 'Transaction ID', 'Period Start', 'Period End', 'Processed At',
            ]);
            foreach ($rows as $payout) {
                fputcsv($out, [
                    $payout->id,
                    $payout->batch_id,
                    $payout->type,
                    $payout->restaurant->name ?? $payout->driver->name ?? 'N/A',
                    implode(',', $payout->order_ids ?: ($payout->gateway_response['order_ids'] ?? [])),
                    $payout->gross_amount,
                    $payout->platform_commission,
                    $payout->gst_on_commission,
                    $payout->payment_gateway_fee,
                    $payout->delivery_fee,
                    $payout->admin_delivery_commission,
                    $payout->driver_deduction,
                    $payout->batch_bonus,
                    $payout->deduction_amount,
                    $payout->net_amount,
                    $payout->currency,
                    $payout->gateway,
                    $payout->status,
                    json_encode($payout->breakdown ?? [], JSON_UNESCAPED_SLASHES),
                    $payout->transaction_id,
                    optional($payout->period_start)->toDateString(),
                    optional($payout->period_end)->toDateString(),
                    optional($payout->processed_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, 'payouts-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function failed()
    {
        $failedPayouts = FailedPayout::with(['payout.restaurant', 'payout.driver'])->latest()->paginate(20);
        return view('admin.payouts.failed', compact('failedPayouts'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'payout_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'payout_day' => 'nullable|string|max:20',
        ]);
        AppSetting::setValue('payout_frequency', $validated['payout_frequency']);
        AppSetting::setValue('payout_day', $validated['payout_day'] ?? '');
        return redirect()->route('admin.payouts.index')->with('success', 'Payout settings updated.');
    }

    public function revokeDeduction(
        Request $request,
        Payout $payout,
        PayoutSettlementService $settlementService
    )
    {
        $request->validate(['reason' => 'required|string|max:500']);
        if (($payout->deduction_amount ?? 0) <= 0) {
            return $this->payoutResponse(false, 'This payout has no deduction to revoke.');
        }
        $restoredAmount = (float) $payout->deduction_amount;
        if (! $settlementService->reserveFunds($payout, $restoredAmount, 'Revoked payout deduction reserved')) {
            return $this->payoutResponse(false, 'The vendor wallet does not have enough balance to revoke this deduction.');
        }

        $payout->update([
            'amount' => $payout->amount + $restoredAmount,
            'net_amount' => $payout->net_amount + $restoredAmount,
            'deduction_amount' => 0,
            'deduction_revoked_at' => now(),
            'deduction_revoke_reason' => $request->reason,
        ]);
        return $this->payoutResponse(true, 'Deduction revoked and payout amount restored.');
    }

    public function generateRestaurantPayouts(Request $request, PayoutCalculationService $calculator, BulkPayoutService $bulkPayoutService)
    {
        $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $batchId = 'REST_' . now()->format('YmdHis');
        $created = 0;
        $payoutIds = [];
        foreach ($calculator->aggregateRestaurantPayouts($request->period_start, $request->period_end) as $row) {
            if ($payout = $calculator->createPayoutFromAggregate($row, $request->period_start, $request->period_end, $batchId)) {
                $created++;
                $payoutIds[] = $payout->id;
            }
        }
        $report = $request->boolean('auto_process') ? $bulkPayoutService->process($payoutIds) : null;
        return $this->generatedResponse("Generated {$created} restaurant payouts!", $created, $batchId, $report);
    }

    public function generateDriverPayouts(Request $request, PayoutCalculationService $calculator, BulkPayoutService $bulkPayoutService)
    {
        $request->validate(['period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start']);
        $batchId = 'DRV_' . now()->format('YmdHis');
        $created = 0;
        $payoutIds = [];
        foreach ($calculator->aggregateDriverPayouts($request->period_start, $request->period_end) as $row) {
            if ($payout = $calculator->createPayoutFromAggregate($row, $request->period_start, $request->period_end, $batchId)) {
                $created++;
                $payoutIds[] = $payout->id;
            }
        }
        $report = $request->boolean('auto_process') ? $bulkPayoutService->process($payoutIds) : null;
        return $this->generatedResponse("Generated {$created} driver payouts!", $created, $batchId, $report);
    }

    private function generatedResponse(string $message, int $created, string $batchId, ?array $report)
    {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(compact('message', 'created', 'batchId', 'report') + ['success' => true, 'batch_id' => $batchId]);
        }
        return redirect()->route('admin.payouts.index')->with('success', $message);
    }

    private function payoutResponse(bool $success, string $message)
    {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }
        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }
}
