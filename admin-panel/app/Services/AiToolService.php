<?php

namespace App\Services;

use App\Http\Controllers\Api\OrderController;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Address;
use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiToolService
{
    public function call(string $tool, array $arguments, Request $request): array
    {
        return match ($tool) {
            'search_restaurants' => $this->searchRestaurants($arguments),
            'search_products' => $this->searchProducts($arguments),
            'get_restaurant' => $this->getRestaurant($arguments),
            'get_product', 'get_product_variants', 'get_product_addons' => $this->getProduct($arguments),
            'get_saved_addresses' => $this->getSavedAddresses($request),
            'calculate_order_totals' => $this->calculateOrderTotals($arguments, $request),
            'get_payment_methods' => $this->getPaymentMethods(),
            'create_order', 'confirm_order' => $this->createOrder($arguments, $request),
            'track_order', 'get_order_status' => $this->getOrderStatus($arguments, $request),
            'cancel_order' => $this->cancelOrder($arguments, $request),
            default => throw ValidationException::withMessages(['tool' => "Unsupported AI tool [{$tool}]."]),
        };
    }

    private function searchRestaurants(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'query' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ])->validate();

        $queryText = trim((string) ($validated['query'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 10);

        $restaurants = Restaurant::query()
            ->verified()
            ->when($queryText !== '', fn ($builder) => $builder->search($queryText))
            ->when(isset($validated['lat'], $validated['lng']), fn ($builder) => $builder->nearby((float) $validated['lat'], (float) $validated['lng']))
            ->limit($limit)
            ->get();

        return ['restaurants' => RestaurantResource::collection($restaurants)->resolve()];
    }

    private function searchProducts(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'query' => ['required', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ])->validate();

        $search = trim($validated['query']);
        $items = MenuItem::query()
            ->with(['restaurant', 'category', 'cuisine', 'masterMenuItem'])
            ->where('is_available', true)
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('approval_status')->orWhere('approval_status', 'approved');
            })
            ->when($validated['restaurant_id'] ?? null, fn ($builder, $restaurantId) => $builder->where('restaurant_id', $restaurantId))
            ->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('cuisine', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->limit((int) ($validated['limit'] ?? 8))
            ->get()
            ->filter(fn (MenuItem $item) => $item->is_scheduled_available && $item->restaurant?->isOpenNow())
            ->values();

        return ['items' => $items->map(fn (MenuItem $item) => $this->productPayload($item))->values()->all()];
    }

    private function getRestaurant(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
        ])->validate();

        return ['restaurant' => (new RestaurantResource(Restaurant::findOrFail($validated['restaurant_id'])))->resolve()];
    }

    private function getProduct(array $arguments): array
    {
        $validated = Validator::make($arguments, [
            'product_id' => ['required_without:item_id', 'integer', 'exists:menu_items,id'],
            'item_id' => ['required_without:product_id', 'integer', 'exists:menu_items,id'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
        ])->validate();

        $item = MenuItem::query()
            ->with(['restaurant', 'category', 'cuisine', 'masterMenuItem'])
            ->when($validated['restaurant_id'] ?? null, fn ($builder, $restaurantId) => $builder->where('restaurant_id', $restaurantId))
            ->findOrFail($validated['product_id'] ?? $validated['item_id']);

        return ['item' => $this->productPayload($item, true)];
    }

    private function getSavedAddresses(Request $request): array
    {
        return [
            'addresses' => Address::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->latest()
                ->get(['id', 'name', 'city', 'state', 'pincode', 'is_default', 'latitude', 'longitude'])
                ->values()
                ->all(),
        ];
    }

    private function calculateOrderTotals(array $arguments, Request $request): array
    {
        $toolRequest = $this->makeOrderRequest('/api/orders/summary', $arguments, $request, false);
        $data = app(OrderController::class)->summary($toolRequest)->getData(true);

        if (! ($data['success'] ?? false)) {
            throw ValidationException::withMessages(['order' => $data['message'] ?? 'Unable to calculate order totals.']);
        }

        return $this->sanitizeOrderSummary($data['data'] ?? []);
    }

    private function createOrder(array $arguments, Request $request): array
    {
        $toolRequest = $this->makeOrderRequest('/api/orders', $arguments, $request, true);
        $data = app(OrderController::class)->store($toolRequest)->getData(true);

        if (! ($data['success'] ?? false)) {
            throw ValidationException::withMessages(['order' => $data['message'] ?? 'Unable to create order.']);
        }

        $order = $data['data']['order'] ?? [];
        $orderId = $order['id'] ?? null;
        $recharged = false;

        if ($orderId) {
            $orderModel = Order::find($orderId);
            if ($orderModel) {
                $recharged = app(VoiceAiAllowanceService::class)->rechargeAfterOrder($orderModel);
            }
        }

        return [
            'order_id' => $orderId,
            'order_number' => $data['data']['order_number'] ?? ($order['order_number'] ?? null),
            'total' => $data['data']['total'] ?? ($order['total'] ?? null),
            'requires_payment' => (bool) ($data['data']['requires_payment'] ?? false),
            'voice_allowance_recharged' => $recharged,
        ];
    }

    private function getOrderStatus(array $arguments, Request $request): array
    {
        $validated = Validator::make($arguments, [
            'order_id' => ['nullable', 'integer'],
            'order_number' => ['nullable', 'string', 'max:80'],
        ])->validate();

        $order = Order::query()
            ->with(['restaurant', 'driver'])
            ->where('customer_id', $request->user()->id)
            ->when($validated['order_id'] ?? null, fn ($builder, $id) => $builder->whereKey($id))
            ->when($validated['order_number'] ?? null, fn ($builder, $number) => $builder->where('order_number', $number))
            ->latest()
            ->firstOrFail();

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'restaurant_name' => $order->restaurant?->name,
            'status' => $order->status,
            'status_label' => Order::getStatuses()[$order->status] ?? $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total' => (float) $order->total,
        ];
    }

    private function cancelOrder(array $arguments, Request $request): array
    {
        $validated = Validator::make($arguments, [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        $toolRequest = Request::create('/api/orders/'.$validated['order_id'].'/cancel', 'POST', ['reason' => $validated['reason']]);
        $toolRequest->setUserResolver(fn () => $request->user());
        $data = app(OrderController::class)->cancel($toolRequest, $validated['order_id'])->getData(true);

        if (! ($data['success'] ?? false)) {
            throw ValidationException::withMessages(['order' => $data['message'] ?? 'Unable to cancel order.']);
        }

        return ['message' => $data['message'] ?? 'Order cancelled.'];
    }

    private function getPaymentMethods(): array
    {
        return [
            'payment_methods' => collect(Order::getPaymentMethods())
                ->reject(fn ($label, $key) => $key === 'cod' && ! filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN))
                ->map(fn ($label, $key) => ['id' => $key, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    private function makeOrderRequest(string $uri, array $arguments, Request $request, bool $placing): Request
    {
        $validated = Validator::make($arguments, [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.selected_variant' => ['nullable', 'array'],
            'items.*.selected_add_ons' => ['nullable', 'array'],
            'delivery_address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'order_type' => ['nullable', 'in:delivery,takeaway'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'payment_method' => [$placing ? 'required' : 'nullable', 'string'],
        ])->validate();

        $validated['order_type'] = $validated['order_type'] ?? 'delivery';
        $validated['payment_method'] = $validated['payment_method'] ?? 'cod';

        $toolRequest = Request::create($uri, 'POST', $validated);
        $toolRequest->setUserResolver(fn () => $request->user());

        return $toolRequest;
    }

    private function productPayload(MenuItem $item, bool $includeOptions = false): array
    {
        $payload = (new MenuItemResource($item))->resolve();

        return [
            'id' => $payload['id'],
            'restaurant_id' => $payload['restaurant_id'],
            'restaurant_name' => $item->restaurant?->name,
            'name' => $payload['name'],
            'price' => (float) ($payload['final_price'] ?? $payload['price']),
            'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
            'available' => (bool) ($payload['is_available'] ?? false) && (bool) ($payload['is_scheduled_available'] ?? false),
            'category_name' => $payload['category_name'] ?? null,
            'food_type' => $payload['food_type'] ?? null,
            'variants' => $includeOptions ? ($payload['variants'] ?? []) : [],
            'add_ons' => $includeOptions ? ($payload['add_ons'] ?? []) : [],
        ];
    }

    private function sanitizeOrderSummary(array $summary): array
    {
        $pricing = $summary['pricing'] ?? $summary;

        return [
            'subtotal' => $pricing['subtotal'] ?? $summary['subtotal'] ?? null,
            'delivery_fee' => $pricing['delivery_fee'] ?? null,
            'surge_fee' => $pricing['surge_fee'] ?? 0,
            'surge_active' => (bool) ($pricing['surge_active'] ?? false),
            'original_delivery_fee' => $pricing['original_delivery_fee'] ?? null,
            'platform_fee' => $pricing['platform_fee'] ?? null,
            'tax' => $pricing['tax'] ?? null,
            'discount' => $pricing['discount'] ?? null,
            'total' => $pricing['total'] ?? null,
            'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
            'items' => collect($summary['items'] ?? [])->map(fn ($item) => [
                'id' => $item['id'] ?? $item['menu_item_id'] ?? null,
                'name' => $item['name'] ?? null,
                'price' => $item['price'] ?? null,
                'quantity' => $item['quantity'] ?? null,
            ])->values()->all(),
        ];
    }
}

