<?php

namespace App\Services\Ai;

use App\Models\AiAlert;
use App\Models\AiDecision;
use App\Models\User;

class AiOrchestrator
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiToolRegistry $tools,
        private readonly AiCostAwareRouter $router,
        private readonly AiDecisionLogger $logger,
        private readonly AiActivityBroadcastService $activity,
    ) {
    }

    public function run(string $agentKey = 'operations', string $trigger = 'manual', ?User $actor = null): AiDecision
    {
        $summary = $this->tools->execute('get_business_summary');
        $operations = $this->tools->execute('get_operations_summary');
        $fleet = $this->tools->execute('get_fleet_status');
        $zones = $this->tools->execute('get_zone_status');
        $anomalies = $this->tools->execute('detect_financial_anomalies');
        $restaurantPerformance = $this->tools->execute('get_restaurant_performance');

        $context = [
            'now' => $this->dateContext(),
            'currency' => $this->currencyContext(),
            'business' => $summary['data'] ?? [],
            'operations' => $operations['data'] ?? [],
            'fleet' => $fleet['data'] ?? [],
            'zones' => $zones['data'] ?? [],
            'financial_anomalies' => $anomalies['data'] ?? [],
            'restaurant_performance' => $restaurantPerformance['data'] ?? [],
            'forecast_driver_shortages' => $this->forecastDriverShortages(),
            'autonomy_mode' => $this->settings->get('ai_autonomy_mode', 'monitor'),
            'simulation_mode' => $this->settings->bool('ai_simulation_mode', true),
        ];

        $reason = $this->deterministicReason($context);
        $aiResult = $reason['decision_worthy']
            ? $this->askProvider($agentKey, $context)
            : ['success' => false, 'error' => 'No AI call needed for normal operating state.'];

        $proposal = $this->normalizeProposal($aiResult, $reason);
        $policyStatus = ($proposal['action'] ?? null) ? 'pending' : 'not_required';

        $decision = $this->logger->decision([
            'agent_key' => $agentKey,
            'decision_type' => $proposal['type'],
            'trigger' => $trigger,
            'provider' => $aiResult['provider'] ?? null,
            'model' => $aiResult['model'] ?? null,
            'risk_level' => $proposal['risk_level'],
            'confidence' => $proposal['confidence'],
            'input_snapshot' => $context,
            'reason_summary' => $proposal['summary'],
            'proposed_action' => $proposal['action'],
            'expected_result' => $proposal['expected_result'],
            'expected_financial_impact' => $proposal['expected_financial_impact'],
            'policy_status' => $policyStatus,
            'requires_approval' => (bool) ($proposal['action'] ?? false),
            'execution_status' => $proposal['action'] ? 'pending' : 'not_required',
        ], $actor);

        if (($proposal['severity'] ?? null) === 'critical') {
            AiAlert::create([
                'ai_decision_id' => $decision->id,
                'agent_key' => $agentKey,
                'severity' => 'critical',
                'title' => 'AI detected a critical operating condition',
                'message' => $proposal['summary'],
                'context' => $context,
            ]);
        }

        $this->applyProposedAction($decision, $proposal['action'] ?? null, $actor);

        return $decision->fresh(['actions', 'approvals']);
    }

    /**
     * Admin chat can now ask the AI to actually carry out a task, not just
     * analyze one -- e.g. "create a gig in Sakchi for tonight". The model is
     * told the same available action tools App\Services\Ai\AiOrchestrator::
     * askProvider() gives the scheduled cycle, and any proposed_action goes
     * through the exact same policy/guardrail/execute-or-queue pipeline as
     * the automatic cycle (applyProposedAction()) -- chat never bypasses
     * approval requirements or risk limits just because a human typed the
     * request.
     */
    public function chat(string $message, ?User $actor = null): array
    {
        $context = [
            'now' => $this->dateContext(),
            'currency' => $this->currencyContext(),
            'business' => $this->tools->execute('get_business_summary')['data'] ?? [],
            'operations' => $this->tools->execute('get_operations_summary')['data'] ?? [],
            'fleet' => $this->tools->execute('get_fleet_status')['data'] ?? [],
            'zones' => $this->tools->execute('get_zone_status')['data'] ?? [],
            'finance' => $this->tools->execute('get_finance_summary')['data'] ?? [],
            'accounting' => $this->tools->execute('get_accounting_summary')['data'] ?? [],
            'promotion' => $this->tools->execute('get_promotion_performance')['data'] ?? [],
            'active_promotions' => $this->tools->execute('get_active_promotions')['data'] ?? [],
            'restaurant_performance' => $this->tools->execute('get_restaurant_performance')['data'] ?? [],
            'abandoned_carts' => $this->tools->execute('get_abandoned_carts')['data'] ?? [],
            'local' => $this->tools->execute('get_local_signals')['data'] ?? [],
            'financial_anomalies' => $this->tools->execute('detect_financial_anomalies')['data'] ?? [],
        ];

        $currency = $context['currency'];
        $now = $context['now'];
        $prompt = 'You are the Swado AI business analyst answering an admin in chat. Think and respond like a sharp, '
            . 'well-informed human analyst would -- the way ChatGPT reasons through a question -- not like a rules '
            . 'engine that just picks a tool. Read across ALL the data below together, notice patterns, compare zones '
            . 'and restaurants against each other, connect cause and effect (e.g. a zone with low orders AND few '
            . 'available drivers AND no active promotion is a different situation than one with plenty of drivers but '
            . 'weak demand), and give a genuinely useful, specific, well-reasoned answer in plain conversational '
            . 'language -- not a generic summary of the numbers. Most of your answers should be analysis, explanation, '
            . 'and recommendations in prose; proposing a concrete action is the exception, not the default. '
            . "Today's real date is {$now['date']} ({$now['day_of_week']}), current time {$now['time']} {$now['timezone']}. "
            . 'When the admin says "today", "tonight", "tomorrow", "this weekend", etc., compute the actual calendar '
            . 'date from this real value -- never guess or use a date from your training data. '
            . "All monetary figures in the tool data are in {$currency['code']} -- always write amounts using the "
            . "\"{$currency['symbol']}\" symbol (e.g. {$currency['symbol']}1,250), never \$ or another currency's symbol, "
            . 'unless a figure is explicitly labeled otherwise (e.g. AI provider cost, which is billed in USD). '
            . 'Data available to you, and what it represents: "business" (platform-wide orders/GMV today and this '
            . 'month), "operations" (unassigned/stale orders right now), "fleet" (driver counts, online/active), '
            . '"zones" (per delivery-area breakdown: orders, active orders, GMV, available drivers, pressure ratio, '
            . 'surge status -- this is your "delivery area" data, compare zones against each other), "finance" '
            . '(commission, driver/restaurant earnings, promotion cost, gateway fees today), "accounting" (COD '
            . 'deposits pending), "promotion" (aggregate promo performance: usage, incremental revenue, ROI), '
            . '"active_promotions" (every live promotion right now, with restaurant/discount detail), '
            . '"restaurant_performance" (restaurants sorted by weakest order volume in the last 30 days -- use this to '
            . 'name a specific underperforming restaurant rather than speaking in generalities), "abandoned_carts" '
            . '(customers who added items but haven\'t ordered -- a direct signal of unmet customer intent/demand), '
            . '"local" (day of week, weekend/weekday, time of day, any real external signals like weather -- use this '
            . 'for timing context, never invent a weather condition that isn\'t present), "financial_anomalies" '
            . '(flagged COD/payment exceptions). Cross-reference these -- e.g. tie a weak restaurant in '
            . '"restaurant_performance" to whether it has anything in "active_promotions", or a pressured zone in '
            . '"zones" to whether "abandoned_carts" shows unmet demand there. '
            . 'If, after genuinely reasoning through this, a specific action clearly follows -- not just "something '
            . 'could theoretically be done" -- you may propose it as action: {tool, parameters}. Available action tools: '
            . 'create_gig {area_id, date, start_time (H:i), end_time (H:i), capacity, title?, base_pay?, order_incentive?, login_incentive?} '
            . '-- always set title to a short, descriptive name for the time period the slot covers (e.g. "Breakfast Slot", '
            . '"Lunch Rush Slot", "Evening Peak Slot", "Dinner Slot", "Late Night Slot"), inferred from start_time, instead of '
            . 'a generic name. Any "date" parameter must be in Y-m-d format and must be '.$now['date'].' or later; never propose a past date. '
            . 'modify_gig {gig_id, title?, capacity?, status?, base_pay?, order_incentive?, login_incentive?, terms_conditions?} '
            . '-- can rename an existing gig slot via title, e.g. when the admin asks to rename or relabel a slot; '
            . 'close_gig {gig_id, reason?}; '
            . 'set_zone_surge {area_id, surge_fee_amount, reason?} -- flat, self-funding customer delivery surcharge that pays '
            . 'drivers the identical amount per delivered order, never a platform cost; '
            . 'clear_zone_surge {area_id}; '
            . 'send_notification {title, message, audience_roles: array of customer|restaurant_owner|restaurant_staff|delivery_partner, '
            . 'notification_type?: "shayari"|"joke"|"human"|"wholesome"|"hype" (VOICE/TONE only -- shayari = a 2-line '
            . 'Hindi/Hinglish couplet, joke = a light food pun, human = a relatable everyday hook, hype = urgent deal energy; '
            . 'default "human"), '
            . 'campaign_type?: MARKETING INTENT, a different axis from tone -- one of discount, free_delivery, cashback, '
            . 'festival, restaurant_promo, food_recommendation, re_engagement, new_restaurant, breakfast, lunch, dinner, '
            . 'late_night (customer promos); omit for operational driver/restaurant notices, '
            . 'campaign_goal?: short snake_case objective, cta_text?: 2-3 words e.g. "Order Now", '
            . 'deep_link?: internal app path only e.g. "/restaurants" or "/offers" (never an external URL), '
            . 'image_required?: boolean (true only for a real customer promotion/recommendation; transactional/operational '
            . 'notices are forced to false), image_concept?: a SHORT visual idea for the artwork (food/scene/mood, NEVER the '
            . 'notification wording), promo_badge_text?: a tiny badge <= 20 chars e.g. "40% OFF" / "FREE DELIVERY" / "NEW" or null, '
            . 'attach_image?: boolean (default true; set false to force text-only)} '
            . '-- sends a real push notification to every user with that role right now, so only propose it when there is '
            . 'a genuinely useful, timely reason. When image_required is true the marketing artwork is generated by OpenAI '
            . '(a real food campaign visual) with the real app logo composited on top, then the notification is sent -- '
            . 'do NOT pass an image_url yourself. Always include one or two tasteful emoji in the message. '
            . 'create_customer_offer {title, promotion_type: "percentage_discount", "free_delivery", or "item_discount" '
            . 'only, reward_value? (percent off 1-100, required for percentage_discount and item_discount), max_discount?, '
            . 'min_order_amount?, restaurant_id?, item_ids? (required for item_discount, menu item ids belonging to '
            . 'restaurant_id), audience_type? ("first_order" for a first-order-only offer, e.g. "20% off first order"), '
            . 'description?, starts_at?, ends_at?, total_budget?, daily_budget?} -- only these three simple types are '
            . 'supported, never invent another promotion_type. Leaving restaurant_id empty makes it platform-funded '
            . '(admin approves it); setting restaurant_id makes it restaurant-funded (that restaurant\'s owner approves '
            . 'it on their own dashboard instead -- tell the admin this when they ask for a restaurant-specific offer). '
            . 'Every discount is checked against real commission margin before creation -- an unsafe one is rejected '
            . 'automatically, so you don\'t need to reason about profitability yourself. '
            . 'modify_customer_offer {promotion_id, title?, description?, reward_value?, min_order_amount?, ends_at?, '
            . 'status?: draft|active|paused} -- can only modify a promotion the AI itself created; '
            . 'stop_customer_offer {promotion_id, reason?} -- pauses a promotion the AI itself created; '
            . 'suggest_menu_price_change {menu_item_id, new_price, reason} -- proposes a price change for one menu item; '
            . 'always requires that restaurant\'s approval and is never applied automatically. '
            . 'You never execute anything yourself -- state your plan and propose the action, then the platform validates it against '
            . 'guardrails and either executes it immediately or queues it in the approval queue, depending on risk and the '
            . 'current autonomy settings. Always explain your reasoning in "answer" even when you also propose an action -- '
            . 'the analysis is the point, the action is just an optional conclusion to it. '
            . 'Return JSON: answer, proposed_action (optional {tool, parameters}), confidence, risk_level. '
            . 'Never propose payouts, refunds, bank, tax, security, or account termination actions. '
            . 'Admin question: ' . $message;

        $result = $this->router->ask($prompt, $context);
        $decoded = $this->decode($result['content'] ?? '{}');
        $proposedAction = $decoded['proposed_action'] ?? null;

        $decision = $this->logger->decision([
            'agent_key' => 'chat',
            'decision_type' => 'chat',
            'trigger' => 'admin_chat',
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'risk_level' => $decoded['risk_level'] ?? 'low',
            'confidence' => $decoded['confidence'] ?? null,
            'input_snapshot' => $context,
            'reason_summary' => $decoded['answer'] ?? ($result['error'] ?? 'AI provider did not return an answer.'),
            'proposed_action' => $proposedAction,
            'policy_status' => empty($proposedAction) ? 'not_required' : 'pending',
            'execution_status' => empty($proposedAction) ? 'not_required' : 'pending',
            'requires_approval' => ! empty($proposedAction),
        ], $actor);

        $executionResult = $this->applyProposedAction($decision, $proposedAction, $actor);
        $answer = $decoded['answer'] ?? ($result['error'] ?? 'AI provider did not return an answer.');
        if ($executionResult) {
            $answer = trim($answer."\n\n".$this->describeExecution($executionResult));
        }

        return [
            'success' => $result['success'] ?? false,
            'answer' => $answer,
            'decision' => $decision->fresh(['actions', 'approvals']),
            'raw' => $result,
        ];
    }

    /**
     * Single execute-or-queue path shared by the scheduled/manual cycle
     * (run()) and chat() -- runs the proposed tool through
     * AiToolRegistry::execute() (policy check, guardrails, simulation vs.
     * real execution) and records the outcome on the decision. Returns null
     * when there was nothing to execute.
     */
    private function applyProposedAction(AiDecision $decision, ?array $action, ?User $actor): ?array
    {
        if (empty($action['tool'])) {
            return null;
        }

        $result = $this->tools->execute($action['tool'], $action['parameters'] ?? [], $actor, $decision);

        $decision->forceFill([
            'policy_status' => data_get($result, 'policy.allowed') ? 'allowed' : 'blocked',
            'execution_status' => ($result['simulated'] ?? false) ? 'simulated' : (($result['success'] ?? false) ? 'executed' : 'blocked'),
            'execution_result' => $result,
            'auto_executed' => ! ($result['simulated'] ?? false) && ($result['success'] ?? false),
        ])->save();
        $this->activity->broadcast($decision);

        return $result;
    }

    private function describeExecution(array $result): string
    {
        if ($result['simulated'] ?? false) {
            $risk = data_get($result, 'policy.risk', 'unknown');

            return "\u{23F3} I've queued this for your approval (risk: {$risk}) — review it on the Approvals page.";
        }

        if ($result['success'] ?? false) {
            return "\u{2705} Done.";
        }

        return "\u{274C} I couldn't do this: ".($result['error'] ?? 'blocked by policy.');
    }

    private function deterministicReason(array $context): array
    {
        $unassigned = (int) data_get($context, 'operations.unassigned_orders', 0);
        $stale = (int) data_get($context, 'operations.stale_active_orders', 0);
        $codAmount = (float) data_get($context, 'financial_anomalies.exceptions.0.amount', 0);

        if ($unassigned >= 5 || $stale >= 3) {
            return ['decision_worthy' => true, 'summary' => 'Operations pressure needs review.', 'type' => 'operations_pressure', 'risk_level' => 'medium'];
        }

        $shortages = data_get($context, 'forecast_driver_shortages', []);
        // Only wake the model for a shortage big enough to be worth acting on --
        // a single missing seat resolves itself as drivers book in.
        if (! empty($shortages) && (int) ($shortages[0]['gap'] ?? 0) >= 2) {
            $top = $shortages[0];

            return [
                'decision_worthy' => true,
                'summary' => sprintf(
                    'Forecast driver shortage in %s at %02d:00 on %s: %d orders expected, %d seat(s) published (recommended %d).',
                    $top['area_name'] ?? 'a zone',
                    (int) ($top['hour'] ?? 0),
                    $top['date'] ?? '',
                    (int) ($top['forecasted_orders'] ?? 0),
                    (int) ($top['existing_capacity'] ?? 0),
                    (int) ($top['recommended_capacity'] ?? 0)
                ),
                'type' => 'forecast_driver_shortage',
                'risk_level' => 'medium',
            ];
        }

        $pressuredZone = $this->mostPressuredZone(data_get($context, 'zones', []));
        if ($pressuredZone) {
            return [
                'decision_worthy' => true,
                'summary' => sprintf(
                    'Driver shortage in %s: %d active order(s) vs %d available driver(s).',
                    $pressuredZone['area_name'] ?? 'a zone',
                    (int) ($pressuredZone['active_orders'] ?? 0),
                    (int) ($pressuredZone['available_drivers'] ?? 0)
                ),
                'type' => 'zone_driver_shortage',
                'risk_level' => 'medium',
            ];
        }

        if ($codAmount > 0) {
            return ['decision_worthy' => true, 'summary' => 'Financial exception candidates detected.', 'type' => 'finance_exception', 'risk_level' => 'medium'];
        }

        return ['decision_worthy' => false, 'summary' => 'Operating state is normal.', 'type' => 'status_check', 'risk_level' => 'low'];
    }

    /**
     * Flags the first zone with active demand and either zero available
     * drivers, or a pressure ratio (App\Services\Ai\AiBusinessSnapshotService
     * ::zoneStatus() -- active_orders / available_drivers) above 2, i.e.
     * more than two active orders per available driver.
     */
    /**
     * Upcoming area/hour slots where the demand forecast wants more driver
     * capacity than the published gig slots provide -- lets the management
     * cycle act on a shortage *before* it becomes live operations pressure.
     * App\Services\Ai\Managers\AiGigProvisioningManager fills these
     * automatically when auto-provisioning is enabled; otherwise the cycle
     * surfaces them here for the model to propose create_gig.
     *
     * @return array<int, array<string, mixed>>
     */
    private function forecastDriverShortages(): array
    {
        try {
            $horizon = (int) $this->settings->get('ai_gig_autoprovision_horizon_hours', 24);
            $minOrders = max(1, (int) $this->settings->get('ai_gig_autoprovision_min_forecast_orders', 5));

            return array_slice(
                app(\App\Services\GigDemandForecastService::class)->upcomingShortages($horizon, $minOrders),
                0,
                5
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function mostPressuredZone(array $zones): ?array
    {
        foreach ($zones as $zone) {
            $activeOrders = (int) ($zone['active_orders'] ?? 0);
            $availableDrivers = (int) ($zone['available_drivers'] ?? 0);

            if ($activeOrders === 0) {
                continue;
            }

            if ($availableDrivers === 0 || (float) ($zone['pressure'] ?? 0) > 2) {
                return $zone;
            }
        }

        return null;
    }

    private function askProvider(string $agentKey, array $context): array
    {
        $currency = $context['currency'] ?? $this->currencyContext();
        $now = $context['now'] ?? $this->dateContext();

        $prompt = 'You are the Swado AI management agent [' . $agentKey . ']. '
            . "Today's real date is {$now['date']} ({$now['day_of_week']}), current time {$now['time']} {$now['timezone']}. "
            . 'Any "date" parameter you propose for create_gig must be in Y-m-d format and must be '
            . "{$now['date']} or later -- never propose a past date. "
            . "All monetary figures in the tool data are in {$currency['code']} -- always write amounts using the "
            . "\"{$currency['symbol']}\" symbol, never \$ or another currency's symbol, unless a figure is explicitly "
            . 'labeled otherwise (e.g. AI provider cost, which is billed in USD). '
            . 'Use only controlled tool output, including the per-zone "zones" array (area_id, area_name, orders_today, '
            . 'active_orders, gmv_today, available_drivers, pressure, surge_fee_active, surge_fee_amount) and the '
            . '"forecast_driver_shortages" array (area_id, area_name, date, hour, forecasted_orders, recommended_capacity, '
            . 'existing_capacity, gap) -- upcoming slots where demand is predicted to outrun the gig capacity already '
            . 'published. When "forecast_driver_shortages" is non-empty, propose create_gig for the largest gap: set '
            . 'area_id to that row\'s area_id, date to its date, start_time to its hour (HH:00), end_time to the next hour, '
            . 'and capacity to its gap. When a zone '
            . 'shows a driver shortage or high pressure relative to demand, prefer a zone-targeted action over a '
            . 'platform-wide one: create_gig (using that zone\'s area_id) to add driver capacity -- always include a '
            . 'title parameter naming the time period the slot covers (e.g. "Lunch Rush Slot", "Dinner Peak Slot", '
            . '"Late Night Slot"), inferred from start_time, never a generic name; modify_gig (gig_id, title?, capacity?, '
            . 'status?, base_pay?, order_incentive?, login_incentive?) can adjust or rename an existing gig slot instead '
            . 'of creating a new one; and/or '
            . 'set_zone_surge (area_id, surge_fee_amount, reason) to activate a flat, self-funding surge fee -- the '
            . "same {$currency['symbol']} amount is added to the customer's delivery fee AND paid to the driver per "
            . 'delivered order in that zone, so it never costs the platform anything. Only call set_zone_surge on a '
            . 'zone that does not already have surge_fee_active=true. Call clear_zone_surge (area_id) once a zone\'s '
            . 'pressure has cleared. Use "restaurant_performance" (restaurant_id, name, orders_last_30_days, sorted weakest '
            . 'first) to find a specific restaurant with genuinely low order volume -- when one stands out, propose a '
            . 'restaurant-funded promotion for it: create_customer_offer {title, promotion_type: "percentage_discount", '
            . '"free_delivery", or "item_discount" only, reward_value? (percent off 1-100, required for percentage_discount '
            . 'and item_discount), max_discount?, min_order_amount?, restaurant_id, item_ids? (required for item_discount, '
            . 'menu item ids belonging to that restaurant), audience_type? ("first_order" to target only that restaurant\'s '
            . 'first-time customers, e.g. "20% off your first order"), total_budget?, daily_budget?} -- only these three '
            . 'simple types exist, never invent another promotion_type. Setting restaurant_id makes it restaurant-funded: '
            . 'it will NOT go live until that restaurant\'s owner approves it on their own dashboard -- this is intentional, '
            . 'never omit restaurant_id to bypass that. Every discount is also checked against real commission margin '
            . 'before it can ever be created, so don\'t worry about proposing something too generous -- an unsafe one is '
            . 'simply rejected. modify_customer_offer {promotion_id, title?, reward_value?, min_order_amount?, ends_at?, '
            . 'status?} and stop_customer_offer {promotion_id, reason?} can only touch a promotion the AI itself created. '
            . 'suggest_menu_price_change {menu_item_id, new_price, reason} proposes a price change for one menu item -- '
            . 'always requires that restaurant\'s approval and is never applied automatically, so only propose it when '
            . 'you have a concrete reason (you will typically only see this opportunity via scheduled demand analysis, '
            . 'not from data available here). '
            . 'Return JSON: type, summary, risk_level, confidence, '
            . 'expected_result, expected_financial_impact, and optional action {tool, parameters}. '
            . 'Never propose payouts, refunds, bank, tax, security, or account termination actions.';

        return $this->router->ask($prompt, $context);
    }

    private function currencyContext(): array
    {
        return AiCurrencyContext::resolve();
    }

    private function dateContext(): array
    {
        $now = now();

        return [
            'date' => $now->toDateString(),
            'day_of_week' => $now->format('l'),
            'time' => $now->format('H:i'),
            'timezone' => $now->timezoneName,
        ];
    }

    private function normalizeProposal(array $aiResult, array $fallback): array
    {
        $decoded = $this->decode($aiResult['content'] ?? '{}');

        return [
            'type' => $decoded['type'] ?? $fallback['type'] ?? 'analysis',
            'summary' => $decoded['summary'] ?? $fallback['summary'] ?? ($aiResult['error'] ?? 'AI analysis completed.'),
            'risk_level' => $decoded['risk_level'] ?? $fallback['risk_level'] ?? 'low',
            'confidence' => $decoded['confidence'] ?? null,
            'action' => $decoded['action'] ?? $decoded['proposed_action'] ?? null,
            'expected_result' => $decoded['expected_result'] ?? [],
            'expected_financial_impact' => $decoded['expected_financial_impact'] ?? null,
            'severity' => $decoded['severity'] ?? null,
        ];
    }

    private function decode(string $content): array
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
