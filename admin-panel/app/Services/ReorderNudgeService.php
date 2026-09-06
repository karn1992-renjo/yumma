<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Log;

/**
 * "You haven't ordered in a while, reorder your favorite" nudges for
 * customers who have ordered before but gone quiet -- deterministic and
 * template-based for the same reason as CartRecoveryService (no creative
 * judgment needed, and this could touch many customers per run).
 */
class ReorderNudgeService
{
    private const IDLE_MIN_DAYS = 7;
    private const IDLE_MAX_DAYS = 30;
    private const COOLDOWN_DAYS = 14;
    private const FAVORITE_ITEM_ORDER_LOOKBACK = 20;

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly PushNotificationService $pushNotifications,
        private readonly AiDecisionLogger $logger,
    ) {
    }

    public function run(): void
    {
        if (! $this->settings->bool('ai_enabled') || $this->settings->get('ai_autonomy_mode', 'monitor') === 'off' || $this->settings->bool('ai_kill_switch')) {
            return;
        }

        $customerIds = Order::query()
            ->where('status', 'delivered')
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, MAX(delivered_at) as last_delivered_at')
            ->groupBy('customer_id')
            ->havingRaw('MAX(delivered_at) BETWEEN ? AND ?', [
                now()->subDays(self::IDLE_MAX_DAYS),
                now()->subDays(self::IDLE_MIN_DAYS),
            ])
            ->limit(100)
            ->pluck('customer_id');

        $simulation = $this->settings->bool('ai_simulation_mode');

        foreach ($customerIds as $customerId) {
            $this->nudge((int) $customerId, $simulation);
        }
    }

    private function nudge(int $customerId, bool $simulation): void
    {
        $customer = User::find($customerId);
        if (! $customer || $customer->notify_offers_promotions === false) {
            return;
        }

        if ($customer->reorder_nudged_at && $customer->reorder_nudged_at->gt(now()->subDays(self::COOLDOWN_DAYS))) {
            return;
        }

        $favoriteItem = $this->favoriteItemName($customerId);
        if (! $favoriteItem) {
            return;
        }

        $title = '👋 We miss you!';
        $message = "Craving {$favoriteItem} again? Reorder your favorite in just a tap. 🍽️";

        $sent = false;
        if (! $simulation) {
            try {
                $sent = $this->pushNotifications->sendToUser($customer, $title, $message, [
                    'type' => 'reorder_nudge',
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Reorder nudge push failed.', ['customer_id' => $customerId, 'error' => $exception->getMessage()]);
            }
        }

        $customer->forceFill(['reorder_nudged_at' => now()])->save();

        $this->logger->decision([
            'agent_key' => 'reorder_nudge',
            'decision_type' => 'reorder_nudge',
            'trigger' => 'scheduled',
            'risk_level' => 'low',
            'reason_summary' => $simulation
                ? "Would have nudged customer #{$customerId} to reorder {$favoriteItem} (simulation mode -- no real push sent)."
                : "Nudged customer #{$customerId} to reorder {$favoriteItem}.",
            'proposed_action' => ['tool' => 'send_notification', 'parameters' => ['title' => $title, 'message' => $message]],
            'policy_status' => 'allowed',
            'requires_approval' => false,
            'auto_executed' => $sent,
            'execution_status' => $simulation ? 'simulated' : ($sent ? 'executed' : 'failed'),
            'execution_result' => ['success' => $sent, 'simulated' => $simulation, 'customer_id' => $customerId],
        ]);
    }

    /**
     * Most-ordered item name (by total quantity) across a customer's recent
     * delivered orders, read from Order::items (the authoritative
     * line-item JSON populated at checkout -- see
     * App\Http\Controllers\Api\OrderController::store()).
     */
    private function favoriteItemName(int $customerId): ?string
    {
        $counts = [];

        Order::where('customer_id', $customerId)
            ->where('status', 'delivered')
            ->latest('delivered_at')
            ->limit(self::FAVORITE_ITEM_ORDER_LOOKBACK)
            ->get(['items'])
            ->each(function (Order $order) use (&$counts) {
                foreach ((array) $order->items as $item) {
                    $name = is_array($item) ? ($item['name'] ?? null) : null;
                    if (! $name) {
                        continue;
                    }
                    $quantity = (int) ($item['quantity'] ?? 1);
                    $counts[$name] = ($counts[$name] ?? 0) + max(1, $quantity);
                }
            });

        if (empty($counts)) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
