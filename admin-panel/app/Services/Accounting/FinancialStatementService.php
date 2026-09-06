<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Services\Tax\TaxConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read models over journal_lines. No stored balances -> nothing to drift.
 */
class FinancialStatementService
{
    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    /** account_id => ['debit'=>, 'credit'=>, 'balance'=> signed by normal side]. */
    private function balances(?Carbon $from, Carbon $to): Collection
    {
        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->whereDate('journal_entries.date', '<=', $to)
            ->groupBy('journal_lines.account_id')
            ->select('journal_lines.account_id', DB::raw('SUM(journal_lines.debit) as d'), DB::raw('SUM(journal_lines.credit) as c'))
            ->get()
            ->keyBy('account_id');

        return ChartOfAccount::orderBy('code')->get()->mapWithKeys(function (ChartOfAccount $a) use ($rows) {
            $d = (float) ($rows[$a->id]->d ?? 0);
            $c = (float) ($rows[$a->id]->c ?? 0);
            $bal = $a->isDebitNormal() ? $d - $c : $c - $d;

            return [$a->id => [
                'account' => $a,
                'debit' => round($d, 2),
                'credit' => round($c, 2),
                'balance' => round($bal, 2),
            ]];
        });
    }

    public function trialBalance(Carbon $asOf): array
    {
        $b = $this->balances(null, $asOf);
        $rows = $b->filter(fn ($r) => abs($r['debit'] - $r['credit']) > 0.005)
            ->map(fn ($r) => [
                'code' => $r['account']->code,
                'name' => $r['account']->name,
                'type' => $r['account']->type,
                'debit' => $r['account']->isDebitNormal() ? max(0, $r['balance']) : max(0, -$r['balance']),
                'credit' => $r['account']->isDebitNormal() ? max(0, -$r['balance']) : max(0, $r['balance']),
            ])->values()->all();

        return [
            'as_of' => $asOf->toDateString(),
            'rows' => $rows,
            'total_debit' => round(collect($rows)->sum('debit'), 2),
            'total_credit' => round(collect($rows)->sum('credit'), 2),
        ];
    }

