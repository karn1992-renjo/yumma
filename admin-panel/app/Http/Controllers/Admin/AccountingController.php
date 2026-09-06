<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Form26qExport;
use App\Exports\Gstr1Export;
use App\Exports\Gstr3bExport;
use App\Exports\Gstr8Export;
use App\Exports\TaxLedgerExport;
use App\Exports\TaxSettlementExport;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\TaxLedgerEntry;
use App\Services\Tax\TaxConfig;
use App\Services\Tax\TaxEntityResolver;
use App\Services\Tax\TaxReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountingController extends Controller
{
    public function __construct(
        private readonly TaxReportService $reports,
        private readonly TaxConfig $config,
    ) {
        $this->middleware(function ($request, $next) {
            abort_unless(
                $this->config->gstRegistered() || $this->config->tdsRegistered() || $this->config->accountingEnabled(),
                404
            );

            return $next($request);
        });
    }

    private function ctx(Request $request): array
    {
        [$from, $to] = $this->reports->range($request->input('from'), $request->input('to'));

        return [$from, $to, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'decimals' => AppSetting::currencyDecimals(),
        ]];
    }

    public function overview(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        return view('admin.accounting.overview', $base + [
            'overview' => $this->reports->overview($from, $to),
        ]);
    }

    public function gst(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        return view('admin.accounting.gst', $base + [
            'gstr1' => $this->reports->gstr1($from, $to),
            'gstr3b' => $this->reports->gstr3b($from, $to),
            'gstr8' => $this->reports->gstr8($from, $to),
        ]);
    }

    public function tds(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        return view('admin.accounting.tds', $base + [
            's194o' => $this->reports->tdsStatement('194O', $from, $to),
            's194c' => $this->reports->tdsStatement('194C', $from, $to),
            'tan' => $this->config->tan(),
        ]);
    }

    public function tcs(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        return view('admin.accounting.tcs', $base + [
            'gstr8' => $this->reports->gstr8($from, $to),
        ]);
    }

    public function settlements(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);
        $type = $request->input('type') === 'driver' ? 'driver' : 'restaurant';

        return view('admin.accounting.settlements', $base + [
            'type' => $type,
            'settlement' => $this->reports->settlements($type, $from, $to),
        ]);
    }

    public function ledger(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        $entries = TaxLedgerEntry::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->input('kind')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.accounting.ledger', $base + [
            'entries' => $entries,
            'kinds' => [
                TaxLedgerEntry::KIND_GST_9_5, TaxLedgerEntry::KIND_GST_SERVICE, TaxLedgerEntry::KIND_GST_COMMISSION,
                TaxLedgerEntry::KIND_TCS, TaxLedgerEntry::KIND_TDS_194O, TaxLedgerEntry::KIND_TDS_194C,
            ],
            'filters' => $request->only(['kind', 'status']),
        ]);
    }

    public function documents(Request $request)
    {
        [, , $base] = $this->ctx($request);

        return view('admin.accounting.documents', $base);
    }

    public function compliance(Request $request)
    {
        [, , $base] = $this->ctx($request);

        $items = \App\Models\ComplianceItem::orderBy('category')->orderBy('name')->get()
            ->filter(fn ($i) => $i->appliesToBusiness($this->config))
            ->values();

        return view('admin.accounting.compliance', $base + [
            'items' => $items,
            'grouped' => $items->groupBy('category'),
            'entityType' => $this->config->entityType(),
        ]);
    }

    public function complianceExport(Request $request)
    {
        $items = \App\Models\ComplianceItem::orderBy('category')->orderBy('name')->get()
            ->filter(fn ($i) => $i->appliesToBusiness($this->config))
            ->values();

        $entity = app(\App\Services\Accounting\FinancialStatementService::class)->entity();
        $tag = now()->format('Ymd');

        if ($request->input('format') === 'pdf') {
            $forms = [
                'GSTR1' => 'GSTR-1', 'GSTR3B' => 'GSTR-3B', 'GSTR8' => 'GSTR-8', 'GSTR9' => 'GSTR-9', 'GSTR9C' => 'GSTR-9C',
                'TDS26Q' => 'Form 26Q', 'FORM16A' => 'Form 16A', 'TDS_CHALLAN' => 'ITNS-281', 'ITR' => 'ITR-6 / ITR-5',
                'TAX_AUDIT' => 'Form 3CA-3CD', 'ROC_AOC4' => 'Form AOC-4', 'ROC_MGT7' => 'Form MGT-7 / 7A', 'DIR3_KYC' => 'DIR-3 KYC',
                'LLP_FORM8' => 'LLP Form 8', 'LLP_FORM11' => 'LLP Form 11', 'PF' => 'ECR', 'ESIC' => 'ESIC Return', 'ADVANCE_TAX' => 'Challan 280',
            ];

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.accounting.pdf.compliance-register', [
                'entity' => $entity,
                'grouped' => $items->groupBy('category'),
                'forms' => $forms,
            ])->setPaper('a4', 'landscape')->download("compliance-calendar-$tag.pdf");
        }

        return Excel::download(new \App\Exports\ComplianceRegisterExport($items, $entity), "compliance-calendar-$tag.xlsx");
    }

    public function updateCompliance(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:compliance_items,id',
            'status' => 'required|in:not_applicable,pending,filed,overdue',
            'period' => 'nullable|string|max:7',
            'due_date' => 'nullable|date',
            'filed_on' => 'nullable|date',
            'reference_no' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:1000',
        ]);

        \App\Models\ComplianceItem::whereKey($data['id'])->update([
            'status' => $data['status'],
            'period' => $data['period'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'filed_on' => $data['filed_on'] ?: ($data['status'] === 'filed' ? now()->toDateString() : null),
            'reference_no' => $data['reference_no'] ?: null,
            'notes' => $data['notes'] ?: null,
        ]);

        return back()->with('success', 'Compliance item updated.');
    }

    public function cess(Request $request)
    {
        [$from, $to, $base] = $this->ctx($request);

        $rows = TaxLedgerEntry::query()
            ->where('kind', TaxLedgerEntry::KIND_GIG_CESS)
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $byPeriod = TaxLedgerEntry::query()
            ->where('kind', TaxLedgerEntry::KIND_GIG_CESS)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("period, count(*) as orders, sum(amount) as amount, sum(case when status = 'filed' then amount else 0 end) as remitted")
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->get();

        return view('admin.accounting.cess', $base + [
            'rows' => $rows,
            'byPeriod' => $byPeriod,
            'enabled' => $this->config->gigCessEnabled(),
            'rate' => $this->config->gigCessRate(),
            'cessBase' => $this->config->gigCessBase(),
        ]);
    }

    public function markFiled(Request $request)
    {
        $data = $request->validate([
            'kind' => 'required|string',
            'period' => 'required|string|size:7',
            'challan_no' => 'nullable|string|max:40',
            'challan_date' => 'nullable|date',
        ]);

        TaxLedgerEntry::where('kind', $data['kind'])
            ->where('period', $data['period'])
            ->update([
                'status' => 'filed',
                'challan_no' => $data['challan_no'] ?: null,
                'challan_date' => $data['challan_date'] ?: null,
            ]);

        return back()->with('success', 'Marked ' . $data['kind'] . ' for ' . $data['period'] . ' as filed.');
    }

    /* ---------------- exports ---------------- */

    public function export(Request $request, string $doc)
    {
        [$from, $to] = $this->reports->range($request->input('from'), $request->input('to'));
        $tag = $from->format('Ymd') . '-' . $to->format('Ymd');

        return match ($doc) {
            'gstr1' => Excel::download(new Gstr1Export($this->reports->gstr1($from, $to), $from, $to), "gstr1-$tag.xlsx"),
            'gstr3b' => Excel::download(new Gstr3bExport($this->reports->gstr3b($from, $to)), "gstr3b-$tag.xlsx"),
            'gstr8' => Excel::download(new Gstr8Export($this->reports->gstr8($from, $to)), "gstr8-$tag.xlsx"),
            'form26q' => $this->form26q($from, $to, $tag),
            'ledger' => Excel::download(new TaxLedgerExport(
                TaxLedgerEntry::whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ), "tax-ledger-$tag.xlsx"),
            'settlement-restaurant' => Excel::download(new TaxSettlementExport($this->reports->settlements('restaurant', $from, $to)), "settlements-restaurant-$tag.xlsx"),
            'settlement-driver' => Excel::download(new TaxSettlementExport($this->reports->settlements('driver', $from, $to)), "settlements-driver-$tag.xlsx"),
            'form16a-194o' => $this->form16aBooklet('194O', $request),
            'form16a-194c' => $this->form16aBooklet('194C', $request),
            'gstr1-json' => response()->json($this->reports->gstr1($from, $to)),
            'gstr3b-json' => response()->json($this->reports->gstr3b($from, $to)),
            'gstr8-json' => response()->json($this->reports->gstr8($from, $to)),
            default => abort(404),
        };
    }

    /**
     * A print-ready Form 16A booklet: one certificate per deductee for the FY
     * (or a single certificate when ?party_id + ?party_type are given).
     */
    private function form16aBooklet(string $section, Request $request)
    {
        $fy = $request->input('fy') ?: $this->config->fy(now());
        $deductor = $this->deductorBlock();
        $sym = AppSetting::sanitizedCurrencySymbol();

        $totals = \App\Models\TdsDeducteeTotal::where('section', $section === '194C' ? '194C' : '194O')
            ->where('fy', $fy)
            ->when($request->filled('party_id'), fn ($q) => $q->where('party_id', $request->integer('party_id')))
            ->get();

        $certs = [];
        foreach ($totals as $t) {
            $c = $this->reports->form16aCertificate($section, $fy, $t->party_type ?: \App\Models\Restaurant::class, (int) $t->party_id);
            if ($c) {
                $certs[] = $c;
            }
        }

        abort_if($certs === [], 404, 'No TDS deducted under this section for ' . $fy . '.');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('documents.form16a-booklet', [
            'certs' => $certs,
            'deductor' => $deductor,
            'sym' => $sym,
        ])->setPaper('a4');

        return $pdf->download("form16a-{$section}-{$fy}.pdf");
    }

    private function deductorBlock(): array
    {
        return [
            'name' => AppSetting::getValue('business_legal_name') ?: AppSetting::getValue('app_name', 'Platform'),
            'tan' => $this->config->tan(),
            'pan' => AppSetting::getValue('business_pan'),
            'address' => trim(implode(', ', array_filter([
                AppSetting::getValue('business_reg_address'),
                AppSetting::getValue('business_city'),
                AppSetting::getValue('business_state'),
                AppSetting::getValue('business_pincode'),
            ]))),
            'cit_tds' => AppSetting::getValue('business_cit_tds'),
            'responsible_person' => AppSetting::getValue('invoice_authorised_signatory'),
            'designation' => AppSetting::getValue('business_signatory_designation', 'Authorised Signatory'),
            'place' => AppSetting::getValue('business_city'),
        ];
    }

    private function form26q($from, $to, string $tag)
    {
        $eco = app(TaxEntityResolver::class)->ecoEntity();

        return Excel::download(new Form26qExport(
            $this->reports->tdsStatement('194O', $from, $to),
            $this->reports->tdsStatement('194C', $from, $to),
            [
                'tan' => $eco->tan,
                'pan' => $eco->pan,
                'name' => $eco->name,
                'fy' => $this->config->fy($to),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ), "form26q-$tag.xlsx");
    }
}
