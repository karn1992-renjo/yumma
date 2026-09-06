<?php

namespace App\Services\Ai\Managers;

use App\Models\Restaurant;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiSettingsService;
use App\Services\Ai\AiToolRegistry;
use App\Services\MenuDemandAnalysisService;

/**
 * Runs weekly (routes/console.php). For each active restaurant, proposes up
 * to 3 menu item price changes based on real demand trend data
 * (App\Services\MenuDemandAnalysisService). Every proposal goes through
 * App\Services\Ai\AiToolRegistry::execute('suggest_menu_price_change', ...)
 * which is always risk=high -- so it always lands in that restaurant's
 * approval queue (App\Http\Controllers\Restaurant\AiApprovalController)
 * and is never auto-applied, regardless of autonomy/simulation settings.
 */
class AiMenuPricingManager
{
    private const SUGGESTIONS_PER_RESTAURANT = 3;

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly MenuDemandAnalysisService $demand,
        private readonly AiToolRegistry $tools,
        private readonly AiDecisionLogger $logger,
    ) {
    }

    public function run(): void
    {
        if (! $this->settings->bool('ai_enabled') || $this->settings->get('ai_autonomy_mode', 'monitor') === 'off' || $this->settings->bool('ai_kill_switch')) {
            return;
        }

        $restaurantIds = Restaurant::where('is_verified', true)->pluck('id');

        foreach ($restaurantIds as $restaurantId) {
            foreach ($this->demand->suggestionsForRestaurant($restaurantId, self::SUGGESTIONS_PER_RESTAURANT) as $suggestion) {
                $this->propose($restaurantId, $suggestion);
            }
        }
    }

    private function propose(int $restaurantId, array $suggestion): void
    {
        $currentPrice = round((float) ($suggestion['current_price'] ?? 0), 2);
        $newPrice = round((float) $suggestion['suggested_price'], 2);
        $delta = $currentPrice > 0
            ? round(($newPrice - $currentPrice) / $currentPrice * 100, 1)
            : null;

        $decision = $this->logger->decision([
            'agent_key' => 'menu_pricing',
            'decision_type' => 'menu_price_suggestion',
            'trigger' => 'scheduled',
            'risk_level' => 'high',
            'reason_summary' => $suggestion['reason'],
            'input_snapshot' => [
                'menu_item_id' => $suggestion['menu_item_id'],
                'item_name' => $suggestion['name'] ?? null,
                'current_price' => $currentPrice,
                'suggested_price' => $newPrice,
                'change_percent' => $delta,
                'direction' => $suggestion['direction'] ?? ($delta !== null && $delta < 0 ? 'decrease' : 'increase'),
                'demand_change_percent' => $suggestion['demand_change_percent'] ?? null,
                'revenue_change_percent' => $suggestion['revenue_change_percent'] ?? null,
                'price_vs_category_median_percent' => $suggestion['price_vs_category_median_percent'] ?? null,
                'recent_orders' => $suggestion['recent_orders'] ?? null,
                'prior_orders' => $suggestion['prior_orders'] ?? null,
            ],
            'proposed_action' => [
                'tool' => 'suggest_menu_price_change',
                'summary' => sprintf(
                    '%s: %s%s → %s%s%s',
                    $suggestion['name'] ?? ('Item #' . $suggestion['menu_item_id']),
                    \App\Models\AppSetting::sanitizedCurrencySymbol(),
                    number_format($currentPrice, 2),
                    \App\Models\AppSetting::sanitizedCurrencySymbol(),
                    number_format($newPrice, 2),
                    $delta !== null ? sprintf(' (%s%.1f%%)', $delta >= 0 ? '+' : '', $delta) : ''
                ),
                'parameters' => [
                    'menu_item_id' => $suggestion['menu_item_id'],
                    'item_name' => $suggestion['name'] ?? null,
                    'current_price' => $currentPrice,
                    'new_price' => $newPrice,
                    'change_percent' => $delta,
                ],
            ],
            'requires_approval' => false,
        ]);

        $this->tools->execute('suggest_menu_price_change', [
            'menu_item_id' => $suggestion['menu_item_id'],
            'new_price' => $suggestion['suggested_price'],
            'reason' => $suggestion['reason'],
        ], null, $decision, approved: false);
    }
}
