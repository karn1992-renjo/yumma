<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\RestaurantLocationChangeRequest;
use App\Models\MenuItem;
use App\Models\Category;
use App\Models\DeliveryChargeSetting;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderCancellationLimit;
use App\Models\OrderItem;
use App\Models\PartnerApplication;
use App\Models\PrinterSetting;
use App\Models\PromoCode;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\AppSetting;
use App\Models\RestaurantStaff;
use App\Events\NewOrderEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Jobs\AutoMarkOrderPreparingJob;
use App\Services\AutoAssignDriverService;
use App\Services\GoogleMapsEtaService;
use App\Services\OrderStatusPushService;
use App\Services\PrinterService;
use App\Services\RefundService;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RestaurantController extends Controller
{
    private function payoutProviderAccountAttributes(Request $request): array
    {
        $provider = AppSetting::getValue('payout_gateway_provider', 'razorpay');
        $gatewayAccountId = $request->gateway_account_id;

        return [
            'gateway_account_id' => $gatewayAccountId,
            'mollie_organization_id' => $provider === 'mollie' ? $gatewayAccountId : null,
            'mercadopago_collector_id' => $provider === 'mercadopago' ? $gatewayAccountId : null,
        ];
    }

    /**
     * List restaurants accessible to the authenticated restaurant user.
     */
    public function myRestaurants(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            return response()->json([
                'success' => true,
                'data' => $restaurants->map(fn ($restaurant) => $this->formatRestaurantSwitcherItem($restaurant))->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $restaurants = $this->resolveRestaurantScope($request, $user);
            $restaurantIds = $restaurants->pluck('id');
            $restaurant = $restaurants->count() === 1 ? $restaurants->first() : null;
            
            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found for current user.'
                ], 404);
            }

            $today = Carbon::today();

            // Get pending orders
            $pendingOrders = Order::whereIn('restaurant_id', $restaurantIds)
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->with('restaurant')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($order) {
                    return $this->formatOrderForApi($order);
                });

            // Get active orders
            $activeOrders = Order::whereIn('restaurant_id', $restaurantIds)
                ->visibleToRestaurant()
                ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'])
                ->with('restaurant')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($order) {
                    return $this->formatOrderForApi($order);
                });

            // Calculate today's stats
            $baseOrders = Order::whereIn('restaurant_id', $restaurantIds)->visibleToRestaurant();
            $todayOrders = (clone $baseOrders)
                ->whereDate('created_at', $today)
                ->count();
            
            $todayRevenue = (float)(clone $baseOrders)
                ->whereDate('created_at', $today)
                ->where('status', 'delivered')
                ->sum('total');
            
            // Calculate total stats
            $totalOrders = (clone $baseOrders)->count();
            $totalRevenue = (float)(clone $baseOrders)->where('status', 'delivered')->sum('total');
            $totalCustomers = (clone $baseOrders)->distinct('customer_id')->count('customer_id');
            $totalMenuItems = MenuItem::whereIn('restaurant_id', $restaurantIds)->count();
            
            $restaurantData = [
                'id' => $restaurant?->id,
                'name' => $restaurant?->name ?? 'All Restaurants',
                'logo' => \App\Services\MediaStorage::url($restaurant?->logo_image),
                'is_open' => $restaurant ? (bool)$restaurant->is_open : $restaurants->contains(fn ($item) => (bool)$item->is_open),
                'rating' => $restaurant && (int)($restaurant->total_ratings ?? 0) >= 3
                    ? (float)($restaurant->rating ?? 0)
                    : null,
                'total_reviews' => $restaurant ? (int)($restaurant->total_ratings ?? $restaurant->reviews()->count() ?? 0) : 0,
                'restaurant_type' => $restaurant?->restaurant_type ?? 'multiple',
                'dining_charge' => (float)($restaurant?->dining_charge ?? 0),
                'accepts_delivery' => $restaurant ? $restaurant->acceptsService('delivery') : $restaurants->contains(fn ($item) => $item->acceptsService('delivery')),
                'accepts_dining' => $restaurant ? $restaurant->acceptsService('dining') : $restaurants->contains(fn ($item) => $item->acceptsService('dining')),
                'accepts_takeaway' => $restaurant ? $restaurant->acceptsService('takeaway') : $restaurants->contains(fn ($item) => $item->acceptsService('takeaway')),
            ];

            $isOpenForScope = $restaurant
                ? (bool) $restaurant->is_open
                : $restaurants->contains(fn ($item) => (bool) $item->is_open);

            $stats = [
                'today_orders' => $todayOrders,
                'today_revenue' => $todayRevenue,
                'pending_orders_count' => $pendingOrders->count(),
                'active_orders_count' => $activeOrders->count(),
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'total_customers' => $totalCustomers,
                'total_menu_items' => $totalMenuItems,
                'is_open' => $isOpenForScope,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'restaurant' => $restaurantData,
                    'restaurants' => $restaurants->map(fn ($item) => $this->formatRestaurantSwitcherItem($item))->values(),
                    'selected_restaurant_id' => $restaurant?->id,
                    'is_all_restaurants' => $restaurant === null,
                    'stats' => $stats,
                    'pending_orders' => $pendingOrders,
                    'active_orders' => $activeOrders,
                    'order_categories' => [
                        'new' => $pendingOrders,
                        'running' => $activeOrders,
                        'completed' => Order::whereIn('restaurant_id', $restaurantIds)
                            ->visibleToRestaurant()
                            ->where('status', 'delivered')
                            ->with('restaurant')
                            ->latest('delivered_at')
                            ->limit(5)
                            ->get()
                            ->map(fn ($order) => $this->formatOrderForApi($order)),
                    ],
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant stats
     */
    public function getStats(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->resolveRestaurantScope($request, $user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $paymentGateway = AppSetting::getValue('payment_gateway_provider', 'razorpay');
            $payoutGateway = AppSetting::getValue('payout_gateway_provider', $paymentGateway);
            $restaurantIds = $restaurants->pluck('id');
            $restaurant = $restaurants->count() === 1 ? $restaurants->first() : null;
            $baseOrders = Order::whereIn('restaurant_id', $restaurantIds)->visibleToRestaurant();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_open' => $restaurant
                        ? (bool) $restaurant->is_open
                        : $restaurants->contains(fn ($item) => (bool) $item->is_open),
                    'pending_orders_count' => (clone $baseOrders)->where('status', 'pending')->count(),
                    'active_orders_count' => (clone $baseOrders)->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])->count(),
                    'today_orders_count' => (clone $baseOrders)->whereDate('created_at', Carbon::today())->count(),
                    'today_revenue' => (float) (clone $baseOrders)->whereDate('created_at', Carbon::today())->where('status', 'delivered')->sum('total'),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Toggle restaurant open/closed status - FIXED
     */
    public function toggleStatus(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($restaurants->count() !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select one restaurant to change open/closed status.'
                ], 422);
            }

            $restaurant = $restaurants->first();

            // Get is_open from request body
            $isOpen = $request->input('is_open');
            
            // If is_open is provided in request
            if ($isOpen !== null) {
                $restaurant->is_open = filter_var($isOpen, FILTER_VALIDATE_BOOLEAN);
            } else {
                // Toggle if no value provided
                $restaurant->is_open = !$restaurant->is_open;
            }
            
            $restaurant->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_open' => (bool)$restaurant->is_open,
                ],
                'message' => $restaurant->is_open ? 'Restaurant is now open' : 'Restaurant is now closed'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Toggle status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get all orders - FIXED
     */
    public function getOrders(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->resolveRestaurantScope($request, $user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $query = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->with('restaurant');

            // Filter by status
            if ($request->status && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            // Search
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('order_number', 'like', "%{$request->search}%")
                      ->orWhere('customer_name', 'like', "%{$request->search}%")
                      ->orWhere('customer_phone', 'like', "%{$request->search}%");
                });
            }

            $orders = $query->orderBy('created_at', 'desc')->paginate(20);

            $formattedOrders = collect($orders->items())->map(function($order) {
                return $this->formatOrderForApi($order);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedOrders,
                'pagination' => [
                    'total' => $orders->total(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get a single order details for the authenticated restaurant owner
     */
    public function getOrderDetails($id)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->with(['customer:id,name,phone,email', 'driver:id,name,phone'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->formatOrderForApi($order)
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get order details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get categories - ADD THIS MISSING METHOD
     */
    public function getCategories(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'menu')) {
                return $response;
            }

            $restaurant = $this->resolveSingleRestaurantForFeature($request, $user, $restaurants);
            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a restaurant to manage categories.'
                ], 422);
            }

            $categories = $restaurant->categories()
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
                ->map(fn ($category) => $this->formatCategoryForApi($category));

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Create category
     */
    public function createCategory(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'menu')) {
                return $response;
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            $imagePath = $request->hasFile('image')
                ? $request->file('image')->store('categories', 'public')
                : null;

            $category = Category::create([
                'restaurant_id' => $restaurant->id,
                'name' => $request->name,
                'description' => $request->description,
                'image' => $imagePath,
                'display_order' => 0,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatCategoryForApi($category),
                'message' => 'Category created successfully.'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update category
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'menu')) {
                return $response;
            }

            $category = $restaurant->categories()->findOrFail($id);

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'is_active' => 'nullable|boolean'
            ]);

            $data = $request->only(['name', 'description', 'is_active']);

            if ($request->hasFile('image')) {
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }

                $data['image'] = $request->file('image')->store('categories', 'public');
            }

            $category->update($data);

            return response()->json([
                'success' => true,
                'data' => $this->formatCategoryForApi($category->fresh()),
                'message' => 'Category updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Delete category
     */
    public function deleteCategory($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'menu')) {
                return $response;
            }

            $category = $restaurant->categories()->findOrFail($id);

            if ($category->menuItems()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with menu items.'
                ], 400);
            }

            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Accept an order
     */
    public function acceptOrder(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'preparation_time_minutes' => 'nullable|integer|min:5|max:180',
            ]);

            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->find($id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order is no longer pending. Please refresh your orders.',
                ], 404);
            }

            $restaurant = $restaurants->firstWhere('id', $order->restaurant_id);

            if (! OrderCancellationLimit::isWithinWindow($order, 'restaurant', 15)) {
                $minutes = OrderCancellationLimit::windowMinutesFor('restaurant', 15);
                return response()->json([
                    'success' => false,
                    'message' => "Restaurant cancellation window expired. Pending orders can only be rejected within {$minutes} minutes of placement.",
                ], 422);
            }

            DB::beginTransaction();

            $order->status = 'confirmed';
            $this->setOrderColumnIfExists($order, 'confirmed_at', Carbon::now());
            if (array_key_exists('preparation_time_minutes', $validated)) {
                $this->setOrderColumnIfExists(
                    $order,
                    'preparation_time_minutes',
                    $validated['preparation_time_minutes']
                );
            }
            $order->save();

            if (!$order->driver_id) {
                app(AutoAssignDriverService::class)->autoAssignOrder($order);
                $order->refresh();
            }

            AutoMarkOrderPreparingJob::dispatch($order->id)->delay(now()->addMinutes(2));

            DB::commit();

            broadcast(new OrderStatusUpdatedEvent($order, $restaurant->id));
            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your order #{$order->order_number} has been confirmed by {$restaurant->name}."
            );

            return response()->json([
                'success' => true,
                'message' => 'Order accepted successfully',
                'data' => $this->formatOrderForApi($order)
            ]);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Accept order error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Reject an order
     */
    public function rejectOrder(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->findOrFail($id);
            $restaurant = $restaurants->firstWhere('id', $order->restaurant_id);

            DB::beginTransaction();

            $order->status = 'cancelled';
            $this->setOrderColumnIfExists($order, 'cancelled_at', Carbon::now());
            $this->setOrderColumnIfExists($order, 'cancellation_reason', $request->reason);
            $order->save();

            DB::commit();

            if ($order->payment_status === 'success') {
                app(RefundService::class)->processRefund($order, 'Order rejected by restaurant');
                $order->refresh();
            }

            broadcast(new OrderStatusUpdatedEvent($order, $restaurant->id));
            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your order #{$order->order_number} was rejected by {$restaurant->name}."
            );

            return response()->json([
                'success' => true,
                'message' => 'Order rejected successfully',
                'data' => $this->formatOrderForApi($order)
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $normalizedStatus = $this->normalizeRestaurantOrderStatus($request->input('status'));
            if ($normalizedStatus) {
                $request->merge(['status' => $normalizedStatus]);
            }

            $request->validate([
                'status' => 'required|in:confirmed,preparing,ready_for_pickup,cancelled',
                'reason' => 'nullable|string|max:500'
            ]);

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->findOrFail($id);
            $restaurant = $restaurants->firstWhere('id', $order->restaurant_id);

            if ($request->status === 'cancelled' && $order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Accepted orders can only be cancelled by an admin.'
                ], 422);
            }

            if (
                $request->status === 'preparing'
                && $order->status === 'confirmed'
                && $order->confirmed_at
                && $order->confirmed_at->gt(Carbon::now()->subMinutes(2))
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order will move to preparing automatically 2 minutes after acceptance.'
                ], 422);
            }

            DB::beginTransaction();

            $order->status = $request->status;

            if ($request->status == 'confirmed') {
                $this->setOrderColumnIfExists($order, 'confirmed_at', Carbon::now());
            }

            if ($request->status == 'preparing') {
                $this->setOrderColumnIfExists($order, 'preparing_at', Carbon::now());
            }

            if ($request->status == 'ready_for_pickup') {
                $this->setOrderColumnIfExists($order, 'ready_at', Carbon::now());
                $this->setOrderColumnIfExists($order, 'delivery_otp', random_int(1000, 9999));
            }

            if ($request->status == 'cancelled') {
                $this->setOrderColumnIfExists($order, 'cancelled_at', Carbon::now());
                $this->setOrderColumnIfExists(
                    $order,
                    'cancellation_reason',
                    $request->reason ?: 'Cancelled by restaurant'
                );
            }

            $order->save();

            if (in_array($request->status, ['confirmed', 'preparing', 'ready_for_pickup'], true) && !$order->driver_id) {
                app(AutoAssignDriverService::class)->autoAssignOrder($order);
                $order->refresh();
            }

            DB::commit();

            if ($order->status === 'cancelled' && $order->payment_status === 'success') {
                app(RefundService::class)->processRefund($order, 'Order cancelled by restaurant');
                $order->refresh();
            }

            broadcast(new OrderStatusUpdatedEvent($order, $restaurant->id));
            $statusMessage = match ($order->status) {
                'confirmed' => "Your order #{$order->order_number} has been confirmed by {$restaurant->name}.",
                'preparing' => "Your order #{$order->order_number} is now being prepared.",
                'ready_for_pickup' => "Your order #{$order->order_number} is ready for pickup.",
                'cancelled' => "Your order #{$order->order_number} was cancelled by {$restaurant->name}.",
                default => "Your order #{$order->order_number} status changed to {$order->status}.",
            };
            app(OrderStatusPushService::class)->notifyParticipants($order, $statusMessage);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $this->formatOrderForApi($order)
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Mark an order as ready for pickup
     */
    public function markOrderReady($id)
    {
        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->findOrFail($id);
            $restaurant = $restaurants->firstWhere('id', $order->restaurant_id);

            DB::beginTransaction();

            $order->status = 'ready_for_pickup';
            $this->setOrderColumnIfExists($order, 'ready_at', Carbon::now());
            $this->setOrderColumnIfExists(
                $order,
                'delivery_otp',
                $order->delivery_otp ?: random_int(1000, 9999)
            );
            $order->save();

            if (!$order->driver_id) {
                app(AutoAssignDriverService::class)->autoAssignOrder($order);
                $order->refresh();
            }

            DB::commit();

            broadcast(new OrderStatusUpdatedEvent($order, $restaurant->id));
            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your order #{$order->order_number} is ready for pickup."
            );

            return response()->json([
                'success' => true,
                'message' => 'Order marked ready successfully',
                'data' => $this->formatOrderForApi($order)
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Mark order ready error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Verify customer pickup OTP and complete a takeaway order.
     */
    public function verifyTakeawayOtp(Request $request, $id)
    {
        $transactionStarted = false;

        try {
            $user = auth()->user();
            $restaurants = $this->getAccessibleRestaurants($user);

            if ($restaurants->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'orders')) {
                return $response;
            }

            $request->validate([
                'otp' => 'required|string|min:4|max:8'
            ]);

            $order = Order::whereIn('restaurant_id', $restaurants->pluck('id'))
                ->visibleToRestaurant()
                ->findOrFail($id);
            $restaurant = $restaurants->firstWhere('id', $order->restaurant_id);

            if (($order->order_type ?? 'delivery') !== 'takeaway') {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP pickup verification is available for takeaway orders only.'
                ], 422);
            }

            if (!in_array($order->status, ['ready_for_pickup', 'picked_up'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order must be ready before pickup OTP can be verified.'
                ], 422);
            }

            if (!$order->delivery_otp || !hash_equals((string) $order->delivery_otp, (string) $request->otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid pickup OTP.'
                ], 422);
            }

            DB::beginTransaction();
            $transactionStarted = true;

            $order->status = 'delivered';
            $this->setOrderColumnIfExists($order, 'otp_verified', true);
            $this->setOrderColumnIfExists($order, 'otp_verified_at', Carbon::now());
            $this->setOrderColumnIfExists($order, 'picked_up_at', Carbon::now());
            $this->setOrderColumnIfExists($order, 'delivered_at', Carbon::now());
            $order->save();

            DB::commit();

            broadcast(new OrderStatusUpdatedEvent($order, $restaurant->id));
            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your takeaway order #{$order->order_number} has been completed."
            );

            return response()->json([
                'success' => true,
                'message' => 'Pickup OTP verified. Takeaway order completed.',
                'data' => $this->formatOrderForApi($order)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }
            Log::error('Takeaway OTP verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant profile details for owner app
     */
    public function getRestaurantInfo(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRestaurantInfo($restaurant)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update restaurant profile details for owner app
     */
    public function updateRestaurantInfo(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:20',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'cuisine' => 'nullable',
                'is_pure_veg' => 'nullable|boolean',
                'min_order_amount' => 'nullable|numeric|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'delivery_time' => 'nullable|integer|min:0',
                'delivery_radius' => 'nullable|numeric|min:0',
                'auto_accept_orders' => 'nullable|boolean',
                'order_lead_time' => 'nullable|integer|min:0',
                'same_day_delivery' => 'nullable|boolean',
            ]);

            if (array_key_exists('cuisine', $validated)) {
                if (is_string($validated['cuisine'])) {
                    $validated['cuisine'] = collect(explode(',', $validated['cuisine']))
                        ->map(fn ($item) => trim($item))
                        ->filter()
                        ->values()
                        ->all();
                }
            }

            $restaurant->update($validated);

            return response()->json([
                'success' => true,
                'data' => $this->formatRestaurantInfo($restaurant->fresh()),
                'message' => 'Restaurant information updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant staff members
     */
    public function getStaff(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantOwnerAccess($user)) {
                return $response;
            }

            $staff = RestaurantStaff::with('user.roles')
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('is_active', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (RestaurantStaff $staff) => $this->formatStaffForApi($staff));

            return response()->json([
                'success' => true,
                'data' => $staff
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Create restaurant staff member
     */
    public function createStaff(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:users,phone',
                'email' => 'required|email|max:255|unique:users,email',
                'role' => 'required|string|max:100',
                'shift' => 'nullable|string|max:100',
                'salary' => 'nullable|numeric|min:0',
                'permissions' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'password' => 'nullable|string|min:8|confirmed',
            ]);

            if ($response = $this->ensureRestaurantOwnerAccess($user)) {
                return $response;
            }

            $validated['restaurant_id'] = $restaurant->id;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $staffRole = $this->ensureStaffAccessControlSeeded($validated['permissions'] ?? []);

            DB::beginTransaction();

            $plainPassword = $validated['password'] ?? $this->generateTemporaryPassword();
            $staffUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($plainPassword),
                'is_active' => $validated['is_active'],
                'current_restaurant_id' => $restaurant->id,
            ]);
            $staffUser->syncRoles([$staffRole]);
            $staffUser->syncPermissions($this->mapStaffPermissionsToRbac($validated['permissions'] ?? []));

            $validated['user_id'] = $staffUser->id;
            $staff = RestaurantStaff::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'staff' => $this->formatStaffForApi($staff->fresh('user.roles')),
                    'account' => [
                        'email' => $staffUser->email,
                        'phone' => $staffUser->phone,
                        'temporary_password' => $plainPassword,
                    ],
                ],
                'message' => 'Staff member account created successfully.'
            ], 201);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update restaurant staff member
     */
    public function updateStaff(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $staff = RestaurantStaff::where('restaurant_id', $restaurant->id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20|unique:users,phone,' . ($staff->user_id ?? 'NULL'),
                'email' => 'nullable|email|max:255|unique:users,email,' . ($staff->user_id ?? 'NULL'),
                'role' => 'nullable|string|max:100',
                'shift' => 'nullable|string|max:100',
                'salary' => 'nullable|numeric|min:0',
                'permissions' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'password' => 'nullable|string|min:8|confirmed',
            ]);

            if ($response = $this->ensureRestaurantOwnerAccess($user)) {
                return $response;
            }

            DB::beginTransaction();

            $staff->update($validated);
            $this->syncStaffUserAccount($staff->fresh(), $restaurant, $validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatStaffForApi($staff->fresh('user.roles')),
                'message' => 'Staff member updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Toggle restaurant staff active status
     */
    public function toggleStaff($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantOwnerAccess($user)) {
                return $response;
            }

            $staff = RestaurantStaff::with('user')->where('restaurant_id', $restaurant->id)->findOrFail($id);
            $staff->is_active = !$staff->is_active;
            $staff->save();
            if ($staff->user) {
                $staff->user->update(['is_active' => $staff->is_active]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatStaffForApi($staff->fresh('user.roles')),
                'message' => 'Staff status updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Delete restaurant staff member
     */
    public function deleteStaff($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantOwnerAccess($user)) {
                return $response;
            }

            $staff = RestaurantStaff::with('user')->where('restaurant_id', $restaurant->id)->findOrFail($id);
            DB::beginTransaction();
            if ($staff->user) {
                $staff->user->tokens()->delete();
                $staff->user->delete();
            }
            $staff->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff member deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant settings
     */
    public function getSettings(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $paymentGateway = AppSetting::getValue('payment_gateway_provider', 'razorpay');
            $payoutGateway = AppSetting::getValue('payout_gateway_provider', $paymentGateway);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'email' => $restaurant->email,
                    'phone' => $restaurant->phone,
                    'address' => $restaurant->address,
                    'city' => $restaurant->city,
                    'state' => $restaurant->state,
                    'pincode' => $restaurant->pincode,
                    'latitude' => $restaurant->latitude !== null ? (float)$restaurant->latitude : null,
                    'longitude' => $restaurant->longitude !== null ? (float)$restaurant->longitude : null,
                    'pending_location_request' => $restaurant->locationChangeRequests()
                        ->where('status', 'pending')
                        ->latest()
                        ->first(),
                    'delivery_fee' => (float)$restaurant->delivery_fee,
                    'min_order_amount' => (float)$restaurant->min_order_amount,
                    'amount_for_one' => $restaurant->amountForOne(),
                    'delivery_time' => $restaurant->delivery_time,
                    'logo_image' => \App\Services\MediaStorage::url($restaurant->logo_image),
                    'banner_image' => \App\Services\MediaStorage::url($restaurant->banner_image),
                    'description' => $restaurant->description,
                    'cuisine' => $restaurant->cuisine,
                    'is_open' => (bool)$restaurant->is_open,
                    'account_holder_name' => $user->account_holder_name,
                    'bank_name' => $user->bank_name,
                    'account_number' => $user->account_number,
                    'ifsc_code' => $user->ifsc_code,
                    'routing_code' => $user->routing_code ?? $user->ifsc_code,
                    'upi_id' => $user->upi_id,
                    'stripe_account_id' => $user->stripe_account_id,
                    'gateway_account_id' => $user->gateway_account_id,
                    'mollie_organization_id' => $user->mollie_organization_id,
                    'mercadopago_collector_id' => $user->mercadopago_collector_id,
                    'payment_gateway_provider' => $paymentGateway,
                    'payout_gateway_provider' => $payoutGateway,
                    'country_code' => GatewayRegistry::resolveCountryCode(
                        AppSetting::getValue('country_code'),
                        $payoutGateway
                    ),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update restaurant settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:20',
                'min_order_amount' => 'nullable|numeric|min:0',
                'delivery_time' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'account_holder_name' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:64',
                'ifsc_code' => 'nullable|string|max:32',
                'routing_code' => 'nullable|string|max:32',
                'upi_id' => 'nullable|string|max:255',
                'stripe_account_id' => 'nullable|string|max:255',
                'gateway_account_id' => 'nullable|string|max:255',
            ]);

            $restaurant->update($request->only([
                'name', 'email', 'phone', 'address', 'city', 'state', 'pincode',
                'min_order_amount', 'delivery_time', 'description'
            ]));

            $user->update($request->only([
                'account_holder_name',
                'bank_name',
                'account_number',
                'upi_id',
                'stripe_account_id',
            ]));
            $user->update($this->payoutProviderAccountAttributes($request));
            $user->update([
                'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
                'routing_code' => $request->routing_code ?: $request->ifsc_code,
            ]);

            return response()->json([
                'success' => true,
                'data' => $restaurant,
                'message' => 'Settings updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    public function requestLocationChange(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $restaurantApplication = $this->approvedRestaurantApplication($restaurant);
            $hasApprovedFssaiLicense = filled($restaurant->fssai_license_number)
                || filled($restaurantApplication?->fssai_license)
                || filled($restaurantApplication?->license_number);

            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'fssai_license' => [
                    $hasApprovedFssaiLicense ? 'nullable' : 'required',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            $pending = $restaurant->locationChangeRequests()
                ->where('status', 'pending')
                ->first();

            if ($pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'A location change request is already pending admin approval.'
                ], 422);
            }

            $path = $request->hasFile('fssai_license')
                ? $request->file('fssai_license')->store('restaurant_location_requests/fssai', 'public')
                : $restaurantApplication?->fssai_license;

            $locationRequest = RestaurantLocationChangeRequest::create([
                'restaurant_id' => $restaurant->id,
                'requested_by' => $user->id,
                'current_latitude' => $restaurant->latitude,
                'current_longitude' => $restaurant->longitude,
                'requested_latitude' => $validated['latitude'],
                'requested_longitude' => $validated['longitude'],
                'fssai_license_path' => $path,
                'status' => 'pending',
            ]);

            SupportTicket::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $user->id,
                'ticket_number' => 'SUP-' . now()->format('YmdHis') . '-' . strtoupper(substr((string) $restaurant->id, -4)),
                'subject' => 'Restaurant location update request',
                'category' => 'location_change',
                'priority' => 'high',
                'description' => implode("\n", array_filter([
                    'Restaurant has requested a location update.',
                    'Current latitude: ' . ($restaurant->latitude ?? 'N/A'),
                    'Current longitude: ' . ($restaurant->longitude ?? 'N/A'),
                    'Requested latitude: ' . $validated['latitude'],
                    'Requested longitude: ' . $validated['longitude'],
                    'FSSAI attachment: ' . ($path ?: 'Approved license already on file'),
                ])),
                'attachment' => $path,
                'status' => 'open',
            ]);

            return response()->json([
                'success' => true,
                'data' => $locationRequest,
                'message' => 'Location change request submitted for admin approval.'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location change request.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant promo codes
     */
    public function getPromos(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $promos = PromoCode::where('restaurant_id', $restaurant->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $promos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Create restaurant promo code
     */
    public function createPromo(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $validated = $request->validate([
                'code' => 'required|string|max:50|unique:promo_codes,code',
                'description' => 'nullable|string',
                'discount_type' => 'required|in:percentage,fixed',
                'discount_value' => 'required|numeric|min:0',
                'min_order_amount' => 'nullable|numeric|min:0',
                'max_discount_amount' => 'nullable|numeric|min:0',
                'usage_limit' => 'nullable|integer|min:1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
            ]);

            $validated['restaurant_id'] = $restaurant->id;
            $validated['code'] = strtoupper($validated['code']);
            $validated['used_count'] = 0;
            $validated['is_active'] = true;

            $promo = PromoCode::create($validated);

            return response()->json([
                'success' => true,
                'data' => $promo,
                'message' => 'Promo code created successfully.'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Toggle restaurant promo status
     */
    public function togglePromo($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
            $promo->is_active = !$promo->is_active;
            $promo->save();

            return response()->json([
                'success' => true,
                'data' => $promo,
                'message' => 'Promo status updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Delete restaurant promo
     */
    public function deletePromo($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $promo = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
            $promo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Promo deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get restaurant printers
     */
    public function getPrinters(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $printers = PrinterSetting::where('restaurant_id', $restaurant->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $printers,
                'settings' => [
                    'auto_print_new_orders' => (bool) $restaurant->auto_print_new_orders,
                ],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Create restaurant printer
     */
    public function createPrinter(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $validated = $request->validate([
                'printer_name' => 'required|string|max:255',
                'printer_type' => 'required|in:network,usb,bluetooth',
                'ip_address' => 'required_if:printer_type,network|nullable|ip',
                'port' => 'required_if:printer_type,network|nullable|integer|min:1|max:65535',
                'usb_path' => 'nullable|string|max:255',
                'bluetooth_mac' => 'nullable|string|max:17',
                'paper_size' => 'required|integer|in:58,80',
                'is_default' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            if (!empty($validated['is_default'])) {
                PrinterSetting::where('restaurant_id', $restaurant->id)->update(['is_default' => false]);
            }

            $printer = PrinterSetting::create([
                'restaurant_id' => $restaurant->id,
                'printer_name' => $validated['printer_name'],
                'printer_type' => $validated['printer_type'],
                'ip_address' => $validated['ip_address'] ?? null,
                'port' => $validated['port'] ?? 9100,
                'usb_path' => $validated['usb_path'] ?? null,
                'bluetooth_mac' => $validated['bluetooth_mac'] ?? null,
                'paper_size' => $validated['paper_size'],
                'is_default' => (bool)($validated['is_default'] ?? false),
                'is_active' => (bool)($validated['is_active'] ?? true),
            ]);

            return response()->json([
                'success' => true,
                'data' => $printer,
                'message' => 'Printer added successfully.'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Test restaurant printer
     */
    public function testPrinter($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $printer = PrinterSetting::where('restaurant_id', $restaurant->id)->findOrFail($id);
            $printed = app(PrinterService::class)->printTest($printer);

            if (!$printed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to printer.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Test print sent successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Set default restaurant printer
     */
    public function setDefaultPrinter($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $printer = PrinterSetting::where('restaurant_id', $restaurant->id)->findOrFail($id);
            PrinterSetting::where('restaurant_id', $restaurant->id)->update(['is_default' => false]);
            $printer->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Default printer updated.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Update restaurant printer settings
     */
    public function updatePrinterSettings(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $validated = $request->validate([
                'auto_print_new_orders' => 'required|boolean',
            ]);

            $restaurant->update([
                'auto_print_new_orders' => (bool) $validated['auto_print_new_orders'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $restaurant->auto_print_new_orders
                    ? 'Auto print enabled for new orders.'
                    : 'Auto print disabled for new orders.',
                'settings' => [
                    'auto_print_new_orders' => (bool) $restaurant->auto_print_new_orders,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Delete restaurant printer
     */
    public function deletePrinter($id)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $printer = PrinterSetting::where('restaurant_id', $restaurant->id)->findOrFail($id);
            $printer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Printer deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Get analytics data
     */
    public function getAnalytics(Request $request)
    {
        try {
            $user = auth()->user();
            $restaurant = $this->getAuthenticatedRestaurant($user);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            if ($response = $this->ensureRestaurantPermission($user, 'reports')) {
                return $response;
            }

            $period = $request->get('period', 'week');
            $endDate = Carbon::now()->endOfDay();
            $startDate = match ($period) {
                'month' => Carbon::now()->subDays(29)->startOfDay(),
                'year' => Carbon::now()->subDays(364)->startOfDay(),
                default => Carbon::now()->subDays(6)->startOfDay(),
            };

            $orders = $restaurant->orders()
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $revenueOrders = $orders->whereNotIn('status', ['cancelled']);
            $deliveredOrders = $orders->where('status', 'delivered');

            $groupedByDate = $revenueOrders->groupBy(function ($order) {
                return Carbon::parse($order->created_at)->toDateString();
            });

            $dailyData = collect();
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $key = $date->toDateString();
                $dayOrders = $groupedByDate->get($key, collect());
                $dailyData->push([
                    'date' => $key,
                    'orders' => (int) $dayOrders->count(),
                    'revenue' => round((float) $dayOrders->sum('total'), 2),
                ]);
            }

            $cancelledOrders = $orders->where('status', 'cancelled')->count();
            $totalOrdersForRate = max($orders->count(), 1);
            $cancellationRate = round(($cancelledOrders / $totalOrdersForRate) * 100, 2);

            $topItems = $this->buildTopSellingItems($restaurant->id, $startDate, $endDate);

            $hourlyGroups = $revenueOrders->groupBy(function ($order) {
                return (int) Carbon::parse($order->created_at)->format('G');
            });
            $hourlyData = collect(range(0, 23))->map(function ($hour) use ($hourlyGroups) {
                $hourOrders = $hourlyGroups->get($hour, collect());
                return [
                    'hour' => $hour,
                    'orders' => (int) $hourOrders->count(),
                    'revenue' => round((float) $hourOrders->sum('total'), 2),
                ];
            })->values();

            $totalRevenue = round((float) $revenueOrders->sum('total'), 2);
            $totalOrders = (int) $revenueOrders->count();
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'avg_order_value' => round($avgOrderValue, 2),
                    'delivered_orders' => (int) $deliveredOrders->count(),
                    'cancelled_orders' => (int) $cancelledOrders,
                    'cancellation_rate' => $cancellationRate,
                    'daily_revenue' => $dailyData->values(),
                    'daily_orders' => $dailyData->values(),
                    'top_items' => $topItems,
                    'hourly_data' => $hourlyData,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    private function buildTopSellingItems(int $restaurantId, Carbon $startDate, Carbon $endDate)
    {
        $summary = [];
        $ordersWithOrderItems = collect();

        if (Schema::hasTable('order_items')) {
            $orderItemRows = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->where('orders.restaurant_id', $restaurantId)
                ->where(function ($query) {
                    $query->where('orders.payment_status', 'success')
                        ->orWhereIn('orders.payment_method', ['cod', 'cash', 'cash_on_delivery'])
                        ->orWhereIn('orders.delivery_payment_mode', ['cod', 'cash', 'cash_on_delivery']);
                })
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->whereNotIn('orders.status', ['cancelled'])
                ->groupBy('order_items.menu_item_id', 'menu_items.name')
                ->selectRaw("COALESCE(menu_items.name, 'Menu item') as name")
                ->selectRaw('SUM(order_items.quantity) as total_orders')
                ->selectRaw('SUM(order_items.total_price) as revenue')
                ->orderByDesc('total_orders')
                ->get();

            foreach ($orderItemRows as $item) {
                $name = (string) $item->name;
                $summary[$name] ??= ['name' => $name, 'total_orders' => 0, 'revenue' => 0.0];
                $summary[$name]['total_orders'] += (int) $item->total_orders;
                $summary[$name]['revenue'] += (float) $item->revenue;
            }

            $ordersWithOrderItems = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.restaurant_id', $restaurantId)
                ->where(function ($query) {
                    $query->where('orders.payment_status', 'success')
                        ->orWhereIn('orders.payment_method', ['cod', 'cash', 'cash_on_delivery'])
                        ->orWhereIn('orders.delivery_payment_mode', ['cod', 'cash', 'cash_on_delivery']);
                })
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->whereNotIn('orders.status', ['cancelled'])
                ->distinct()
                ->pluck('orders.id');
        }

        $ordersQuery = Order::where('restaurant_id', $restaurantId)
            ->visibleToRestaurant()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled']);

        if ($ordersWithOrderItems->isNotEmpty()) {
            $ordersQuery->whereNotIn('id', $ordersWithOrderItems->all());
        }

        $orders = $ordersQuery->get(['items']);

        foreach ($orders as $order) {
            $orderItems = is_string($order->items)
                ? json_decode($order->items, true)
                : $order->items;
            if (!is_array($orderItems)) continue;

            foreach ($orderItems as $item) {
                if (!is_array($item)) continue;
                $name = (string) ($item['name'] ?? $item['item_name'] ?? 'Menu item');
                $quantity = (int) ($item['quantity'] ?? 1);
                $total = (float) ($item['total'] ?? $item['total_price'] ?? (($item['price'] ?? $item['unit_price'] ?? 0) * $quantity));
                $summary[$name] ??= ['name' => $name, 'total_orders' => 0, 'revenue' => 0.0];
                $summary[$name]['total_orders'] += $quantity;
                $summary[$name]['revenue'] += $total;
            }
        }

        return collect($summary)
            ->sortByDesc('total_orders')
            ->take(10)
            ->map(fn ($item) => [
                'name' => $item['name'],
                'total_orders' => (int) $item['total_orders'],
                'revenue' => round((float) $item['revenue'], 2),
            ])
            ->values();
    }

    /**
     * Get nearby restaurants for customer discovery
     */
    public function nearby(Request $request)
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'radius' => 'nullable|numeric',
                'query' => 'nullable|string|max:255',
                'open_now' => 'nullable|boolean',
            ]);

            $latitude = (float)$validated['lat'];
            $longitude = (float)$validated['lng'];
            $radius = isset($validated['radius']) ? min(100.0, (float)$validated['radius']) : 100.0;

            $restaurantsQuery = Restaurant::query()->where('is_verified', true);

            if (!empty($validated['query'])) {
                $search = $validated['query'];
                $restaurantsQuery->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('city', 'like', "%{$search}%")
                          ->orWhere('address', 'like', "%{$search}%")
                          ->orWhere('cuisine', 'like', "%{$search}%");
                });
            }

            if (!empty($validated['open_now'])) {
                $restaurantsQuery->where('is_open', true);
            }

            $restaurants = $restaurantsQuery
                ->nearby($latitude, $longitude, $radius)
                ->get();

            $data = $restaurants
                ->map(fn (Restaurant $restaurant) => $this->augmentRestaurantResource(
                    $restaurant,
                    (float) $latitude,
                    (float) $longitude
                ))
                ->sortByDesc(fn ($restaurant) => (bool) ($restaurant['is_open_now'] ?? $restaurant['is_open'] ?? false))
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'page' => 1,
                    'per_page' => $data->count(),
                    'total' => $data->count(),
                    'has_more' => false,
                    'next_page' => null,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location parameters provided.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Nearby restaurants error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Search restaurants by query
     */
    public function search(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'nullable|string|max:255',
                'lat' => 'nullable|numeric',
                'lng' => 'nullable|numeric',
                'radius' => 'nullable|numeric',
                'open_now' => 'nullable|boolean',
                'delivery_zone_only' => 'nullable|boolean',
                'cuisine_id' => 'nullable|integer',
            ]);

            $search = trim((string) ($validated['query'] ?? $request->input('q', '')));
            $cuisineId = isset($validated['cuisine_id'])
                ? (int) $validated['cuisine_id']
                : null;
            if ($search === '' && ($cuisineId === null || $cuisineId <= 0)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'suggestions' => [],
                ]);
            }
            $resolvedCuisine = $cuisineId !== null && $cuisineId > 0
                ? Cuisine::query()->find($cuisineId)
                : Cuisine::query()
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%" . \Illuminate\Support\Str::slug($search) . "%")
                    ->first();
            if (($cuisineId === null || $cuisineId <= 0) && $resolvedCuisine) {
                $cuisineId = (int) $resolvedCuisine->id;
            }
            $cuisineTerms = collect([
                $search,
                \Illuminate\Support\Str::slug($search),
                $cuisineId,
                $cuisineId !== null ? (string) $cuisineId : null,
                $resolvedCuisine?->id,
                $resolvedCuisine?->name,
                $resolvedCuisine?->slug,
            ])
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->values();
            $cuisineFilter = $cuisineId !== null && $cuisineId > 0;
            $categorySearch = $request->input('type') === 'category'
                || $request->filled('category')
                || $request->filled('cuisine')
                || $cuisineFilter;
            if (!empty($validated['delivery_zone_only']) &&
                (empty($validated['lat']) || empty($validated['lng']))) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'suggestions' => [],
                ]);
            }

            $restaurantsQuery = Restaurant::query()
                ->where('is_verified', true)
                ->where(function ($query) use ($search, $cuisineId, $categorySearch, $cuisineTerms, $cuisineFilter) {
                    if ($cuisineFilter) {
                        $query->where(function ($cuisineQuery) use ($cuisineTerms) {
                            foreach ($cuisineTerms as $term) {
                                $cuisineQuery->orWhereJsonContains('cuisine', $term)
                                    ->orWhere('cuisine', 'like', "%{$term}%");
                            }
                        })->orWhereHas('menuItems', function ($menuQuery) use ($cuisineId, $cuisineTerms) {
                            $menuQuery->where('is_available', true)
                                ->where(function ($statusQuery) {
                                    $statusQuery->whereNull('approval_status')
                                        ->orWhere('approval_status', 'approved');
                                })
                                ->where(function ($itemQuery) use ($cuisineId, $cuisineTerms) {
                                    $itemQuery->where('cuisine_id', $cuisineId)
                                        ->orWhereHas('cuisine', function ($cuisineQuery) use ($cuisineTerms) {
                                            foreach ($cuisineTerms as $term) {
                                                $cuisineQuery->orWhere('name', 'like', "%{$term}%")
                                                    ->orWhere('slug', 'like', "%{$term}%");
                                            }
                                        })
                                        ->orWhereHas('category', function ($categoryQuery) use ($cuisineTerms) {
                                            foreach ($cuisineTerms as $term) {
                                                $categoryQuery->orWhere('name', 'like', "%{$term}%");
                                            }
                                        });
                                });
                        });
                        return;
                    }

                    if (! $categorySearch) {
                        $query->where('name', 'like', "%{$search}%")
                              ->orWhere('city', 'like', "%{$search}%")
                              ->orWhere('address', 'like', "%{$search}%")
                              ->orWhereHas('menuItems', function ($menuQuery) use ($search) {
                                  $menuQuery->where('is_available', true)
                                      ->where(function ($statusQuery) {
                                          $statusQuery->whereNull('approval_status')
                                              ->orWhere('approval_status', 'approved');
                                      })
                                      ->where(function ($itemQuery) use ($search) {
                                          $itemQuery->where('name', 'like', "%{$search}%")
                                              ->orWhere('description', 'like', "%{$search}%")
                                              ->orWhereHas('category', function ($categoryQuery) use ($search) {
                                                  $categoryQuery->where('name', 'like', "%{$search}%");
                                              })
                                              ->orWhereHas('cuisine', function ($cuisineQuery) use ($search) {
                                                  $cuisineQuery->where('name', 'like', "%{$search}%");
                                              });
                                      });
                              });
                        foreach ($cuisineTerms as $term) {
                            $query->orWhere('cuisine', 'like', "%{$term}%")
                                ->orWhereJsonContains('cuisine', $term);
                        }
                        return;
                    }

                    $query->where(function ($cuisineQuery) use ($cuisineTerms) {
                        foreach ($cuisineTerms as $term) {
                            $cuisineQuery->orWhere('cuisine', 'like', "%{$term}%")
                                ->orWhereJsonContains('cuisine', $term);
                        }
                    })
                        ->orWhereHas('menuItems', function ($menuQuery) use ($search) {
                            $menuQuery->where('is_available', true)
                                ->where(function ($statusQuery) {
                                    $statusQuery->whereNull('approval_status')
                                        ->orWhere('approval_status', 'approved');
                                })
                                ->where(function ($itemQuery) use ($search) {
                                    $itemQuery->whereHas('category', function ($categoryQuery) use ($search) {
                                        $categoryQuery->where('name', 'like', "%{$search}%");
                                    })->orWhereHas('cuisine', function ($cuisineQuery) use ($search) {
                                        $cuisineQuery->where('name', 'like', "%{$search}%");
                                    });
                                });
                        });

                    if ($cuisineId !== null && $cuisineId > 0) {
                        $query->orWhereJsonContains('cuisine', $cuisineId)
                            ->orWhereJsonContains('cuisine', (string) $cuisineId)
                            ->orWhereHas('menuItems', function ($menuQuery) use ($cuisineId) {
                                $menuQuery->where('is_available', true)
                                    ->where(function ($statusQuery) {
                                        $statusQuery->whereNull('approval_status')
                                            ->orWhere('approval_status', 'approved');
                                    })
                                    ->where('cuisine_id', $cuisineId);
                            });
                    }
                });

            if (!empty($validated['open_now'])) {
                $restaurantsQuery->where('is_open', true);
            }

            if (!empty($validated['lat']) && !empty($validated['lng'])) {
                $radius = isset($validated['radius']) ? min(100.0, (float)$validated['radius']) : 100.0;
                $restaurantsQuery = $restaurantsQuery->nearby((float)$validated['lat'], (float)$validated['lng'], $radius);
            } else {
                $restaurantsQuery->orderByDesc('is_open')->orderByDesc('rating');
            }

            $customerLat = isset($validated['lat']) ? (float) $validated['lat'] : null;
            $customerLng = isset($validated['lng']) ? (float) $validated['lng'] : null;
            $restaurants = $restaurantsQuery->get();
            $restaurantIds = $restaurants->pluck('id')->values();
            $restaurantsById = $restaurants->keyBy('id');

            $matchedItems = MenuItem::query()
                ->with(['category:id,name', 'cuisine:id,name'])
                ->whereIn('restaurant_id', $restaurantIds)
                ->where('is_available', true)
                ->where(function ($statusQuery) {
                    $statusQuery->whereNull('approval_status')
                        ->orWhere('approval_status', 'approved');
                })
                ->where(function ($itemQuery) use ($search, $categorySearch, $cuisineId, $cuisineFilter, $cuisineTerms) {
                    if ($cuisineFilter) {
                        $itemQuery->where('cuisine_id', $cuisineId)
                            ->orWhereHas('cuisine', function ($cuisineQuery) use ($cuisineTerms) {
                                foreach ($cuisineTerms as $term) {
                                    $cuisineQuery->orWhere('name', 'like', "%{$term}%")
                                        ->orWhere('slug', 'like', "%{$term}%");
                                }
                            })
                            ->orWhereHas('category', function ($categoryQuery) use ($cuisineTerms) {
                                foreach ($cuisineTerms as $term) {
                                    $categoryQuery->orWhere('name', 'like', "%{$term}%");
                                }
                            });
                        return;
                    }

                    if ($categorySearch) {
                        $itemQuery->whereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })->orWhereHas('cuisine', function ($cuisineQuery) use ($search) {
                            $cuisineQuery->where('name', 'like', "%{$search}%");
                        });
                        return;
                    }

                    $itemQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('cuisine', function ($cuisineQuery) use ($search) {
                            $cuisineQuery->where('name', 'like', "%{$search}%");
                        });
                })
                ->orderByDesc('total_orders')
                ->get();

            $itemsByRestaurant = $matchedItems->groupBy('restaurant_id');
            $suggestions = collect();
            $data = $restaurants->map(function (Restaurant $restaurant) use ($customerLat, $customerLng, $itemsByRestaurant, $suggestions) {
                $restaurantItems = $itemsByRestaurant->get($restaurant->id, collect());
                foreach ($restaurantItems as $item) {
                    $suggestions->push($item->name);
                }
                $suggestions->push($restaurant->name);

                return $this->formatSearchRestaurantPayload(
                    $restaurant,
                    $customerLat,
                    $customerLng,
                    $restaurantItems->pluck('name')->filter()->unique()->values()->all(),
                    $restaurantItems->take(8)->map(fn ($item) => $this->formatSearchMenuItemPayload($item, $restaurant))->values()->all()
                );
            })->values();

            $matchedMenuItems = $matchedItems
                ->map(function ($item) use ($restaurantsById) {
                    $restaurant = $restaurantsById->get($item->restaurant_id);
                    return $restaurant
                        ? $this->formatSearchMenuItemPayload($item, $restaurant)
                        : null;
                })
                ->filter()
                ->unique(fn ($item) => $item['restaurant_id'] . ':' . $item['id'])
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'menu_items' => $matchedMenuItems
                    ->unique(fn ($item) => $item['restaurant_id'] . ':' . $item['id'])
                    ->values(),
                'suggestions' => $suggestions
                    ->filter(fn ($value) => filled($value))
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->take(8)
                    ->values(),
                'meta' => [
                    'page' => 1,
                    'per_page' => $data->count(),
                    'total' => $data->count(),
                    'restaurant_count' => $data->count(),
                    'item_count' => $matchedMenuItems->count(),
                    'has_more' => false,
                    'next_page' => null,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search parameters provided.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Restaurant search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Show restaurant details by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $restaurant = Restaurant::with('owner')->find($id);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.'
                ], 404);
            }

            $reviewHighlights = Review::query()
                ->with('user:id,name')
                ->where('restaurant_id', $restaurant->id)
                ->approved()
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'user_name' => $review->user->name ?? 'Customer',
                    'is_verified' => (bool) $review->is_verified,
                    'created_at' => optional($review->created_at)->toIso8601String(),
                ])
                ->values();

            $similarRestaurants = $this->similarRestaurantsFor($restaurant);

            $restaurantApplication = PartnerApplication::query()
                ->where('partner_type', 'restaurant')
                ->where(function ($query) use ($restaurant) {
                    if ($restaurant->owner?->email) {
                        $query->orWhere('contact_email', $restaurant->owner->email);
                    }

                    if ($restaurant->email) {
                        $query->orWhere('business_email', $restaurant->email);
                    }

                    $query->orWhere('business_name', $restaurant->name);
                })
                ->latest('id')
                ->first();

            $customerLat = $request->filled('lat') ? (float) $request->input('lat') : null;
            $customerLng = $request->filled('lng') ? (float) $request->input('lng') : null;
            $resource = $this->augmentRestaurantResource($restaurant, $customerLat, $customerLng);

            return response()->json([
                'success' => true,
                'data' => array_merge($resource, [
                    'fssai_license_number' => $restaurant->fssai_license_number
                        ?: $restaurantApplication?->license_number,
                    'review_highlights' => $reviewHighlights,
                    'review_comment_count' => (int) Review::query()
                        ->where('restaurant_id', $restaurant->id)
                        ->approved()
                        ->whereNotNull('comment')
                        ->where('comment', '!=', '')
                        ->count(),
                    'similar_restaurants' => RestaurantResource::collection($similarRestaurants)->resolve(),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Restaurant show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
            ], 500);
        }
    }

    /**
     * Format order for API response
     */
    private function formatCategoryForApi(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'image' => $category->image,
            'image_url' => \App\Services\MediaStorage::url($category->image),
            'display_order' => $category->display_order,
            'is_active' => (bool) $category->is_active,
            'item_count' => $category->relationLoaded('menuItems')
                ? $category->menuItems->count()
                : $category->menuItems()->count(),
        ];
    }

    private function formatOrderForApi($order)
    {
        $order->loadMissing('branch');

        $items = is_string($order->items) ? json_decode($order->items, true) : $order->items;
        
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type ?? 'delivery',
            'customer_name' => $order->customer_name ?? 'Guest',
            'customer_phone' => $order->customer_phone ?? '',
            'delivery_address' => $order->delivery_address ?? '',
            'total' => (float)($order->total ?? 0),
            'subtotal' => (float)($order->subtotal ?? 0),
            'delivery_fee' => (float)($order->delivery_fee ?? 0),
            'tax' => (float)($order->tax ?? 0),
            'discount' => (float)($order->discount ?? 0),
            'status' => $order->status ?? 'pending',
            'driver_assignment_attempts' => (int)($order->driver_assignment_attempts ?? 0),
            'driver_assigned_at' => $order->driver_assigned_at ? $order->driver_assigned_at->toIso8601String() : null,
            'driver_accepted_at' => $order->driver_accepted_at ? $order->driver_accepted_at->toIso8601String() : null,
            'items' => $items ?? [],
            'items_count' => count($items ?? []),
            'payment_method' => $order->payment_method ?? 'cod',
            'payment_status' => $order->payment_status ?? 'pending',
            'restaurant_id' => $order->restaurant_id,
            'restaurant_name' => $order->relationLoaded('restaurant') && $order->restaurant
                ? $order->restaurant->name
                : null,
            'branch_id' => $order->branch_id,
            'branch' => $order->branch ? [
                'id' => $order->branch->id,
                'name' => $order->branch->name,
                'code' => $order->branch->code,
                'city' => $order->branch->city,
                'state' => $order->branch->state,
                'status' => $order->branch->status,
            ] : null,
            'customer' => $order->relationLoaded('customer') && $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
                'email' => $order->customer->email,
            ] : null,
            'driver' => $order->relationLoaded('driver') && $order->driver ? [
                'id' => $order->driver->id,
                'name' => $order->driver->name,
                'phone' => $order->driver->phone,
            ] : null,
            'customer_address' => $order->customer_address,
            'delivery_lat' => $order->delivery_lat !== null ? (float)$order->delivery_lat : null,
            'delivery_lng' => $order->delivery_lng !== null ? (float)$order->delivery_lng : null,
            'special_instructions' => $order->special_instructions,
            'cancellation_reason' => $order->cancellation_reason,
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : Carbon::now()->toIso8601String(),
            'confirmed_at' => $order->confirmed_at ? $order->confirmed_at->toIso8601String() : null,
            'preparation_time_minutes' => $order->preparation_time_minutes,
            'preparing_at' => $order->preparing_at ? $order->preparing_at->toIso8601String() : null,
            'ready_at' => $order->ready_at ? $order->ready_at->toIso8601String() : null,
            'reached_at' => $order->reached_at ? $order->reached_at->toIso8601String() : null,
            'delivered_at' => $order->delivered_at ? $order->delivered_at->toIso8601String() : null,
            'cancelled_at' => $order->cancelled_at ? $order->cancelled_at->toIso8601String() : null,
            'delivery_otp' => ($order->order_type ?? 'delivery') === 'takeaway' ? $order->delivery_otp : null,
        ];
    }

    /**
     * Format restaurant profile for owner app screens.
     */
    private function formatRestaurantInfo($restaurant)
    {
        $restaurant->loadMissing('branch');

        $restaurantApplication = $this->approvedRestaurantApplication($restaurant);
        $hasApprovedFssaiLicense = filled($restaurant->fssai_license_number)
            || filled($restaurantApplication?->fssai_license)
            || filled($restaurantApplication?->license_number);

        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'description' => $restaurant->description,
            'phone' => $restaurant->phone,
            'email' => $restaurant->email,
            'address' => $restaurant->address,
            'city' => $restaurant->city,
            'state' => $restaurant->state,
            'pincode' => $restaurant->pincode,
            'latitude' => $restaurant->latitude !== null ? (float)$restaurant->latitude : null,
            'longitude' => $restaurant->longitude !== null ? (float)$restaurant->longitude : null,
            'cuisine' => $this->resolveCuisineNamesForPayload($restaurant->cuisine ?? []),
            'cuisine_ids' => $this->resolveCuisineIdsForPayload($restaurant->cuisine ?? []),
            'cuisine_text' => implode(', ', $this->resolveCuisineNamesForPayload($restaurant->cuisine ?? [])),
            'is_open' => $restaurant->isOpenNow(),
            'manual_is_open' => (bool)$restaurant->is_open,
            'is_open_now' => $restaurant->isOpenNow(),
            'is_pure_veg' => (bool)$restaurant->is_pure_veg,
            'min_order_amount' => (float)($restaurant->min_order_amount ?? 0),
            'amount_for_one' => $restaurant->amountForOne(),
            'delivery_fee' => (float)($restaurant->delivery_fee ?? 0),
            'delivery_time' => (int)($restaurant->delivery_time ?? 30),
            'delivery_radius' => $restaurant->delivery_radius !== null ? (float)$restaurant->delivery_radius : null,
            'rating' => (int)($restaurant->total_ratings ?? 0) >= 3
                ? (float)($restaurant->rating ?? 0)
                : null,
            'total_ratings' => (int)($restaurant->total_ratings ?? 0),
            'is_verified' => (bool)$restaurant->is_verified,
            'fssai_license_number' => $restaurant->fssai_license_number ?: $restaurantApplication?->license_number,
            'has_approved_fssai_license' => $hasApprovedFssaiLicense,
            'auto_accept_orders' => (bool)($restaurant->auto_accept_orders ?? false),
            'order_lead_time' => (int)($restaurant->order_lead_time ?? 0),
            'same_day_delivery' => (bool)($restaurant->same_day_delivery ?? true),
            'restaurant_type' => $restaurant->restaurant_type ?? 'delivery',
            'dining_charge' => (float)($restaurant->dining_charge ?? 0),
            'dining_settings' => $restaurant->dining_settings ?? [],
            'accepts_delivery' => $restaurant->acceptsService('delivery'),
            'accepts_dining' => $restaurant->acceptsService('dining'),
            'accepts_takeaway' => $restaurant->acceptsService('takeaway'),
            'logo_image' => \App\Services\MediaStorage::url($restaurant->logo_image),
            'banner_image' => \App\Services\MediaStorage::url($restaurant->banner_image),
            'cover_image' => \App\Services\MediaStorage::url($restaurant->cover_image),
        ];
    }

    /**
     * Accept common app/UI status aliases and convert them to stored values.
     */
    private function normalizeRestaurantOrderStatus($status)
    {
        if (!$status) {
            return null;
        }

        $status = strtolower(trim((string)$status));
        $status = str_replace([' ', '-'], '_', $status);

        $aliases = [
            'accept' => 'confirmed',
            'accepted' => 'confirmed',
            'confirm' => 'confirmed',
            'confirmed' => 'confirmed',
            'start_preparing' => 'preparing',
            'preparing' => 'preparing',
            'ready' => 'ready_for_pickup',
            'mark_ready' => 'ready_for_pickup',
            'ready_for_pickup' => 'ready_for_pickup',
            'ready_for_delivery' => 'ready_for_pickup',
            'cancel' => 'cancelled',
            'canceled' => 'cancelled',
            'cancelled' => 'cancelled',
            'rejected' => 'cancelled',
            'reject' => 'cancelled',
        ];

        return $aliases[$status] ?? $status;
    }

    private function getAuthenticatedRestaurant(?User $user): ?Restaurant
    {
        if (!$user) {
            return null;
        }

        $selectedId = request()->input('restaurant_id');
        if ($selectedId && $selectedId !== 'all') {
            return $this->getAccessibleRestaurants($user)
                ->firstWhere('id', (int) $selectedId);
        }

        return $user->activeRestaurant();
    }

    private function approvedRestaurantApplication($restaurant): ?PartnerApplication
    {
        return PartnerApplication::query()
            ->restaurant()
            ->approved()
            ->where(function ($query) use ($restaurant) {
                if ($restaurant->owner?->email) {
                    $query->orWhere('contact_email', $restaurant->owner->email);
                }

                if ($restaurant->email) {
                    $query->orWhere('business_email', $restaurant->email);
                }

                $query->orWhere('business_name', $restaurant->name);
            })
            ->latest('id')
            ->first();
    }

    private function formatSearchRestaurantPayload(
        Restaurant $restaurant,
        ?float $customerLat = null,
        ?float $customerLng = null,
        array $matchedItemNames = [],
        array $matchedMenuItems = []
    ): array {
        $cuisineNames = $this->resolveCuisineNamesForPayload($restaurant->cuisine ?? []);
        $cuisineIds = $this->resolveCuisineIdsForPayload($restaurant->cuisine ?? []);
        $distance = $restaurant->distance ?? null;
        if ($distance === null &&
            $customerLat !== null &&
            $customerLng !== null &&
            $restaurant->latitude !== null &&
            $restaurant->longitude !== null) {
            $distance = $this->calculateDistance(
                (float) $restaurant->latitude,
                (float) $restaurant->longitude,
                $customerLat,
                $customerLng
            );
        }

        return [
            'id' => $restaurant->id,
            'branch_id' => $restaurant->branch_id,
            'branch' => $restaurant->branch ? [
                'id' => $restaurant->branch->id,
                'name' => $restaurant->branch->name,
                'code' => $restaurant->branch->code,
                'city' => $restaurant->branch->city,
                'state' => $restaurant->branch->state,
                'status' => $restaurant->branch->status,
            ] : null,
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'description' => $restaurant->description,
            'email' => $restaurant->email,
            'phone' => $restaurant->phone,
            'address' => $restaurant->address,
            'city' => $restaurant->city,
            'state' => $restaurant->state,
            'pincode' => $restaurant->pincode,
            'latitude' => $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
            'longitude' => $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
            'cuisine' => $cuisineNames,
            'cuisine_ids' => $cuisineIds,
            'cuisine_text' => implode(', ', $cuisineNames),
            'is_open' => $restaurant->isOpenNow(),
            'manual_is_open' => (bool) $restaurant->is_open,
            'is_open_now' => $restaurant->isOpenNow(),
            'is_pure_veg' => (bool) $restaurant->is_pure_veg,
            'min_order_amount' => (float) ($restaurant->min_order_amount ?? 0),
            'amount_for_one' => null,
            'delivery_fee' => $distance !== null
                ? round((float) DeliveryChargeSetting::getDeliveryCharge($distance), 2)
                : (float) ($restaurant->delivery_fee ?? 0),
            'delivery_time' => (int) ($restaurant->delivery_time ?? 30),
            'eta_minutes' => null,
            'eta_range' => null,
            'restaurant_type' => $restaurant->restaurant_type ?? 'delivery',
            'accepts_delivery' => $restaurant->acceptsService('delivery'),
            'accepts_dining' => $restaurant->acceptsService('dining'),
            'accepts_takeaway' => $restaurant->acceptsService('takeaway'),
            'dining_charge' => (float) ($restaurant->dining_charge ?? 0),
            'rating' => (int) ($restaurant->total_ratings ?? 0) >= 3
                ? (float) ($restaurant->rating ?? 0)
                : null,
            'total_ratings' => (int) ($restaurant->total_ratings ?? 0),
            'banner_image' => \App\Services\MediaStorage::url($restaurant->banner_image),
            'logo_image' => \App\Services\MediaStorage::url($restaurant->logo_image),
            'distance' => $distance !== null ? round((float) $distance, 2) : null,
            'matched_item_names' => $matchedItemNames,
            'matched_menu_items' => $matchedMenuItems,
            'weekly_timings' => $restaurant->weekly_timings,
            'is_featured' => (bool) ($restaurant->is_featured ?? false),
            'orders_count' => 0,
            'created_at' => optional($restaurant->created_at)->toIso8601String(),
        ];
    }

    private function formatSearchMenuItemPayload(MenuItem $item, Restaurant $restaurant): array
    {
        $images = $this->resolveMenuItemImages($item->images ?? []);

        return [
            'id' => $item->id,
            'restaurant_id' => $item->restaurant_id,
            'category_id' => $item->category_id,
            'cuisine_id' => $item->cuisine_id,
            'name' => $item->name,
            'description' => $item->description,
            'price' => (float) $item->price,
            'discounted_price' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
            'images' => $images,
            'image' => $images[0] ?? null,
            'image_url' => $images[0] ?? null,
            'is_veg' => (bool) $item->is_veg,
            'food_type' => $item->food_type,
            'diet_label' => $item->diet_label,
            'is_available' => (bool) $item->is_available,
            'preparation_time' => $item->preparation_time,
            'rating' => $item->rating,
            'total_orders' => (int) ($item->total_orders ?? 0),
            'category_name' => $item->category?->name,
            'cuisine_name' => $item->cuisine?->name,
            'is_recommended' => (bool) ($item->is_recommended ?? false),
            'is_bestseller' => (bool) ($item->is_bestseller ?? false),
            'is_new' => (bool) ($item->is_new ?? false),
            'is_spicy' => (bool) ($item->is_spicy ?? false),
            'is_combo' => (bool) ($item->is_combo ?? false),
            'created_at' => optional($item->created_at)->toIso8601String(),
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'logo_image' => \App\Services\MediaStorage::url($restaurant->logo_image),
                'banner_image' => \App\Services\MediaStorage::url($restaurant->banner_image),
                'is_open' => $restaurant->isOpenNow(),
                'manual_is_open' => (bool) $restaurant->is_open,
                'is_open_now' => $restaurant->isOpenNow(),
                'rating' => (float) ($restaurant->rating ?? 0),
                'total_ratings' => (int) ($restaurant->total_ratings ?? 0),
                'cuisine' => $this->resolveCuisineNamesForPayload($restaurant->cuisine ?? []),
                'cuisine_ids' => $this->resolveCuisineIdsForPayload($restaurant->cuisine ?? []),
                'cuisine_text' => implode(', ', $this->resolveCuisineNamesForPayload($restaurant->cuisine ?? [])),
                'delivery_fee' => (float) ($restaurant->delivery_fee ?? 0),
                'delivery_time' => (int) ($restaurant->delivery_time ?? 30),
                'restaurant_type' => $restaurant->restaurant_type ?? 'delivery',
                'created_at' => optional($restaurant->created_at)->toIso8601String(),
            ],
        ];
    }

    private function resolveMenuItemImages($images): array
    {
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [$images];
        }

        if (! is_array($images)) {
            return [];
        }

        return collect($images)
            ->map(fn ($image) => \App\Services\MediaStorage::url((string) $image))
            ->filter()
            ->values()
            ->all();
    }

    private function augmentRestaurantResource(
        Restaurant $restaurant,
        ?float $customerLat = null,
        ?float $customerLng = null
    ): array {
        $resource = (new RestaurantResource($restaurant))->resolve();
        $eta = app(GoogleMapsEtaService::class)->estimateDelivery(
            $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
            $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
            $customerLat,
            $customerLng,
            (int) ($restaurant->order_lead_time ?? 20)
        );

        if (($eta['eta_minutes'] ?? null) !== null) {
            $resource['delivery_time'] = $eta['eta_minutes'];
        }

        $resource['eta_minutes'] = $eta['eta_minutes'];
        $resource['eta_range'] = $eta['eta_range'];
        $resource['travel_minutes'] = $eta['traffic_travel_minutes'];
        $resource['travel_distance_km'] = $eta['travel_distance_km'];
        $resource['preparation_minutes'] = $eta['preparation_minutes'];
        $resource['eta_source'] = $eta['source'];
        if ($customerLat !== null && $customerLng !== null) {
            $distance = $eta['travel_distance_km'];
            if ($distance === null &&
                $restaurant->latitude !== null &&
                $restaurant->longitude !== null) {
                $distance = $this->calculateDistance(
                    (float) $restaurant->latitude,
                    (float) $restaurant->longitude,
                    $customerLat,
                    $customerLng
                );
            }
            $resource['delivery_fee'] = round(
                (float) DeliveryChargeSetting::getDeliveryCharge($distance),
                2
            );
        }

        return $resource;
    }

    private function similarRestaurantsFor(Restaurant $restaurant)
    {
        $restaurantArea = $this->deliveryAreaForRestaurant($restaurant);
        $cuisineNames = collect($this->resolveCuisineNamesForPayload($restaurant->cuisine ?? []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();

        $cuisineIds = collect($this->resolveCuisineIdsForPayload($restaurant->cuisine ?? []))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($cuisineNames->isEmpty() && $cuisineIds->isEmpty()) {
            $menuCuisineIds = $restaurant->menuItems()
                ->whereNotNull('cuisine_id')
                ->distinct()
                ->pluck('cuisine_id')
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values();

            $cuisineIds = $menuCuisineIds;
            if ($menuCuisineIds->isNotEmpty()) {
                $cuisineNames = Cuisine::whereIn('id', $menuCuisineIds)
                    ->pluck('name')
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values();
            }
        }

        $query = Restaurant::query()
            ->whereKeyNot($restaurant->id)
            ->where('is_verified', true);

        if ($cuisineNames->isNotEmpty() || $cuisineIds->isNotEmpty()) {
            $query->where(function ($builder) use ($cuisineNames, $cuisineIds) {
                foreach ($cuisineNames as $name) {
                    $builder->orWhereJsonContains('cuisine', $name)
                        ->orWhere('cuisine', 'like', '%' . $name . '%');
                }

                foreach ($cuisineIds as $id) {
                    $builder->orWhereJsonContains('cuisine', $id)
                        ->orWhereJsonContains('cuisine', (string) $id)
                        ->orWhereHas('menuItems', fn ($menuQuery) => $menuQuery->where('cuisine_id', $id));
                }
            });
        } elseif ($restaurant->city) {
            $query->where('city', $restaurant->city);
        }

        $similar = $this->filterRestaurantsToSameDeliveryArea(
            $query
                ->orderByDesc('is_open')
                ->orderByDesc('is_featured')
                ->orderByDesc('rating')
                ->orderByDesc('total_ratings')
                ->limit($restaurantArea ? 30 : 6)
                ->get(),
            $restaurantArea
        )
            ->take(6)
            ->values();

        if ($similar->isNotEmpty() || ! $restaurant->city) {
            return $similar;
        }

        return $this->filterRestaurantsToSameDeliveryArea(
            Restaurant::query()
                ->whereKeyNot($restaurant->id)
                ->where('is_verified', true)
                ->where('city', $restaurant->city)
                ->orderByDesc('is_open')
                ->orderByDesc('is_featured')
                ->orderByDesc('rating')
                ->orderByDesc('total_ratings')
                ->limit($restaurantArea ? 30 : 6)
                ->get(),
            $restaurantArea
        )
            ->take(6)
            ->values();
    }

    private function deliveryAreaForRestaurant(Restaurant $restaurant): ?DeliveryArea
    {
        if ($restaurant->latitude === null || $restaurant->longitude === null) {
            return null;
        }

        return DeliveryArea::active()
            ->get()
            ->first(fn (DeliveryArea $area) => $area->containsPoint((float) $restaurant->latitude, (float) $restaurant->longitude));
    }

    private function filterRestaurantsToSameDeliveryArea($restaurants, ?DeliveryArea $area)
    {
        if (! $area) {
            return $restaurants;
        }

        return $restaurants->filter(function (Restaurant $candidate) use ($area) {
            if ($candidate->latitude === null || $candidate->longitude === null) {
                return false;
            }

            return $area->containsPoint((float) $candidate->latitude, (float) $candidate->longitude);
        });
    }

    private function getAccessibleRestaurants(?User $user)
    {
        if (!$user) {
            return collect();
        }

        if ($user->restaurants()->exists()) {
            return $user->restaurants()->orderBy('name')->get();
        }

        $staffRestaurant = $user->restaurantStaff()->with('restaurant')->first()?->restaurant;
        if ($staffRestaurant) {
            return collect([$staffRestaurant]);
        }

        if ($user->current_restaurant_id) {
            $currentRestaurant = Restaurant::find($user->current_restaurant_id);

            if ($currentRestaurant) {
                return collect([$currentRestaurant]);
            }
        }

        return collect();
    }

    private function resolveRestaurantScope(Request $request, ?User $user)
    {
        $restaurants = $this->getAccessibleRestaurants($user);
        $selectedId = $request->input('restaurant_id');

        if ($selectedId === null || $selectedId === '' || $selectedId === 'all') {
            return $restaurants;
        }

        $selectedId = (int) $selectedId;
        return $restaurants->where('id', $selectedId)->values();
    }

    private function resolveSingleRestaurantForFeature(Request $request, ?User $user, $restaurants): ?Restaurant
    {
        $selectedId = $request->input('restaurant_id');

        if ($selectedId && $selectedId !== 'all') {
            return $restaurants->firstWhere('id', (int) $selectedId);
        }

        $activeRestaurant = $this->getAuthenticatedRestaurant($user);
        if ($activeRestaurant && $restaurants->contains('id', $activeRestaurant->id)) {
            return $activeRestaurant;
        }

        return $restaurants->first();
    }

    private function formatRestaurantSwitcherItem(Restaurant $restaurant): array
    {
        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'city' => $restaurant->city,
            'is_open' => (bool) $restaurant->is_open,
            'restaurant_type' => $restaurant->restaurant_type ?? 'delivery',
            'accepts_delivery' => $restaurant->acceptsService('delivery'),
            'accepts_dining' => $restaurant->acceptsService('dining'),
            'accepts_takeaway' => $restaurant->acceptsService('takeaway'),
            'logo' => \App\Services\MediaStorage::url($restaurant->logo_image),
        ];
    }

    private function resolveCuisineNamesForPayload($cuisine): array
    {
        if (is_string($cuisine)) {
            $decoded = json_decode($cuisine, true);
            $cuisine = is_array($decoded) ? $decoded : explode(',', $cuisine);
        }

        $values = collect($cuisine ?? [])->filter(fn ($value) => $value !== null && $value !== '');
        $ids = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value)->values();
        $names = $values->reject(fn ($value) => is_numeric($value))->map(function ($value) {
            return is_array($value) ? ($value['name'] ?? null) : trim((string) $value);
        })->filter()->values();

        if ($ids->isNotEmpty()) {
            $names = $names->merge(Cuisine::whereIn('id', $ids)->pluck('name'));
        }

        return $names->unique()->values()->all();
    }

    private function resolveCuisineIdsForPayload($cuisine): array
    {
        if (is_string($cuisine)) {
            $decoded = json_decode($cuisine, true);
            $cuisine = is_array($decoded) ? $decoded : explode(',', $cuisine);
        }

        $values = collect($cuisine ?? [])->filter(fn ($value) => $value !== null && $value !== '');
        $ids = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value);
        $names = $values->reject(fn ($value) => is_numeric($value))->map(function ($value) {
            return is_array($value) ? ($value['name'] ?? null) : trim((string) $value);
        })->filter()->values();

        if ($names->isNotEmpty()) {
            $ids = $ids->merge(Cuisine::whereIn('name', $names)->pluck('id'));
        }

        return $ids->unique()->values()->all();
    }

    private function ensureRestaurantOwnerAccess(User $user)
    {
        if ($user->restaurants()->exists()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Only the restaurant owner can manage staff accounts.'
        ], 403);
    }

    private function ensureRestaurantPermission(User $user, string $feature)
    {
        if ($user->restaurants()->exists()) {
            return null;
        }

        $featurePermissions = [
            'orders' => ['manage_orders', 'view_orders'],
            'menu' => ['manage_menu', 'view_menu_items'],
            'reports' => ['view_reports'],
        ];

        foreach ($featurePermissions[$feature] ?? [] as $permission) {
            if ($user->can($permission)) {
                return null;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access this section.'
        ], 403);
    }

    private function mapStaffPermissionsToRbac(array $permissions): array
    {
        $mapped = ['view_dashboard', 'view_restaurants', 'edit_restaurants'];

        if (in_array('orders', $permissions, true)) {
            $mapped = array_merge($mapped, [
                'manage_orders',
                'view_orders',
                'update_order_status',
                'view_order_details',
            ]);
        }

        if (in_array('menu', $permissions, true)) {
            $mapped = array_merge($mapped, [
                'manage_menu',
                'view_menu_items',
                'create_menu_items',
                'edit_menu_items',
                'delete_menu_items',
                'manage_categories',
                'view_categories',
                'create_categories',
                'edit_categories',
                'delete_categories',
            ]);
        }

        if (in_array('reports', $permissions, true)) {
            $mapped = array_merge($mapped, [
                'view_reports',
                'view_payouts',
            ]);
        }

        return array_values(array_unique($mapped));
    }

    private function ensureStaffAccessControlSeeded(array $permissions = []): Role
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('restaurant_staff', 'web');
        $requiredPermissions = $this->mapStaffPermissionsToRbac($permissions);

        foreach ($requiredPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if (!empty($requiredPermissions)) {
            $role->givePermissionTo($requiredPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $role;
    }

    private function generateTemporaryPassword(): string
    {
        return Str::upper(Str::random(4)) . random_int(1000, 9999);
    }

    private function syncStaffUserAccount(RestaurantStaff $staff, Restaurant $restaurant, array $attributes): void
    {
        $staffUser = $staff->user;
        $permissions = $attributes['permissions'] ?? ($staff->permissions ?? []);
        $staffRole = $this->ensureStaffAccessControlSeeded(is_array($permissions) ? $permissions : []);

        if (!$staffUser) {
            if (empty($staff->email) || empty($staff->phone)) {
                throw new \RuntimeException('Staff account requires both email and phone number.');
            }

            $staffUser = User::create([
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'password' => Hash::make($this->generateTemporaryPassword()),
                'is_active' => $staff->is_active,
                'current_restaurant_id' => $restaurant->id,
            ]);
            $staffUser->syncRoles([$staffRole]);
            $staff->update(['user_id' => $staffUser->id]);
        }

        $staffUser->update([
            'name' => $attributes['name'] ?? $staff->name,
            'email' => $attributes['email'] ?? $staff->email,
            'phone' => $attributes['phone'] ?? $staff->phone,
            'is_active' => $attributes['is_active'] ?? $staff->is_active,
            'current_restaurant_id' => $restaurant->id,
        ]);

        if (!empty($attributes['password'])) {
            $staffUser->update([
                'password' => Hash::make($attributes['password']),
            ]);
        }

        if (array_key_exists('permissions', $attributes)) {
            $staffUser->syncPermissions($this->mapStaffPermissionsToRbac($attributes['permissions'] ?? []));
        }
    }

    private function formatStaffForApi(RestaurantStaff $staff): array
    {
        $permissions = $staff->permissions ?? [];
        $user = $staff->user;

        return [
            'id' => $staff->id,
            'user_id' => $staff->user_id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'role' => $staff->role,
            'shift' => $staff->shift,
            'salary' => $staff->salary !== null ? (float) $staff->salary : null,
            'permissions' => is_array($permissions) ? array_values($permissions) : [],
            'is_active' => (bool) $staff->is_active,
            'has_account' => (bool) $staff->user_id,
            'account_role' => $user?->roles?->first()?->name,
            'created_at' => optional($staff->created_at)?->toIso8601String(),
            'updated_at' => optional($staff->updated_at)?->toIso8601String(),
        ];
    }

    private function setOrderColumnIfExists(Order $order, string $column, $value): void
    {
        static $columns = null;

        if ($columns === null) {
            $columns = array_flip(Schema::getColumnListing('orders'));
        }

        if (isset($columns[$column])) {
            $order->{$column} = $value;
        }
    }
}
