<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\OrderItem;
use Carbon\Carbon;

/**
 * Deterministic, rule-based item-wise pricing analysis -- no LLM call, since
 * comparing windows of real order data is a mechanical calculation, not
 * creative judgment (same cost-conscious pattern as CartRecoveryService /
 * ReorderNudgeService). Aggregates App\Models\OrderItem (normalized per-line
 * quantity/price) rather than Order.items JSON so real SQL aggregation works.
 *
 * The analysis genuinely looks both ways -- it can propose an increase OR a
 * decrease -- by combining three signals per item:
 *   1. demand trend      (unit volume, recent 14d vs prior 14d)
 *   2. revenue trend      (line revenue, same windows)
 *   3. price position     (this item's price vs the median of its category)
 * and only surfaces items where the signals agree on a direction.
 */
class MenuDemandAnalysisService
{
    private const WINDOW_DAYS = 14;
    private const MIN_ORDERS_FOR_SIGNAL = 5;

    // Demand-trend bands.
    private const DEMAND_UP_PERCENT = 20.0;
    private const DEMAND_DOWN_PERCENT = -20.0;

    // How far off the category median counts as "mispriced".
    private const PRICE_HIGH_PERCENT = 12.0;
    private const PRICE_LOW_PERCENT = -12.0;

    // Adjustment sizing (clamped again downstream by ai_menu_price_max_change_percent).
    private const MIN_ADJUST_PERCENT = 4.0;
    private const MAX_ADJUST_PERCENT = 12.0;

    public function suggestionsForRestaurant(int $restaurantId, int $limit = 3): array
    {
        $items = MenuItem::where('restaurant_id', $restaurantId)
            ->get(['id', 'name', 'price', 'category_id']);
        if ($items->isEmpty()) {
            return [];
        }

        $recentQty = $this->aggregateByItem($restaurantId, now()->subDays(self::WINDOW_DAYS), now(), 'qty');
        $priorQty = $this->aggregateByItem($restaurantId, now()->subDays(self::WINDOW_DAYS * 2), now()->subDays(self::WINDOW_DAYS), 'qty');
        $recentRev = $this->aggregateByItem($restaurantId, now()->subDays(self::WINDOW_DAYS), now(), 'revenue');
        $priorRev = $this->aggregateByItem($restaurantId, now()->subDays(self::WINDOW_DAYS * 2), now()->subDays(self::WINDOW_DAYS), 'revenue');

        $categoryMedian = $this->categoryMedianPrices($items);

        $suggestions = [];

        foreach ($items as $item) {
            $recent = $recentQty[$item->id] ?? 0;
            $prior = $priorQty[$item->id] ?? 0;

            if ($recent < self::MIN_ORDERS_FOR_SIGNAL && $prior < self::MIN_ORDERS_FOR_SIGNAL) {
                continue; // not enough data to say anything
            }

            $demandChange = $this->pctChange($recent, $prior);
            $revenueChange = $this->pctChange($recentRev[$item->id] ?? 0.0, $priorRev[$item->id] ?? 0.0);

            $currentPrice = (float) $item->price;
            $median = $categoryMedian[$item->category_id ?? 0] ?? null;
            $priceVsMedian = ($median && $median > 0)
                ? round((($currentPrice - $median) / $median) * 100, 1)
                : null;

            [$direction, $strength, $driver] = $this->decide($demandChange, $revenueChange, $priceVsMedian);
            if ($direction === null) {
                continue;
            }

            // Adjustment size scales with how strong the signal is.
            $adjust = round(
                self::MIN_ADJUST_PERCENT
                + ($strength * (self::MAX_ADJUST_PERCENT - self::MIN_ADJUST_PERCENT)),
                1
            );
            $suggestedPrice = $direction === 'increase'
                ? round($currentPrice * (1 + $adjust / 100), 2)
                : round($currentPrice * (1 - $adjust / 100), 2);

            if ($suggestedPrice <= 0 || abs($suggestedPrice - $currentPrice) < 0.5) {
                continue;
            }

            $suggestions[] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'current_price' => $currentPrice,
                'suggested_price' => $suggestedPrice,
                'direction' => $direction,
                'adjust_percent' => $adjust,
                'demand_change_percent' => round($demandChange, 1),
                'revenue_change_percent' => round($revenueChange, 1),
                'price_vs_category_median_percent' => $priceVsMedian,
                'recent_orders' => $recent,
                'prior_orders' => $prior,
                'signal_strength' => round($strength, 2),
                'reason' => $this->reasonText($direction, $driver, $demandChange, $revenueChange, $priceVsMedian, $recent, $prior),
            ];
        }

