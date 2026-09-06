<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FinancialStatementExport;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Tax\TaxConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly LedgerPostingService $ledger,
        private readonly TaxConfig $config,
    ) {
        $this->middleware(function ($request, $next) {
            abort_unless(
                $this->config->accountingEnabled() || $this->config->gstRegistered() || $this->config->tdsRegistered(),
                404
            );

            return $next($request);
        });
    }

    private function base(): array
    {
        return [
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'decimals' => AppSetting::currencyDecimals(),
            'accounting_on' => $this->config->accountingEnabled(),
        ];
    }

    private function asOf(Request $r): Carbon
    {
        return $r->filled('as_of') ? Carbon::parse($r->input('as_of'))->endOfDay() : now()->endOfDay();
    }

    private function range(Request $r): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $r->filled('to') ? Carbon::parse($r->input('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    public function chart()
    {
        return view('admin.accounting.gl.chart', $this->base() + [
            'accounts' => ChartOfAccount::orderBy('code')->get(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:12|unique:chart_of_accounts,code',
            'name' => 'required|string|max:150',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'subtype' => 'nullable|string|max:32',
        ]);
        ChartOfAccount::create($data + ['is_system' => false, 'is_active' => true]);

        return back()->with('success', 'Account ' . $data['code'] . ' added.');
    }

    public function journals(Request $request)
    {
        $entries = JournalEntry::query()
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->input('kind')))
            ->when($request->filled('manual'), fn ($q) => $q->where('is_manual', true))
            ->with('lines.account')
            ->latest('date')->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.accounting.gl.journals', $this->base() + [
            'entries' => $entries,
            'accounts' => ChartOfAccount::where('is_active', true)->orderBy('code')->get(),
            'filters' => $request->only(['kind', 'manual']),
        ]);
    }

    public function storeJournal(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'narration' => 'required|string|max:200',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:chart_of_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.memo' => 'nullable|string|max:150',
        ]);

        $codeById = ChartOfAccount::whereIn('id', collect($data['lines'])->pluck('account_id'))->pluck('code', 'id');
        $lines = collect($data['lines'])->map(fn ($l) => [
            'account' => $codeById[$l['account_id']] ?? null,
            'debit' => (float) ($l['debit'] ?? 0),
            'credit' => (float) ($l['credit'] ?? 0),
            'memo' => $l['memo'] ?? null,
        ])->all();

        $entry = $this->ledger->postManualJournal($lines, $data['narration'], $data['date']);

        return $entry
            ? back()->with('success', 'Journal ' . $entry->entry_no . ' posted.')
            : back()->with('error', 'Journal must balance (total debit = total credit) and have at least two lines.');
    }

    public function voidJournal(JournalEntry $journal)
    {
        abort_if($journal->is_manual === false, 403, 'Only manual journals can be voided.');
        $journal->update(['status' => 'void']);

        return back()->with('success', 'Journal voided.');
    }

    public function trialBalance(Request $request)
    {
        return view('admin.accounting.gl.trial-balance', $this->base() + [
            'as_of' => $this->asOf($request)->toDateString(),
            'report' => $this->statements->trialBalance($this->asOf($request)),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        return view('admin.accounting.gl.balance-sheet', $this->base() + [
            'as_of' => $this->asOf($request)->toDateString(),
            'report' => $this->statements->balanceSheet($this->asOf($request)),
        ]);
    }

    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.accounting.gl.profit-loss', $this->base() + [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'report' => $this->statements->profitAndLoss($from, $to),
        ]);
    }

    public function cashFlow(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.accounting.gl.cash-flow', $this->base() + [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'report' => $this->statements->cashFlow($from, $to),
        ]);
    }

    public function closePeriod(Request $request)
    {
        $data = $request->validate(['period' => 'required|string|size:7']);
        AccountingPeriod::updateOrCreate(
            ['fy' => $this->config->fy($data['period'] . '-01'), 'period' => $data['period']],
            ['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()],
        );

        return back()->with('success', 'Period ' . $data['period'] . ' closed.');
    }

    public function export(Request $request, string $doc)
    {
        $asOf = $this->asOf($request);
        [$from, $to] = $this->range($request);
        $tag = now()->format('Ymd');

        if ($request->input('format') === 'pdf' && in_array($doc, ['balance-sheet', 'profit-loss'], true)) {
            if ($doc === 'balance-sheet') {
                $pdf = Pdf::loadView('admin.accounting.gl.pdf.balance-sheet', [
                    'bs' => $this->statements->balanceSheetScheduleIII($asOf),
                ]);
            } else {
                $pdf = Pdf::loadView('admin.accounting.gl.pdf.profit-loss', [
                    'pl' => $this->statements->profitLossScheduleIII($from, $to),
                ]);
            }

            return $pdf->setPaper('a4')->download("$doc-$tag.pdf");
        }

        return match ($doc) {
            'balance-sheet' => Excel::download(new FinancialStatementExport('Balance Sheet', $this->statements->balanceSheetScheduleIII($asOf)), "balance-sheet-$tag.xlsx"),
            'profit-loss' => Excel::download(new FinancialStatementExport('Profit and Loss', $this->statements->profitLossScheduleIII($from, $to)), "profit-loss-$tag.xlsx"),
            'trial-balance' => Excel::download(new FinancialStatementExport('Trial Balance', $this->statements->trialBalance($asOf) + ['entity' => $this->statements->entity()]), "trial-balance-$tag.xlsx"),
            'cash-flow' => Excel::download(new FinancialStatementExport('Cash Flow', $this->statements->cashFlow($from, $to) + ['entity' => $this->statements->entity()]), "cash-flow-$tag.xlsx"),
            default => abort(404),
        };
    }
}
