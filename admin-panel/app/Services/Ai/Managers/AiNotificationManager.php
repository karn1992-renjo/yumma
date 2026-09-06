<?php

namespace App\Services\Ai\Managers;

use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\Ai\AiActivityBroadcastService;
use App\Services\Ai\AiCurrencyContext;
use App\Services\Ai\AiCostAwareRouter;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiSettingsService;
use App\Services\Ai\AiToolRegistry;

/**
 * Runs hourly (routes/console.php) and decides, per audience, whether
 * there's a genuinely timely reason to send a push notification right now
 * -- most hours nothing has changed and it should recommend nothing, to
 * avoid notification fatigue. When it does propose one, the send goes through
 * App\Services\Ai\AiToolRegistry::execute(); AI-authored role broadcasts are
 * exempted from the approval queue by App\Services\Ai\AiPolicyEngine (bounded
 * instead by the hourly cadence, the per-audience cooldown and the marketing
 * opt-out) unless ai_notifications_require_approval is turned on. For customer
 * promotions the model also classifies a campaign_type + image_concept and
 * App\Services\Ai\AiToolRegistry queues App\Jobs\GenerateCampaignArtworkJob to
 * attach OpenAI-generated marketing artwork (never for operational sends).
 */
class AiNotificationManager
{
    /**
     * role_group => spatie roles that make up that audience, matching
     * App\Http\Controllers\Admin\PushNotificationController's accepted
     * audience_roles values and App\Models\User::fcmTokenForApp().
     */
    private const ROLE_GROUPS = [
        'customer' => ['customer'],
        'driver' => ['delivery_partner'],
        'restaurant' => ['restaurant_owner', 'restaurant_staff'],
    ];

    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiToolRegistry $tools,
        private readonly AiCostAwareRouter $router,
        private readonly AiDecisionLogger $logger,
        private readonly AiActivityBroadcastService $activity,
    ) {
    }

    public function run(?User $actor = null): void
    {
        if (! $this->settings->bool('ai_enabled') || $this->settings->get('ai_autonomy_mode', 'monitor') === 'off') {
            return;
        }

        $eligibleGroups = collect(self::ROLE_GROUPS)->keys()
            ->reject(fn (string $group) => $this->recentlyNotified($group))
            ->values();

        if ($eligibleGroups->isEmpty()) {
            // Every audience is still within its cooldown window -- skip
            // the LLM call entirely rather than pay for a no-op response.
            return;
        }

        $context = [
            'currency' => AiCurrencyContext::resolve(),
            'business' => $this->tools->execute('get_business_summary')['data'] ?? [],
            'operations' => $this->tools->execute('get_operations_summary')['data'] ?? [],
            'zones' => $this->tools->execute('get_zone_status')['data'] ?? [],
            'promotion_stats' => $this->tools->execute('get_promotion_performance')['data'] ?? [],
            'active_promotions' => $this->tools->execute('get_active_promotions')['data'] ?? [],
            'local' => $this->tools->execute('get_local_signals')['data'] ?? [],
            'eligible_audiences' => $eligibleGroups->all(),
        ];

        $result = $this->router->ask($this->prompt($context['currency']), $context);
        $decoded = json_decode($result['content'] ?? '{}', true);
        $decoded = is_array($decoded) ? $decoded : [];

        $decision = $this->logger->decision([
            'agent_key' => 'notifications',
            'decision_type' => 'role_notification_review',
            'trigger' => 'scheduled',
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'risk_level' => 'medium',
            'input_snapshot' => $context,
            'reason_summary' => $result['success'] ?? false
                ? 'Hourly review of customer/driver/restaurant notification opportunities.'
                : ($result['error'] ?? 'AI provider did not return a notification review.'),
            'proposed_action' => $decoded,
            'requires_approval' => false,
        ], $actor);

        foreach ($eligibleGroups as $group) {
            $plan = $decoded[$group] ?? null;
            if (empty($plan['notify']) || empty($plan['title']) || empty($plan['message'])) {
                continue;
            }

            $this->tools->execute('send_notification', array_filter([
                'title' => mb_substr((string) $plan['title'], 0, 150),
                'message' => mb_substr((string) $plan['message'], 0, 500),
                'audience_roles' => self::ROLE_GROUPS[$group],
                'role_group' => $group,
                // notification_type = voice/tone; campaign_type = marketing intent.
                'notification_type' => isset($plan['notification_type'])
                    ? (string) $plan['notification_type']
                    : (isset($plan['type']) ? (string) $plan['type'] : null),
                'campaign_type' => isset($plan['campaign_type']) ? (string) $plan['campaign_type'] : null,
                'campaign_goal' => isset($plan['campaign_goal']) ? (string) $plan['campaign_goal'] : null,
                'cta_text' => isset($plan['cta_text']) ? (string) $plan['cta_text'] : null,
                'deep_link' => isset($plan['deep_link']) ? (string) $plan['deep_link'] : null,
                'image_required' => array_key_exists('image_required', $plan) ? (bool) $plan['image_required'] : null,
                'image_concept' => isset($plan['image_concept']) ? (string) $plan['image_concept'] : null,
                'promo_badge_text' => isset($plan['promo_badge_text']) ? (string) $plan['promo_badge_text'] : null,
            ], fn ($value) => $value !== null), $actor, $decision);
        }

        $this->activity->broadcast($decision->fresh(['actions', 'approvals']));
    }

    private function recentlyNotified(string $roleGroup): bool
    {
        $cooldownHours = max(1, (int) $this->settings->get('ai_notification_cooldown_hours', 4));

        return PushBroadcast::where('source', 'ai')
            ->where('role_group', $roleGroup)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subHours($cooldownHours))
            ->exists();
    }

    private function prompt(array $currency): string
    {
        return 'You are the Swado AI notification manager, reviewing whether to send a push notification this hour to '
            . 'each eligible audience listed in "eligible_audiences" (customer, driver, restaurant). '
            . "All monetary figures are in {$currency['code']} -- use the \"{$currency['symbol']}\" symbol, never \$. "
            . 'Style: write like a real food-delivery app notification, not a system alert -- short, punchy, human. '
            . 'ALWAYS include one or two tasteful emoji per message (food/delivery/mood emoji), never more than two. '
            . 'Pick a "notification_type" per audience that fits the moment and set the whole tone of that message: '
            . '"shayari" = a 2-line rhyming Hindi/Hinglish couplet that lands on food or ordering; '
            . '"joke" = one light, clean pun or one-liner about being hungry / cooking / cravings; '
            . '"human" = a relatable everyday-life hook (bored of home food, too tired to cook, mid-week slump); '
            . '"wholesome" = warm, feel-good, a small kind nudge; '
            . '"hype" = urgent deal energy for a real live promotion. '
            . 'Match type to audience: customers can be shayari / joke / human / wholesome / hype; drivers and '
            . 'restaurants should stay "human" or "hype" and factual. Vary the type across hours -- do not always '
            . 'pick "hype". The type only changes the voice; the message must still be grounded in REAL data below. '
            . 'Ground every notification in REAL data, never invent facts: '
            . 'for customers, prefer naming a specific live deal from "active_promotions" (its real title, restaurant '
            . 'name if scoped, discount_value/discount_type, is_free_delivery, coupon_code) over a generic message -- '
            . 'e.g. "🍕 20% off at {restaurant} today! Use CODE20, min order {symbol}199." If "active_promotions" is '
            . 'empty, do not invent a promotion -- either skip the customer notification or use a truthful non-promo '
            . 'reason (e.g. low driver utilization from "zones" meaning fast delivery right now). '
            . 'For drivers, reference a specific zone from "zones" that has real pressure or zero available_drivers -- '
            . 'name the zone and the earning opportunity. '
            . 'For restaurants, reference real operational numbers from "operations" (unassigned/stale orders) -- only '
            . 'notify a specific, true concern, never a vague "check your dashboard". '
            . 'Use "local" (day_of_week, is_weekend, time_of_day, recent_external_signals for weather/traffic/events -- '
            . 'note recent_external_signals is often empty in this deployment, only reference weather/occasion framing '
            . 'when a real signal is present, never assume weather you cannot see) to make timing feel natural, e.g. '
            . 'weekend evening dinner-rush framing, but never as the sole reason to notify. '
            . 'Most hours nothing meaningfully new has happened for a given audience -- in that case recommend nothing '
            . 'for it; sending a notification with no real news is notification fatigue and actively bad. '
            . 'Keep title under 60 characters and message under 150 characters. '
            . 'For each audience you DO notify, also classify the campaign so artwork can be produced: '
            . '"campaign_type" is the MARKETING INTENT and is a DIFFERENT axis from "notification_type" (the voice/tone). '
            . 'campaign_type is one of: discount, free_delivery, cashback, festival, restaurant_promo, food_recommendation, '
            . 're_engagement, new_restaurant, breakfast, lunch, dinner, late_night (customers); for driver and restaurant '
            . 'audiences the notification is operational -- set "image_required": false and you may omit campaign_type. '
            . '"campaign_goal" is a short snake_case objective (e.g. "increase_dinner_orders"). '
            . '"image_required" is true for a real customer promotion/recommendation, false for operational or low-value '
            . 'nudges. When image_required is true, add "image_concept": a SHORT visual idea for the artwork (the food / '
            . 'scene / mood) -- never the notification wording -- and optionally "promo_badge_text": a tiny badge of at '
            . 'most 20 characters (e.g. "40% OFF", "FREE DELIVERY", "₹100 OFF", "NEW") or null. '
            . 'Always include "cta_text" (2-3 words, e.g. "Order Now") and "deep_link" as an internal app path only '
            . '(e.g. "/restaurants", "/offers", "/restaurant/123") -- never an external URL. '
            . 'Return strict JSON with exactly these keys, one per eligible audience, each either null or '
            . '{"notify": true, "notification_type": "shayari|joke|human|wholesome|hype", "campaign_type": "...", '
            . '"campaign_goal": "...", "title": "...", "message": "...", "cta_text": "...", "deep_link": "...", '
            . '"image_required": true, "image_concept": "...", "promo_badge_text": "..."}: '
            . '{"customer": ..., "driver": ..., "restaurant": ...}';
    }
}