        // Strongest, most confident signals first.
        usort($suggestions, fn ($a, $b) => $b['signal_strength'] <=> $a['signal_strength']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * @return array{0: ?string, 1: float, 2: string} [direction|null, strength 0..1, driver]
     */
    private function decide(float $demandChange, float $revenueChange, ?float $priceVsMedian): array
    {
        $strongUp = $demandChange >= self::DEMAND_UP_PERCENT;
        $strongDown = $demandChange <= self::DEMAND_DOWN_PERCENT;
        $pricedHigh = $priceVsMedian !== null && $priceVsMedian >= self::PRICE_HIGH_PERCENT;
        $pricedLow = $priceVsMedian !== null && $priceVsMedian <= self::PRICE_LOW_PERCENT;

        // --- INCREASE: demand rising and there's headroom vs peers ---
        if ($strongUp && ! $pricedHigh) {
            $strength = $this->clamp01(
                (abs($demandChange) / 100) * 0.6
                + (max(0, $revenueChange) / 100) * 0.2
                + ($pricedLow ? 0.2 : 0.0)
            );

            return ['increase', $strength, 'demand_up'];
        }

        // Selling steadily but clearly underpriced vs the category.
        if ($pricedLow && $demandChange > self::DEMAND_DOWN_PERCENT && $revenueChange >= -5) {
            return ['increase', $this->clamp01(abs($priceVsMedian) / 60 + 0.15), 'underpriced'];
        }

        // --- DECREASE: demand falling, or clearly overpriced vs peers ---
        if ($strongDown && $revenueChange <= 0) {
            $strength = $this->clamp01(
                (abs($demandChange) / 100) * 0.6
                + (abs(min(0, $revenueChange)) / 100) * 0.2
                + ($pricedHigh ? 0.2 : 0.0)
            );

            return ['decrease', $strength, 'demand_down'];
        }

        // Flat/soft demand and clearly overpriced vs the category -> competitiveness cut.
        if ($pricedHigh && $demandChange < self::DEMAND_UP_PERCENT) {
            return ['decrease', $this->clamp01(abs($priceVsMedian) / 60 + 0.15), 'overpriced'];
        }

        return [null, 0.0, 'none'];
    }

    private function reasonText(
        string $direction,
        string $driver,
        float $demandChange,
        float $revenueChange,
        ?float $priceVsMedian,
        int $recent,
        int $prior
    ): string {
        $window = self::WINDOW_DAYS;
        $priceNote = $priceVsMedian === null
            ? ''
            : sprintf(' Priced %s%.0f%% vs the category median.', $priceVsMedian >= 0 ? '+' : '', $priceVsMedian);

        return match ($driver) {
            'demand_up' => sprintf(
                'Orders up %.0f%% over %d days (%d vs %d) and revenue %s %.0f%%. Demand can absorb a modest price rise.%s',
                $demandChange, $window, $recent, $prior,
                $revenueChange >= 0 ? 'up' : 'down', abs($revenueChange), $priceNote
            ),
            'underpriced' => sprintf(
                'Steady demand (%d orders in %d days) but the item sits well below its category on price.%s Room to recover margin.',
                $recent, $window, $priceNote
            ),
            'demand_down' => sprintf(
                'Orders down %.0f%% over %d days (%d vs %d) and revenue down %.0f%%. A small price cut may help demand and revenue recover.%s',
                abs($demandChange), $window, $recent, $prior, abs(min(0, $revenueChange)), $priceNote
            ),
            'overpriced' => sprintf(
                'Demand is flat/soft while the item is priced well above its category.%s A cut should improve competitiveness.',
                $priceNote
            ),
            default => sprintf('Demand change %.0f%% over %d days.', $demandChange, $window),
        };
    }

    private function pctChange(float $recent, float $prior): float
    {
        if ($prior > 0) {
            return (($recent - $prior) / $prior) * 100;
        }

        return $recent > 0 ? 100.0 : 0.0;
    }

    private function clamp01(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     * @return array<int, float> category_id => median price
     */
    private function categoryMedianPrices($items): array
    {
        $byCategory = [];
        foreach ($items as $item) {
            $cid = (int) ($item->category_id ?? 0);
            $price = (float) $item->price;
            if ($price > 0) {
                $byCategory[$cid][] = $price;
            }
        }

        $medians = [];
        foreach ($byCategory as $cid => $prices) {
            if (count($prices) < 3) {
                continue; // need a few peers for a meaningful median
            }
            sort($prices);
            $mid = intdiv(count($prices), 2);
            $medians[$cid] = count($prices) % 2
                ? $prices[$mid]
                : ($prices[$mid - 1] + $prices[$mid]) / 2;
        }

        return $medians;
    }

    /**
     * @return array<int, int|float>
     */
    private function aggregateByItem(int $restaurantId, Carbon $from, Carbon $to, string $metric): array
    {
        $select = $metric === 'revenue'
            ? 'order_items.menu_item_id as menu_item_id, SUM(order_items.total_price) as value'
            : 'order_items.menu_item_id as menu_item_id, SUM(order_items.quantity) as value';

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'delivered')
            ->whereBetween('orders.delivered_at', [$from, $to])
            ->selectRaw($select)
            ->groupBy('order_items.menu_item_id')
            ->pluck('value', 'menu_item_id')
            ->map(fn ($value) => $metric === 'revenue' ? (float) $value : (int) $value)
            ->all();
    }
}
