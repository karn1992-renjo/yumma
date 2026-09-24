<?php

namespace App\Services\Ai;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Picks a small bundle of a restaurant's own menu items (e.g. a bread + a curry + rice) that
 * pair well as a combo, for display on the customer app's restaurant-detail screen. Tries the
 * platform AI first, falls back to a deterministic keyword heuristic when AI is disabled, out
 * of budget, or returns something unusable — either way a customer always sees a sensible combo
 * as long as the restaurant has enough eligible items. No admin/restaurant approval involved:
 * this is a read-only suggestion, not a mutating "AI action".
 */
class MenuComboSuggestionService
{
    private const CACHE_TTL_HOURS = 12;
    private const MIN_ITEMS = 2;
    private const MAX_ITEMS = 4;

    public function __construct(private readonly AiCostAwareRouter $router)
    {
    }

    /**
     * @return array{combo_name:string,source:string,items:Collection<int,MenuItem>,actual_price:float,price:float}|null
     */
    public function suggest(Restaurant $restaurant): ?array
    {
        $cacheKey = "ai_combo:restaurant:{$restaurant->id}:v1";

        $cached = Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () use ($restaurant) {
            $candidates = $this->eligibleItems($restaurant);
            if ($candidates->count() < self::MIN_ITEMS) {
                return ['item_ids' => []];
            }

            $picked = $this->aiPick($restaurant, $candidates) ?? $this->heuristicPick($candidates);

            return [
                'combo_name' => $picked['combo_name'],
                'source' => $picked['source'],
                'item_ids' => $picked['items']->pluck('id')->values()->all(),
            ];
        });

        $itemIds = $cached['item_ids'] ?? [];
        if (empty($itemIds)) {
            return null;
        }

        // Re-check availability on every call (cache only remembers the *choice*, not stale
        // item rows) — an item can go out of stock well within the 12h suggestion cache window.
        $items = MenuItem::query()
            ->whereIn('id', $itemIds)
            ->where('is_available', true)
            ->get()
            ->sortBy(fn (MenuItem $item) => array_search($item->id, $itemIds, true))
            ->values();

        if ($items->count() < self::MIN_ITEMS) {
            Cache::forget($cacheKey);

            return null;
        }

        $actualPrice = round((float) $items->sum(fn (MenuItem $item) => (float) $item->final_price), 2);

        return [
            'combo_name' => $cached['combo_name'],
            'source' => $cached['source'],
            'items' => $items,
            'actual_price' => $actualPrice,
            'price' => $actualPrice,
        ];
    }

    /**
     * @return Collection<int,MenuItem>
     */
    private function eligibleItems(Restaurant $restaurant): Collection
    {
        return MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_available', true)
            ->where('is_combo', false)
            ->where(function ($query) {
                $query->whereNull('approval_status')->orWhere('approval_status', 'approved');
            })
            ->with('category')
            ->get()
            // Only items a single tap can add to the cart with no customization sheet —
            // mirrors the app's own MenuItem.hasCustomizations check (variants || add_ons).
            ->filter(fn (MenuItem $item) => empty($item->variants) && empty($item->add_ons) && $item->is_scheduled_available)
            ->values();
    }

    /**
     * @param  Collection<int,MenuItem>  $candidates
     */
    private function aiPick(Restaurant $restaurant, Collection $candidates): ?array
    {
        $payload = $candidates->map(fn (MenuItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category?->name,
            'price' => (float) $item->final_price,
            'veg' => (bool) $item->is_veg,
            'bestseller' => (bool) $item->is_bestseller,
            'orders' => (int) $item->total_orders,
        ])->values()->all();

        $prompt = 'You are a menu strategist for the restaurant "' . $restaurant->name . '". '
            . 'Below is its available menu (id, name, category, price, veg, bestseller, orders). '
            . 'Pick 2 to 4 items that genuinely pair well as a single combo meal a customer would '
            . 'order together (for example a bread + a curry/sabzi + rice, or a main + a side + a '
            . 'drink). Prefer bestsellers and popular items when a good pairing exists. Keep the '
            . 'combo diet-consistent where possible (do not mix veg and non-veg unless the menu '
            . 'gives no better option). Give the combo a short, appetizing name (max 4 words, never '
            . 'mention "AI"). Return ONLY JSON, no markdown: {"combo_name": string, "item_ids": '
            . '[int, ...]}.'
            . "\n\nMENU:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->router->ask($prompt, ['feature' => 'menu_combo_suggestion'], ['temperature' => 0.4]);
        if (! ($result['success'] ?? false)) {
            return null;
        }

        $decoded = json_decode($result['content'] ?? '', true);
        $itemIds = array_values(array_unique(array_map('intval', (array) ($decoded['item_ids'] ?? []))));
        $comboName = trim((string) ($decoded['combo_name'] ?? ''));

        $validIds = $candidates->pluck('id')->all();
        $itemIds = array_values(array_intersect($itemIds, $validIds));

        if (count($itemIds) < self::MIN_ITEMS || $comboName === '') {
            return null;
        }

        $itemIds = array_slice($itemIds, 0, self::MAX_ITEMS);

        return [
            'combo_name' => $comboName,
            'source' => 'ai',
            'items' => $candidates->whereIn('id', $itemIds)->values(),
        ];
    }

    /**
     * Deterministic, no-AI-required grouping: 1 bread + 1 main/curry + 1 rice when the menu has
     * those buckets, otherwise the most popular items. Always returns a result as long as
     * $candidates has at least MIN_ITEMS entries.
     *
     * @param  Collection<int,MenuItem>  $candidates
     */
    private function heuristicPick(Collection $candidates): array
    {
        $bucket = function (array $keywords) use ($candidates) {
            return $candidates->first(function (MenuItem $item) use ($keywords) {
                $haystack = mb_strtolower($item->name . ' ' . ($item->category?->name ?? ''));
                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        return true;
                    }
                }

                return false;
            });
        };

        $bread = $bucket(['roti', 'naan', 'paratha', 'kulcha', 'chapati', 'poori', 'puri', 'bread']);
        $rice = $bucket(['rice', 'biryani', 'pulao']);
        $main = $bucket(['curry', 'sabzi', 'masala', 'gravy', 'dal', 'paneer', 'chicken', 'mutton', 'fish']);

        $picked = collect([$bread, $main, $rice])->filter()->unique('id')->values();

        if ($picked->count() < self::MIN_ITEMS) {
            $picked = $candidates
                ->sortByDesc(fn (MenuItem $item) => ((int) $item->is_bestseller * 100000) + (int) $item->total_orders + (float) $item->rating)
                ->take(3)
                ->values();
        }

        $isVeg = $picked->isNotEmpty() && $picked->every(fn (MenuItem $item) => (bool) $item->is_veg);

        return [
            'combo_name' => $isVeg ? "Chef's Veg Combo" : "Chef's Special Combo",
            'source' => 'heuristic',
            'items' => $picked,
        ];
    }
}
