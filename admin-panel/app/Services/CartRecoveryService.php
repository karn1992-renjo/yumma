<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\Services\Ai\AiCurrencyContext;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Log;

/**
 * "You forgot something in your cart" reminders. Deterministic and
 * template-based rather than LLM-generated -- the content here is highly
 * structured (item name, restaurant, subtotal) and firing per-cart through
 * an LLM call would be slow and needlessly costly for content that doesn't
 * need creative judgment. Still respects the platform AI on/off switch and
 * logs a lightweight AiDecision/AiAction row per reminder so it shows up in
 * the AI Control Center's activity feed and decision history alongside the
 * LLM-driven managers, since it's still part of the same automated
 * customer-facing notification story.
 */
class CartRecoveryService
{
    private const REMINDER_AFTER_MINUTES = 45;

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly PushNotificationService $pushNotifications,
        private readonly AiDecisionLogger $logger,
        private readonly \App\Services\PromotionProvisioningService $promotionProvisioning,
    ) {
    }

    public function run(): void
    {
        if (! $this->settings->bool('ai_enabled') || $this->settings->get('ai_autonomy_mode', 'monitor') === 'off' || $this->settings->bool('ai_kill_switch')) {
            return;
        }

        $simulation = $this->settings->bool('ai_simulation_mode');

        $carts = Cart::whereNull('notified_at')
            ->where('last_activity_at', '<=', now()->subMinutes(self::REMINDER_AFTER_MINUTES))
            ->with(['customer', 'restaurant'])
            ->limit(200)
            ->get();

        foreach ($carts as $cart) {
            $this->remind($cart, $simulation);
        }

        $couponThreshold = max(self::REMINDER_AFTER_MINUTES, (int) $this->settings->get('ai_cart_coupon_threshold_minutes', 60));
        $couponCandidates = Cart::whereNotNull('notified_at')
            ->whereNull('coupon_sent_at')
            ->where('last_activity_at', '<=', now()->subMinutes($couponThreshold))
            ->with(['customer', 'restaurant'])
            ->limit(200)
            ->get();

        foreach ($couponCandidates as $cart) {
            $this->offerCoupon($cart, $simulation);
        }
    }

    private function remind(Cart $cart, bool $simulation): void
    {
        $customer = $cart->customer;
        $restaurant = $cart->restaurant;

        if (! $customer || ! $restaurant) {
            $cart->delete();

            return;
        }

        $items = collect($cart->items);
        $firstItemName = (string) ($items->first()['name'] ?? 'your order');
        $extraCount = max(0, $items->count() - 1);

        $itemsPhrase = $extraCount > 0
            ? "{$firstItemName} + {$extraCount} more item".($extraCount > 1 ? 's' : '')
            : $firstItemName;

        $currency = AiCurrencyContext::resolve();
        $title = '🛒 You left something behind!';
        $message = "{$itemsPhrase} from {$restaurant->name} is still in your cart"
            .($cart->subtotal > 0 ? " ({$currency['symbol']}".number_format((float) $cart->subtotal, 0).')' : '')
            .'. Complete your order before it\'s gone!';

        $sent = false;
        if (! $simulation) {
            try {
                $sent = $this->pushNotifications->sendToUser($customer, $title, $message, [
                    'type' => 'cart_reminder',
                    'restaurant_id' => (string) $restaurant->id,
                    'cart_id' => (string) $cart->id,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Cart recovery push failed.', ['cart_id' => $cart->id, 'error' => $exception->getMessage()]);
            }
        }

        $cart->forceFill(['notified_at' => now()])->save();

        $this->logDecision($customer, $restaurant->name, $itemsPhrase, $sent, $title, $message, $simulation);
    }

    private function logDecision(User $customer, string $restaurantName, string $itemsPhrase, bool $sent, string $title, string $message, bool $simulation): void
    {
        $this->logger->decision([
            'agent_key' => 'cart_recovery',
            'decision_type' => 'cart_reminder',
            'trigger' => 'scheduled',
            'risk_level' => 'low',
            'reason_summary' => $simulation
                ? "Would have reminded customer #{$customer->id} about {$itemsPhrase} left at {$restaurantName} (simulation mode -- no real push sent)."
                : "Reminded customer #{$customer->id} about {$itemsPhrase} left at {$restaurantName}.",
            'proposed_action' => ['tool' => 'send_notification', 'parameters' => ['title' => $title, 'message' => $message]],
            'policy_status' => 'allowed',
            'requires_approval' => false,
            'auto_executed' => $sent,
            'execution_status' => $simulation ? 'simulated' : ($sent ? 'executed' : 'failed'),
            'execution_result' => ['success' => $sent, 'simulated' => $simulation, 'customer_id' => $customer->id],
        ]);
    }

    /**
     * Escalation tier: a cart that already got the plain reminder and is
     * still idle past ai_cart_coupon_threshold_minutes gets a personal,
     * single-use discount coupon (App\Services\PromotionProvisioningService
     * ::createPersonalCoupon()) instead of a second plain nudge. Always
     * stamps coupon_sent_at so this never fires twice for the same cart,
     * even when the margin guard rejects the coupon or simulation mode
     * suppresses the real send -- a rejected/simulated offer is still a
     * decision that was made, not one to retry every 15 minutes forever.
     */
    private function offerCoupon(Cart $cart, bool $simulation): void
    {
        $customer = $cart->customer;
        $restaurant = $cart->restaurant;

        if (! $customer || ! $restaurant) {
            $cart->delete();

            return;
        }

        $cart->forceFill(['coupon_sent_at' => now()])->save();

        if ($simulation) {
            $this->logger->decision([
                'agent_key' => 'cart_recovery',
                'decision_type' => 'cart_coupon',
                'trigger' => 'scheduled',
                'risk_level' => 'low',
                'reason_summary' => "Would have issued customer #{$customer->id} a personal coupon for their cart at {$restaurant->name} (simulation mode -- nothing created).",
                'proposed_action' => ['tool' => 'create_personal_coupon'],
                'policy_status' => 'allowed',
                'requires_approval' => false,
                'auto_executed' => false,
                'execution_status' => 'simulated',
                'execution_result' => ['success' => false, 'simulated' => true, 'customer_id' => $customer->id],
            ]);

            return;
        }

        $result = $this->promotionProvisioning->createPersonalCoupon(
            $customer,
            $restaurant->id,
            (float) $cart->subtotal,
            "Cart idle at {$restaurant->name}, customer #{$customer->id}."
        );

        if ($result['success']) {
            $currency = AiCurrencyContext::resolve();
            $title = '🎁 Here\'s a little something!';
            $message = "Use code {$result['coupon_code']} for {$result['discount_percent']}% off your order at {$restaurant->name}. Expires soon!";

            try {
                $this->pushNotifications->sendToUser($customer, $title, $message, [
                    'type' => 'cart_coupon',
                    'restaurant_id' => (string) $restaurant->id,
                    'coupon_code' => $result['coupon_code'],
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Cart coupon push failed.', ['cart_id' => $cart->id, 'error' => $exception->getMessage()]);
            }
        }

        $this->logger->decision([
            'agent_key' => 'cart_recovery',
            'decision_type' => 'cart_coupon',
            'trigger' => 'scheduled',
            'risk_level' => 'low',
            'reason_summary' => $result['success']
                ? "Issued customer #{$customer->id} coupon {$result['coupon_code']} for their cart at {$restaurant->name}."
                : "Skipped coupon for customer #{$customer->id} at {$restaurant->name}: {$result['error']}",
            'proposed_action' => ['tool' => 'create_personal_coupon'],
            'policy_status' => 'allowed',
            'requires_approval' => false,
            'auto_executed' => $result['success'],
            'execution_status' => $result['success'] ? 'executed' : 'failed',
            'execution_result' => $result + ['customer_id' => $customer->id],
        ]);
    }
}
