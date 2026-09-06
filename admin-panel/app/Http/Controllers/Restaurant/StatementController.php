<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\TaxLedgerEntry;
use App\Models\TdsDeducteeTotal;
use App\Services\Tax\TaxConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * The restaurant's own tax-aware settlement view: gross sales, commission + 18%
 * GST (claimable as ITC), TDS 194-O, TCS, net payout — per cycle and per FY —
 * plus Form 16A. Only meaningful when the platform runs GST invoicing.
 */
class StatementController extends Controller
{
    public function __construct(private readonly TaxConfig $config)
    {
    }

    public function index(Request $request)
    {
        $restaurant = $this->restaurant();
        [$from, $to] = $this->range($request);

        $payouts = Payout::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $agg = Payout::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('count(*) as cycles, sum(gross_amount) as gross, sum(platform_commission) as commission, sum(gst_on_commission) as commission_gst, sum(tds_amount) as tds, sum(tcs_amount) as tcs, sum(net_amount) as net')
            ->first();

        // GST the ECO paid on this restaurant's food under Sec 9(5) — informational.
        $eco95 = TaxLedgerEntry::where('kind', TaxLedgerEntry::KIND_GST_9_5)
            ->whereHas('order', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $fy = $this->config->fy($to);
        $tds194o = TdsDeducteeTotal::where('party_type', \App\Models\Restaurant::class)
            ->where('party_id', $restaurant->id)
            ->where('section', '194O')
            ->where('fy', $fy)
            ->first();

        return view('restaurant.statements.index', [
            'restaurant' => $restaurant,
            'payouts' => $payouts,
            'agg' => $agg,
            'eco95' => (float) $eco95,
            'tds194o' => $tds194o,
            'fy' => $fy,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'decimals' => AppSetting::currencyDecimals(),
            'gstOn' => $this->config->gstEnabled(),
        ]);
    }

    public function form16a(Request $request)
    {
        $restaurant = $this->restaurant();
        $fy = $request->input('fy') ?: $this->config->fy(now());

        $cert = app(\App\Services\Tax\TaxReportService::class)
            ->form16aCertificate('194O', $fy, \App\Models\Restaurant::class, (int) $restaurant->id);

        abort_unless($cert, 404, 'No TDS was deducted for this financial year.');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('documents.form16a', [
            'cert' => $cert,
            'sym' => AppSetting::sanitizedCurrencySymbol(),
            'deductor' => $this->deductorBlock(),
        ])->setPaper('a4');

        return $pdf->download('form-16a-' . $restaurant->id . '-' . $fy . '.pdf');
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

    private function restaurant()
    {
        $user = Auth::user();

        return $user->current_restaurant_id
            ? $user->restaurants()->findOrFail($user->current_restaurant_id)
            : $user->restaurants()->firstOrFail();
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
