<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionLog;
use App\Services\CouponService;
use App\Services\PromotionAnalytics;
use App\Services\PromotionEngineService;
use App\Services\PromotionPreviewService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionEngineService $engine,
        private readonly CouponService $coupons,
        private readonly PromotionAnalytics $analytics,
        private readonly PromotionPreviewService $previewer,
    ) {
    }

    public function index(Request $request)
    {
        $context = $request->validate([
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'order_type' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'owner_type' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->engine->list($context),
        ]);
    }

    public function show(Promotion $promotion)
    {
        $promotion->load('couponCodes');

        return response()->json([
            'success' => true,
            'data' => $promotion->toPublicPayload() + [
                'coupon_codes' => $promotion->couponCodes,
            ],
        ]);
    }

    public function calculate(Request $request)
    {
        $context = $this->cartContext($request);

        return response()->json([
            'success' => true,
            'data' => $this->engine->calculate($context),
        ]);
    }

    public function validateCoupon(Request $request)
    {
        $context = $this->cartContext($request, [
            'code' => ['required_without:coupon_code', 'string', 'max:100'],
            'coupon_code' => ['required_without:code', 'string', 'max:100'],
        ]);
        $result = $this->engine->validateCoupon($context);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ], $result['success'] ? 200 : 400);
    }

    public function applyBest(Request $request)
    {
        $context = $this->cartContext($request);

        return response()->json([
            'success' => true,
            'data' => $this->engine->applyBest($context),
        ]);
    }

    public function remove(Request $request)
    {
        $context = $this->cartContext($request);

        return response()->json([
            'success' => true,
            'data' => $this->engine->remove($context),
        ]);
    }

    public function preview(Request $request)
    {
        $context = $this->cartContext($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewer->preview($context),
        ]);
    }

    public function generateCoupons(Request $request)
    {
        $validated = $request->validate([
            'promotion_id' => ['required', 'exists:promotions,id'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'length' => ['nullable', 'integer', 'min:4', 'max:32'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sequential' => ['nullable', 'boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $codes = $this->coupons->generateAndPersist($validated);

        return response()->json([
            'success' => true,
            'data' => ['codes' => $codes],
        ]);
    }

    public function couponDetails(string $code)
    {
        $details = $this->coupons->details($code);

        if (! $details) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found or inactive.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    public function analytics(Request $request)
    {
        $filters = $request->validate([
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->summary($filters),
        ]);
    }

    public function logs(Request $request)
    {
        $validated = $request->validate([
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'event_type' => ['nullable', 'string'],
        ]);

        $logs = PromotionLog::query()
            ->when($validated['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($validated['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($validated['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->latest()
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    private function cartContext(Request $request, array $extraRules = []): array
    {
        $validated = $request->validate(array_merge([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'platform_fee' => ['nullable', 'numeric', 'min:0'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'order_type' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'device' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'nth_order' => ['nullable', 'integer', 'min:1'],
            'zone_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'customer_tags' => ['nullable', 'array'],
            'customer_tags.*' => ['string', 'max:80'],
            'user_group' => ['nullable', 'string', 'max:80'],
            'customer_tier' => ['nullable', 'string', 'max:80'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.menu_item_id' => ['nullable', 'integer'],
            'items.*.category_id' => ['nullable', 'integer'],
            'items.*.brand_id' => ['nullable', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.cuisine_id' => ['nullable', 'integer'],
            'items.*.addon_ids' => ['nullable', 'array'],
            'items.*.addon_ids.*' => ['integer'],
            'items.*.tags' => ['nullable', 'array'],
            'items.*.tags.*' => ['string', 'max:80'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ], $extraRules));

        $validated['user_id'] = $request->user()?->id;

        return $validated;
    }
}
