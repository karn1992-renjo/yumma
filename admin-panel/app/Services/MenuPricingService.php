<?php

namespace App\Services;

use App\Models\MenuItem;

/**
 * Applies an AI-suggested menu item price change -- but only ever called
 * from the restaurant-approval flow (App\Services\Ai\AiToolRegistry's
 * suggest_menu_price_change tool is always risk=high, so it always lands in
 * the restaurant's approval queue and is never auto-executed). Uses the
 * same validation Restaurant\MenuController::update() applies to a
 * human-authored price edit.
 */
class MenuPricingService
{
    public function applyPriceChange(int $menuItemId, float $newPrice, ?string $reason = null): array
    {
        $menuItem = MenuItem::find($menuItemId);
        if (! $menuItem) {
            return ['success' => false, 'error' => 'Menu item not found.'];
        }

        if ($newPrice < 0) {
            return ['success' => false, 'error' => 'new_price cannot be negative.'];
        }

        $maxChangePercent = (float) app(\App\Services\Ai\AiSettingsService::class)->get('ai_menu_price_max_change_percent', 15);
        $currentPrice = (float) $menuItem->price;
        if ($currentPrice > 0) {
            $changePercent = abs($newPrice - $currentPrice) / $currentPrice * 100;
            if ($changePercent > $maxChangePercent) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Suggested change of %.1f%% exceeds the configured maximum of %.1f%% (current price %.2f, suggested %.2f).',
                        $changePercent,
                        $maxChangePercent,
                        $currentPrice,
                        $newPrice
                    ),
                ];
            }
        }

        $previousPrice = $menuItem->price;
        $menuItem->update(['price' => round($newPrice, 2)]);

        return [
            'success' => true,
            'menu_item_id' => $menuItem->id,
            'previous_price' => (float) $previousPrice,
            'new_price' => (float) $menuItem->price,
            'reason' => $reason,
        ];
    }
}