    public function profitAndLoss(Carbon $from, Carbon $to): array
    {
        $b = $this->balances($from, $to);
        $group = fn (string $type) => $b->filter(fn ($r) => $r['account']->type === $type)
            ->map(fn ($r) => ['code' => $r['account']->code, 'name' => $r['account']->name, 'amount' => abs($r['balance'])])
            ->filter(fn ($r) => $r['amount'] > 0.005)->values()->all();

        $income = $group('income');
        $expense = $group('expense');
        $totalIncome = round(collect($income)->sum('amount'), 2);
        $totalExpense = round(collect($expense)->sum('amount'), 2);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'income' => $income,
            'expense' => $expense,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => round($totalIncome - $totalExpense, 2),
        ];
    }

    public function balanceSheet(Carbon $asOf): array
    {
        $b = $this->balances(null, $asOf);

        $group = fn (string $type) => $b->filter(fn ($r) => $r['account']->type === $type)
            ->map(fn ($r) => ['code' => $r['account']->code, 'name' => $r['account']->name, 'subtype' => $r['account']->subtype, 'amount' => $r['balance']])
            ->filter(fn ($r) => abs($r['amount']) > 0.005)->values()->all();

        $assets = $group('asset');
        $liabilities = $group('liability');
        $equity = $group('equity');

        // Retained earnings = FY-to-date net profit rolled into equity.
        $fyStart = Carbon::parse($this->config->fy($asOf) ? substr($this->config->fy($asOf), 0, 4) . '-' . str_pad((string) $this->config->fyStartMonth(), 2, '0', STR_PAD_LEFT) . '-01' : $asOf);
        $pl = $this->profitAndLoss($fyStart, $asOf);
        $equity[] = ['code' => '3900*', 'name' => 'Retained earnings (FY to date)', 'subtype' => 'retained_earnings', 'amount' => $pl['net_profit']];

        $totalAssets = round(collect($assets)->sum('amount'), 2);
        $totalLiabilities = round(collect($liabilities)->sum('amount'), 2);
        $totalEquity = round(collect($equity)->sum('amount'), 2);
        $diff = round($totalAssets - $totalLiabilities - $totalEquity, 2);

        if (abs($diff) > 0.005) {
            // Un-journalled remainder (bank/capital/opex not yet entered).
            $liabilities[] = ['code' => '1900*', 'name' => 'Unreconciled (needs manual journals)', 'subtype' => 'suspense', 'amount' => $diff];
            $totalLiabilities = round($totalLiabilities + $diff, 2);
        }

        return [
            'as_of' => $asOf->toDateString(),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities_equity' => round($totalLiabilities + $totalEquity, 2),
            'balanced' => abs(round($totalAssets - $totalLiabilities - $totalEquity, 2)) <= 0.005,
        ];
    }

    /**
     * Company identity block used on the statutory statement letterhead.
     */
    public function entity(): array
    {
        $g = fn (string $k, $d = null) => \App\Models\AppSetting::getValue($k, $d);
        $parts = array_filter([
            $g('business_reg_address') ?: $g('invoice_company_address'),
            trim(implode(', ', array_filter([$g('business_city'), $g('business_state'), $g('business_pincode')]))),
        ]);

        return [
            'name' => $g('business_legal_name') ?: ($g('invoice_company_name') ?: $g('app_name', 'Company')),
            'cin' => $g('business_cin'),
            'pan' => $g('business_pan'),
            'gstin' => $g('business_gstin') ?: $g('invoice_company_tax_id'),
            'address' => implode(' — ', $parts),
            'entity_type' => $this->config->entityType(),
            'currency' => \App\Models\AppSetting::sanitizedCurrencySymbol(),
        ];
    }

    /**
     * Balance Sheet grouped into Companies Act, 2013 Schedule III (Division I)
     * heads, with a note number per line and the note detail. Two comparative
     * columns (as-at and prior period-end).
     */
    public function balanceSheetScheduleIII(Carbon $asOf, ?Carbon $priorAsOf = null): array
    {
        $priorAsOf ??= $asOf->copy()->subYear();
        $cur = $this->balanceSheet($asOf);
        $prev = $this->balanceSheet($priorAsOf);

        $bySub = function (array $bs, string $group, array $subtypes): array {
            return collect($bs[$group])->filter(fn ($l) => in_array($l['subtype'], $subtypes, true))
                ->map(fn ($l) => ['name' => $l['name'], 'amount' => round((float) $l['amount'], 2)])->values()->all();
        };

        $notes = [];
        $n = 0;
        $line = function (string $label, array $curDetail, array $prevDetail) use (&$notes, &$n) {
            $n++;
            $cAmt = round(collect($curDetail)->sum('amount'), 2);
            $pAmt = round(collect($prevDetail)->sum('amount'), 2);
            $notes[] = ['no' => $n, 'title' => $label, 'current' => $curDetail, 'prior' => $prevDetail, 'current_total' => $cAmt, 'prior_total' => $pAmt];

            return ['label' => $label, 'note' => $n, 'current' => $cAmt, 'prior' => $pAmt];
        };

        $shareCapitalCur = $bySub($cur, 'equity', ['capital']);
        $shareCapitalPrev = $bySub($prev, 'equity', ['capital']);
        $reservesCur = collect($cur['equity'])->filter(fn ($l) => $l['subtype'] === 'retained_earnings')
            ->map(fn ($l) => ['name' => 'Surplus / (deficit) in Statement of Profit and Loss', 'amount' => round((float) $l['amount'], 2)])->values()->all();
        $reservesPrev = collect($prev['equity'])->filter(fn ($l) => $l['subtype'] === 'retained_earnings')
            ->map(fn ($l) => ['name' => 'Surplus / (deficit) in Statement of Profit and Loss', 'amount' => round((float) $l['amount'], 2)])->values()->all();

        $tradePayCur = $bySub($cur, 'liabilities', ['current_liability']);
        $tradePayPrev = $bySub($prev, 'liabilities', ['current_liability']);
        $otherCurLiabCur = collect($cur['liabilities'])->filter(fn ($l) => in_array($l['subtype'], ['tax_liability', 'suspense'], true))
            ->map(fn ($l) => ['name' => $l['name'], 'amount' => round((float) $l['amount'], 2)])->values()->all();
        $otherCurLiabPrev = collect($prev['liabilities'])->filter(fn ($l) => in_array($l['subtype'], ['tax_liability', 'suspense'], true))
            ->map(fn ($l) => ['name' => $l['name'], 'amount' => round((float) $l['amount'], 2)])->values()->all();

        $cashCur = $bySub($cur, 'assets', ['bank', 'cash']);
        $cashPrev = $bySub($prev, 'assets', ['bank', 'cash']);
        $recvCur = $bySub($cur, 'assets', ['receivable']);
        $recvPrev = $bySub($prev, 'assets', ['receivable']);
        $otherAssetCur = collect($cur['assets'])->filter(fn ($l) => in_array($l['subtype'], ['suspense'], true))
            ->map(fn ($l) => ['name' => $l['name'], 'amount' => round((float) $l['amount'], 2)])->values()->all();
        $otherAssetPrev = collect($prev['assets'])->filter(fn ($l) => in_array($l['subtype'], ['suspense'], true))
            ->map(fn ($l) => ['name' => $l['name'], 'amount' => round((float) $l['amount'], 2)])->values()->all();

        $eq = [
            $line('Share capital', $shareCapitalCur, $shareCapitalPrev),
            $line('Reserves and surplus', $reservesCur, $reservesPrev),
        ];
        $curLiab = [
            $line('Trade payables', $tradePayCur, $tradePayPrev),
            $line('Other current liabilities', $otherCurLiabCur, $otherCurLiabPrev),
        ];
        $curAssets = [
            $line('Trade and other receivables', $recvCur, $recvPrev),
            $line('Cash and cash equivalents', $cashCur, $cashPrev),
        ];
        if ($otherAssetCur || $otherAssetPrev) {
            $curAssets[] = $line('Other current assets', $otherAssetCur, $otherAssetPrev);
        }

        $sum = fn (array $lines, string $k) => round(collect($lines)->sum($k), 2);
        $equityTotal = ['current' => $sum($eq, 'current'), 'prior' => $sum($eq, 'prior')];
        $curLiabTotal = ['current' => $sum($curLiab, 'current'), 'prior' => $sum($curLiab, 'prior')];
        $curAssetTotal = ['current' => $sum($curAssets, 'current'), 'prior' => $sum($curAssets, 'prior')];

        return [
            'entity' => $this->entity(),
            'as_of' => $asOf->toDateString(),
            'prior_as_of' => $priorAsOf->toDateString(),
            'equity_and_liabilities' => [
                ['head' => "Shareholders' funds", 'lines' => $eq],
                ['head' => 'Current liabilities', 'lines' => $curLiab],
            ],
            'assets' => [
                ['head' => 'Current assets', 'lines' => $curAssets],
            ],
            'totals' => [
                'equity' => $equityTotal,
                'current_liabilities' => $curLiabTotal,
                'total_equity_liabilities' => ['current' => round($equityTotal['current'] + $curLiabTotal['current'], 2), 'prior' => round($equityTotal['prior'] + $curLiabTotal['prior'], 2)],
                'current_assets' => $curAssetTotal,
                'total_assets' => ['current' => $curAssetTotal['current'], 'prior' => $curAssetTotal['prior']],
            ],
            'notes' => $notes,
            'balanced' => $cur['balanced'],
        ];
    }

    /**
     * Statement of Profit and Loss in Schedule III (Division I) order, with
     * comparative prior-year column and supporting notes.
     */
    public function profitLossScheduleIII(Carbon $from, Carbon $to): array
    {
        $priorFrom = $from->copy()->subYear();
        $priorTo = $to->copy()->subYear();
        $cur = $this->profitAndLoss($from, $to);
        $prev = $this->profitAndLoss($priorFrom, $priorTo);

        $byName = fn (array $pl, string $key, callable $filter) => collect($pl[$key])->filter($filter)
            ->map(fn ($r) => ['name' => $r['name'], 'amount' => round((float) $r['amount'], 2)])->values()->all();

        $isOther = fn ($r) => str_contains(strtolower($r['name']), 'other income');
        $revOpsCur = $byName($cur, 'income', fn ($r) => ! $isOther($r));
        $revOpsPrev = $byName($prev, 'income', fn ($r) => ! $isOther($r));
        $otherIncCur = $byName($cur, 'income', $isOther);
        $otherIncPrev = $byName($prev, 'income', $isOther);
        $expCur = $byName($cur, 'expense', fn () => true);
        $expPrev = $byName($prev, 'expense', fn () => true);

        $s = fn (array $l) => round(collect($l)->sum('amount'), 2);
        $notes = [];
        $n = 0;
        $note = function (string $title, array $c, array $p) use (&$notes, &$n) {
            $n++;
            $notes[] = ['no' => $n, 'title' => $title, 'current' => $c, 'prior' => $p, 'current_total' => round(collect($c)->sum('amount'), 2), 'prior_total' => round(collect($p)->sum('amount'), 2)];

            return $n;
        };

        $revNote = $note('Revenue from operations', $revOpsCur, $revOpsPrev);
        $othNote = $note('Other income', $otherIncCur, $otherIncPrev);
        $expNote = $note('Expenses', $expCur, $expPrev);

        $revOps = ['current' => $s($revOpsCur), 'prior' => $s($revOpsPrev)];
        $othInc = ['current' => $s($otherIncCur), 'prior' => $s($otherIncPrev)];
        $totalRev = ['current' => round($revOps['current'] + $othInc['current'], 2), 'prior' => round($revOps['prior'] + $othInc['prior'], 2)];
        $totalExp = ['current' => $s($expCur), 'prior' => $s($expPrev)];
        $pbt = ['current' => round($totalRev['current'] - $totalExp['current'], 2), 'prior' => round($totalRev['prior'] - $totalExp['prior'], 2)];

        return [
            'entity' => $this->entity(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'prior_period' => ['from' => $priorFrom->toDateString(), 'to' => $priorTo->toDateString()],
            'rows' => [
                ['label' => 'Revenue from operations', 'note' => $revNote, 'current' => $revOps['current'], 'prior' => $revOps['prior']],
                ['label' => 'Other income', 'note' => $othNote, 'current' => $othInc['current'], 'prior' => $othInc['prior']],
                ['label' => 'Total revenue', 'total' => true, 'current' => $totalRev['current'], 'prior' => $totalRev['prior']],
                ['label' => 'Total expenses', 'note' => $expNote, 'current' => $totalExp['current'], 'prior' => $totalExp['prior']],
                ['label' => 'Profit / (loss) before tax', 'total' => true, 'current' => $pbt['current'], 'prior' => $pbt['prior']],
                ['label' => 'Tax expense', 'current' => 0.0, 'prior' => 0.0],
                ['label' => 'Profit / (loss) for the period', 'total' => true, 'current' => $pbt['current'], 'prior' => $pbt['prior']],
            ],
            'notes' => $notes,
        ];
    }

    public function cashFlow(Carbon $from, Carbon $to): array
    {
        $bankIds = ChartOfAccount::whereIn('subtype', ['bank', 'cash'])->pluck('id');

        $moves = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $bankIds)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.date', '>=', $from)
            ->whereDate('journal_entries.date', '<=', $to)
            ->get(['journal_lines.debit', 'journal_lines.credit', 'journal_entries.kind', 'journal_entries.narration']);

        $inflow = round((float) $moves->sum('debit'), 2);
        $outflow = round((float) $moves->sum('credit'), 2);

        $opening = $this->bankBalance($from->copy()->subDay());
        $closing = $this->bankBalance($to);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'opening_cash' => $opening,
            'inflows' => $inflow,
            'outflows' => $outflow,
            'net_change' => round($inflow - $outflow, 2),
            'closing_cash' => $closing,
            'by_activity' => $moves->groupBy(fn ($m) => $this->activity($m->kind))->map(fn ($g) => round((float) $g->sum('debit') - (float) $g->sum('credit'), 2)),
        ];
    }

    private function bankBalance(Carbon $asOf): float
    {
        $bankIds = ChartOfAccount::whereIn('subtype', ['bank', 'cash'])->pluck('id');

        $row = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $bankIds)
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.date', '<=', $asOf)
            ->selectRaw('SUM(debit) - SUM(credit) as bal')
            ->value('bal');

        return round((float) $row, 2);
    }

    private function activity(?string $kind): string
    {
        return match ($kind) {
            'payout', 'order_placed', 'order_delivered', 'cod_reconciled', 'refund', 'wallet_recharge' => 'operating',
            default => str_starts_with((string) $kind, 'tax_') ? 'operating' : 'financing',
        };
    }
}
