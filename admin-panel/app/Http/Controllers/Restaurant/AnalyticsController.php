<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RestaurantSalesExport;
use App\Models\AppSetting;

class AnalyticsController extends Controller
{
    use ResolvesRestaurantContext;

    public function index(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        // Date range
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }
        
        // Check database driver
        $driver = DB::connection()->getDriverName();
        
        // Sales data
        if ($driver === 'sqlite') {
            $salesDataRaw = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )
                ->groupBy(DB::raw("strftime('%Y-%m-%d', created_at)"))
                ->orderBy('date')
                ->get();
        } else {
            $salesDataRaw = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        }

        $salesByDate = $salesDataRaw->keyBy(function ($row) {
            return Carbon::parse($row->date)->format('Y-m-d');
        });
        $salesData = collect();
        for ($date = $startDate->copy()->startOfDay(); $date->lte($endDate); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $row = $salesByDate->get($key);
            $salesData->push((object) [
                'date' => $key,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'avg_order_value' => (float) ($row->avg_order_value ?? 0),
            ]);
        }
        
        // Status breakdown
        $statusBreakdown = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        // Top selling items
        $topItems = MenuItem::with('category')
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('total_orders', 'desc')
            ->limit(10)
            ->get();
        
        // Hourly distribution - Fixed for SQLite
        if ($driver === 'sqlite') {
            $hourlyDataRaw = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("CAST(strftime('%H', created_at) AS INTEGER) as hour"),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue')
                )
                ->groupBy(DB::raw("strftime('%H', created_at)"))
                ->orderBy('hour')
                ->get();
        } else {
            $hourlyDataRaw = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue')
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        $ordersByHour = $hourlyDataRaw->keyBy(function ($row) {
            return (int) $row->hour;
        });
        $hourlyData = collect(range(0, 23))->map(function (int $hour) use ($ordersByHour) {
            $row = $ordersByHour->get($hour);

            return (object) [
                'hour' => $hour,
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
        });
        
        // Summary stats
        $summary = [
            'total_revenue' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->sum('total'),
            'total_orders' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'avg_order_value' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->avg('total'),
            'total_customers' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('customer_id')
                ->count('customer_id'),
            'cancellation_rate' => $this->calculateCancellationRate($restaurant->id, $startDate, $endDate),
        ];

        $currencySymbol = AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
        $currencyDecimals = AppSetting::currencyDecimals();
        $startDateValue = $startDate->format('Y-m-d');
        $endDateValue = $endDate->format('Y-m-d');
        $dateRangeLabel = $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y');
        $salesChartData = $salesData->map(function ($row) {
            return [
                'date' => Carbon::parse($row->date)->format('d M'),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'orders' => (int) ($row->orders ?? 0),
            ];
        })->values()->all();
        $statusChartData = $statusBreakdown->map(function ($row) {
            return [
                'status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
                'count' => (int) $row->count,
            ];
        })->values()->all();
        $hourlyChartData = $hourlyData->map(function ($row) {
            return [
                'hour' => str_pad((string) ((int) $row->hour), 2, '0', STR_PAD_LEFT) . ':00',
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
            ];
        })->values()->all();
        $totalRevenue = (float) ($summary['total_revenue'] ?? 0);
        $totalOrders = (int) ($summary['total_orders'] ?? 0);
        $avgOrderValue = (float) ($summary['avg_order_value'] ?? 0);
        $totalCustomers = (int) ($summary['total_customers'] ?? 0);
        $cancellationRate = (float) ($summary['cancellation_rate'] ?? 0);
        $kpiCards = [
            [
                'label' => 'Delivered Revenue',
                'value' => $currencySymbol . number_format($totalRevenue, $currencyDecimals),
                'note' => 'Completed order value',
                'icon' => 'rupee-sign',
                'tone' => 'success',
            ],
            [
                'label' => 'Total Orders',
                'value' => number_format($totalOrders),
                'note' => 'All statuses included',
                'icon' => 'shopping-bag',
                'tone' => 'primary',
            ],
            [
                'label' => 'Average Order',
                'value' => $currencySymbol . number_format($avgOrderValue, $currencyDecimals),
                'note' => 'Delivered order average',
                'icon' => 'receipt',
                'tone' => 'info',
            ],
            [
                'label' => 'Customers',
                'value' => number_format($totalCustomers),
                'note' => 'Unique ordering users',
                'icon' => 'users',
                'tone' => 'warning',
            ],
            [
                'label' => 'Cancellation',
                'value' => number_format($cancellationRate, 1) . '%',
                'note' => 'Cancelled share',
                'icon' => 'ban',
                'tone' => 'danger',
            ],
        ];
        $topItemRows = $topItems->map(function ($item, $index) use ($currencySymbol, $currencyDecimals) {
            $orders = (int) ($item->total_orders ?? 0);
            $unitPrice = (float) ($item->discounted_price ?: $item->price ?: 0);

            return [
                'rank' => $index + 1,
                'name' => $item->name,
                'orders' => $orders,
                'category' => $item->category ? $item->category->name : null,
                'image' => $item->image_url,
                'estimated_revenue' => $currencySymbol . number_format($unitPrice * $orders, $currencyDecimals),
            ];
        })->values()->all();
        $promotionPerformance = $this->buildPromotionPerformance(
            $restaurant->id,
            $startDate,
            $endDate,
            $currencySymbol,
            $currencyDecimals
        );
        
        return view('restaurant.analytics.index', compact(
            'salesData', 'statusBreakdown', 'topItems', 
            'hourlyData', 'summary', 'startDate', 'endDate',
            'currencySymbol', 'currencyDecimals', 'startDateValue',
            'endDateValue', 'dateRangeLabel', 'salesChartData',
            'statusChartData', 'hourlyChartData', 'kpiCards',
            'topItemRows', 'promotionPerformance'
        ));
    }

    private function buildPromotionPerformance($restaurantId, Carbon $startDate, Carbon $endDate, string $currencySymbol, int $currencyDecimals): array
    {
        $totalPromotions = 0;
        $activePromotions = 0;
        $topPromos = collect();

        if (Schema::hasTable('promo_codes')) {
            $totalPromotions = PromoCode::where('restaurant_id', $restaurantId)->count();
            $activePromotions = PromoCode::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->where(function ($query) use ($endDate) {
                    $query->whereNull('start_date')->orWhere('start_date', '<=', $endDate);
                })
                ->where(function ($query) use ($startDate) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                })
                ->count();
        }

        $usageCount = 0;
        $discountGiven = 0.0;

        if (Schema::hasTable('promotion_usage')) {
            $usageRows = DB::table('promotion_usage')
                ->leftJoin('promotions', 'promotion_usage.promotion_id', '=', 'promotions.id')
                ->where('promotion_usage.restaurant_id', $restaurantId)
                ->whereBetween('promotion_usage.created_at', [$startDate, $endDate])
                ->groupBy('promotion_usage.promotion_id', 'promotion_usage.coupon_code', 'promotions.title')
                ->selectRaw('promotion_usage.promotion_id, promotion_usage.coupon_code, promotions.title')
                ->selectRaw('COUNT(DISTINCT promotion_usage.order_id) as usage_count')
                ->selectRaw('SUM(promotion_usage.discount_amount) as discount_given')
                ->orderByDesc('usage_count')
                ->limit(5)
                ->get();

            $usageCount = (int) DB::table('promotion_usage')
                ->where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('order_id')
                ->count('order_id');

            $discountGiven = round((float) DB::table('promotion_usage')
                ->where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('discount_amount'), 2);

            $legacyNames = Schema::hasTable('promo_codes')
                ? PromoCode::where('restaurant_id', $restaurantId)
                    ->get(['code', 'title'])
                    ->mapWithKeys(fn ($promo) => [strtoupper((string) $promo->code) => $promo->title ?: $promo->code])
                : collect();

            $topPromos = $usageRows->map(function ($row) use ($legacyNames, $currencySymbol, $currencyDecimals) {
                $code = $row->coupon_code ?: 'Auto promotion';
                $lookup = strtoupper((string) $code);
                $discount = round((float) $row->discount_given, 2);

                return [
                    'title' => $row->title ?: ($legacyNames[$lookup] ?? $code),
                    'code' => $row->coupon_code,
                    'usage_count' => (int) $row->usage_count,
                    'discount_given' => $discount,
                    'discount_label' => $currencySymbol . number_format($discount, $currencyDecimals),
                ];
            });
        }

        if ($usageCount === 0) {
            $discountedOrders = Order::where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('discount', '>', 0)
                ->whereNotIn('status', ['cancelled'])
                ->get(['discount']);
            $usageCount = (int) $discountedOrders->count();
            $discountGiven = round((float) $discountedOrders->sum('discount'), 2);
        }

        if ($topPromos->isEmpty() && Schema::hasTable('promo_codes')) {
            $topPromos = PromoCode::where('restaurant_id', $restaurantId)
                ->orderByDesc('used_count')
                ->limit(5)
                ->get()
                ->map(function (PromoCode $promo) use ($currencySymbol, $currencyDecimals) {
                    return [
                        'title' => $promo->title ?: $promo->code,
                        'code' => $promo->code,
                        'usage_count' => (int) ($promo->used_count ?? 0),
                        'discount_given' => 0.0,
                        'discount_label' => $currencySymbol . number_format(0, $currencyDecimals),
                    ];
                });
        }

        $avgDiscount = $usageCount > 0 ? round($discountGiven / $usageCount, 2) : 0;

        return [
            'total_promotions' => (int) $totalPromotions,
            'active_promotions' => (int) $activePromotions,
            'coupon_orders' => (int) $usageCount,
            'discount_given' => $discountGiven,
            'discount_given_label' => $currencySymbol . number_format($discountGiven, $currencyDecimals),
            'avg_discount' => $avgDiscount,
            'avg_discount_label' => $currencySymbol . number_format($avgDiscount, $currencyDecimals),
            'top_promos' => $topPromos->values()->all(),
        ];
    }
    
    private function calculateCancellationRate($restaurantId, $startDate, $endDate)
    {
        $totalOrders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $cancelledOrders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'cancelled')
            ->count();
            
        if ($totalOrders == 0) return 0;
        
        return round(($cancelledOrders / $totalOrders) * 100, 2);
    }
    
    public function export(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        $startDate = $request->start_date ?? now()->subDays(30);
        $endDate = $request->end_date ?? now();
        
        return Excel::download(
            new RestaurantSalesExport($restaurant->id, $startDate, $endDate),
            'sales-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
