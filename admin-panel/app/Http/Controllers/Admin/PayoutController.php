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
use App\Models\WalletTransaction;
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
        $pendingRestaurantAmount = Payout::whereIn('status', ['pending', 'partially_paid'])->whereNotNull('restaurant_id')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total');
        $pendingDriverAmount = Payout::whereIn('status', ['pending', 'partially_paid'])->whereNotNull('driver_id')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total');
        $payoutFrequency = AppSetting::getValue('payout_frequency', 'weekly');
        $payoutDay = AppSetting::getValue('payout_day', 'monday');
        $totalProcessed = Payout::where('status', 'completed')->sum('amount');
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

    /**
     * Wallet snapshot for the manual-payout form: how much can actually be
     * reserved right now (available balance) plus the settled-but-unpaid
     * earning the "Generate Payouts" run would otherwise pick up.
     */
    public function vendorWallet(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:restaurant,driver'],
            'id' => ['required', 'integer'],
        ]);

        if ($validated['type'] === 'restaurant') {
            $restaurant = Restaurant::with('owner')->find($validated['id']);
            $payee = $restaurant?->owner;
            $unsettled = $restaurant
                ? (float) \App\Models\Order::where('restaurant_id', $restaurant->id)
                    ->where('status', 'delivered')
                    ->where('payout_processed', true)
                    ->whereNull('restaurant_payout_id')
                    ->sum('restaurant_earning')
                : 0.0;
        } else {
            $payee = User::find($validated['id']);
            $unsettled = $payee
                ? (float) \App\Models\Order::where('driver_id', $payee->id)
                    ->where('status', 'delivered')
                    ->where('payout_processed', true)
                    ->whereNull('driver_payout_id')
                    ->sum('driver_earning')
                : 0.0;
        }

        $wallet = $payee ? Wallet::where('user_id', $payee->id)->first() : null;

        return response()->json([
            'success' => true,
            'payee_name' => $payee->name ?? null,
            'wallet_found' => $wallet !== null,
            'wallet_balance' => round((float) ($wallet->balance ?? 0), 2),
            'wallet_locked' => round((float) ($wallet->locked_balance ?? 0), 2),
            'unsettled_earning' => round(max(0, $unsettled), 2),
            'currency' => AppSetting::getValue('currency_code', 'INR'),
        ]);
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
            'source' => 'manual_admin',
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

            if (! $settlementService->reserveFunds($payout, (float) $payout->amount, 'Admin payout reserved', 'manual_admin')) {
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

    public function markCashPaid(Request $request, Payout $payout, PayoutSettlementService $settlementService)
    {
        $validated = $request->validate([
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $isCompletedCashPayout = $payout->status === 'completed'
            && strtolower((string) $payout->gateway) === 'cash';

        if (! $isCompletedCashPayout && ! in_array($payout->status, ['pending', 'processing', 'queued', 'failed', 'partially_paid'], true)) {
            return $this->payoutResponse(false, 'Only pending, processing, partially paid, or failed payouts can be marked as cash paid.');
        }

        if ($payout->status === 'pending' && ! $settlementService->ensureFundsReserved(
            $payout->loadMissing(['restaurant.owner', 'driver'])
        )) {
            return $this->payoutResponse(false, 'Insufficient wallet balance to reserve this payout.');
        }

        if ($payout->status === 'failed' && ! $settlementService->reserveFundsForRetry(
            $payout->loadMissing(['restaurant.owner', 'driver'])
        )) {
            return $this->payoutResponse(false, 'Insufficient wallet balance to settle this failed payout.');
        }

        $reference = trim((string) ($validated['transaction_reference'] ?? ''));
        if ($reference === '') {
            $reference = 'CASH_PAYOUT_' . $payout->id . '_' . now()->format('YmdHis');
        }

        $payout->refresh();
        $outstanding = round((float) $payout->amount - (float) $payout->paid_amount, 2);
        $requestedAmount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : $outstanding;
        // A full settlement (whole outstanding amount) always goes through the
        // reservation-aware settleFromGatewayResult() path so the funds locked
        // at payout generation are consumed, not debited a second time. Only a
        // genuine partial payment uses settlePartialCash().
        $isFullSettlement = $isCompletedCashPayout || $requestedAmount >= $outstanding - 0.005;

        if ($isFullSettlement) {
            try {
                $status = $settlementService->settleFromGatewayResult(
                    $payout->loadMissing(['restaurant.owner', 'driver']),
                    [
                        'gateway' => 'cash',
                        'transaction_id' => $reference,
                        'gateway_reference_id' => $reference,
                        'gateway_status' => 'paid',
                        'response' => [
                            'mode' => 'cash',
                            'reference' => $reference,
                            'notes' => $validated['notes'] ?? null,
                            'marked_by' => auth()->id(),
                            'marked_at' => now()->toIso8601String(),
                        ],
                    ],
                    auth()->id()
                );
            } catch (\Throwable $e) {
                return $this->payoutResponse(false, 'Cash payout settlement failed: ' . $e->getMessage());
            }

            \App\Models\PayoutAuditLog::create([
                'payout_id' => $payout->id,
                'user_id' => auth()->id(),
                'action' => 'cash_paid',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => [
                    'transaction_id' => $reference,
                    'status' => $status,
                    'notes' => $validated['notes'] ?? null,
                ],
                'meta' => ['mode' => 'cash'],
            ]);

            return $this->payoutResponse(true, 'Payout marked as cash paid successfully.');
        }

        $remaining = round((float) $payout->amount - (float) $payout->paid_amount, 2);
        if ($remaining <= 0) {
            return $this->payoutResponse(false, 'This payout has no outstanding balance.');
        }

        $amountToPay = round((float) ($validated['amount'] ?? $remaining), 2);
        if ($amountToPay > $remaining + 0.005) {
            return $this->payoutResponse(false, "Amount exceeds the outstanding balance of {$remaining}.");
        }

        try {
            $status = $settlementService->settlePartialCash(
                $payout->loadMissing(['restaurant.owner', 'driver']),
                $amountToPay,
                ['reference' => $reference, 'notes' => $validated['notes'] ?? null],
                auth()->id()
            );
        } catch (\Throwable $e) {
            return $this->payoutResponse(false, 'Cash payout settlement failed: ' . $e->getMessage());
        }

        $remainingAfter = max(0, $remaining - $amountToPay);

        \App\Models\PayoutAuditLog::create([
            'payout_id' => $payout->id,
            'user_id' => auth()->id(),
            'action' => $status === 'completed' ? 'cash_paid' : 'cash_partial_paid',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => [
                'transaction_id' => $reference,
                'status' => $status,
                'amount_paid' => $amountToPay,
                'remaining_balance' => $remainingAfter,
                'notes' => $validated['notes'] ?? null,
            ],
            'meta' => ['mode' => 'cash'],
        ]);

        return $this->payoutResponse(true, $status === 'completed'
            ? 'Payout marked as fully paid in cash.'
            : 'Partial cash payment of ' . number_format($amountToPay, 2) . ' recorded; ' . number_format($remainingAfter, 2) . ' still outstanding.');
    }

    /**
     * Cancel a pending/failed payout and return its wallet reservation.
     *
     * Releases only THIS payout's still-locked amount back to the payee's
     * available balance, un-stamps its orders so the earnings fall back into
     * "pending payout", reverses the payout journal entry (a no-op while the
     * GL is off) and marks the payout `cancelled`.
     */
    public function cancel(Request $request, Payout $payout)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if (! in_array($payout->status, ['pending', 'failed'], true)) {
            return $this->payoutResponse(false, 'Only pending or failed payouts can be cancelled. Processing, queued, partially paid or completed payouts cannot be reversed here.');
        }
        if ((float) $payout->paid_amount > 0.005) {
            return $this->payoutResponse(false, 'This payout already has a recorded payment — use the cash settlement flow instead of cancelling.');
        }
        if ((float) ($payout->tds_amount ?? 0) > 0 || (float) ($payout->tcs_amount ?? 0) > 0) {
            return $this->payoutResponse(false, 'This payout has tax withholding recorded and must be reversed by finance, not cancelled here.');
        }

        $result = DB::transaction(function () use ($payout, $request) {
            $payout->loadMissing(['restaurant.owner', 'driver']);

            // 1. Release only this payout's still-reserved amount to the wallet balance.
            $payee = $payout->driver_id ? $payout->driver : $payout->restaurant?->owner;
            $wallet = $payee ? Wallet::where('user_id', $payee->id)->lockForUpdate()->first() : null;
            $released = 0.0;
            if ($wallet) {
                $txns = WalletTransaction::where('reference_type', 'payout')
                    ->where('reference_id', $payout->id)
                    ->get(['type', 'amount']);
                $reserved = round(
                    (float) $txns->where('type', 'debit')->sum('amount')
                    - (float) $txns->where('type', 'credit')->sum('amount'),
                    2
                );
                $released = round(min(max(0, $reserved), (float) $wallet->locked_balance), 2);
                if ($released > 0) {
                    $wallet->decrement('locked_balance', $released);
                    $wallet->increment('balance', $released);
                    $wallet->refresh();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'user_id' => $wallet->user_id,
                        'type' => 'credit',
                        'amount' => $released,
                        'balance_after' => $wallet->balance,
                        'reference_type' => 'payout',
                        'reference_id' => $payout->id,
                        'description' => 'Payout cancelled — reservation released',
                        'created_by' => auth()->id(),
                        'meta' => ['source' => 'payout_cancelled', 'reason' => $request->reason],
                    ]);
                }
            }

            // 2. Un-stamp the orders so the earnings return to "pending payout".
            $orderIds = $payout->order_ids ?: [];
            if (! empty($orderIds)) {
                $column = $payout->restaurant_id ? 'restaurant_payout_id' : 'driver_payout_id';
                \App\Models\Order::whereIn('id', $orderIds)
                    ->where($column, $payout->id)
                    ->update([
                        $column => null,
                        'payout_status' => 'pending',
                        'payout_released_at' => null,
                    ]);
            }

            // 3. Reverse the payout journal entry (best-effort; GL is a no-op when off).
            try {
                $entry = \App\Models\JournalEntry::where('source_type', Payout::class)
                    ->where('source_id', $payout->id)
                    ->where('kind', 'payout')
                    ->with('lines.account')
                    ->first();
                if ($entry) {
                    $reversal = $entry->lines->map(fn ($l) => [
                        'account' => optional($l->account)->code,
                        'debit' => (float) $l->credit,
                        'credit' => (float) $l->debit,
                        'party_type' => $l->party_type,
                        'party_id' => $l->party_id,
                        'memo' => 'Reversal — payout cancelled',
                    ])->filter(fn ($l) => $l['account'])->values()->all();

                    if (count($reversal) >= 2) {
                        app(\App\Services\Accounting\LedgerPostingService::class)->post(
                            $reversal,
                            'Payout cancelled ' . $payout->uuid,
                            now(),
                            ['type' => Payout::class, 'id' => $payout->id, 'kind' => 'payout_cancelled']
                        );
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Payout cancel: journal reversal failed.', [
                    'payout_id' => $payout->id,
                    'message' => $e->getMessage(),
                ]);
            }

            // 4. Mark the payout cancelled.
            $payout->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelled by admin: ' . $request->reason,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            \App\Models\PayoutAuditLog::create([
                'payout_id' => $payout->id,
                'user_id' => auth()->id(),
                'action' => 'cancelled',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => [
                    'status' => 'cancelled',
                    'reason' => $request->reason,
                    'released_to_balance' => $released,
                    'orders_unstamped' => count($orderIds),
                ],
                'meta' => ['source' => 'payout_cancel'],
            ]);

            return $released;
        });

        return $this->payoutResponse(true, $result > 0
            ? 'Payout cancelled. ' . number_format($result, 2) . ' returned to the vendor wallet balance.'
            : 'Payout cancelled. No reserved funds were held for this payout.');
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

    public function show(Payout $payout, PayoutGatewayService $gatewayService)
    {
        $payout->loadMissing(['restaurant.owner', 'driver', 'bankAccount', 'auditLogs', 'failedAttempts']);

        $payee = $payout->driver_id ? $payout->driver : $payout->restaurant?->owner;
        $wallet = $payee ? Wallet::where('user_id', $payee->id)->first() : null;

        // How much of THIS payout is already reserved (debited to locked_balance)
        // at generation time, vs. what still has to come out of the free balance.
        $txns = WalletTransaction::where('reference_type', 'payout')
            ->where('reference_id', $payout->id)
            ->get(['type', 'amount']);
        $reserved = round(
            (float) $txns->where('type', 'debit')->sum('amount')
            - (float) $txns->where('type', 'credit')->sum('amount'),
            2
        );
        $outstanding = round((float) $payout->amount - (float) $payout->paid_amount, 2);
        $needsFromBalance = round(max(0, $outstanding - max(0, $reserved)), 2);
        $available = (float) ($wallet->balance ?? 0);
        $provider = $payout->gateway ?: PayoutSetting::activeGateway();

        return response()->json([
            'success' => true,
            'payout' => array_merge($payout->toArray(), [
                'recipient_name' => $payout->restaurant->name ?? $payout->driver->name ?? 'N/A',
                'order_ids' => $payout->order_ids ?: ($payout->gateway_response['order_ids'] ?? []),
            ]),
            'settlement' => [
                'payee_name' => $payee->name ?? 'N/A',
                'payee_type' => $payout->driver_id ? 'driver' : 'restaurant',
                'wallet_balance' => round($available, 2),
                'wallet_locked' => round((float) ($wallet->locked_balance ?? 0), 2),
                'wallet_total' => round($available + (float) ($wallet->locked_balance ?? 0), 2),
                'currency' => $payout->currency ?: AppSetting::getValue('currency_code', 'INR'),
                'payout_amount' => round((float) $payout->amount, 2),
                'deduction' => round((float) $payout->deduction_amount, 2),
                'paid_amount' => round((float) $payout->paid_amount, 2),
                'outstanding' => $outstanding,
                'already_reserved' => max(0, $reserved),
                'needs_from_balance' => $needsFromBalance,
                'can_fund' => $wallet !== null && $available + 0.01 >= $needsFromBalance,
                'shortfall' => round(max(0, $needsFromBalance - $available), 2),
                'gateway' => $provider,
                'gateway_automation' => $gatewayService->supportsAutomatedProcessing($provider),
            ],
        ]);
    }

    public function export()
    {
        $rows = Payout::with(['restaurant', 'driver'])->latest()->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Batch ID', 'Type', 'Vendor', 'Order IDs', 'Gross Amount',
                'Earning Commission', 'GST on Restaurant Commission', 'Online Payment Gateway Fee',
                'Delivery Settlement Base', 'Driver Earning Commission', 'Batch Bonus',
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
                    (float) $payout->admin_delivery_commission + (float) $payout->driver_deduction,
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
        if (! $settlementService->reserveFunds($payout, $restoredAmount, 'Revoked payout deduction reserved', 'deduction_revoke')) {
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
