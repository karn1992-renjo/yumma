<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\PromotionUsage;
use Illuminate\Support\Collection;

class PromotionEngineService
{
    public function __construct(
        private readonly PromotionFinder $finder,
        private readonly PromotionValidator $validator,
        private readonly PromotionCalculator $calculator,
        private readonly PromotionStackService $stacking,
        private readonly PromotionLogger $logger,
        private readonly PromotionAccountingService $accounting,
    ) {
    }

    public function list(array $context = []): Collection
    {
        $limit = max(1, min(100, (int) ($context['limit'] ?? 50)));
        $restaurantId = isset($context['restaurant_id']) ? (int) $context['restaurant_id'] : null;

        return Promotion::query()
            ->with(['couponCodes' => fn ($query) => $query->where('is_active', true)])
            ->active()
            ->when($context['owner_type'] ?? null, fn ($query, $ownerType) => $query->where('owner_type', $ownerType))
            ->when($restaurantId, function ($query) use ($restaurantId) {
                $query->where(function ($builder) use ($restaurantId) {
                    $builder->whereNull('restaurant_id')
                        ->orWhere('restaurant_id', $restaurantId);
                });
            })
            ->orderBy('priority')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Promotion $promotion) => $this->promotionPayload($promotion))
            ->values();
    }

    public function listForViewer(array $context = []): Collection
    {
        $limit = max(1, min(100, (int) ($context['limit'] ?? 50)));
        $context = $this->finder->normalizeContext($context);
        $coupon = $context['coupon_code'] ? $this->finder->coupon($context['coupon_code']) : null;

        return $this->finder
            ->candidates($context, min(300, max($limit * 3, $limit)))
            ->filter(function (Promotion $promotion) use ($context, $coupon) {
                $promotionCoupon = $coupon && (int) $coupon->promotion_id === (int) $promotion->id
                    ? $coupon
                    : null;

                return (bool) ($this->validator->checkViewer($promotion, $context, $promotionCoupon)['eligible'] ?? false);
            })
            ->take($limit)
            ->map(fn (Promotion $promotion) => $this->promotionPayload($promotion))
            ->values();
    }

    public function calculate(array $context): array
    {
        $context = array_merge($this->finder->normalizeContext($context), [
            '_started_at' => microtime(true),
        ]);
        $couponCode = $context['coupon_code'];
        $coupon = $couponCode ? $this->finder->coupon($couponCode) : null;
        $invalidReasons = [];
        $workflowProgress = collect();
        $rewardActions = collect();
        $rewardCandidates = collect();
        $eligible = collect();

        $promotions = $this->finder->candidates($context)
            ->filter(function (Promotion $promotion) use ($couponCode, $coupon) {
                if (! $couponCode) {
                    return true;
                }

                return $promotion->application_mode === 'automatic'
                    || ($coupon && (int) $promotion->id === (int) $coupon->promotion_id);
            })
            ->values();

        foreach ($promotions as $promotion) {
            $promotionCoupon = $coupon && (int) $coupon->promotion_id === (int) $promotion->id ? $coupon : null;
            $check = $this->validator->check($promotion, $context, $promotionCoupon);

            if (! $check['eligible']) {
                $preview = $this->calculator->preview($promotion, $context);
                if (($preview['eligible'] ?? false) === false) {
                    $this->collectWorkflowState($preview, $workflowProgress, $rewardActions, $rewardCandidates);
                }

                $invalidReasons[] = [
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $promotionCoupon?->code,
                    'title' => $promotion->title,
                    'reason' => $check['reason'],
                    'reason_code' => $check['reason_code'] ?? ($preview['reason_code'] ?? null),
                    'progress' => ($preview['eligible'] ?? false) === false
                        ? ($preview['progress'] ?? [])
                        : [],
                ];
                continue;
            }

            $line = $this->calculator->calculate($promotion, $context);
            $workflow = data_get($line, 'reward_payload.workflow');
            $this->collectWorkflowState($workflow, $workflowProgress, $rewardActions, $rewardCandidates);

            if (is_array($workflow) && ! ($workflow['eligible'] ?? true)) {
                $invalidReasons[] = [
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $promotionCoupon?->code,
                    'title' => $promotion->title,
                    'reason' => $workflow['reason'] ?? 'Promotion requirements are not met',
                    'reason_code' => $workflow['reason_code'] ?? null,
                    'progress' => $workflow['progress'] ?? [],
                ];
                continue;
            }

            if (
                is_array($workflow)
                && ($workflow['selection_required'] ?? false)
                && empty($workflow['reward_lines'] ?? [])
            ) {
                $invalidReasons[] = [
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $promotionCoupon?->code,
                    'title' => $promotion->title,
                    'reason' => 'Choose a reward to apply this promotion',
                    'reason_code' => 'reward_selection_required',
                    'progress' => $workflow['progress'] ?? [],
                ];
                continue;
            }

            if (
                $line['discount_amount'] <= 0
                && $line['cashback_amount'] <= 0
                && empty($line['reward_points'])
                && empty($line['free_item_id'])
                && empty($line['gift_voucher_amount'])
                && empty($line['reward_payload'])
            ) {
                $preview = $this->calculator->preview($promotion, $context);
                $this->collectWorkflowState($preview, $workflowProgress, $rewardActions, $rewardCandidates);

                $invalidReasons[] = [
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $promotionCoupon?->code,
                    'title' => $promotion->title,
                    'reason' => $preview['reason'] ?? 'Promotion does not change the current cart total',
                    'reason_code' => $preview['reason_code'] ?? null,
                    'progress' => $preview['progress'] ?? [],
                ];
                continue;
            }

            $budgetCheck = $this->accounting->budgetCheck($promotion, $line, $context);
            if (! ($budgetCheck['eligible'] ?? false)) {
                $invalidReasons[] = [
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $promotionCoupon?->code,
                    'title' => $promotion->title,
                    'reason' => $budgetCheck['reason'] ?? 'Promotion budget is exhausted',
                    'reason_code' => $budgetCheck['reason_code'] ?? 'campaign_budget_exhausted',
                    'budget_remaining' => $budgetCheck['budget_remaining'] ?? 0,
                ];
                continue;
            }

            $line = $this->accounting->decorateLine($promotion, $line, $context);

            $eligible->push([
                'promotion' => $promotion,
                'coupon' => $promotionCoupon,
                'line' => $line,
            ]);
        }

        if ($couponCode && ! $coupon) {
            $invalidReasons[] = [
                'promotion_id' => null,
                'coupon_code' => $couponCode,
                'title' => null,
                'reason' => 'Invalid or expired coupon code',
            ];
        }

        if ($couponCode && $coupon?->promotion && $this->isScratchRewardPromotion($coupon->promotion)) {
            $blockingOffer = $eligible->first(function (array $candidate) {
                return ! $this->isScratchRewardPromotion($candidate['promotion'])
                    && $this->isItemLevelOffer($candidate['promotion'], $candidate['line'] ?? []);
            });

            if ($blockingOffer || $this->cartContainsItemOffer($context)) {
                $eligible = $eligible
                    ->reject(fn (array $candidate) => $this->isScratchRewardPromotion($candidate['promotion']))
                    ->values();

                $invalidReasons[] = [
                    'promotion_id' => $coupon->promotion->id,
                    'coupon_code' => $couponCode,
                    'title' => $coupon->promotion->title,
                    'reason' => 'Scratch card coupons cannot be combined with active item offers.',
                    'reason_code' => 'scratch_coupon_blocked_by_item_offer',
                    'blocked_by_promotion_id' => $blockingOffer['promotion']->id ?? null,
                    'blocked_by_title' => $blockingOffer['promotion']->title ?? 'Cart item offer',
                ];
            }
        }

        $applied = $this->dedupeOverlappingItemPromotions(
            $this->stacking->select($eligible),
            $context
        );
        $discountLines = $applied->map(function (array $candidate) {
            return array_merge($candidate['line'], [
                'coupon_code' => $candidate['coupon']?->code,
                'promotion' => $this->promotionPayload($candidate['promotion']),
            ]);
        })->values();

        $summary = $this->summaryFromLines($context, $discountLines);
        $rewardLines = $this->rewardLinesFromLines($discountLines);

        $firstApplied = $applied->first();

        $result = array_merge($summary, [
            'eligible_promotions' => $eligible
                ->map(fn (array $candidate) => $this->promotionPayload($candidate['promotion'], $candidate['coupon']))
                ->values(),
            'applied_promotions' => $applied
                ->map(fn (array $candidate) => $this->promotionPayload($candidate['promotion'], $candidate['coupon']))
                ->values(),
            'discount_lines' => $discountLines,
            'reward_lines' => $rewardLines,
            'promo_liability_lines' => $this->liabilityLinesFromLines($discountLines),
            'funding_breakdown' => $this->fundingSummaryFromLines($discountLines),
            'budget_remaining' => $this->budgetRemainingFromApplied($applied),
            'offer_applicability' => $this->offerApplicability($discountLines, $invalidReasons),
            'promotion_progress' => $workflowProgress
                ->unique(fn (array $item) => ($item['promotion_id'] ?? '').':'.($item['type'] ?? ''))
                ->values(),
            'reward_actions' => $rewardActions
                ->unique(fn (array $item) => ($item['promotion_id'] ?? '').':'.($item['action'] ?? ''))
                ->values(),
            'reward_candidates' => $rewardCandidates
                ->unique(fn (array $item) => ($item['promotion_id'] ?? '').':'.($item['item_id'] ?? $item['title'] ?? 'reward'))
                ->values(),
            'invalid_reasons' => $invalidReasons,
            'coupon_code' => $couponCode,
            'promo' => $firstApplied['promotion'] ?? null,
        ]);

        $this->logger->log('calculate', $context, $result, $firstApplied['promotion']->id ?? null, $couponCode);

        return $result;
    }

    public function validateCoupon(array $context): array
    {
        $result = $this->calculate(array_merge($context, [
            'coupon_code' => $context['coupon_code'] ?? $context['code'] ?? null,
        ]));

        $couponApplied = collect($result['discount_lines'])
            ->first(fn (array $line) => ! empty($line['coupon_code']));

        if (! $couponApplied) {
            $reason = collect($result['invalid_reasons'])->first()['reason']
                ?? 'Coupon does not apply to the current order total';

            return array_merge($result, [
                'success' => false,
                'message' => $reason,
            ]);
        }

        return array_merge($result, [
            'success' => true,
            'message' => 'Coupon applied successfully',
        ]);
    }

    public function applyBest(array $context): array
    {
        return $this->calculate(array_merge($context, ['coupon_code' => null]));
    }

    public function bestCoupon(array $context): array
    {
        $context = $this->finder->normalizeContext($context);
        $coupons = $this->finder->activeCoupons($context);

        $best = $coupons
            ->map(fn (PromotionCouponCode $coupon) => $this->validateCoupon(array_merge($context, [
                'coupon_code' => $coupon->code,
            ])))
            ->filter(fn (array $result) => (bool) ($result['success'] ?? false))
            ->sortByDesc(fn (array $result) => (float) ($result['coupon_discount'] ?? 0))
            ->first();

        return $best ?: array_merge($this->calculate($context), [
            'success' => false,
            'message' => 'No valid coupon is available for this cart right now.',
        ]);
    }

    public function remove(array $context): array
    {
        return $this->calculate(array_merge($context, ['coupon_code' => null]));
    }

    public function recordUsage(Order $order, array $result, array $context = []): void
    {
        $lines = collect($result['discount_lines'] ?? []);

        foreach ($lines as $line) {
            $promotionId = $line['promotion_id'] ?? null;
            $couponCode = $line['coupon_code'] ?? null;
            $coupon = $couponCode ? $this->finder->coupon($couponCode) : null;

            $usage = PromotionUsage::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'promotion_id' => $promotionId,
                    'coupon_code' => $couponCode,
                ],
                [
                    'promotion_coupon_code_id' => $coupon?->id,
                    'user_id' => $order->customer_id,
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'source_type' => $line['source_type'] ?? null,
                    'source_id' => $line['source_id'] ?? null,
                    'discount_amount' => $line['discount_amount'] ?? 0,
                    'cashback_amount' => $line['cashback_amount'] ?? 0,
                    'gross_liability_amount' => data_get($line, 'funding_breakdown.gross_liability_amount', 0),
                    'platform_liability_amount' => data_get($line, 'funding_breakdown.platform_liability_amount', 0),
                    'restaurant_liability_amount' => data_get($line, 'funding_breakdown.restaurant_liability_amount', 0),
                    'partner_liability_amount' => data_get($line, 'funding_breakdown.partner_liability_amount', 0),
                    'funding_breakdown' => $line['funding_breakdown'] ?? null,
                    'discount_lines' => [$line],
                    'context' => $context,
                ]
            );

            if (! $usage->wasRecentlyCreated) {
                continue;
            }

            if ($promotionId) {
                Promotion::whereKey($promotionId)->lockForUpdate()->increment('used_count');
            }

            if ($coupon) {
                PromotionCouponCode::whereKey($coupon->id)->lockForUpdate()->increment('used_count');
            }

            if (($line['source_type'] ?? null) === 'promo_code' && ! empty($line['source_id'])) {
                PromoCode::whereKey($line['source_id'])->lockForUpdate()->increment('used_count');
            }

            $this->accounting->recordLedger($order, $usage->fresh('promotion'), $line);
        }

        if ($order->isVisibleToRestaurant()) {
            app(ScratchCardService::class)->issueForOrder($order, $result);
        }
    }

    public function activeOfferPayloads(array $context = []): Collection
    {
        $limit = $context['limit'] ?? 10;
        $baseContext = array_merge(['limit' => $limit], $context);
        $automatic = $this->listForViewer($baseContext);
        $couponOffers = $this->finder->activeCoupons($baseContext)
            ->filter(fn (PromotionCouponCode $coupon) => $coupon->promotion !== null)
            ->map(function (PromotionCouponCode $coupon) use ($baseContext) {
                $couponContext = array_merge($baseContext, ['coupon_code' => $coupon->code]);
                $viewer = $this->validator->checkViewer($coupon->promotion, $this->finder->normalizeContext($couponContext), $coupon);
                $payload = $this->promotionPayload($coupon->promotion, $coupon);

                return array_merge($payload, [
                    'offer_applicability' => [
                        'can_apply_now' => (bool) ($viewer['eligible'] ?? false),
                        'reason' => $viewer['reason'] ?? null,
                        'reason_code' => $viewer['reason_code'] ?? null,
                    ],
                ]);
            });

        return $automatic
            ->merge($couponOffers)
            ->unique(fn (array $promotion) => $promotion['display_id'] ?? ('promotion:' . ($promotion['id'] ?? '0')))
            ->sortByDesc(fn (array $promotion) => (bool) data_get($promotion, 'offer_applicability.can_apply_now', true))
            ->map(function (array $promotion) {
                $code = data_get($promotion, 'coupon_codes.0.code') ?: ($promotion['coupon_code'] ?? null);

                return array_merge($promotion, [
                    'source_type' => 'promotion',
                    'code' => $code,
                    'coupon_code' => $code,
                    'reward_type' => data_get($promotion, 'rewards.type') ?? ($promotion['promotion_type'] ?? null),
                    'subtitle' => $promotion['description'] ?? 'Special offer',
                ]);
            })
            ->flatMap(fn (array $promotion) => $this->promotionDisplayPayloads($promotion))
            ->take($limit)
            ->values();
    }

    private function summaryFromLines(array $context, Collection $lines): array
    {
        $subtotal = (float) $context['subtotal'];
        $deliveryFee = (float) $context['delivery_fee'];
        $platformFee = (float) $context['platform_fee'];
        $packagingFee = (float) $context['packaging_fee'];
        $tax = (float) $context['tax'];
        $couponDiscount = round((float) $lines->where('bucket', 'coupon_discount')->sum('discount_amount'), 2);
        $deliveryDiscount = round((float) $lines->where('bucket', 'delivery_discount')->sum('discount_amount'), 2);
        $packagingDiscount = round((float) $lines->where('bucket', 'packaging_discount')->sum('discount_amount'), 2);
        $restaurantDiscount = round((float) $lines->where('bucket', 'restaurant_discount')->sum('discount_amount'), 2);
        $promotionDiscount = round((float) $lines->where('bucket', 'promotion_discount')->sum('discount_amount'), 2);
        $itemDiscount = round((float) $lines->where('bucket', 'item_discount')->sum('discount_amount'), 2);
        $cashback = round((float) $lines->sum('cashback_amount'), 2);
        $rewardPoints = (int) $lines->sum('reward_points');
        $giftVoucherAmount = round((float) $lines->sum('gift_voucher_amount'), 2);
        $freeItems = $lines
            ->filter(fn (array $line) => ! empty($line['free_item_id']))
            ->map(fn (array $line) => [
                'promotion_id' => $line['promotion_id'] ?? null,
                'free_item_id' => $line['free_item_id'],
                'title' => $line['title'] ?? null,
            ])
            ->values();
        $totalSavings = round($couponDiscount + $deliveryDiscount + $packagingDiscount + $restaurantDiscount + $promotionDiscount + $itemDiscount, 2);
        $grossTotal = round($subtotal + $deliveryFee + $platformFee + $packagingFee + $tax, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round(max(0, $deliveryFee - $deliveryDiscount), 2),
            'original_delivery_fee' => round($deliveryFee, 2),
            'platform_fee' => round($platformFee, 2),
            'packaging_fee' => round($packagingFee, 2),
            'tax' => round($tax, 2),
            'restaurant_discount' => $restaurantDiscount,
            'promotion_discount' => $promotionDiscount,
            'item_discount' => $itemDiscount,
            'coupon_discount' => $couponDiscount,
            'wallet_discount' => 0.0,
            'delivery_discount' => $deliveryDiscount,
            'packaging_discount' => $packagingDiscount,
            'cashback_earned' => $cashback,
            'reward_points_earned' => $rewardPoints,
            'gift_voucher_amount' => $giftVoucherAmount,
            'free_items' => $freeItems,
            'total_savings' => $totalSavings,
            'discount' => $totalSavings,
            'final_total' => max(0, round($grossTotal - $totalSavings, 2)),
            'total' => max(0, round($grossTotal - $totalSavings, 2)),
        ];
    }

    private function liabilityLinesFromLines(Collection $lines): Collection
    {
        return $lines
            ->map(function (array $line) {
                $funding = (array) ($line['funding_breakdown'] ?? []);

                return [
                    'promotion_id' => $line['promotion_id'] ?? null,
                    'coupon_code' => $line['coupon_code'] ?? null,
                    'title' => $line['title'] ?? null,
                    'funding_type' => $funding['funding_type'] ?? null,
                    'partner_name' => $funding['partner_name'] ?? null,
                    'discount_amount' => round((float) ($line['discount_amount'] ?? 0), 2),
                    'cashback_amount' => round((float) ($line['cashback_amount'] ?? 0), 2),
                    'gift_voucher_amount' => round((float) ($line['gift_voucher_amount'] ?? 0), 2),
                    'gross_liability_amount' => round((float) ($funding['gross_liability_amount'] ?? 0), 2),
                    'platform_liability_amount' => round((float) ($funding['platform_liability_amount'] ?? 0), 2),
                    'restaurant_liability_amount' => round((float) ($funding['restaurant_liability_amount'] ?? 0), 2),
                    'partner_liability_amount' => round((float) ($funding['partner_liability_amount'] ?? 0), 2),
                ];
            })
            ->values();
    }

    private function fundingSummaryFromLines(Collection $lines): array
    {
        $liability = $this->liabilityLinesFromLines($lines);

        return [
            'gross_liability_amount' => round((float) $liability->sum('gross_liability_amount'), 2),
            'platform_liability_amount' => round((float) $liability->sum('platform_liability_amount'), 2),
            'restaurant_liability_amount' => round((float) $liability->sum('restaurant_liability_amount'), 2),
            'partner_liability_amount' => round((float) $liability->sum('partner_liability_amount'), 2),
        ];
    }

    private function budgetRemainingFromApplied(Collection $applied): ?float
    {
        $remaining = $applied
            ->map(fn (array $candidate) => $candidate['promotion']->budgetRemaining())
            ->filter(fn ($value) => $value !== null)
            ->min();

        return $remaining !== null ? round((float) $remaining, 2) : null;
    }

    private function offerApplicability(Collection $discountLines, array $invalidReasons): array
    {
        return [
            'can_apply_now' => $discountLines->isNotEmpty(),
            'reason' => $discountLines->isNotEmpty() ? null : (collect($invalidReasons)->first()['reason'] ?? null),
            'reason_code' => $discountLines->isNotEmpty() ? null : (collect($invalidReasons)->first()['reason_code'] ?? null),
            'invalid_reasons' => $invalidReasons,
        ];
    }

    private function collectWorkflowState(?array $workflow, Collection $progress, Collection $actions, Collection $candidates): void
    {
        if (! is_array($workflow) || $workflow === []) {
            return;
        }

        foreach ((array) ($workflow['progress'] ?? []) as $item) {
            if (is_array($item)) {
                $progress->push($item);
            }
        }

        $action = $workflow['action'] ?? 'none';
        if ($action !== 'none') {
            $actions->push([
                'promotion_id' => $workflow['promotion_id'] ?? null,
                'title' => $workflow['title'] ?? null,
                'action' => $action,
                'auto_add' => (bool) ($workflow['auto_add'] ?? false),
                'selection_required' => (bool) ($workflow['selection_required'] ?? false),
                'reward_units' => $workflow['reward_units'] ?? 0,
            ]);
        }

        foreach ((array) ($workflow['reward_candidates'] ?? []) as $candidate) {
            if (is_array($candidate)) {
                $candidates->push(array_merge([
                    'promotion_id' => $workflow['promotion_id'] ?? null,
                    'promotion_title' => $workflow['title'] ?? null,
                ], $candidate));
            }
        }
    }

    private function rewardLinesFromLines(Collection $lines): Collection
    {
        return $lines
            ->flatMap(fn (array $line) => (array) data_get($line, 'reward_payload.workflow.reward_lines', []))
            ->filter(fn ($line) => is_array($line))
            ->values();
    }

    private function isScratchRewardPromotion(Promotion $promotion): bool
    {
        return $promotion->migrated_from_type === 'scratch_card'
            || data_get($promotion->visibility ?? [], 'source') === 'scratch_card'
            || data_get($promotion->couponCodes->first()?->metadata ?? [], 'coupon_type') === 'scratch_card_reward';
    }

    private function isItemLevelOffer(Promotion $promotion, array $line): bool
    {
        $type = strtolower(str_replace('-', '_', (string) ($line['type'] ?? $promotion->promotion_type)));

        return ($line['bucket'] ?? null) === 'item_discount'
            || in_array($type, [
                'bogo',
                'buy_1_get_1',
                'buy_2_get_1',
                'buy_3_get_1',
                'buy_3_get_2',
                'buy_x_get_y',
                'buy_x_get_x',
                'combo_deal',
                'meal_deal',
                'free_item',
                'free_product',
                'free_drink',
                'free_dessert',
                'free_quantity',
                'flash_sale',
                'festival_offer',
            ], true);
    }

    private function cartContainsItemOffer(array $context): bool
    {
        foreach ((array) ($context['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $dealPrice = (float) ($item['promotion_deal_price'] ?? 0);
            $promotionId = $item['promotion_id'] ?? null;
            $title = strtolower(str_replace('-', '_', (string) ($item['promotion_title'] ?? '')));

            if (! empty($promotionId) || $dealPrice > 0) {
                return true;
            }

            if (str_contains($title, 'bogo')
                || str_contains($title, 'buy_')
                || str_contains($title, 'combo')
                || str_contains($title, 'free_item')
                || str_contains($title, 'free item')) {
                return true;
            }
        }

        return false;
    }

    private function dedupeOverlappingItemPromotions(Collection $applied, array $context): Collection
    {
        if ($applied->count() <= 1) {
            return $applied->values();
        }

        $claimedItemIds = [];
        $filtered = collect();

        foreach ($applied as $candidate) {
            if (! $this->isItemLevelOffer($candidate['promotion'], $candidate['line'] ?? [])) {
                $filtered->push($candidate);
                continue;
            }

            $scope = $this->promotionCartItemScope($candidate['promotion'], $context);
            if ($scope === []) {
                $filtered->push($candidate);
                continue;
            }

            if (array_intersect($scope, $claimedItemIds) !== []) {
                continue;
            }

            $claimedItemIds = array_values(array_unique(array_merge($claimedItemIds, $scope)));
            $filtered->push($candidate);
        }

        return $filtered->values();
    }

    private function promotionCartItemScope(Promotion $promotion, array $context): array
    {
        $cartItems = collect((array) ($context['items'] ?? []))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                return [
                    'id' => (int) ($item['id'] ?? $item['menu_item_id'] ?? 0),
                    'category_id' => (int) ($item['category_id'] ?? $item['menu_category_id'] ?? 0),
                ];
            })
            ->filter(fn (array $item) => $item['id'] > 0)
            ->values();

        if ($cartItems->isEmpty()) {
            return [];
        }

        $reward = $promotion->rewards ?? [];
        $targets = $promotion->targets ?? [];
        $conditions = $promotion->conditions ?? [];
        $explicitItemIds = collect([
            ...((array) data_get($reward, 'item_ids', [])),
            ...((array) data_get($reward, 'menu_item_ids', [])),
            ...((array) data_get($targets, 'item_ids', [])),
            ...((array) data_get($targets, 'menu_item_ids', [])),
            ...((array) data_get($conditions, 'contains_item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.menu_item_ids', [])),
            ...collect((array) data_get($reward, 'combo_groups', []))
                ->flatMap(fn ($group) => is_array($group) ? (array) ($group['item_ids'] ?? []) : [])
                ->all(),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($explicitItemIds->isNotEmpty()) {
            return $cartItems
                ->pluck('id')
                ->intersect($explicitItemIds)
                ->values()
                ->all();
        }

        $categoryIds = collect([
            ...((array) data_get($reward, 'category_ids', [])),
            ...((array) data_get($reward, 'menu_category_ids', [])),
            ...((array) data_get($targets, 'category_ids', [])),
            ...((array) data_get($targets, 'menu_category_ids', [])),
            ...((array) data_get($conditions, 'contains_category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_category_ids', [])),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isNotEmpty()) {
            return $cartItems
                ->filter(fn (array $item) => $categoryIds->contains((int) $item['category_id']))
                ->pluck('id')
                ->values()
                ->all();
        }

        return $cartItems->pluck('id')->values()->all();
    }

    private function promotionPayload(Promotion $promotion, ?PromotionCouponCode $coupon = null): array
    {
        $payload = $promotion->toPublicPayload();
        $payload['display_id'] = 'promotion:'.$promotion->id;
        $payload['display_mode'] = $this->promotionDisplayMode((string) $promotion->promotion_type);
        $payload['coupon_codes'] = $promotion->relationLoaded('couponCodes')
            ? $promotion->couponCodes->map(fn (PromotionCouponCode $code) => [
                'id' => $code->id,
                'code' => $code->code,
                'usage_limit' => $code->usage_limit,
                'used_count' => $code->used_count,
                'is_active' => $code->is_active,
            ])->values()
            : [];

        $firstCoupon = $payload['coupon_codes'][0]['code'] ?? null;
        if ($firstCoupon) {
            $payload['code'] = $firstCoupon;
            $payload['coupon_code'] = $firstCoupon;
        }

        if ($coupon) {
            $payload['code'] = $coupon->code;
            $payload['coupon_code'] = $coupon->code;
        }

        $payload['menu_items'] = $this->promotionMenuItems($promotion);
        $payload['promotion_categories'] = $this->promotionCategories($promotion);

        return $payload;
    }

    private function promotionCategories(Promotion $promotion): array
    {
        $reward = $promotion->rewards ?? [];
        $targets = $promotion->targets ?? [];
        $conditions = $promotion->conditions ?? [];
        $ids = collect([
            ...((array) data_get($reward, 'category_ids', [])),
            ...((array) data_get($reward, 'menu_category_ids', [])),
            ...((array) data_get($targets, 'category_ids', [])),
            ...((array) data_get($targets, 'menu_category_ids', [])),
            ...((array) data_get($conditions, 'contains_category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.category_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_category_ids', [])),
            ...((array) data_get($reward, 'reward_rule.category_ids', [])),
            ...((array) data_get($reward, 'reward_rule.menu_category_ids', [])),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');
        $fallbackImages = MenuItem::query()
            ->whereIn('category_id', $ids->all())
            ->whereNotNull('images')
            ->orderByDesc('is_bestseller')
            ->orderByDesc('is_recommended')
            ->get(['id', 'category_id', 'images'])
            ->groupBy('category_id')
            ->map(fn (Collection $items) => $items
                ->map(fn (MenuItem $item) => $item->image_url)
                ->first(fn (?string $url) => filled($url)));

        return $ids
            ->map(fn (int $id) => $categories->get($id))
            ->filter()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'category_id' => $category->id,
                'name' => $category->name,
                'title' => $category->name,
                'image' => MediaStorage::url($category->image) ?: $fallbackImages->get($category->id),
                'image_url' => MediaStorage::url($category->image) ?: $fallbackImages->get($category->id),
                'restaurant_id' => $category->restaurant_id,
            ])
            ->values()
            ->all();
    }

    private function promotionMenuItems(Promotion $promotion): array
    {
        $reward = $promotion->rewards ?? [];
        $targets = $promotion->targets ?? [];
        $conditions = $promotion->conditions ?? [];
        $ids = collect([
            ...((array) data_get($reward, 'item_ids', [])),
            ...((array) data_get($reward, 'menu_item_ids', [])),
            ...((array) data_get($targets, 'item_ids', [])),
            ...((array) data_get($targets, 'menu_item_ids', [])),
            ...((array) data_get($conditions, 'contains_item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.item_ids', [])),
            ...((array) data_get($conditions, 'buy_rule.menu_item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.item_ids', [])),
            ...((array) data_get($reward, 'reward_rule.menu_item_ids', [])),
            ...collect((array) data_get($reward, 'combo_groups', []))
                ->flatMap(fn ($group) => is_array($group) ? (array) ($group['item_ids'] ?? []) : [])
                ->all(),
            data_get($reward, 'free_item_id'),
            data_get($reward, 'item_id'),
            data_get($reward, 'reward_rule.free_item_id'),
            data_get($reward, 'reward_rule.item_id'),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $items = MenuItem::query()
            ->with('restaurant:id,name')
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        return $ids
            ->map(fn (int $id) => $items->get($id))
            ->filter()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'image' => $item->image_url,
                'image_url' => $item->image_url,
                'price' => (float) $item->price,
                'discounted_price' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
                'restaurant_id' => $item->restaurant_id,
                'restaurant_name' => $item->restaurant?->name,
                'is_veg' => (bool) $item->is_veg,
                'is_combo' => (bool) $item->is_combo,
                'is_reward_item' => (int) data_get($reward, 'free_item_id') === (int) $item->id,
            ])
            ->values()
            ->all();
    }

    private function promotionDisplayPayloads(array $payload): Collection
    {
        $mode = $payload['display_mode'] ?? $this->promotionDisplayMode((string) ($payload['promotion_type'] ?? ''));
        if ($mode !== 'item_reward') {
            return collect([$payload]);
        }

        $items = collect($payload['menu_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();
        $eligibleItems = $items
            ->reject(fn (array $item) => (bool) ($item['is_reward_item'] ?? false))
            ->values();
        $rewardItems = $items
            ->filter(fn (array $item) => (bool) ($item['is_reward_item'] ?? false))
            ->values();

        if ($eligibleItems->isEmpty()) {
            $eligibleItems = $items;
        }

        if ($eligibleItems->count() <= 1) {
            $item = $eligibleItems->first();
            if ($item && $items->count() > 1) {
                $payload['menu_items'] = [$item];
                $payload['reward_menu_items'] = $rewardItems->all();
            }

            return collect([$payload]);
        }

        return $eligibleItems->map(function (array $item) use ($payload) {
            $itemId = $item['menu_item_id'] ?? $item['id'] ?? md5(json_encode($item));

            return array_merge($payload, [
                'display_id' => ($payload['display_id'] ?? ('promotion:'.($payload['id'] ?? '0'))).':item:'.$itemId,
                'display_item_id' => $itemId,
                'title' => $item['name'] ?? $payload['title'],
                'subtitle' => $payload['title'] ?? $payload['subtitle'] ?? $payload['description'] ?? 'Special offer',
                'reward_menu_items' => collect($payload['menu_items'] ?? [])
                    ->filter(fn ($candidate) => is_array($candidate) && (bool) ($candidate['is_reward_item'] ?? false))
                    ->values()
                    ->all(),
                'menu_items' => [$item],
            ]);
        })->values();
    }

    private function promotionDisplayMode(string $type): string
    {
        $type = strtolower($type);
        if (str_contains($type, 'combo') || str_contains($type, 'meal')) {
            return 'bundle';
        }

        if ($type === 'bogo' || str_starts_with($type, 'buy_') || str_starts_with($type, 'free_')) {
            return 'item_reward';
        }

        if (str_contains($type, 'item') || str_contains($type, 'category')) {
            return 'item_offer';
        }

        return 'order_offer';
    }

}



