<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\DeliveryArea;
use App\Models\GlobalMenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\PromotionLog;
use App\Models\PromotionUsage;
use App\Models\Restaurant;
use App\Services\MediaStorage;
use App\Services\PromotionAnalyticsService;
use App\Support\PromotionTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionEngineController extends Controller
{
    public function index(Request $request, PromotionAnalyticsService $analytics)
    {
        $status = $request->input('status');

        $promotions = Promotion::query()
            ->withCount('usages')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.promotion-engine.index', [
            'promotions' => $promotions,
            'summary' => $analytics->summary(),
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('admin.promotion-engine.form', array_merge([
            'promotion' => new Promotion([
                'owner_type' => 'admin',
                'promotion_type' => 'free_delivery',
                'application_mode' => 'automatic',
                'status' => Promotion::STATUS_DRAFT,
                'priority' => 100,
                'conditions' => [],
                'rewards' => ['type' => 'free_delivery'],
                'targets' => [],
                'stacking' => [],
            ]),
            'couponCode' => null,
        ], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $promotion = Promotion::create($this->payload($request));
        $this->syncCouponCode($request, $promotion);

        return redirect()->route('admin.promotion-engine.index')
            ->with('success', 'Promotion created successfully.');
    }

    public function edit(Promotion $promotion)
    {
        $promotion->load('couponCodes');

        return view('admin.promotion-engine.form', array_merge([
            'promotion' => $promotion,
            'couponCode' => $promotion->couponCodes->first(),
        ], $this->formOptions()));
    }

    public function update(Request $request, Promotion $promotion)
    {
        $promotion->update($this->payload($request, $promotion));
        $this->syncCouponCode($request, $promotion);

        return redirect()->route('admin.promotion-engine.index')
            ->with('success', 'Promotion updated successfully.');
    }

    public function toggle(Promotion $promotion)
    {
        $promotion->update([
            'status' => $promotion->status === Promotion::STATUS_ACTIVE
                ? Promotion::STATUS_PAUSED
                : Promotion::STATUS_ACTIVE,
        ]);

        return back()->with('success', 'Promotion status updated.');
    }

    public function destroy(Promotion $promotion)
    {
        MediaStorage::delete($promotion->image_path);
        foreach (['banner_image', 'thumbnail', 'campaign_banner'] as $field) {
            MediaStorage::delete(data_get($promotion->visibility, $field));
        }
        $promotion->delete();

        return redirect()->route('admin.promotion-engine.index')
            ->with('success', 'Promotion deleted successfully.');
    }

    public function analytics(Request $request, PromotionAnalyticsService $analytics)
    {
        $filters = $request->validate([
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'delivery_area_id' => ['nullable', 'integer', 'exists:delivery_areas,id'],
        ]);
        $area = isset($filters['delivery_area_id'])
            ? DeliveryArea::active()->find($filters['delivery_area_id'])
            : null;
        if ($area) {
            $filters['order_ids'] = $this->orderIdsForDeliveryArea($area);
        }

        $usageRows = PromotionUsage::query()
            ->with(['promotion:id,title,status', 'order:id,order_number,total'])
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('order_id', $ids))
            ->latest()
            ->paginate(25)
            ->withQueryString();
        $summary = $analytics->summary($filters);

        return view('admin.promotion-engine.analytics', [
            'summary' => $summary,
            'usageRows' => $usageRows,
            'promotions' => Promotion::query()->orderBy('title')->get(['id', 'title']),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name']),
            'deliveryAreas' => DeliveryArea::active()->orderBy('name')->get(['id', 'name', 'area_type', 'radius_km']),
            'zoneBreakdown' => $this->zoneBreakdown($filters),
            'filters' => $filters,
        ]);
    }

    public function coupons(Request $request)
    {
        $filters = $request->validate([
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'status' => ['nullable', 'in:active,inactive,used,unused,expired'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $coupons = PromotionCouponCode::query()
            ->with(['promotion:id,title,status,promotion_type', 'user:id,name,phone,email'])
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('code', 'like', '%' . $search . '%'))
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->active();
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($status === 'used') {
                    $query->where('used_count', '>', 0);
                } elseif ($status === 'unused') {
                    $query->where('used_count', 0);
                } elseif ($status === 'expired') {
                    $query->whereNotNull('ends_at')->where('ends_at', '<', now());
                }
            })
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.promotion-engine.coupons', [
            'coupons' => $coupons,
            'promotions' => Promotion::query()->orderBy('title')->get(['id', 'title']),
            'filters' => $filters,
        ]);
    }

    public function logs(Request $request)
    {
        $filters = $request->validate([
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'event_type' => ['nullable', 'string', 'max:80'],
        ]);

        $logs = PromotionLog::query()
            ->with('promotion:id,title')
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($filters['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.promotion-engine.logs', [
            'logs' => $logs,
            'promotions' => Promotion::query()->orderBy('title')->get(['id', 'title']),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => PromotionLog::query()->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
            'filters' => $filters,
        ]);
    }

    private function payload(Request $request, ?Promotion $promotion = null): array
    {
        $this->prepareSelectionArrays($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'owner_type' => ['required', 'in:admin,restaurant,branch,system,ai'],
            'restaurant_id' => ['nullable', 'exists:restaurants,id'],
            'promotion_type' => ['required', 'string', 'max:80'],
            'application_mode' => ['required', 'in:automatic,coupon'],
            'status' => ['required', 'in:draft,active,paused'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_exclusive' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'reward_type' => ['required', 'string', 'max:80'],
            'reward_value' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'free_quantity' => ['nullable', 'integer', 'min:1'],
            'free_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'scratch_card_pool' => ['nullable', 'string', 'max:2000'],
            'scratch_trigger' => ['nullable', 'in:order_placed,restaurant_accepts,payment_success,delivery,every_order,every_n_orders,manual'],
            'scratch_every_n_orders' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'scratch_generate_reward_timing' => ['nullable', 'in:during_scratch,on_card_creation'],
            'scratch_card_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'scratch_reward_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'scratch_rewards' => ['nullable', 'array', 'max:50'],
            'scratch_rewards.*.name' => ['nullable', 'string', 'max:120'],
            'scratch_rewards.*.type' => ['nullable', 'string', 'max:80'],
            'scratch_rewards.*.value' => ['nullable', 'numeric', 'min:0'],
            'scratch_rewards.*.probability' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scratch_rewards.*.max_redemptions' => ['nullable', 'integer', 'min:1'],
            'scratch_rewards.*.daily_limit' => ['nullable', 'integer', 'min:1'],
            'scratch_rewards.*.budget' => ['nullable', 'numeric', 'min:0'],
            'scratch_rewards.*.expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'scratch_rewards.*.priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'scratch_rewards.*.metadata' => ['nullable', 'string', 'max:2000'],
            'referral_bonus_type' => ['required_if:reward_type,referral_bonus', 'nullable', 'in:wallet_credit,reward_points,gift_voucher'],
            'custom_rule_config' => ['required_if:reward_type,custom_rule', 'nullable', 'string', 'max:4000'],
            'reward_item_ids' => ['nullable', 'array'],
            'reward_item_ids.*' => ['integer', 'exists:menu_items,id'],
            'reward_category_ids' => ['nullable', 'array'],
            'reward_category_ids.*' => ['integer', 'exists:categories,id'],
            'combo_groups' => ['nullable', 'array', 'max:30'],
            'combo_groups.*.name' => ['nullable', 'string', 'max:120'],
            'combo_groups.*.item_ids' => ['nullable', 'array'],
            'combo_groups.*.item_ids.*' => ['integer', 'exists:menu_items,id'],
            'combo_groups.*.price' => ['nullable', 'numeric', 'min:0'],
            'combo_groups.*.actual_price' => ['nullable', 'numeric', 'min:0'],
            'combo_groups.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'audience_type' => ['nullable', 'string', 'max:80'],
            'order_types' => ['nullable', 'array'],
            'order_types.*' => ['string', 'max:30'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', 'max:30'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:30'],
            'target_zone_ids' => ['nullable', 'array'],
            'target_zone_ids.*' => ['integer', 'exists:delivery_areas,id'],
            'customer_tags' => ['nullable', 'string', 'max:500'],
            'customer_ids' => ['nullable', 'string', 'max:500'],
            'target_cuisine_ids' => ['nullable', 'array'],
            'target_cuisine_ids.*' => ['integer', 'exists:cuisines,id'],
            'target_subcategory_ids' => ['nullable', 'array'],
            'target_subcategory_ids.*' => ['integer', 'exists:global_menu_categories,id'],
            'contains_item_ids' => ['nullable', 'array'],
            'contains_item_ids.*' => ['integer', 'exists:menu_items,id'],
            'exclude_item_ids' => ['nullable', 'array'],
            'exclude_item_ids.*' => ['integer', 'exists:menu_items,id'],
            'contains_category_ids' => ['nullable', 'array'],
            'contains_category_ids.*' => ['integer', 'exists:categories,id'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_category_ids.*' => ['integer', 'exists:categories,id'],
            'min_item_count' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'min_distance_km' => ['nullable', 'numeric', 'min:0'],
            'max_distance_km' => ['nullable', 'numeric', 'min:0'],
            'min_delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'max_delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'min_packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'max_packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'min_tax' => ['nullable', 'numeric', 'min:0'],
            'max_tax' => ['nullable', 'numeric', 'min:0'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'allow_multiple' => ['nullable', 'boolean'],
            'allow_coupon_promotion' => ['nullable', 'boolean'],
            'best_offer_only' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => ['string', 'max:40'],
            'promotion_color' => ['nullable', 'string', 'max:20'],
            'tags' => ['nullable', 'string', 'max:500'],
            'internal_notes' => ['nullable', 'string', 'max:200'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'landing_page' => ['nullable', 'string', 'max:255'],
            'push_notification' => ['nullable', 'string', 'max:255'],
            'email_copy' => ['nullable', 'string', 'max:1000'],
            'sms_copy' => ['nullable', 'string', 'max:255'],
            'whatsapp_copy' => ['nullable', 'string', 'max:255'],
            'campaign_audience' => ['nullable', 'string', 'max:255'],
            'campaign_status' => ['nullable', 'string', 'max:80'],
            'campaign_tab_budget' => ['nullable', 'numeric', 'min:0'],
            'promotion_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
            'banner_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
            'campaign_banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ]);

        $ownerType = $validated['owner_type'] ?? 'admin';
        $validated['promotion_type'] = PromotionTypeRegistry::normalize($validated['promotion_type'] ?? null);
        $allowedTypes = PromotionTypeRegistry::typesForOwner($ownerType);
        if (! array_key_exists($validated['promotion_type'], $allowedTypes)) {
            throw ValidationException::withMessages([
                'promotion_type' => in_array($ownerType, ['restaurant', 'branch'], true)
                    ? 'Restaurant-owned promotions can use only restaurant menu promotion types.'
                    : 'Admin promotions can use only platform promotion types.',
            ]);
        }
        $validated['reward_type'] = $allowedTypes[$validated['promotion_type']]['reward_type'] ?? $validated['reward_type'];

        $scratchPool = $this->scratchRewardPool($validated);
        if (($validated['reward_type'] ?? null) === 'scratch_card' && $scratchPool === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'scratch_rewards' => 'Add at least one scratch card reward.',
            ]);
        }

        if (($validated['reward_type'] ?? null) === 'scratch_card' && collect($scratchPool)->contains(fn (array $reward) => array_key_exists('probability', $reward))) {
            $probabilityTotal = round((float) collect($scratchPool)->sum(fn (array $reward) => (float) ($reward['probability'] ?? 0)), 2);
            if (abs($probabilityTotal - 100.0) > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'scratch_rewards' => 'Scratch card reward probability total must equal 100%. Current total is '.$probabilityTotal.'%.',
                ]);
            }
        }
        if (($validated['reward_type'] ?? null) === 'scratch_card') {
            $this->validateScratchEnterpriseRules($validated, $scratchPool);
        }

        $restaurantId = isset($validated['restaurant_id']) ? (int) $validated['restaurant_id'] : null;
        $promotionType = strtolower((string) $validated['promotion_type']);
        $rewardType = strtolower((string) $validated['reward_type']);
        $rewardItemIds = array_values(array_unique(array_map('intval', $validated['reward_item_ids'] ?? [])));
        $rewardCategoryIds = array_values(array_unique(array_map('intval', $validated['reward_category_ids'] ?? [])));
        $comboGroups = $this->normalizedComboGroups($validated['combo_groups'] ?? [], isset($validated['reward_value']) ? (float) $validated['reward_value'] : null);
        if ($comboGroups !== []) {
            $rewardItemIds = array_values(array_unique(array_merge(
                $rewardItemIds,
                collect($comboGroups)->flatMap(fn (array $group) => $group['item_ids'] ?? [])->map(fn ($id) => (int) $id)->all()
            )));
        }
        $targetCuisineIds = array_values(array_unique(array_map('intval', $validated['target_cuisine_ids'] ?? [])));
        $targetSubcategoryIds = array_values(array_unique(array_map('intval', $validated['target_subcategory_ids'] ?? [])));
        $conditionContainsItemIds = array_values(array_unique(array_map('intval', $validated['contains_item_ids'] ?? [])));
        $conditionExcludeItemIds = array_values(array_unique(array_map('intval', $validated['exclude_item_ids'] ?? [])));
        $conditionContainsCategoryIds = array_values(array_unique(array_map('intval', $validated['contains_category_ids'] ?? [])));
        $conditionExcludeCategoryIds = array_values(array_unique(array_map('intval', $validated['exclude_category_ids'] ?? [])));
        $freeItemId = isset($validated['free_item_id']) ? (int) $validated['free_item_id'] : null;
        $buyQuantity = isset($validated['buy_quantity']) ? (int) $validated['buy_quantity'] : null;
        $freeQuantity = isset($validated['free_quantity']) ? (int) $validated['free_quantity'] : null;

        $this->validateScopedSelections(
            $rewardItemIds,
            $rewardCategoryIds,
            $freeItemId,
            $restaurantId
        );
        $this->validateTypeCompleteness(
            $promotionType,
            $rewardType,
            $rewardItemIds,
            $rewardCategoryIds,
            $comboGroups
        );

        $targetItemIds = $this->usesMappedItemsAsTargets($promotionType, $rewardType)
            ? $rewardItemIds
            : [];
        $targetCategoryIds = $this->usesMappedCategoriesAsTargets($promotionType, $rewardType)
            ? $rewardCategoryIds
            : [];
        if ($targetSubcategoryIds !== []) {
            $conditionContainsItemIds = array_values(array_unique(array_merge(
                $conditionContainsItemIds,
                $this->menuItemIdsForGlobalSubcategories($targetSubcategoryIds, $restaurantId)
            )));
        }
        $requiresAllTargetItems = in_array($promotionType, ['combo_deal', 'meal_deal'], true) && $comboGroups === [];
        $buyRule = $this->buyRuleForType($promotionType, $rewardType, $buyQuantity);
        $rewardRule = $this->rewardRuleForType(
            $promotionType,
            $rewardType,
            $buyQuantity,
            $freeQuantity,
            $freeItemId
        );

        $rewards = [
            'type' => $validated['reward_type'],
            'value' => isset($validated['reward_value']) ? (float) $validated['reward_value'] : 0,
            'max_discount' => isset($validated['max_discount']) ? (float) $validated['max_discount'] : null,
            'buy_quantity' => $buyQuantity,
            'free_quantity' => $freeQuantity,
            'free_item_id' => $freeItemId,
            'reward_rule' => $rewardRule,
            'pool' => $scratchPool,
            'settings' => array_filter([
                'trigger' => $validated['scratch_trigger'] ?? 'payment_success',
                'every_n_orders' => isset($validated['scratch_every_n_orders']) ? (int) $validated['scratch_every_n_orders'] : null,
                'generate_reward_timing' => $validated['scratch_generate_reward_timing'] ?? 'during_scratch',
                'expiry_days' => isset($validated['scratch_card_expiry_days']) ? (int) $validated['scratch_card_expiry_days'] : null,
                'reward_expiry_days' => isset($validated['scratch_reward_expiry_days']) ? (int) $validated['scratch_reward_expiry_days'] : null,
                'auto_credit_wallet' => $request->boolean('scratch_auto_credit_wallet', true),
                'auto_credit_points' => $request->boolean('scratch_auto_credit_points', true),
                'generate_coupon_on_reveal' => $request->boolean('scratch_generate_coupon_on_reveal', true),
                'auto_notify_customer' => $request->boolean('scratch_auto_notify_customer', true),
                'enable_confetti' => $request->boolean('scratch_enable_confetti', true),
                'enable_animation' => $request->boolean('scratch_enable_animation', true),
                'enable_sound' => $request->boolean('scratch_enable_sound'),
                'enable_haptic' => $request->boolean('scratch_enable_haptic', true),
            ], fn ($value) => $value !== null),
            'bonus_type' => $validated['referral_bonus_type'] ?? null,
            'custom_rule' => isset($validated['custom_rule_config']) ? trim((string) $validated['custom_rule_config']) : null,
            'item_ids' => $rewardItemIds,
            'category_ids' => $rewardCategoryIds,
            'combo_groups' => $comboGroups,
        ];
        $existingVisibility = $promotion?->visibility ?? [];
        $imagePath = $promotion?->image_path;
        if ($request->hasFile('promotion_image')) {
            MediaStorage::delete($imagePath);
            $imagePath = MediaStorage::store($request->file('promotion_image'), 'promotions');
        }

        $visibilityMedia = [
            'banner_image' => data_get($existingVisibility, 'banner_image'),
            'thumbnail' => data_get($existingVisibility, 'thumbnail'),
            'campaign_banner' => data_get($existingVisibility, 'campaign_banner'),
        ];
        foreach (array_keys($visibilityMedia) as $field) {
            if ($request->hasFile($field)) {
                MediaStorage::delete($visibilityMedia[$field]);
                $visibilityMedia[$field] = MediaStorage::store($request->file($field), 'promotions');
            }
        }

        $visibility = array_filter(array_merge($visibilityMedia, [
            'show_on' => $validated['visibility'] ?? [],
            'color' => $validated['promotion_color'] ?? data_get($existingVisibility, 'color'),
            'tags' => $this->csvToArray($validated['tags'] ?? null),
            'internal_notes' => $validated['internal_notes'] ?? data_get($existingVisibility, 'internal_notes'),
            'campaign_name' => $validated['campaign_name'] ?? data_get($existingVisibility, 'campaign_name'),
            'landing_page' => $validated['landing_page'] ?? data_get($existingVisibility, 'landing_page'),
            'push_notification' => $validated['push_notification'] ?? data_get($existingVisibility, 'push_notification'),
            'email_copy' => $validated['email_copy'] ?? data_get($existingVisibility, 'email_copy'),
            'sms_copy' => $validated['sms_copy'] ?? data_get($existingVisibility, 'sms_copy'),
            'whatsapp_copy' => $validated['whatsapp_copy'] ?? data_get($existingVisibility, 'whatsapp_copy'),
            'campaign_audience' => $validated['campaign_audience'] ?? data_get($existingVisibility, 'campaign_audience'),
            'campaign_status' => $validated['campaign_status'] ?? data_get($existingVisibility, 'campaign_status'),
            'campaign_budget' => isset($validated['campaign_tab_budget']) ? (float) $validated['campaign_tab_budget'] : data_get($existingVisibility, 'campaign_budget'),
        ]), fn ($value) => $value !== null && $value !== '' && $value !== []);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'owner_type' => $validated['owner_type'],
            'owner_id' => $restaurantId,
            'restaurant_id' => $restaurantId,
            'promotion_type' => $validated['promotion_type'],
            'application_mode' => $validated['application_mode'],
            'status' => $validated['status'],
            'priority' => (int) ($validated['priority'] ?? 100),
            'is_exclusive' => $request->boolean('is_exclusive'),
            'image_path' => $imagePath,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'starts_time' => $validated['starts_time'] ?? null,
            'ends_time' => $validated['ends_time'] ?? null,
            'schedule' => [
                'weekdays' => array_map('intval', $validated['weekdays'] ?? []),
            ],
            'targets' => [
                'restaurant_ids' => $restaurantId ? [(int) $restaurantId] : [],
                'item_ids' => $targetItemIds,
                'category_ids' => $targetCategoryIds,
                'cuisine_ids' => $targetCuisineIds,
                'subcategory_ids' => $targetSubcategoryIds,
                'zone_ids' => array_map('intval', $validated['target_zone_ids'] ?? []),
                'order_types' => $validated['order_types'] ?? [],
                'platforms' => $validated['platforms'] ?? [],
                'customer_tags' => $this->csvToArray($validated['customer_tags'] ?? null),
                'customer_ids' => $this->csvToIntArray($validated['customer_ids'] ?? null),
            ],
            'conditions' => [
                'min_order_amount' => $validated['min_order_amount'] ?? null,
                'max_order_amount' => $validated['max_order_amount'] ?? null,
                'audience_type' => $validated['audience_type'] ?? 'all',
                'payment_methods' => $validated['payment_methods'] ?? [],
                'requires_all_target_items' => $requiresAllTargetItems,
                'buy_rule' => $buyRule,
                'contains_item_ids' => $conditionContainsItemIds,
                'exclude_item_ids' => $conditionExcludeItemIds,
                'contains_category_ids' => $conditionContainsCategoryIds,
                'exclude_category_ids' => $conditionExcludeCategoryIds,
                'contains_subcategory_ids' => $targetSubcategoryIds,
                'min_item_count' => $validated['min_item_count'] ?? null,
                'min_quantity' => $validated['min_quantity'] ?? null,
                'max_quantity' => $validated['max_quantity'] ?? null,
                'min_distance_km' => $validated['min_distance_km'] ?? null,
                'max_distance_km' => $validated['max_distance_km'] ?? null,
                'min_delivery_fee' => $validated['min_delivery_fee'] ?? null,
                'max_delivery_fee' => $validated['max_delivery_fee'] ?? null,
                'min_packaging_fee' => $validated['min_packaging_fee'] ?? null,
                'max_packaging_fee' => $validated['max_packaging_fee'] ?? null,
                'min_tax' => $validated['min_tax'] ?? null,
                'max_tax' => $validated['max_tax'] ?? null,
                'min_weight' => $validated['min_weight'] ?? null,
                'max_weight' => $validated['max_weight'] ?? null,
            ],
            'rewards' => array_filter($rewards, fn ($value) => $value !== null && $value !== []),
            'stacking' => [
                'allow_multiple' => $request->boolean('allow_multiple'),
                'allow_coupon_promotion' => $request->boolean('allow_coupon_promotion', true),
                'best_offer_only' => $request->boolean('best_offer_only'),
            ],
            'visibility' => $visibility,
        ];
    }

    private function linesToArray(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function scratchRewardPool(array $validated): array
    {
        $rows = collect($validated['scratch_rewards'] ?? [])
            ->map(function (array $row, int $index) {
                $name = trim((string) ($row['name'] ?? ''));
                $type = trim((string) ($row['type'] ?? ''));
                if ($name === '' || $type === '') {
                    return null;
                }

                $metadata = [];
                if (isset($row['metadata']) && trim((string) $row['metadata']) !== '') {
                    $decoded = json_decode((string) $row['metadata'], true);
                    $metadata = is_array($decoded) ? $decoded : ['note' => trim((string) $row['metadata'])];
                }

                return array_filter([
                    'key' => 'reward_' . ($index + 1),
                    'name' => $name,
                    'type' => $type,
                    'value' => isset($row['value']) && $row['value'] !== '' ? (float) $row['value'] : 0,
                    'probability' => isset($row['probability']) && $row['probability'] !== '' ? (float) $row['probability'] : null,
                    'max_redemptions' => isset($row['max_redemptions']) && $row['max_redemptions'] !== '' ? (int) $row['max_redemptions'] : null,
                    'daily_limit' => isset($row['daily_limit']) && $row['daily_limit'] !== '' ? (int) $row['daily_limit'] : null,
                    'budget' => isset($row['budget']) && $row['budget'] !== '' ? (float) $row['budget'] : null,
                    'expiry_days' => isset($row['expiry_days']) && $row['expiry_days'] !== '' ? (int) $row['expiry_days'] : null,
                    'priority' => isset($row['priority']) && $row['priority'] !== '' ? (int) $row['priority'] : null,
                    'metadata' => $metadata,
                ], fn ($value) => $value !== null && $value !== []);
            })
            ->filter()
            ->values()
            ->all();

        return $rows !== [] ? $rows : $this->linesToArray($validated['scratch_card_pool'] ?? null);
    }

    private function validateScratchEnterpriseRules(array $validated, array $scratchPool): void
    {
        $messages = [];

        if (($validated['scratch_generate_reward_timing'] ?? 'during_scratch') !== 'during_scratch') {
            $messages['scratch_generate_reward_timing'] = 'Scratch rewards must be generated during scratch to prevent reward manipulation.';
        }

        if (($validated['scratch_trigger'] ?? null) === 'every_n_orders' && empty($validated['scratch_every_n_orders'])) {
            $messages['scratch_every_n_orders'] = 'Enter the order interval for Every N Orders scratch campaigns.';
        }

        foreach ($scratchPool as $index => $reward) {
            $type = (string) ($reward['type'] ?? '');
            $value = (float) ($reward['value'] ?? 0);
            $dailyLimit = (int) ($reward['daily_limit'] ?? 0);
            $maxRedemptions = (int) ($reward['max_redemptions'] ?? 0);

            if (in_array($type, ['wallet_cashback', 'wallet_credit', 'cashback'], true) && $value <= 0) {
                $messages["scratch_rewards.$index.value"] = 'Wallet cashback reward amount must be positive.';
            }

            if ($maxRedemptions > 0 && $dailyLimit > 0 && $maxRedemptions < $dailyLimit) {
                $messages["scratch_rewards.$index.max_redemptions"] = 'Maximum redemption must be greater than or equal to daily limit.';
            }
        }

        if ($messages !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }
    }

    private function usesMappedItemsAsTargets(string $promotionType, string $rewardType): bool
    {
        return in_array($promotionType, [
            'item_discount',
            'combo_deal',
            'meal_deal',
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
            'free_item',
        ], true) || in_array($rewardType, [
            'item_discount',
            'combo_deal',
            'meal_deal',
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
            'free_item',
        ], true);
    }

    private function usesMappedCategoriesAsTargets(string $promotionType, string $rewardType): bool
    {
        return in_array($promotionType, [
            'category_discount',
            'combo_deal',
            'meal_deal',
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
        ], true) || in_array($rewardType, [
            'category_discount',
            'combo_deal',
            'meal_deal',
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
        ], true);
    }

    private function rewardRuleForType(
        string $promotionType,
        string $rewardType,
        ?int $buyQuantity,
        ?int $freeQuantity,
        ?int $freeItemId
    ): array {
        $workflowTypes = [
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
            'free_item',
        ];

        if (! in_array($promotionType, $workflowTypes, true) && ! in_array($rewardType, $workflowTypes, true)) {
            return [];
        }

        $rule = [
            'reward_type' => $freeItemId ? 'configured_item' : 'same_item',
            'reward_quantity' => max(1, (int) ($freeQuantity ?: 1)),
            'auto_add' => true,
            'manual_selection' => false,
        ];

        if ($freeItemId) {
            $rule['item_ids'] = [$freeItemId];
        }

        if ($buyQuantity) {
            $rule['buy_quantity'] = max(1, $buyQuantity);
        }

        return $rule;
    }

    private function buyRuleForType(string $promotionType, string $rewardType, ?int $buyQuantity): array
    {
        $workflowTypes = [
            'bogo',
            'buy_x_get_y',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
            'free_item',
        ];

        if (! in_array($promotionType, $workflowTypes, true) && ! in_array($rewardType, $workflowTypes, true)) {
            return [];
        }

        return [
            'buy_quantity' => max(1, (int) ($buyQuantity ?: match ($promotionType) {
                'buy_2_get_1' => 2,
                'buy_3_get_1' => 3,
                'buy_3_get_2' => 3,
                default => 1,
            })),
        ];
    }

    private function csvToArray(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values()
            ->all();
    }

    private function csvToIntArray(?string $value): array
    {
        return collect($this->csvToArray($value))
            ->map(fn ($part) => (int) $part)
            ->filter(fn (int $part) => $part > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedComboGroups(array $groups, ?float $fallbackPrice = null): array
    {
        $priceByItemId = MenuItem::query()
            ->whereIn('id', collect($groups)
                ->flatMap(fn ($group) => is_array($group) ? (array) ($group['item_ids'] ?? []) : [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->pluck('price', 'id');

        return collect($groups)
            ->map(function (array $group, int $index) use ($priceByItemId, $fallbackPrice) {
                $itemIds = collect($group['item_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if (count($itemIds) < 2) {
                    return null;
                }

                $name = trim((string) ($group['name'] ?? ''));
                $actualPrice = $itemIds === []
                    ? 0.0
                    : round((float) collect($itemIds)->sum(fn (int $id) => (float) ($priceByItemId[$id] ?? 0)), 2);
                $effectivePrice = isset($group['price']) && $group['price'] !== ''
                    ? (float) $group['price']
                    : $fallbackPrice;
                $discountPercent = $actualPrice > 0 && $effectivePrice !== null
                    ? max(0, round((($actualPrice - (float) $effectivePrice) / $actualPrice) * 100, 2))
                    : null;

                return array_filter([
                    'key' => 'combo_' . ($index + 1),
                    'name' => $name !== '' ? $name : 'Combo ' . ($index + 1),
                    'item_ids' => $itemIds,
                    'actual_price' => $actualPrice,
                    'price' => $effectivePrice,
                    'discount_percent' => $discountPercent,
                ], fn ($value) => $value !== null && $value !== []);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function prepareSelectionArrays(Request $request): void
    {
        foreach ([
            'target_cuisine_ids',
            'target_subcategory_ids',
            'contains_item_ids',
            'exclude_item_ids',
            'contains_category_ids',
            'exclude_category_ids',
        ] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            if (is_array($value)) {
                $request->merge([
                    $field => collect($value)
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn (int $id) => $id > 0)
                        ->unique()
                        ->values()
                        ->all(),
                ]);
                continue;
            }

            $request->merge([$field => $this->csvToIntArray((string) $value)]);
        }
    }

    private function menuItemIdsForGlobalSubcategories(array $subcategoryIds, ?int $restaurantId): array
    {
        if ($subcategoryIds === []) {
            return [];
        }

        $subcategoryNames = GlobalMenuCategory::query()
            ->whereIn('id', $subcategoryIds)
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter()
            ->values();

        if ($subcategoryNames->isEmpty()) {
            return [];
        }

        return MenuItem::query()
            ->whereNotNull('master_menu_item_id')
            ->when($restaurantId, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->whereHas('masterMenuItem', function ($query) use ($subcategoryNames) {
                $query->whereIn(DB::raw('LOWER(subcategory_name)'), $subcategoryNames->all());
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validateScopedSelections(array $itemIds, array $categoryIds, $freeItemId, ?int $restaurantId): void
    {
        if (! $restaurantId) {
            return;
        }

        $messages = [];

        if ($itemIds !== [] && MenuItem::query()
            ->whereIn('id', $itemIds)
            ->where('restaurant_id', '!=', $restaurantId)
            ->exists()) {
            $messages['reward_item_ids'] = 'Selected items must belong to the selected restaurant scope.';
        }

        if ($categoryIds !== [] && Category::query()
            ->whereIn('id', $categoryIds)
            ->where('restaurant_id', '!=', $restaurantId)
            ->exists()) {
            $messages['reward_category_ids'] = 'Selected categories must belong to the selected restaurant scope.';
        }

        if ($freeItemId && MenuItem::query()
            ->whereKey((int) $freeItemId)
            ->where('restaurant_id', '!=', $restaurantId)
            ->exists()) {
            $messages['free_item_id'] = 'The free item must belong to the selected restaurant scope.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function validateTypeCompleteness(string $promotionType, string $rewardType, array $itemIds, array $categoryIds, array $comboGroups = []): void
    {
        $messages = [];

        if (in_array($promotionType, ['combo_deal', 'meal_deal'], true) && $comboGroups === [] && count($itemIds) < 2) {
            $messages['combo_groups'] = 'Combo and meal deals need at least one group with two or more menu items.';
        }

        if (in_array($promotionType, ['combo_deal', 'meal_deal'], true) && $comboGroups !== []) {
            foreach ($comboGroups as $index => $group) {
                if ((float) ($group['price'] ?? 0) <= 0) {
                    $messages["combo_groups.$index.price"] = 'Set an effective combo price for every combo group.';
                }
            }
        }

        if ($promotionType === 'item_discount' && count($itemIds) < 1) {
            $messages['reward_item_ids'] = 'Item discounts need at least one mapped menu item.';
        }

        if ($promotionType === 'category_discount' && count($categoryIds) < 1) {
            $messages['reward_category_ids'] = 'Category discounts need at least one mapped category.';
        }

        if (in_array($promotionType, ['bogo', 'buy_x_get_y', 'buy_2_get_1', 'buy_3_get_1', 'buy_3_get_2', 'free_item'], true) && count($itemIds) < 1) {
            $messages['reward_item_ids'] = 'Buy/free and free-item promotions need at least one mapped menu item.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function formOptions(): array
    {
        return [
            'adminPromotionTypes' => PromotionTypeRegistry::adminTypes(),
            'restaurantPromotionTypes' => PromotionTypeRegistry::restaurantTypes(),
            'restaurants' => Restaurant::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->with('restaurant:id,name')
                ->orderBy('restaurant_id')
                ->orderBy('name')
                ->get(['id', 'restaurant_id', 'name']),
            'cuisines' => Cuisine::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'globalSubcategories' => GlobalMenuCategory::query()
                ->active()
                ->whereNotNull('parent_id')
                ->with('parent:id,name')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name']),
            'menuItems' => MenuItem::query()
                ->with(['restaurant:id,name', 'category:id,name', 'cuisine:id,name', 'masterMenuItem:id,subcategory_name'])
                ->orderBy('restaurant_id')
                ->orderBy('name')
                ->limit(1000)
                ->get(['id', 'restaurant_id', 'category_id', 'cuisine_id', 'master_menu_item_id', 'name', 'price']),
            'deliveryAreas' => DeliveryArea::active()
                ->orderBy('name')
                ->get(['id', 'name', 'area_type', 'radius_km']),
        ];
    }

    private function orderIdsForDeliveryArea(DeliveryArea $area): array
    {
        return Order::query()
            ->whereNotNull('delivery_lat')
            ->whereNotNull('delivery_lng')
            ->latest()
            ->limit(5000)
            ->get(['id', 'delivery_lat', 'delivery_lng'])
            ->filter(fn (Order $order) => $area->containsPoint((float) $order->delivery_lat, (float) $order->delivery_lng))
            ->pluck('id')
            ->values()
            ->all() ?: [-1];
    }

    private function zoneBreakdown(array $filters)
    {
        $areas = DeliveryArea::active()->orderBy('name')->get();
        if ($areas->isEmpty()) {
            return collect();
        }

        $usageRows = PromotionUsage::query()
            ->with('order:id,delivery_lat,delivery_lng')
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->whereNotNull('order_id')
            ->latest()
            ->limit(5000)
            ->get();

        return $areas->map(function (DeliveryArea $area) use ($usageRows) {
            $matched = $usageRows->filter(fn (PromotionUsage $usage) => $usage->order
                && $usage->order->delivery_lat !== null
                && $usage->order->delivery_lng !== null
                && $area->containsPoint((float) $usage->order->delivery_lat, (float) $usage->order->delivery_lng));

            return [
                'id' => $area->id,
                'name' => $area->name,
                'usage_count' => $matched->count(),
                'discount_given' => round((float) $matched->sum('discount_amount'), 2),
            ];
        })->filter(fn ($row) => $row['usage_count'] > 0)->values();
    }

    private function syncCouponCode(Request $request, Promotion $promotion): void
    {
        $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'coupon_usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($promotion->application_mode !== 'coupon' || ! $request->filled('coupon_code')) {
            return;
        }

        PromotionCouponCode::updateOrCreate(
            ['code' => strtoupper(trim($request->input('coupon_code')))],
            [
                'promotion_id' => $promotion->id,
                'usage_limit' => $request->input('coupon_usage_limit'),
                'is_active' => $promotion->status === Promotion::STATUS_ACTIVE,
                'starts_at' => $promotion->starts_at,
                'ends_at' => $promotion->ends_at,
            ]
        );
    }
}
