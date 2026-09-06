<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Restaurant\Concerns\ResolvesRestaurantScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Month-wise invoice listing for the restaurant app.
 *
 *   GET /api/restaurant/invoices?type=ordering|ads&months=12&restaurant_id=
 *   -> { data: { type, invoices: [ { id, label, period, amount, tax, total, download_url } ] } }
 *
 *   type=ordering : platform commission + commission-GST billed to the restaurant, per month
 *                   (derived from settled payouts)
 *   type=ads      : ad-wallet spend per month (derived from restaurant_ad_wallet_transactions)
 *
 * download_url is null until the PDF generator ships; the app shows the figures
 * and a "link not available yet" note rather than an empty tab.
 */
class RestaurantInvoicesController extends Controller
{
    use ResolvesRestaurantScope;

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }

        $type = $request->input('type', 'ordering') === 'ads' ? 'ads' : 'ordering';
        $months = max(1, min((int) $request->input('months', 12), 24));
        $since = now()->copy()->subMonths($months)->startOfMonth();

        $invoices = $type === 'ads'
            ? $this->adsInvoices($restaurant->id, $since)
            : $this->orderingInvoices($restaurant->id, $since);

        return response()->json([
            'success' => true,
            'data' => ['type' => $type, 'invoices' => $invoices],
        ]);
    }

    private function orderingInvoices(int $restaurantId, Carbon $since): array
    {
        if (! Schema::hasTable('payouts')) {
            return [];
        }

        $rows = DB::table('payouts')
            ->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->selectRaw('SUM(platform_commission) as commission')
            ->selectRaw('SUM(gst_on_commission) as commission_gst')
            ->selectRaw('SUM(gross_amount) as gross')
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->get();

        return $rows->map(function ($r) {
            $commission = round((float) $r->commission, 2);
            $tax = round((float) $r->commission_gst, 2);
            $month = Carbon::createFromFormat('Y-m', $r->ym)->startOfMonth();

            return [
                'id' => 'ORD-' . $r->ym,
                'label' => 'Ordering services · ' . $month->format('M Y'),
                'period' => $month->format('d M') . ' – ' . $month->copy()->endOfMonth()->format('d M Y'),
                'amount' => $commission,
                'tax' => $tax,
                'total' => round($commission + $tax, 2),
                'meta' => ['gross_sales' => round((float) $r->gross, 2)],
                'download_url' => null,
            ];
        })->values()->all();
    }

    private function adsInvoices(int $restaurantId, Carbon $since): array
    {
        if (! Schema::hasTable('restaurant_ad_wallet_transactions')) {
            return [];
        }

        $rows = DB::table('restaurant_ad_wallet_transactions')
            ->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $since)
            ->whereRaw('LOWER(type) IN (?, ?, ?, ?, ?)', ['debit', 'spend', 'charge', 'deduction', 'deduct'])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->selectRaw('SUM(ABS(amount)) as spend')
            ->selectRaw('COUNT(*) as txns')
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->get();

        return $rows->map(function ($r) {
            $spend = round((float) $r->spend, 2);
            $month = Carbon::createFromFormat('Y-m', $r->ym)->startOfMonth();

            return [
                'id' => 'ADS-' . $r->ym,
                'label' => 'Ad spend · ' . $month->format('M Y'),
                'period' => $month->format('d M') . ' – ' . $month->copy()->endOfMonth()->format('d M Y'),
                'amount' => $spend,
                'tax' => 0,
                'total' => $spend,
                'meta' => ['transactions' => (int) $r->txns],
                'download_url' => null,
            ];
        })->values()->all();
    }
}
