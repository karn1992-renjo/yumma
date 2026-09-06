<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BranchCollectionExport;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\User;
use App\Services\CodReconciliationService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CodManagementController extends Controller
{
    public function __construct(private CodReconciliationService $cod)
    {
    }

    public function index(Request $request)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $search = $request->filled('search') ? trim((string) $request->search) : null;

        $drivers = $this->cod->driverSummaries($branchId, $search);
        $totals = $this->cod->totals($branchId);
        $branches = Branch::orderBy('name')->get();

        return view('admin.cod.index', compact('drivers', 'totals', 'branches', 'branchId', 'search'));
    }

    public function settle(Request $request)
    {
        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'collected_amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $driver = User::role('delivery_partner')->findOrFail($data['driver_id']);
        $result = $this->cod->collectFromDriver(
            $driver,
            (float) $data['collected_amount'],
            $request->user(),
            $data['reference'] ?? null
        );

        return back()->with('success', $this->collectionMessage($result));
    }

    public function history(Request $request)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $driverId = $request->integer('driver_id') ?: null;
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        $transactions = $this->cod->historyQuery($branchId, $driverId, $dateFrom, $dateTo)
            ->paginate(25)
            ->withQueryString();
        $branches = Branch::orderBy('name')->get();
        $drivers = User::role('delivery_partner')->orderBy('name')->get(['id', 'name']);

        return view('admin.cod.history', compact('transactions', 'branches', 'drivers', 'branchId', 'driverId', 'dateFrom', 'dateTo'));
    }

    public function export(Request $request)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $driverId = $request->integer('driver_id') ?: null;
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        $rows = $this->cod->historyQuery($branchId, $driverId, $dateFrom, $dateTo)
            ->get()
            ->map(fn ($transaction) => [
                optional($transaction->created_at)->format('Y-m-d H:i:s'),
                $transaction->user?->name,
                $transaction->user?->branch?->name,
                $transaction->amount,
                $transaction->description,
                $transaction->creator?->name ?? 'System',
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Date',
            'Driver',
            'Branch',
            'Amount',
            'Description',
            'Settled By',
        ]), 'cod-reconciliation-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    private function collectionMessage(array $result): string
    {
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $decimals = AppSetting::currencyDecimals();
        $message = sprintf(
            'Collected %s%s. %s%s applied to COD balance across %d order(s). Remaining COD balance: %s%s.',
            $currencySymbol,
            number_format($result['amount'] ?? 0, $decimals),
            $currencySymbol,
            number_format($result['cod_applied_amount'] ?? 0, $decimals),
            $result['touched_orders'] ?? 0,
            $currencySymbol,
            number_format($result['balance_after'] ?? 0, $decimals)
        );

        if (($result['excess_credit_amount'] ?? 0) > 0) {
            $message .= sprintf(
                ' Extra %s%s credited to driver wallet.',
                $currencySymbol,
                number_format($result['excess_credit_amount'], $decimals)
            );
        }

        return $message;
    }
}
