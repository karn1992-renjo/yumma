<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\CommissionSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TaxSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $restaurant = Auth::user()->activeRestaurant();
        abort_unless($restaurant, 404);

        $products = MenuItem::query()
            ->with('category')
            ->where('restaurant_id', $restaurant->id)
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('approval_status')->orWhere('approval_status', 'approved');
            })
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $recentOrders = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where(function ($query) {
                $query->where('order_processing_type', 'pos');

                if (Schema::hasColumn('orders', 'payment_source')) {
                    $query->orWhere('payment_source', 'pos');
                }
            })
            ->latest()
            ->limit(12)
            ->get();

        $summary = [
            'today_orders' => Order::where('restaurant_id', $restaurant->id)
                ->where(function ($query) {
                    $query->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $query->orWhere('payment_source', 'pos');
                    }
                })
                ->whereDate('created_at', today())
                ->count(),
            'today_revenue' => (float) Order::where('restaurant_id', $restaurant->id)
                ->where(function ($query) {
                    $query->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $query->orWhere('payment_source', 'pos');
                    }
                })
                ->whereDate('created_at', today())
                ->sum('total'),
            'total_orders' => Order::where('restaurant_id', $restaurant->id)
                ->where(function ($query) {
                    $query->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $query->orWhere('payment_source', 'pos');
                    }
                })
                ->count(),
        ];

        $receiptOrder = null;
        if (request()->filled('receipt')) {
            $receiptOrder = Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where(function ($query) {
                    $query->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $query->orWhere('payment_source', 'pos');
                    }
                })
                ->with('orderItems.menuItem')
                ->find(request()->integer('receipt'));
        }

        return view('restaurant.pos.index', compact(
            'restaurant',
            'products',
            'categories',
            'recentOrders',
            'summary',
            'receiptOrder'
        ));
    }

    public function terminal(Request $request): View
    {
        $restaurant = Auth::user()->activeRestaurant();
        abort_unless($restaurant, 404);

        $restaurant->loadMissing('branch');
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = AppSetting::currencyDecimals();

        $statusLabels = [
            'pending' => 'New Orders',
            'confirmed' => 'Accepted',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'Out for Delivery',
            'delivered' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
        $activeStatuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'];
        $todayOrderBase = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->whereDate('created_at', today());

        $statusCounts = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $typeCounts = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->whereDate('created_at', today())
            ->selectRaw('order_type, order_processing_type, count(*) as count')
            ->groupBy('order_type', 'order_processing_type')
            ->get();

        $orders = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->with(['customer', 'driver', 'orderItems.menuItem'])
            ->latest()
            ->limit(80)
            ->get();

        $todayOrders = (clone $todayOrderBase)->count();
        $todayRevenue = (float) (clone $todayOrderBase)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total');
        $avgOrderValue = $todayOrders > 0 ? round($todayRevenue / $todayOrders, 2) : 0;
        $deliveryOrders = 0;
        $pickupOrders = 0;
        $dineInOrders = 0;
        $walkInOrders = 0;

        foreach ($typeCounts as $row) {
            $type = strtolower((string) $row->order_type);
            $processing = strtolower((string) $row->order_processing_type);
            $count = (int) $row->count;

            if ($processing === 'pos') {
                $walkInOrders += $count;
            } elseif (in_array($type, ['delivery', 'home_delivery'], true)) {
                $deliveryOrders += $count;
            } elseif (in_array($type, ['pickup', 'takeaway'], true)) {
                $pickupOrders += $count;
            } elseif (in_array($type, ['dine_in', 'dining'], true)) {
                $dineInOrders += $count;
            }
        }

        $orderPayload = $orders->map(function ($order) use ($currencySymbol, $currencyDecimals, $statusLabels) {
            return $this->formatTerminalOrder($order, $currencySymbol, $currencyDecimals, $statusLabels);
        })->values();

        $products = MenuItem::query()
            ->with('category')
            ->where('restaurant_id', $restaurant->id)
            ->where('is_available', true)
            ->where(function ($query) {
                $query->whereNull('approval_status')->orWhere('approval_status', 'approved');
            })
            ->orderByDesc('total_orders')
            ->orderBy('name')
            ->limit(120)
            ->get();

        $menuPayload = $products->map(function ($product) use ($currencySymbol, $currencyDecimals) {
            $price = (float) ($product->final_price ?? $product->getFinalPriceAttribute());

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name ?: 'Uncategorised',
                'price' => $price,
                'price_label' => $currencySymbol . number_format($price, $currencyDecimals),
                'image' => $product->image_url,
                'orders' => (int) ($product->total_orders ?? 0),
                'diet' => $product->diet_label,
            ];
        })->values();

        $printer = $restaurant->printerSettings()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        $allOrdersCount = array_sum(array_map('intval', $statusCounts));
        $scheduledCount = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->whereNotNull('scheduled_time')
            ->where('scheduled_time', '>=', now())
            ->count();
        $pendingPayout = 0;

        $summaryCards = [
            [
                'label' => "Today's Orders",
                'value' => number_format($todayOrders),
                'trend' => number_format((int) ($statusCounts['pending'] ?? 0)) . ' new',
                'icon' => 'shopping-bag',
                'tone' => 'blue',
            ],
            [
                'label' => "Today's Revenue",
                'value' => AppSetting::sanitizedCurrencySymbol() . number_format($todayRevenue, AppSetting::currencyDecimals()),
                'trend' => 'Live sales',
                'icon' => 'rupee-sign',
                'tone' => 'green',
            ],
            [
                'label' => 'Avg. Order Value',
                'value' => AppSetting::sanitizedCurrencySymbol() . number_format($avgOrderValue, AppSetting::currencyDecimals()),
                'trend' => number_format($todayOrders) . ' bills',
                'icon' => 'chart-line',
                'tone' => 'orange',
            ],
            [
                'label' => 'Pending Orders',
                'value' => number_format((int) ($statusCounts['pending'] ?? 0)),
                'trend' => 'Awaiting action',
                'icon' => 'clock',
                'tone' => 'red',
            ],
            [
                'label' => 'Preparing',
                'value' => number_format((int) ($statusCounts['preparing'] ?? 0)),
                'trend' => 'Kitchen load',
                'icon' => 'utensils',
                'tone' => 'orange',
            ],
            [
                'label' => 'Ready',
                'value' => number_format((int) ($statusCounts['ready_for_pickup'] ?? 0)),
                'trend' => 'Pickup queue',
                'icon' => 'box-open',
                'tone' => 'green',
            ],
        ];

        $statusFilters = [
            ['key' => 'all', 'label' => 'All Orders', 'count' => $allOrdersCount, 'color' => '#64748b'],
            ['key' => 'pending', 'label' => 'New Orders', 'count' => (int) ($statusCounts['pending'] ?? 0), 'color' => '#ef4444'],
            ['key' => 'confirmed', 'label' => 'Accepted', 'count' => (int) ($statusCounts['confirmed'] ?? 0), 'color' => '#2563eb'],
            ['key' => 'preparing', 'label' => 'Preparing', 'count' => (int) ($statusCounts['preparing'] ?? 0), 'color' => '#f97316'],
            ['key' => 'ready_for_pickup', 'label' => 'Ready', 'count' => (int) ($statusCounts['ready_for_pickup'] ?? 0), 'color' => '#22c55e'],
            ['key' => 'picked_up', 'label' => 'Picked Up', 'count' => (int) ($statusCounts['picked_up'] ?? 0), 'color' => '#8b5cf6'],
            ['key' => 'on_the_way', 'label' => 'Out for Delivery', 'count' => (int) ($statusCounts['on_the_way'] ?? 0), 'color' => '#60a5fa'],
            ['key' => 'delivered', 'label' => 'Completed', 'count' => (int) ($statusCounts['delivered'] ?? 0), 'color' => '#64748b'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => (int) ($statusCounts['cancelled'] ?? 0), 'color' => '#ef4444'],
            ['key' => 'scheduled', 'label' => 'Scheduled', 'count' => $scheduledCount, 'color' => '#14b8a6'],
        ];

        $terminalMeta = [
            'restaurant_name' => $restaurant->name,
            'branch_name' => $restaurant->branch?->name ?: ($restaurant->city ?: 'Main Branch'),
            'location' => trim(collect([$restaurant->city, $restaurant->state])->filter()->implode(', ')),
            'is_open' => (bool) $restaurant->is_open,
            'cashier_name' => Auth::user()->name,
            'cashier_role' => Auth::user()->hasRole('restaurant_owner') ? 'Owner' : 'Cashier',
            'printer_name' => $printer?->printer_name,
            'printer_online' => (bool) $printer,
            'cash_drawer' => AppSetting::sanitizedCurrencySymbol() . number_format($todayRevenue, AppSetting::currencyDecimals()),
            'sync_label' => now()->format('h:i A'),
        ];

        return view('restaurant.pos.terminal', [
            'restaurant' => $restaurant,
            'orders' => $orderPayload,
            'menuItems' => $menuPayload,
            'summaryCards' => $summaryCards,
            'statusFilters' => $statusFilters,
            'terminalMeta' => $terminalMeta,
            'currencySymbol' => AppSetting::sanitizedCurrencySymbol(),
            'currencyDecimals' => AppSetting::currencyDecimals(),
            'deliveryOrders' => $deliveryOrders,
            'pickupOrders' => $pickupOrders,
            'dineInOrders' => $dineInOrders,
            'walkInOrders' => $walkInOrders,
            'pendingPayout' => $pendingPayout,
            'canPrint' => Auth::user()->hasRole('restaurant_owner'),
        ]);
    }

    public function terminalData(): JsonResponse
    {
        $restaurant = Auth::user()->activeRestaurant();
        abort_unless($restaurant, 404);

        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = AppSetting::currencyDecimals();
        $statusLabels = [
            'pending' => 'New Orders',
            'confirmed' => 'Accepted',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'Out for Delivery',
            'delivered' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
        $activeStatuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'];

        $orders = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->with(['customer', 'driver', 'orderItems.menuItem'])
            ->latest()
            ->limit(80)
            ->get()
            ->map(function ($order) use ($currencySymbol, $currencyDecimals, $statusLabels) {
                return $this->formatTerminalOrder($order, $currencySymbol, $currencyDecimals, $statusLabels);
            })
            ->values();

        $counts = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'orders' => $orders,
            'counts' => $counts,
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = Auth::user()->activeRestaurant();
        abort_unless($restaurant, 404);

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:120',
            'customer_phone' => 'nullable|string|max:40',
            'payment_method' => 'required|in:cash,card,upi,wallet,split',
            'discount_amount' => 'nullable|numeric|min:0|max:999999',
            'paid_cash' => 'nullable|numeric|min:0|max:999999',
            'paid_card' => 'nullable|numeric|min:0|max:999999',
            'paid_upi' => 'nullable|numeric|min:0|max:999999',
            'paid_wallet' => 'nullable|numeric|min:0|max:999999',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:0|max:999',
        ]);

        if (! $restaurant->is_open) {
            return back()
                ->withErrors(['pos' => 'Store is closed. POS orders cannot be created while the store is offline.'])
                ->withInput();
        }

        $order = DB::transaction(function () use ($validated, $restaurant) {
            $selected = collect($validated['items'])
                ->filter(fn ($item) => (int) ($item['quantity'] ?? 0) > 0)
                ->values();

            if ($selected->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Select at least one product.']);
            }

            $subtotal = 0.0;
            $orderItems = [];

            foreach ($selected as $item) {
                $menuItem = MenuItem::whereKey($item['id'])->lockForUpdate()->firstOrFail();
                if ((int) $menuItem->restaurant_id !== (int) $restaurant->id) {
                    throw ValidationException::withMessages(['items' => 'Invalid product for this store.']);
                }

                if (! $menuItem->is_available || ($menuItem->approval_status && $menuItem->approval_status !== 'approved')) {
                    throw ValidationException::withMessages(['items' => "{$menuItem->name} is not available."]);
                }

                if (method_exists($menuItem, 'hasInventoryAvailable') && ! $menuItem->hasInventoryAvailable((int) $item['quantity'])) {
                    throw ValidationException::withMessages(['items' => "{$menuItem->name} does not have enough stock."]);
                }

                $unitPrice = (float) ($menuItem->final_price ?? $menuItem->getFinalPriceAttribute());
                $lineTotal = round($unitPrice * (int) $item['quantity'], 2);
                $subtotal += $lineTotal;
                $orderItems[] = [
                    'id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $unitPrice,
                    'quantity' => (int) $item['quantity'],
                    'selected_variant' => null,
                    'selected_add_ons' => [],
                    'total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = min(round((float) ($validated['discount_amount'] ?? 0), 2), $subtotal);
            $taxableSubtotal = max(0, round($subtotal - $discount, 2));
            $tax = round((float) TaxSetting::calculateTax($taxableSubtotal, 0), 2);
            $posCommission = CommissionSetting::calculate('pos', $taxableSubtotal, 0);
            $total = round($taxableSubtotal + $tax, 2);
            $customerName = $validated['customer_name'] ?: 'Walk-in Customer';
            $customerPhone = $validated['customer_phone'] ?: (Auth::user()->phone ?: '0000000000');
            $paymentBreakdown = collect([
                'cash' => round((float) ($validated['paid_cash'] ?? 0), 2),
                'card' => round((float) ($validated['paid_card'] ?? 0), 2),
                'upi' => round((float) ($validated['paid_upi'] ?? 0), 2),
                'wallet' => round((float) ($validated['paid_wallet'] ?? 0), 2),
            ])->filter(fn ($amount) => $amount > 0);

            if ($validated['payment_method'] === 'split' && abs($paymentBreakdown->sum() - $total) >= 0.05) {
                throw ValidationException::withMessages(['payment_method' => 'Split payment total must match the bill total.']);
            }

            if ($validated['payment_method'] !== 'split') {
                $paymentBreakdown = collect([$validated['payment_method'] => $total]);
            }

            $paymentNote = $paymentBreakdown
                ->map(fn ($amount, $method) => strtoupper((string) $method) . ': ' . number_format((float) $amount, AppSetting::currencyDecimals(), '.', ''))
                ->implode(', ');
            $notes = trim((string) ($validated['notes'] ?? ''));
            $specialInstructions = trim('Created from store POS. Payment: ' . $paymentNote . ($notes !== '' ? '. Note: ' . $notes : ''));

            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => Auth::id(),
                'restaurant_id' => $restaurant->id,
                'order_type' => 'takeaway',
                'order_processing_type' => 'pos',
                'items' => $orderItems,
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'platform_fee' => 0,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'delivery_payment_mode' => in_array($validated['payment_method'], ['cash'], true) ? 'cod' : 'online',
                'payment_status' => 'success',
                'status' => 'delivered',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => [
                    'name' => $customerName,
                    'address' => 'POS sale at ' . $restaurant->name,
                    'phone' => $customerPhone,
                    'latitude' => $restaurant->latitude,
                    'longitude' => $restaurant->longitude,
                ],
                'delivery_address' => 'POS sale at ' . $restaurant->name,
                'delivery_lat' => $restaurant->latitude,
                'delivery_lng' => $restaurant->longitude,
                'restaurant_earning' => max(0, $taxableSubtotal - $posCommission),
                'platform_commission' => $posCommission,
                'restaurant_commission_type' => CommissionSetting::getCalculationType('pos'),
                'restaurant_commission_value' => CommissionSetting::getRate('pos'),
                'delivered_at' => now(),
                'confirmed_at' => now(),
                'ready_at' => now(),
                'special_instructions' => $specialInstructions,
            ];

            foreach ([
                'payment_source' => 'pos',
                'payment_gateway' => 'pos',
                'paid_at' => now(),
                'cash_collected_amount' => $validated['payment_method'] === 'cash' ? $total : null,
                'cash_collected_at' => $validated['payment_method'] === 'cash' ? now() : null,
                'cod_reconciliation_status' => $validated['payment_method'] === 'cash' ? 'collected' : null,
            ] as $column => $value) {
                if (Schema::hasColumn('orders', $column)) {
                    $orderData[$column] = $value;
                }
            }

            $order = Order::create($orderData);

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'selected_variant' => null,
                    'selected_add_ons' => [],
                ]);

                $inventoryItem = MenuItem::whereKey($item['id'])->lockForUpdate()->first();
                if ($inventoryItem && method_exists($inventoryItem, 'consumeInventory')) {
                    $inventoryItem->consumeInventory((int) $item['quantity']);
                }
            }

            return $order;
        });

        $route = $request->input('redirect_to') === 'terminal'
            ? 'restaurant.pos.terminal'
            : 'restaurant.pos.index';

        return redirect()
            ->route($route, ['receipt' => $order->id])
            ->with('success', 'POS order created successfully.');
    }

    protected function formatTerminalOrder(Order $order, string $currencySymbol, int $currencyDecimals, array $statusLabels): array
    {
        $items = $this->terminalOrderItems($order, $currencySymbol, $currencyDecimals);
        $status = strtolower((string) ($order->status ?: 'pending'));
        $orderType = $this->terminalOrderType($order);
        $createdAt = $order->created_at ?: now();
        $subtotal = (float) ($order->subtotal ?? $items->sum('total'));
        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $packagingFee = (float) ($order->platform_fee ?? 0);
        $tax = (float) ($order->tax ?? 0);
        $discount = (float) ($order->discount ?? 0);
        $total = (float) ($order->total ?? ($subtotal + $deliveryFee + $packagingFee + $tax - $discount));

        return [
            'id' => $order->id,
            'number' => $order->order_number ?? $order->id,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)),
            'type' => $orderType['key'],
            'type_label' => $orderType['label'],
            'type_icon' => $orderType['icon'],
            'priority' => $order->scheduled_time ? 'Scheduled' : ($total >= 1000 ? 'VIP' : 'Normal'),
            'customer_name' => $order->customer->name ?? $order->customer_name ?? 'Guest',
            'customer_phone' => $order->customer->phone ?? $order->customer_phone ?? '',
            'customer_email' => $order->customer->email ?? '',
            'address' => $order->delivery_address ?: 'Counter pickup',
            'map_url' => ($order->delivery_lat && $order->delivery_lng)
                ? 'https://www.google.com/maps?q=' . $order->delivery_lat . ',' . $order->delivery_lng
                : null,
            'created_time' => $createdAt->format('h:i A'),
            'created_date' => $createdAt->format('d M Y'),
            'elapsed' => $createdAt->diffForHumans(null, true) . ' ago',
            'scheduled_time' => $order->scheduled_time ? $order->scheduled_time->format('d M Y, h:i A') : null,
            'items_count' => (int) $items->sum('qty'),
            'items' => $items->values(),
            'subtotal' => $currencySymbol . number_format($subtotal, $currencyDecimals),
            'delivery_fee' => $currencySymbol . number_format($deliveryFee, $currencyDecimals),
            'packaging_fee' => $currencySymbol . number_format($packagingFee, $currencyDecimals),
            'tax' => $currencySymbol . number_format($tax, $currencyDecimals),
            'discount' => $currencySymbol . number_format($discount, $currencyDecimals),
            'total' => $currencySymbol . number_format($total, $currencyDecimals),
            'payment_method' => strtoupper((string) ($order->payment_method ?: $order->delivery_payment_mode ?: 'N/A')),
            'payment_status' => $this->terminalPaymentLabel($order),
            'payment_status_key' => $this->terminalPaymentKey($order),
            'driver_name' => $order->driver->name ?? null,
            'driver_phone' => $order->driver->phone ?? null,
            'table_label' => data_get($order->customer_address, 'table') ?: data_get($order->customer_address, 'table_number'),
            'otp' => $order->delivery_otp,
            'notes' => $order->special_instructions,
            'timeline' => $this->terminalTimeline($order),
            'actions' => $this->terminalActions($order),
        ];
    }

    protected function terminalOrderItems(Order $order, string $currencySymbol, int $currencyDecimals)
    {
        if ($order->relationLoaded('orderItems') && $order->orderItems->count() > 0) {
            return $order->orderItems->map(function ($item) use ($currencySymbol, $currencyDecimals) {
                $name = $item->menuItem->name ?? data_get($item->toArray(), 'name') ?? 'Item';
                $qty = max(1, (int) ($item->quantity ?? 1));
                $price = (float) ($item->unit_price ?? 0);
                $total = (float) ($item->total_price ?? ($price * $qty));

                return [
                    'name' => $name,
                    'qty' => $qty,
                    'unit' => $currencySymbol . number_format($price, $currencyDecimals),
                    'total' => $total,
                    'total_label' => $currencySymbol . number_format($total, $currencyDecimals),
                    'image' => $item->menuItem->image_url ?? null,
                    'variant' => $this->stringifyOption($item->selected_variant ?? null),
                    'addons' => $this->stringifyOption($item->selected_add_ons ?? null),
                    'notes' => $item->special_instructions ?? null,
                ];
            });
        }

        $items = is_array($order->items) ? $order->items : [];

        return collect($items)->map(function ($item) use ($currencySymbol, $currencyDecimals) {
            $qty = max(1, (int) (data_get($item, 'quantity') ?? data_get($item, 'qty') ?? 1));
            $price = (float) (data_get($item, 'unit_price') ?? data_get($item, 'price') ?? 0);
            $total = (float) (data_get($item, 'total_price') ?? data_get($item, 'total') ?? ($price * $qty));

            return [
                'name' => data_get($item, 'name') ?? data_get($item, 'item_name') ?? 'Item',
                'qty' => $qty,
                'unit' => $currencySymbol . number_format($price, $currencyDecimals),
                'total' => $total,
                'total_label' => $currencySymbol . number_format($total, $currencyDecimals),
                'image' => data_get($item, 'image_url') ?? data_get($item, 'image'),
                'variant' => $this->stringifyOption(data_get($item, 'selected_variant')),
                'addons' => $this->stringifyOption(data_get($item, 'selected_add_ons')),
                'notes' => data_get($item, 'notes') ?? data_get($item, 'special_instructions'),
            ];
        });
    }

    protected function terminalOrderType(Order $order): array
    {
        $type = strtolower((string) $order->order_type);
        $processing = strtolower((string) $order->order_processing_type);

        if ($processing === 'pos') {
            return ['key' => 'walk_in', 'label' => 'Walk-in', 'icon' => 'cash-register'];
        }

        if (in_array($type, ['pickup', 'takeaway'], true)) {
            return ['key' => 'pickup', 'label' => 'Pickup', 'icon' => 'shopping-bag'];
        }

        if (in_array($type, ['dine_in', 'dining'], true)) {
            return ['key' => 'dine_in', 'label' => 'Dine-In', 'icon' => 'utensils'];
        }

        return ['key' => 'delivery', 'label' => 'Delivery', 'icon' => 'motorcycle'];
    }

    protected function terminalPaymentKey(Order $order): string
    {
        if ($order->payment_status === 'success') {
            return 'paid';
        }

        if ($order->isCashOnDelivery()) {
            return 'cod';
        }

        return strtolower((string) ($order->payment_status ?: 'pending'));
    }

    protected function terminalPaymentLabel(Order $order): string
    {
        $key = $this->terminalPaymentKey($order);

        return $key === 'cod' ? 'COD' : ucfirst(str_replace('_', ' ', $key));
    }

    protected function terminalTimeline(Order $order): array
    {
        $timeline = [
            ['label' => 'Received', 'done' => true, 'time' => $order->created_at?->format('h:i A')],
            ['label' => 'Accepted', 'done' => (bool) $order->confirmed_at || !in_array($order->status, ['pending', 'cancelled'], true), 'time' => $order->confirmed_at?->format('h:i A')],
            ['label' => 'Preparing', 'done' => (bool) $order->preparing_at || in_array($order->status, ['preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'], true), 'time' => $order->preparing_at?->format('h:i A')],
            ['label' => 'Ready', 'done' => (bool) $order->ready_at || in_array($order->status, ['ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'], true), 'time' => $order->ready_at?->format('h:i A')],
            ['label' => 'Completed', 'done' => $order->status === 'delivered', 'time' => $order->delivered_at?->format('h:i A')],
        ];

        if ($order->status === 'cancelled') {
            $timeline[] = ['label' => 'Cancelled', 'done' => true, 'time' => $order->cancelled_at?->format('h:i A')];
        }

        return $timeline;
    }

    protected function terminalActions(Order $order): array
    {
        if ($order->status === 'pending') {
            return ['accept', 'reject', 'view'];
        }

        if ($order->status === 'confirmed') {
            return ['print_kot', 'preparing', 'view'];
        }

        if ($order->status === 'preparing') {
            return ['ready', 'print_kot', 'view'];
        }

        if ($order->status === 'ready_for_pickup') {
            return ['print_invoice', 'view'];
        }

        return ['view'];
    }

    protected function stringifyOption($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(function ($item) {
                    if (is_array($item)) {
                        return $item['name'] ?? $item['title'] ?? null;
                    }

                    return $item;
                })
                ->filter()
                ->implode(', ');
        }

        return null;
    }
}
