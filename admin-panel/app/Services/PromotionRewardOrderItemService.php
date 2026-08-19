<?php

namespace App\Services;

use App\Models\MenuItem;

class PromotionRewardOrderItemService
{
    public function applyRewardLines(array $orderItems, iterable $rewardLines, ?int $restaurantId = null): array
    {
        $items = array_values($orderItems);
        $rewardLines = collect($rewardLines)->filter(fn ($line) => is_array($line))->values();

        if ($restaurantId && $rewardLines->isNotEmpty()) {
            $rewardItemIds = $rewardLines
                ->map(fn (array $line) => (int) ($line['menu_item_id'] ?? $line['item_id'] ?? 0))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();
            $rewardItems = MenuItem::query()
                ->with('restaurant')
                ->where('restaurant_id', $restaurantId)
                ->whereIn('id', $rewardItemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($rewardItemIds as $rewardItemId) {
                $rewardItem = $rewardItems->get($rewardItemId);
                $temporarilyUnavailable = $rewardItem?->unavailable_until
                    && $rewardItem->unavailable_until->isFuture();

                if (! $rewardItem || ! $rewardItem->is_scheduled_available || $temporarilyUnavailable) {
                    throw new \InvalidArgumentException(
                        'A promotion reward item is no longer available. Your bill has been refreshed; please review the cart.'
                    );
                }
            }
        }

        foreach ($rewardLines as $line) {
            $menuItemId = (int) ($line['menu_item_id'] ?? $line['item_id'] ?? 0);
            if ($menuItemId <= 0) {
                continue;
            }

            $quantity = $this->rewardLineQuantityForOrder($line);
            $matchedIndex = null;

            foreach ($items as $index => $item) {
                $itemId = (int) ($item['menu_item_id'] ?? $item['id'] ?? 0);
                $isRewardLine = ($item['line_type'] ?? null) === 'promotion_reward'
                    || in_array('promotion_reward', (array) ($item['tags'] ?? []), true);

                if ($itemId === $menuItemId && ! $isRewardLine) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                $items = array_merge($items, $this->rewardOrderItems([$line]));

                continue;
            }

            if (! ($line['included_in_cart'] ?? false)) {
                $items[$matchedIndex]['quantity'] = max(1, (int) ($items[$matchedIndex]['quantity'] ?? 1)) + $quantity;
            }

            $items[$matchedIndex] = $this->annotatePromotionFreeQuantity($items[$matchedIndex], $line, $quantity);
        }

        return array_values($items);
    }

    private function rewardOrderItems(array $rewardLines): array
    {
        return collect($rewardLines)
            ->filter(fn (array $line) => (! empty($line['menu_item_id']) || ! empty($line['item_id'])) && ! ($line['included_in_cart'] ?? false))
            ->map(function (array $line) {
                $quantity = $this->rewardLineQuantityForOrder($line);

                return [
                    'id' => (int) ($line['menu_item_id'] ?? $line['item_id']),
                    'menu_item_id' => (int) ($line['menu_item_id'] ?? $line['item_id']),
                    'name' => $line['name'] ?? $line['title'] ?? 'Promotion reward',
                    'category_id' => $line['category_id'] ?? null,
                    'brand_id' => null,
                    'variant_id' => $line['variant_id'] ?? null,
                    'addon_ids' => (array) ($line['addon_ids'] ?? []),
                    'tags' => ['promotion_reward'],
                    'food_type' => null,
                    'price' => 0,
                    'quantity' => $quantity,
                    'selected_variant' => [
                        'name' => 'Promotion reward',
                        'price' => 0,
                        'custom_fields' => [
                            'line_type' => 'promotion_reward',
                            'promotion_id' => (string) ($line['promotion_id'] ?? ''),
                            'promotion_title' => (string) ($line['title'] ?? 'Promotion'),
                        ],
                    ],
                    'selected_add_ons' => [],
                    'total' => 0,
                    'line_type' => 'promotion_reward',
                    'promotion_id' => $line['promotion_id'] ?? null,
                    'promotion_title' => $line['title'] ?? 'Promotion',
                    'special_instructions' => 'Promotion reward: ' . ($line['title'] ?? 'Promotion'),
                ];
            })
            ->values()
            ->all();
    }

    private function annotatePromotionFreeQuantity(array $item, array $line, int $freeQuantity): array
    {
        $selectedVariant = is_array($item['selected_variant'] ?? null)
            ? $item['selected_variant']
            : [];
        $customFields = is_array($selectedVariant['custom_fields'] ?? null)
            ? $selectedVariant['custom_fields']
            : [];

        $currentFreeQuantity = (int) ($item['promotion_free_quantity']
            ?? ($customFields['promotion_free_quantity'] ?? 0));
        $freeQuantity = $currentFreeQuantity + max(1, $freeQuantity);
        $totalQuantity = max(1, (int) ($item['quantity'] ?? 1));
        $paidQuantity = max(0, $totalQuantity - $freeQuantity);
        $promotionTitle = (string) ($line['title'] ?? $line['promotion_title'] ?? 'Promotion');

        $customFields = array_merge($customFields, [
            'promotion_line_type' => 'buy_get_free',
            'promotion_id' => (string) ($line['promotion_id'] ?? ''),
            'promotion_title' => $promotionTitle,
            'promotion_free_quantity' => (string) $freeQuantity,
            'promotion_paid_quantity' => (string) $paidQuantity,
        ]);

        $selectedVariant['custom_fields'] = $customFields;
        if (empty($selectedVariant['name'])) {
            $selectedVariant['name'] = 'Promotion info';
            $selectedVariant['price'] = 0;
        }

        $tags = array_values(array_unique(array_merge((array) ($item['tags'] ?? []), ['promotion_free_units'])));
        $promotionNote = 'Promotion: ' . $promotionTitle . ' includes ' . $freeQuantity . ' free item' . ($freeQuantity === 1 ? '' : 's');

        return array_merge($item, [
            'selected_variant' => $selectedVariant,
            'tags' => $tags,
            'promotion_line_type' => 'buy_get_free',
            'promotion_id' => $line['promotion_id'] ?? null,
            'promotion_title' => $promotionTitle,
            'promotion_free_quantity' => $freeQuantity,
            'promotion_paid_quantity' => $paidQuantity,
            'special_instructions' => trim((string) ($item['special_instructions'] ?? '')) !== ''
                ? trim((string) $item['special_instructions']) . ' | ' . $promotionNote
                : $promotionNote,
        ]);
    }

    private function rewardLineQuantityForOrder(array $line): int
    {
        return max(1, (int) ($line['quantity'] ?? 1));
    }
}
