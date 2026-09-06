<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Restaurant\Concerns\ResolvesRestaurantScope;
use App\Services\Ai\AiCostAwareRouter;
use App\Services\Ai\AiSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu performance analysis for the restaurant app.
 *
 *   GET /api/restaurant/menu/analytics?days=30&restaurant_id=
 *   -> { data: {
 *          period_days, totals: { items_sold, dishes, revenue },
 *          items: [ { id, name, qty, revenue, group: "best"|"low"|"mid", ai_suggestion } ]
 *      } }
 *
 * Groups: dishes are ranked by units sold; the top third are "best", the bottom
 * third "low", the rest "mid". Per-item AI suggestions are generated in a single
 * batched call and only when the admin AI Control Center has a key + kill switch off.
 */
class MenuAnalyticsController extends Controller
{
    use ResolvesRestaurantScope;

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiCostAwareRouter $router,
    ) {
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'No restaurant found.'], 404);
        }

        $days = (int) $request->input('days', 30);
        $days = max(7, min($days, 180));
        $since = now()->subDays($days);

        if (! Schema::hasTable('order_items') || ! Schema::hasTable('orders')) {
            return response()->json([
                'success' => true,
                'data' => ['period_days' => $days, 'totals' => ['items_sold' => 0, 'dishes' => 0, 'revenue' => 0], 'items' => []],
            ]);
        }

        $rows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where(function ($q) {
                $q->where('orders.payment_status', 'success')
                    ->orWhereIn('orders.payment_method', ['cod', 'cash', 'cash_on_delivery'])
                    ->orWhereIn('orders.delivery_payment_mode', ['cod', 'cash', 'cash_on_delivery']);
            })
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', ['cancelled'])
            ->groupBy('order_items.menu_item_id', 'menu_items.name')
            ->selectRaw('order_items.menu_item_id as id')
            ->selectRaw("COALESCE(menu_items.name, 'Menu item') as name")
            ->selectRaw('SUM(order_items.quantity) as qty')
            ->selectRaw('SUM(order_items.total_price) as revenue')
            ->orderByDesc('qty')
            ->get();

        $items = $rows->map(fn ($r) => [
            'id' => $r->id ? (int) $r->id : null,
            'name' => (string) $r->name,
            'qty' => (int) $r->qty,
            'revenue' => round((float) $r->revenue, 2),
            'group' => 'mid',
            'ai_suggestion' => '',
        ])->values()->all();

        $n = count($items);
        $third = (int) ceil($n / 3);
        for ($i = 0; $i < $n; $i++) {
            if ($i < $third) {
                $items[$i]['group'] = 'best';
            } elseif ($i >= $n - $third) {
                $items[$i]['group'] = 'low';
            }
        }

        $items = $this->attachAiSuggestions($restaurant->name, $days, $items);

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $days,
                'totals' => [
                    'items_sold' => array_sum(array_column($items, 'qty')),
                    'dishes' => $n,
                    'revenue' => round(array_sum(array_column($items, 'revenue')), 2),
                ],
                'items' => $items,
            ],
        ]);
    }

    /**
     * One batched AI call for the notable dishes (best + low). Optional — if the
     * AI is off or the call fails the suggestions simply stay empty and the app
     * shows its "enable the platform AI" hint.
     */
    private function attachAiSuggestions(string $restaurantName, int $days, array $items): array
    {
        if (! $this->settings->bool('ai_enabled') || empty($items)) {
            return $items;
        }

        $notable = array_values(array_filter($items, fn ($i) => $i['group'] !== 'mid'));
        if (empty($notable)) {
            return $items;
        }
        $notable = array_slice($notable, 0, 12);

        $payload = array_map(fn ($i) => [
            'name' => $i['name'],
            'qty' => $i['qty'],
            'revenue' => $i['revenue'],
            'group' => $i['group'],
        ], $notable);

        $prompt = 'You are a restaurant menu strategist for "' . $restaurantName . '". '
            . 'Below is per-dish sales over the last ' . $days . ' days. group "best" = top sellers, '
            . '"low" = underperformers. For EACH dish give one specific, practical suggestion '
            . '(max 22 words): for "best" how to upsell/bundle/raise margin, for "low" whether to '
            . 'reposition, re-photograph, bundle, discount, or remove. Return ONLY JSON: '
            . '{"suggestions":[{"name":string,"tip":string}, ...]}.' . "\n\nDISHES:\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->router->ask($prompt, ['feature' => 'menu_analytics'], ['temperature' => 0.5]);
        if (! ($result['success'] ?? false)) {
            return $items;
        }

        $decoded = json_decode($result['content'] ?? '', true);
        $tips = [];
        foreach ((array) ($decoded['suggestions'] ?? []) as $s) {
            if (is_array($s) && isset($s['name'])) {
                $tips[mb_strtolower(trim((string) $s['name']))] = (string) ($s['tip'] ?? '');
            }
        }

        foreach ($items as &$item) {
            $key = mb_strtolower(trim($item['name']));
            if (! empty($tips[$key])) {
                $item['ai_suggestion'] = $tips[$key];
            }
        }
        unset($item);

        return $items;
    }
}
