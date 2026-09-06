<?php

namespace App\Services\Ai;

use App\Jobs\GenerateCampaignArtworkJob;
use App\Models\AiAction;
use App\Models\AiDecision;
use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\GigExternalSignal;
use App\Models\Promotion;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\GigOperationsBroadcastService;
use App\Services\GigProvisioningService;
use App\Services\PromotionAnalyticsService;
use App\Services\PromotionProvisioningService;
use App\Services\PushNotificationService;
use App\Services\ZoneSurgeService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AiToolRegistry
{
    private const NOTIFICATION_ROLES = ['customer', 'restaurant_owner', 'restaurant_staff', 'delivery_partner'];

    public function __construct(
        private readonly AiBusinessSnapshotService $snapshot,
        private readonly AiDemandForecastService $forecasts,
        private readonly AiDriverPerformanceService $driverPerformance,
        private readonly AiFinanceExceptionService $financeExceptions,
        private readonly AiPolicyEngine $policy,
        private readonly AiDecisionLogger $logger,
        private readonly AiSimulationService $simulation,
        private readonly GigProvisioningService $gigProvisioning,
        private readonly PromotionAnalyticsService $promotionAnalytics,
        private readonly PromotionProvisioningService $promotionProvisioning,
        private readonly ZoneSurgeService $zoneSurge,
        private readonly PushNotificationService $pushNotifications,
        private readonly AiNotificationImageService $notificationImages,
    ) {
    }

    public function definitions(): array
    {
        return [
            'get_business_summary' => ['type' => 'read', 'risk' => 'low'],
            'get_operations_summary' => ['type' => 'read', 'risk' => 'low'],
            'get_fleet_status' => ['type' => 'read', 'risk' => 'low'],
            'get_zone_status' => ['type' => 'read', 'risk' => 'low'],
            'get_driver_performance' => ['type' => 'read', 'risk' => 'low'],
            'get_finance_summary' => ['type' => 'read', 'risk' => 'low'],
            'get_accounting_summary' => ['type' => 'read', 'risk' => 'low'],
            'get_promotion_performance' => ['type' => 'read', 'risk' => 'low'],
            'get_active_promotions' => ['type' => 'read', 'risk' => 'low'],
            'get_local_signals' => ['type' => 'read', 'risk' => 'low'],
            'get_abandoned_carts' => ['type' => 'read', 'risk' => 'low'],
            'get_restaurant_performance' => ['type' => 'read', 'risk' => 'low'],
            'forecast_cashflow' => ['type' => 'read', 'risk' => 'low'],
            'detect_financial_anomalies' => ['type' => 'read', 'risk' => 'medium'],
            'create_gig' => ['type' => 'action', 'risk' => 'medium'],
            'modify_gig' => ['type' => 'action', 'risk' => 'medium'],
            'close_gig' => ['type' => 'action', 'risk' => 'medium'],
            'set_zone_surge' => ['type' => 'action', 'risk' => 'high'],
            'clear_zone_surge' => ['type' => 'action', 'risk' => 'medium'],
            'create_customer_offer' => ['type' => 'action', 'risk' => 'high'],
            'modify_customer_offer' => ['type' => 'action', 'risk' => 'high'],
            'stop_customer_offer' => ['type' => 'action', 'risk' => 'medium'],
            'send_notification' => ['type' => 'action', 'risk' => 'medium'],
            'create_finance_exception' => ['type' => 'action', 'risk' => 'medium'],
            'suggest_menu_price_change' => ['type' => 'action', 'risk' => 'high'],
        ];
    }

    /**
     * $approved=true is set only by AiControlCenterController::approve(),
     * after a human has explicitly reviewed a specific pending action. It
     * skips the simulation-mode/requires-approval branch below (a human
     * just gave the approval that branch exists to collect) and executes
     * for real -- but NOT the guardrail/kill-switch check above it: a hard
     * policy failure (kill switch on, protected action, guardrail breach)
     * still blocks even an approved action, since those are absolute
     * limits an approval click was never meant to override. Without this
     * flag, approving an action while global simulation mode is on would
     * just re-simulate it identically to the original automatic pass,
     * making the Approvals queue unable to ever actually execute anything.
     */
    public function execute(string $tool, array $params = [], ?User $actor = null, ?AiDecision $decision = null, bool $approved = false): array
    {
        $definition = $this->definitions()[$tool] ?? null;
        if (! $definition) {
            return ['success' => false, 'error' => "Unknown AI tool [{$tool}]."];
        }

        if ($definition['type'] === 'read') {
            return ['success' => true, 'tool' => $tool, 'data' => $this->read($tool, $params)];
        }

        $policy = $this->policy->evaluate([
            'tool' => $tool,
            'params' => $params,
            'risk' => $definition['risk'],
            'read_only' => false,
        ], $actor, $approved);

        if (! $policy['allowed']) {
            $result = ['success' => false, 'tool' => $tool, 'policy' => $policy, 'error' => implode(' ', $policy['reasons'])];
            $this->logAction($decision, $tool, $params, $result, 'blocked');

            return $result;
        }

        if (! $approved && ($policy['simulation'] || $policy['requires_approval'])) {
            $result = $this->simulation->simulate($tool, $params, ['policy' => $policy]) + ['policy' => $policy];
            $action = $this->logAction($decision, $tool, $params, $result, 'simulated');
            if ($decision && $policy['requires_approval']) {
                $approverContext = $this->approverContextFor($tool, $params);
                $this->logger->approval($decision, $action, 'pending', $approverContext['approver_type'], $approverContext['restaurant_id']);
            }

            return $result;
        }

        $result = $this->write($tool, $params, $decision) + ['policy' => $policy];
        $this->logAction($decision, $tool, $params, $result, ($result['success'] ?? false) ? 'executed' : 'failed', $approved);

        return $result;
    }

    /**
     * A restaurant-funded promotion/coupon or a menu price change needs the
     * *restaurant owner's* sign-off, not the admin's -- resolves which
     * restaurant (if any) a pending action belongs to so
     * AiDecisionLogger::approval() can route it to the right queue.
     */
    private function approverContextFor(string $tool, array $params): array
    {
        $restaurantId = null;

        if (in_array($tool, ['create_customer_offer'], true)) {
            $restaurantId = isset($params['restaurant_id']) ? (int) $params['restaurant_id'] : null;
        } elseif (in_array($tool, ['modify_customer_offer', 'stop_customer_offer'], true)) {
            $restaurantId = Promotion::find($params['promotion_id'] ?? null)?->restaurant_id;
        } elseif ($tool === 'suggest_menu_price_change') {
            $restaurantId = \App\Models\MenuItem::find($params['menu_item_id'] ?? null)?->restaurant_id;
        }

        return $restaurantId
            ? ['approver_type' => 'restaurant', 'restaurant_id' => (int) $restaurantId]
            : ['approver_type' => 'admin', 'restaurant_id' => null];
    }

    private function read(string $tool, array $params): array
    {
        return match ($tool) {
            'get_business_summary' => $this->snapshot->businessSummary(),
            'get_operations_summary' => $this->snapshot->operationsSummary(),
            'get_fleet_status' => $this->snapshot->fleetStatus(),
            'get_zone_status' => $this->snapshot->zoneStatus(isset($params['area_id']) ? (int) $params['area_id'] : null),
            'get_driver_performance' => $this->driverPerformance->score(isset($params['driver_id']) ? (int) $params['driver_id'] : null),
            'get_finance_summary' => $this->snapshot->financeSummary(),
            'get_accounting_summary' => $this->snapshot->accountingSummary(),
            'detect_financial_anomalies' => $this->financeExceptions->detect(false),
            'forecast_cashflow' => $this->forecastCashflow(),
            'get_promotion_performance' => $this->promotionPerformance($params),
            'get_active_promotions' => $this->activePromotions(),
            'get_local_signals' => $this->localSignals(),
            'get_abandoned_carts' => $this->abandonedCarts(),
            'get_restaurant_performance' => $this->restaurantPerformance(),
            default => [],
        };
    }

    private function write(string $tool, array $params, ?AiDecision $decision = null): array
    {
        $result = match ($tool) {
            'create_gig' => $this->createGig($params),
            'modify_gig' => $this->modifyGig($params),
            'close_gig' => $this->closeGig($params),
            'set_zone_surge' => $this->setZoneSurge($params, $decision),
            'clear_zone_surge' => $this->clearZoneSurge($params),
            'create_finance_exception' => $this->financeExceptions->detect(true) + ['success' => true],
            'send_notification' => $this->sendNotification($params, $decision),
            'create_customer_offer' => $this->createCustomerOffer($params),
            'modify_customer_offer' => $this->modifyCustomerOffer($params),
            'stop_customer_offer' => $this->stopCustomerOffer($params),
            'suggest_menu_price_change' => $this->suggestMenuPriceChange($params),
            default => ['success' => false, 'error' => 'This action is approval/simulation-only until its existing domain service adapter is connected.'],
        };

        if (($result['success'] ?? false) && in_array($tool, ['create_gig', 'modify_gig', 'close_gig'], true)) {
            app(GigOperationsBroadcastService::class)->broadcast();
        }

        return $result;
    }

    private function createGig(array $params): array
    {
        $validator = Validator::make($params, [
            'area_id' => ['required', 'integer', 'exists:delivery_areas,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'base_pay' => ['nullable', 'numeric', 'min:0'],
            'order_incentive' => ['nullable', 'numeric', 'min:0'],
            'login_incentive' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        return $this->gigProvisioning->createGig($validator->validated() + $params);
    }

    private function modifyGig(array $params): array
    {
        $gig = DriverGig::find($params['gig_id'] ?? null);
        if (! $gig) {
            return ['success' => false, 'error' => 'Gig not found.'];
        }

        return $this->gigProvisioning->modifyGig($gig, $params);
    }

    private function closeGig(array $params): array
    {
        $gig = DriverGig::find($params['gig_id'] ?? null);
        if (! $gig) {
            return ['success' => false, 'error' => 'Gig not found.'];
        }

        return $this->gigProvisioning->closeGig($gig, $params['reason'] ?? null);
    }

    private function createCustomerOffer(array $params): array
    {
        $validator = Validator::make($params, [
            'title' => ['required', 'string', 'max:255'],
            'promotion_type' => ['required', 'string'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'reward_value' => ['nullable', 'numeric'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'daily_budget' => ['nullable', 'numeric', 'min:0'],
            'audience_type' => ['nullable', 'in:all,first_order'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
            'estimated_order_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        return $this->promotionProvisioning->createOffer($params);
    }

    private function modifyCustomerOffer(array $params): array
    {
        $promotion = Promotion::find($params['promotion_id'] ?? null);
        if (! $promotion) {
            return ['success' => false, 'error' => 'Promotion not found.'];
        }

        return $this->promotionProvisioning->modifyOffer($promotion, $params);
    }

    private function suggestMenuPriceChange(array $params): array
    {
        $validator = Validator::make($params, [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'new_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        return app(\App\Services\MenuPricingService::class)->applyPriceChange(
            (int) $params['menu_item_id'],
            (float) $params['new_price'],
            $params['reason'] ?? null
        );
    }

    private function stopCustomerOffer(array $params): array
    {
        $promotion = Promotion::find($params['promotion_id'] ?? null);
        if (! $promotion) {
            return ['success' => false, 'error' => 'Promotion not found.'];
        }

        return $this->promotionProvisioning->stopOffer($promotion, $params['reason'] ?? null);
    }

    /**
     * Flat, self-funding zone surge: activates a customer-facing surge fee
     * on a delivery zone (App\Services\ZoneSurgeService), which
     * App\Http\Controllers\Api\OrderController::buildPricingSummary() adds
     * to every order's delivery fee for that zone, and
     * App\Services\GigIncentiveService::calculateGigEarnings() pays back out
     * to drivers 1:1 per delivered order. Amount is guardrail-checked in
     * AiPolicyEngine against ai_max_surge_fee_amount before this ever runs.
     */
    private function setZoneSurge(array $params, ?AiDecision $decision = null): array
    {
        $validator = Validator::make($params, [
            'area_id' => ['required', 'integer', 'exists:delivery_areas,id'],
            'surge_fee_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $area = DeliveryArea::find($params['area_id']);
        if (! $area) {
            return ['success' => false, 'error' => 'Delivery zone not found.'];
        }

        $area = $this->zoneSurge->activate(
            $area,
            (float) $params['surge_fee_amount'],
            $params['reason'] ?? 'AI-detected driver shortage',
            $decision?->id
        );

        return [
            'success' => true,
            'area_id' => $area->id,
            'area_name' => $area->name,
            'surge_fee_amount' => (float) $area->surge_fee_amount,
        ];
    }

    private function clearZoneSurge(array $params): array
    {
        $validator = Validator::make($params, [
            'area_id' => ['required', 'integer', 'exists:delivery_areas,id'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $area = DeliveryArea::find($params['area_id']);
        if (! $area) {
            return ['success' => false, 'error' => 'Delivery zone not found.'];
        }

        $area = $this->zoneSurge->deactivate($area);

        return ['success' => true, 'area_id' => $area->id, 'area_name' => $area->name];
    }

    /** campaign_type values that DEFAULT to having marketing artwork. */
    private const CAMPAIGN_TYPES_PROMOTIONAL = [
        'discount', 'free_delivery', 'cashback', 'festival', 'restaurant_promo',
        'food_recommendation', 're_engagement', 'new_restaurant',
        'breakfast', 'lunch', 'dinner', 'late_night',
        'product_promotion', 'special_campaign',
    ];

    /** campaign_type values that must NEVER trigger image generation. */
    private const CAMPAIGN_TYPES_TRANSACTIONAL = [
        'otp', 'order_accepted', 'driver_assigned', 'payment_success',
        'delivery_complete', 'account_security', 'transactional',
    ];

    /**
     * Sends a real push notification via the existing admin broadcast
     * infrastructure (App\Services\PushNotificationService, the same service
     * /admin/push-notifications uses). Every call here is AI-authored, so it is
     * tagged source=ai and carries a notification_type (voice/tone) plus a
     * campaign_type (marketing intent -- a DIFFERENT axis).
     *
     * When image_required resolves true, the marketing artwork is produced by
     * App\Services\Ai\AiNotificationImageService (OpenAI campaign visual + the
     * real app logo composited in PHP -- never the notification copy). A cache
     * hit is attached inline; otherwise App\Jobs\GenerateCampaignArtworkJob
     * generates it off this path and sends the broadcast when ready, so a cold
     * image call never blocks the AI cycle. Any failure -> text-only.
     *
     * `role_group` is set only by App\Services\Ai\Managers\AiNotificationManager
     * (per-audience cooldown + marketing opt-out); the chat path leaves it null.
     */
    private function sendNotification(array $params, ?AiDecision $decision = null): array
    {
        $validator = Validator::make($params, [
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:500'],
            'audience_roles' => ['required', 'array', 'min:1'],
            'audience_roles.*' => ['string', 'in:'.implode(',', self::NOTIFICATION_ROLES)],
            'role_group' => ['nullable', 'string', 'max:20'],
            'notification_type' => ['nullable', 'string', 'max:30'],
            'campaign_type' => ['nullable', 'string', 'max:40'],
            'campaign_goal' => ['nullable', 'string', 'max:80'],
            'cta_text' => ['nullable', 'string', 'max:40'],
            'deep_link' => ['nullable', 'string', 'max:200'],
            'image_required' => ['nullable', 'boolean'],
            'image_concept' => ['nullable', 'string', 'max:400'],
            'promo_badge_text' => ['nullable', 'string', 'max:24'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'attach_image' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()->toArray()];
        }

        $audienceRoles = array_values($params['audience_roles']);

        // notification_type = voice/tone (accept legacy "type" alias).
        $notificationType = $this->notificationImages->normalizeType(
            $params['notification_type'] ?? $params['type'] ?? null
        );

        // campaign_type = marketing intent; validated deterministically.
        $campaignType = $this->normalizeCampaignType($params['campaign_type'] ?? null, $notificationType, $audienceRoles);

        $llmImageRequired = array_key_exists('image_required', $params) && $params['image_required'] !== null
            ? filter_var($params['image_required'], FILTER_VALIDATE_BOOLEAN)
            : null;
        $imageRequired = $this->resolveImageRequired($llmImageRequired, $campaignType, $audienceRoles);

        $deepLink = $this->sanitizeDeepLink($params['deep_link'] ?? null);

        $spec = new CampaignImageSpec(
            campaignType: $campaignType,
            campaignGoal: $params['campaign_goal'] ?? null,
            imageConcept: $params['image_concept'] ?? null,
            promoBadgeText: $params['promo_badge_text'] ?? null,
            notificationType: $notificationType,
        );

        // Explicit image_url always wins.
        $explicitImageUrl = $params['image_url'] ?? null;
        $attachImage = filter_var($params['attach_image'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $wantImage = $attachImage && $imageRequired && blank($explicitImageUrl);

        $imageUrl = $explicitImageUrl;
        $imageStatus = null;
        $queueArtwork = false;

        if ($wantImage) {
            $cached = $this->notificationImages->cachedImageUrl($spec);
            if (filled($cached)) {
                $imageUrl = $cached;
                $imageStatus = 'ready';
            } elseif (! $this->notificationImages->imagesEnabled() || ! $this->notificationImages->withinImageBudget()) {
                $imageStatus = 'skipped'; // disabled / over budget -> text-only
            } elseif (app()->runningInConsole() && ! $this->notificationImages->asyncEnabled()) {
                // Scheduled cycle (CLI, no time limit): generate now, inline, so
                // the broadcast is sent complete and never sits in "pending".
                try {
                    $imageUrl = $this->notificationImages->campaignImageUrl($spec, allowGeneration: true);
                } catch (\Throwable $e) {
                    report($e);
                }
                $imageStatus = filled($imageUrl) ? 'ready' : 'skipped';
            } else {
                // Web request (chat): don't block the request ~50s on OpenAI.
                // Hand off -- a worker if one runs, otherwise ai:flush-stale-
                // notifications (CLI, every few min) generates it and sends.
                $imageStatus = 'queued';
                $queueArtwork = true;
            }
        }

        $dataPayload = array_filter([
            'notification_type' => $notificationType,
            'campaign_type' => $campaignType,
            'cta_text' => isset($params['cta_text']) ? trim((string) $params['cta_text']) : null,
            'image_url' => $imageUrl,
            'image' => $imageUrl,
            // Regeneration hints for ai:flush-stale-notifications if a queued
            // artwork job is used and never runs.
            'campaign_image_concept' => $queueArtwork ? $spec->imageConcept : null,
            'campaign_goal' => $queueArtwork ? $spec->campaignGoal : null,
            'promo_badge_text' => $queueArtwork ? $spec->promoBadgeText : null,
        ], fn ($value) => filled($value));

        $broadcast = PushBroadcast::create([
            'title' => $params['title'],
            'body' => $params['message'],
            'audience_type' => 'roles',
            'audience_roles' => $audienceRoles,
            'deep_link' => $deepLink,
            'status' => 'pending',
            'source' => 'ai',
            'role_group' => $params['role_group'] ?? null,
            'notification_type' => $notificationType,
            'campaign_type' => $campaignType,
            'image_status' => $imageStatus,
            'data_payload' => $dataPayload ?: null,
            'ai_decision_id' => $decision?->id,
        ]);

        if ($queueArtwork) {
            GenerateCampaignArtworkJob::dispatch($broadcast->id, $spec->toArray());

            return [
                'success' => true,
                'queued' => true,
                'status' => 'queued',
                'broadcast_id' => $broadcast->id,
                'campaign_type' => $campaignType,
                'image_required' => true,
                'message' => 'Campaign artwork queued; the notification sends automatically when it is ready.',
            ];
        }

        $broadcast = $this->pushNotifications->sendBroadcast($broadcast);

        return [
            'success' => $broadcast->status === 'sent',
            'broadcast_id' => $broadcast->id,
            'status' => $broadcast->status,
            'campaign_type' => $campaignType,
            'image_required' => $imageRequired,
            'image_attached' => filled($imageUrl),
            'recipients_count' => $broadcast->recipients_count,
            'delivered_count' => $broadcast->delivered_count,
            'failure_reason' => $broadcast->failure_reason,
        ];
    }

    /**
     * Resolve a safe campaign_type. Trusts a value the LLM sends only when it
     * is a known promotional or transactional type; otherwise falls back by
     * audience (operational for driver/restaurant) then by tone.
     */
    private function normalizeCampaignType(?string $raw, string $notificationType, array $audienceRoles): string
    {
        $raw = strtolower(trim(str_replace([' ', '-'], '_', (string) $raw)));

        if (in_array($raw, self::CAMPAIGN_TYPES_PROMOTIONAL, true) || in_array($raw, self::CAMPAIGN_TYPES_TRANSACTIONAL, true)) {
            return $raw;
        }

        if ($this->audienceIsOperational($audienceRoles)) {
            return 'transactional';
        }

        return $notificationType === 'hype' ? 'discount' : 're_engagement';
    }

    /**
     * Deterministic image_required. Hard guards (transactional type, or a
     * driver/restaurant-only audience) always win over the LLM; otherwise the
     * LLM's explicit choice is honoured; otherwise it defaults by type.
     */
    private function resolveImageRequired(?bool $llmValue, string $campaignType, array $audienceRoles): bool
    {
        if (in_array($campaignType, self::CAMPAIGN_TYPES_TRANSACTIONAL, true)) {
            return false;
        }

        if ($this->audienceIsOperational($audienceRoles)) {
            return false;
        }

        if ($llmValue !== null) {
            return $llmValue;
        }

        return in_array($campaignType, self::CAMPAIGN_TYPES_PROMOTIONAL, true);
    }

    /** True when the audience has no customers (driver/restaurant only). */
    private function audienceIsOperational(array $roles): bool
    {
        $roles = array_map('strtolower', $roles);
        foreach ($roles as $role) {
            if (str_contains($role, 'customer')) {
                return false;
            }
        }

        return $roles !== [];
    }

    /**
     * Accept only an internal deep link: a leading-slash path (or app://path,
     * normalised) whose first segment is allow-listed and which has no scheme,
     * host or traversal. Anything else -> null (existing "no deep link"
     * behaviour), so the LLM can never point users at an arbitrary URL.
     */
    private function sanitizeDeepLink(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (Str::startsWith($raw, 'app://')) {
            $raw = '/'.ltrim(Str::after($raw, 'app://'), '/');
        }

        if (! preg_match('#^/[a-z0-9\-]+(?:/[a-z0-9\-]+){0,3}$#', $raw)) {
            return null;
        }

        $allowedRoots = [
            'restaurants', 'restaurant', 'offers', 'offer', 'promotions', 'promotion',
            'orders', 'order', 'cart', 'wallet', 'home', 'search', 'categories',
            'category', 'profile', 'referrals', 'notifications', 'menu', 'gigs',
        ];

        $root = explode('/', ltrim($raw, '/'))[0];

        return in_array($root, $allowedRoots, true) ? $raw : null;
    }

    private function forecastCashflow(): array
    {
        $finance = $this->snapshot->financeSummary();
        $net = ($finance['admin_commission_today'] ?? 0) - ($finance['promotion_liability_today'] ?? 0) - ($finance['payment_gateway_fee_today'] ?? 0);

        return [
            'today_net_platform_estimate' => round($net, 2),
            'seven_day_projection' => round($net * 7, 2),
            'basis' => 'Simple run-rate projection from existing order finance fields.',
        ];
    }

    /**
     * Wraps App\Services\PromotionAnalyticsService::summary() (the same
     * service the admin promotion-engine dashboard uses) and trims it down
     * to the ROI-relevant fields from spec section 15 -- the full summary()
     * also returns hourly/daily/monthly usage time-series and long
     * top-restaurant/top-city/top-coupon lists that would bloat every AI
     * prompt for little analytical value.
     */
    private function promotionPerformance(array $params): array
    {
        $filters = array_filter([
            'promotion_id' => isset($params['promotion_id']) ? (int) $params['promotion_id'] : null,
            'restaurant_id' => isset($params['restaurant_id']) ? (int) $params['restaurant_id'] : null,
        ]);

        $summary = $this->promotionAnalytics->summary($filters);

        return [
            'promotion_count' => $summary['promotion_count'],
            'active_promotion_count' => $summary['active_promotion_count'],
            'orders_generated' => $summary['orders_generated'],
            'incremental_revenue' => $summary['revenue'],
            'discount_given' => $summary['discount_given'],
            'cashback_given' => $summary['cashback_given'],
            'roi' => $summary['roi'],
            'conversion_percent' => $summary['conversion'],
            'budget_used' => $summary['budget_used'],
            'platform_burn' => $summary['platform_burn'],
            'restaurant_burn' => $summary['restaurant_burn'],
            'partner_burn' => $summary['partner_burn'],
            'top_promotions' => $summary['top_promotions'],
        ];
    }

    /**
     * Real, specific, currently-running promotions (title, restaurant,
     * discount amount/percent, free delivery, coupon code) -- unlike
     * promotionPerformance()'s aggregate ROI numbers, this is what a
     * customer-facing notification should actually reference so it names a
     * real deal instead of a vague statistic.
     */
    private function activePromotions(): array
    {
        return Promotion::active()
            ->with('restaurant:id,name')
            ->orderByDesc('priority')
            ->limit(15)
            ->get()
            ->map(fn (Promotion $promo) => [
                'id' => $promo->id,
                'title' => $promo->title,
                'description' => $promo->description,
                'restaurant' => $promo->restaurant?->name,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'is_free_delivery' => $promo->discount_type === 'free_delivery',
                'coupon_code' => $promo->code,
                'minimum_order_amount' => $promo->minimum_order_amount,
                'ends_at' => $promo->ends_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Real-world framing signals for notification copy: current local
     * date/time/day-of-week (always real), plus any weather/traffic/event
     * baselines already ingested for gig forecasting
     * (App\Services\GigExternalSignalService) if present. Note: this app
     * has no live weather API wired up -- GigExternalSignal rows are
     * currently synthetic forecasting baselines, not real meteorological
     * data, so "weather" content should only be used once real signals
     * exist here, not fabricated.
     */
    private function localSignals(): array
    {
        $now = now();

        return [
            'date' => $now->toDateString(),
            'day_of_week' => $now->format('l'),
            'is_weekend' => $now->isWeekend(),
            'hour' => (int) $now->format('G'),
            'time_of_day' => match (true) {
                $now->hour < 11 => 'morning',
                $now->hour < 16 => 'afternoon',
                $now->hour < 21 => 'evening',
                default => 'late_night',
            },
            'recent_external_signals' => GigExternalSignal::whereIn('source', ['weather', 'traffic', 'events'])
                ->where('created_at', '>=', now()->subHours(6))
                ->latest()
                ->limit(10)
                ->get(['source', 'score', 'payload'])
                ->toArray(),
        ];
    }

    /**
     * Read-only visibility into currently tracked carts (App\Models\Cart,
     * synced from the customer app) that have gone quiet for at least 15
     * minutes -- for chat/dashboard visibility. The actual reminder sends
     * are handled deterministically by App\Services\CartRecoveryService on
     * its own schedule, not through this tool.
     */
    private function abandonedCarts(): array
    {
        return \App\Models\Cart::with('restaurant:id,name')
            ->where('last_activity_at', '<=', now()->subMinutes(15))
            ->orderBy('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn ($cart) => [
                'cart_id' => $cart->id,
                'restaurant' => $cart->restaurant?->name,
                'item_count' => count($cart->items ?? []),
                'subtotal' => (float) $cart->subtotal,
                'idle_minutes' => (int) $cart->last_activity_at?->diffInMinutes(now()),
                'reminded' => $cart->notified_at !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * Active restaurants with weak recent order volume -- the concrete
     * signal the scheduled AI cycle uses to decide *which* restaurant might
     * benefit from a promotion, instead of proposing one in the abstract.
     */
    private function restaurantPerformance(): array
    {
        return \App\Models\Restaurant::query()
            ->where('is_verified', true)
            ->withCount(['orders as orders_last_30_days' => fn ($query) => $query->where('created_at', '>=', now()->subDays(30))])
            ->orderBy('orders_last_30_days')
            ->limit(15)
            ->get(['id', 'name'])
            ->map(fn ($restaurant) => [
                'restaurant_id' => $restaurant->id,
                'name' => $restaurant->name,
                'orders_last_30_days' => (int) $restaurant->orders_last_30_days,
            ])
            ->values()
            ->all();
    }

    /**
     * When $reuseExisting is true (i.e. this is an approved re-run of a
     * previously simulated proposal -- see execute()), update that same
     * AiAction row in place instead of inserting a second one, so the
     * approval's ai_action_id keeps pointing at the row that actually
     * reflects the real outcome rather than orphaning a stale duplicate.
     */
    private function logAction(?AiDecision $decision, string $tool, array $params, array $result, string $status, bool $reuseExisting = false): ?AiAction
    {
        if (! $decision) {
            return null;
        }

        if ($reuseExisting) {
            $existing = $decision->actions()->where('action_key', $tool)->where('status', 'simulated')->latest('id')->first();
            if ($existing) {
                $existing->forceFill([
                    'result' => $result,
                    'policy_result' => $result['policy'] ?? [],
                    'status' => $status,
                    'executed_by' => auth()->id(),
                    'executed_at' => now(),
                ])->save();

                return $existing;
            }
        }

        return $this->logger->action($decision, $tool, $params, $result, $status);
    }
}
