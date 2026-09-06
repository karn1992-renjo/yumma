<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantMenuController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderChatController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverRestaurantOnboardingController;
use App\Http\Controllers\Api\DiningController;
use App\Http\Controllers\Api\RestaurantDiningController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\FlashResaleController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\AdCampaignController;
use App\Http\Controllers\Api\AdWalletController;
use App\Http\Controllers\Api\AdTrackingController;
use App\Http\Controllers\Api\AiToolController;
use App\Http\Controllers\Api\AiSessionTokenController;
use App\Http\Controllers\Api\VoiceAiConfigController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ScratchCardController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\RecaptchaController;
use App\Http\Controllers\Api\PartnerApplicationController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\DirectChatController;
use App\Http\Controllers\Api\Restaurant\RestaurantAssistantController;
use App\Http\Controllers\Api\Restaurant\MenuAnalyticsController;
use App\Http\Controllers\Api\Restaurant\RestaurantInvoicesController;
use App\Http\Controllers\Api\Restaurant\NotificationTestController;
use App\Http\Resources\RestaurantResource;
use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\Restaurant;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/auth/social-login', [AuthController::class, 'loginWithSocial'])->middleware('throttle:20,1');
Route::post('/auth/login-with-phone', [AuthController::class, 'loginWithPhone'])->middleware('throttle:20,1');
Route::post('/auth/phone/verify-firebase', [AuthController::class, 'verifyFirebasePhone'])->middleware('throttle:20,1');
Route::match(['get', 'post'], '/auth/phone/status', [AuthController::class, 'phoneStatus'])->middleware('throttle:60,1');
Route::match(['get', 'post'], '/auth/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:10,1');
Route::post('/forgot-password/reset-by-phone', [AuthController::class, 'resetPasswordByPhone'])->middleware('throttle:20,1');
Route::post('/partner-applications', [PartnerApplicationController::class, 'submit'])->middleware('throttle:3,1');
Route::get('/partner-applications/{applicationNumber}', [PartnerApplicationController::class, 'status'])->middleware('throttle:10,1');

// Realtime field-level document verification (used while filling the driver/restaurant registration form)
Route::post('/verification/gstin', [VerificationController::class, 'gstin'])->middleware('throttle:12,1');
Route::post('/verification/pan', [VerificationController::class, 'pan'])->middleware('throttle:12,1');
Route::post('/verification/vehicle-rc', [VerificationController::class, 'vehicleRc'])->middleware('throttle:12,1');
Route::post('/verification/driving-license', [VerificationController::class, 'drivingLicense'])->middleware('throttle:12,1');
Route::post('/verification/pan-document', [VerificationController::class, 'panDocument'])->middleware('throttle:6,1');
Route::post('/verification/aadhaar-document', [VerificationController::class, 'aadhaarDocument'])->middleware('throttle:6,1');
Route::get('/delivery-areas/active', function () {
    return response()->json([
        'success' => true,
        'data' => DeliveryArea::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'area_type',
                'latitude',
                'longitude',
                'radius_km',
                'polygon_coordinates',
                'max_daily_bookings',
            ]),
    ]);
})->middleware('throttle:20,1');

// reCAPTCHA
Route::post('/recaptcha/validate', [RecaptchaController::class, 'validateRecaptcha']);
Route::post('/recaptcha/validate-otp', [RecaptchaController::class, 'validateOtpWithRecaptcha']);

// Refund Policy (Public)
Route::get('/refund-policy', [OrderController::class, 'getRefundPolicy']);
Route::get('/home/sections', [ContentController::class, 'homeSections']);
Route::get('/banners', [ContentController::class, 'banners']);
Route::get('/banners/{type}', [ContentController::class, 'bannersByType']);
Route::get('/cuisines/popular', [ContentController::class, 'popularCuisines']);
Route::get('/offers/active', [ContentController::class, 'activeOffers']);
Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/{promotion}', [PromotionController::class, 'show'])->whereNumber('promotion');
Route::get('/app/branding', [AuthController::class, 'branding']);
Route::get('/content/legal', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    $legalText = static function (string $key, string $fallback) use ($settings): string {
        $value = trim((string) ($settings[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    };
    $contactEmail = trim((string) ($settings['legal_contact_email'] ?? ($settings['contact_email'] ?? '')));

    return response()->json([
        'success' => true,
        'data' => [
            'terms' => $legalText('legal_terms', 'Use of this platform is subject to account, order, payment, cancellation and support policies.'),
            'privacy' => $legalText('legal_privacy', 'We process customer, restaurant, driver, location and order data to operate delivery and support workflows.'),
            'refund' => $legalText('legal_refund', 'Refund eligibility depends on payment status, restaurant acceptance, delivery progress and support review.'),
            'account_deletion' => $legalText('legal_account_deletion', 'You can request account deletion from within the app or by emailing support. We remove personal data within 30 days, except records we must retain for tax, fraud-prevention and legal-compliance purposes.'),
            'contact_email' => $contactEmail !== '' ? $contactEmail : 'support@foodflow.com',
        ],
    ]);
});
// Public Restaurant Discovery Routes
Route::get('/restaurants/nearby', [RestaurantController::class, 'nearby']);
Route::get('/restaurants/search', [RestaurantController::class, 'search']);
Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
Route::get('/restaurants/{restaurantId}/menu', [MenuController::class, 'index']);
Route::get('/restaurants/{restaurantId}/menu/search', [MenuController::class, 'search']);
Route::get('/restaurants/{restaurantId}/menu/{itemId}', [MenuController::class, 'show']);
Route::get('/v1/search', [SearchController::class, 'index'])->middleware('throttle:60,1');
Route::get('/v1/search/suggestions', [SearchController::class, 'suggestions'])->middleware('throttle:120,1');
Route::get('/v1/search/trending', [SearchController::class, 'trending'])->middleware('throttle:60,1');
Route::post('/v1/search/click', [SearchController::class, 'trackClick'])->middleware('throttle:120,1');

Route::post('/ai/tools/{tool}', AiToolController::class)->middleware('throttle:120,1');
Route::get('/ai/status', [VoiceAiConfigController::class, 'status'])->middleware('throttle:30,1');
Route::get('/ai/settings', [VoiceAiConfigController::class, 'internal'])->middleware('throttle:60,1');
Route::post('/ai/usage', [VoiceAiConfigController::class, 'usage'])->middleware('throttle:120,1');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ai/config', [VoiceAiConfigController::class, 'public'])->middleware('throttle:30,1');
    Route::post('/ai/session-token', AiSessionTokenController::class)->middleware('throttle:30,1');

    Route::post('/broadcasting/auth', function (Request $request) {
        $validated = $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string'],
        ]);

        $user = $request->user();
        $channelName = $validated['channel_name'];
        $isAuthorized = false;

        if ($channelName === 'private-admin.gig-operations') {
            $isAuthorized = $user->hasRole('admin') || $user->hasRole('super_admin');
        } elseif ($channelName === 'private-admin.ai-activity') {
            $isAuthorized = $user->hasRole('admin') || $user->hasRole('super_admin');
        } elseif (preg_match('/^private-order\.(\d+)$/', $channelName, $matches)) {
            $order = \App\Models\Order::find((int) $matches[1]);
            $isAuthorized = $order
                && (
                    (int) $order->customer_id === (int) $user->id
                    || ((int) $order->driver_id === (int) $user->id && $user->hasRole('delivery_partner'))
                    || $user->restaurants()->whereKey($order->restaurant_id)->exists()
                );
        } elseif (preg_match('/^private-restaurant\.(\d+)$/', $channelName, $matches)) {
            $restaurantId = (int) $matches[1];
            $isAuthorized = (int) $user->current_restaurant_id === $restaurantId
                || $user->restaurants()->whereKey($restaurantId)->exists();
        } elseif (preg_match('/^private-driver\.(\d+)$/', $channelName, $matches)) {
            $driverId = (int) $matches[1];
            $isAuthorized = (int) $user->id === $driverId
                && $user->hasRole('delivery_partner');
        } elseif (preg_match('/^private-user\.(\d+)$/', $channelName, $matches)) {
            $isAuthorized = (int) $user->id === (int) $matches[1];
        }

        abort_unless($isAuthorized, 403, 'Not authorized for this channel.');

        $key = Config::get('broadcasting.connections.pusher.key');
        $secret = Config::get('broadcasting.connections.pusher.secret');

        abort_unless($key && $secret, 500, 'Pusher credentials are not configured.');

        $signature = hash_hmac(
            'sha256',
            $validated['socket_id'] . ':' . $channelName,
            $secret
        );

        return response()->json([
            'auth' => $key . ':' . $signature,
        ]);
    });

    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/user/fcm-token', [AuthController::class, 'registerFcmToken']);
    Route::match(['put', 'post'], '/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
    Route::get('/v1/search/history', [SearchController::class, 'history']);
    Route::delete('/v1/search/history', [SearchController::class, 'clearHistory']);
    Route::delete('/v1/search/history/{id}', [SearchController::class, 'clearHistory']);
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/scratch-cards', [ScratchCardController::class, 'index']);
    Route::post('/scratch-cards/{scratchCard}/view', [ScratchCardController::class, 'view']);
    Route::post('/scratch-cards/{scratchCard}/reveal', [ScratchCardController::class, 'reveal']);
    Route::get('/rewards/history', [ScratchCardController::class, 'rewardHistory']);
    Route::get('/rewards/coupons', [ScratchCardController::class, 'generatedCoupons']);
    Route::get('/rewards/points', [ScratchCardController::class, 'rewardPoints']);
    Route::post('/rewards/points/redeem', [ScratchCardController::class, 'redeemRewardPoints']);
    Route::get('/referrals/summary', [ReferralController::class, 'summary']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::delete('/notifications', [NotificationController::class, 'clear']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    // POST aliases -- some hosts (LiteSpeed/cPanel) block DELETE at the web-server level.
    Route::post('/notifications/clear', [NotificationController::class, 'clear']);
    Route::post('/notifications/{id}/delete', [NotificationController::class, 'destroy']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
    Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('/wallet/top-up', [WalletController::class, 'topUp']);
    Route::post('/wallet/top-up/verify', [WalletController::class, 'verifyTopUp']);
    Route::post('/wallet/gift-card/redeem', [WalletController::class, 'redeemGiftCard']);
    Route::prefix('support')->group(function () {
        Route::get('/conversations', [SupportController::class, 'index']);
        Route::post('/conversations', [SupportController::class, 'store']);
        Route::get('/conversations/{id}', [SupportController::class, 'show']);
        Route::post('/conversations/{id}/messages', [SupportController::class, 'sendMessage']);
        Route::post('/conversations/{id}/escalate', [SupportController::class, 'escalate']);
        Route::post('/conversations/{id}/read', [SupportController::class, 'markRead']);
        Route::post('/conversations/{id}/csat', [SupportController::class, 'submitCsat']);
    });
    Route::prefix('direct-chat')->group(function () {
        Route::get('/conversations', [DirectChatController::class, 'index']);
        Route::get('/users', [DirectChatController::class, 'searchUsers']);
        Route::post('/conversations', [DirectChatController::class, 'start']);
        Route::get('/conversations/{conversation}', [DirectChatController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [DirectChatController::class, 'store']);
        Route::post('/conversations/{conversation}/read', [DirectChatController::class, 'markRead']);
    });

    // Menu reads are public above for customer discovery screens.
    Route::get('/restaurants/{restaurant}/promos', function (Request $request, Restaurant $restaurant, \App\Services\PromotionEngineService $engine) {
        return response()->json([
            'success' => true,
            'data' => $engine->listForCheckout([
                'restaurant_id' => $restaurant->id,
                'user_id' => $request->user()?->id,
                'platform' => 'customer_app',
            ]),
        ]);
    });
    Route::get('/favorites/restaurants', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => RestaurantResource::collection($request->user()->favoriteRestaurants()->get()),
        ]);
    });
    Route::post('/favorites/restaurants/{restaurant}', function (Request $request, Restaurant $restaurant) {
        $request->user()->favoriteRestaurants()->syncWithoutDetaching([$restaurant->id]);
        return response()->json(['success' => true, 'message' => 'Restaurant saved.']);
    });
    Route::post('/favorites/restaurants/{restaurant}/remove', function (Request $request, Restaurant $restaurant) {
        $request->user()->favoriteRestaurants()->detach($restaurant->id);
        return response()->json(['success' => true, 'message' => 'Restaurant removed.']);
    });
    Route::delete('/favorites/restaurants/{restaurant}', function (Request $request, Restaurant $restaurant) {
        $request->user()->favoriteRestaurants()->detach($restaurant->id);
        return response()->json(['success' => true, 'message' => 'Restaurant removed.']);
    });

    // Address APIs
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::post('/addresses/{id}/delete', [AddressController::class, 'destroy']);
    Route::post('/addresses/default/{id}', [AddressController::class, 'setDefault']);

    // Restaurant Owner APIs
    Route::prefix('restaurant')->middleware('role:restaurant_owner,restaurant_staff')->group(function () {
        // Dashboard & Status
        Route::get('/restaurants', [RestaurantController::class, 'myRestaurants']);
        Route::get('/dashboard', [RestaurantController::class, 'dashboard']);
        Route::get('/stats', [RestaurantController::class, 'getStats']);
        Route::get('/reviews', [RestaurantController::class, 'getReviews'])->middleware('restaurant.permission:view_reports');
        Route::get('/statements', [RestaurantController::class, 'statements'])->middleware('restaurant.permission:view_reports,view_dashboard');
        Route::get('/analytics/compare', [RestaurantController::class, 'getAnalyticsCompare'])->middleware('restaurant.permission:view_reports');

        // --- BATCH 4f: AI assistant, menu analytics, invoices, notification test ---
        Route::get('/assistant/status',  [RestaurantAssistantController::class, 'status']);
        Route::get('/assistant/history', [RestaurantAssistantController::class, 'history'])
            ->middleware('restaurant.permission:view_dashboard');
        Route::get('/assistant/conversations', [RestaurantAssistantController::class, 'conversations'])
            ->middleware('restaurant.permission:view_dashboard');
        Route::post('/assistant/message', [RestaurantAssistantController::class, 'message'])
            ->middleware(['restaurant.permission:view_dashboard', 'throttle:20,1']);
        Route::post('/assistant/action', [RestaurantAssistantController::class, 'action'])
            ->middleware(['restaurant.permission:view_dashboard', 'throttle:30,1']);

        Route::get('/menu/analytics', [MenuAnalyticsController::class, 'index'])
            ->middleware('restaurant.permission:view_menu_items,manage_menu');

        Route::get('/invoices', [RestaurantInvoicesController::class, 'index'])
            ->middleware('restaurant.permission:view_reports,view_dashboard');

        Route::post('/notifications/test', [NotificationTestController::class, 'send'])
            ->middleware(['restaurant.permission:view_dashboard', 'throttle:6,1']);
        // --- end BATCH 4f ---
        Route::get('/complaints', [RestaurantController::class, 'getComplaints'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/toggle-status', [RestaurantController::class, 'toggleStatus'])
            ->middleware('restaurant.permission:view_dashboard');
        
        // Orders Management
        Route::get('/orders', [RestaurantController::class, 'getOrders'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::get('/orders/{id}', [RestaurantController::class, 'getOrderDetails'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/orders/{id}/call-driver', [RestaurantController::class, 'callDriver'])->middleware(['restaurant.permission:view_orders,manage_orders', 'throttle:10,1']);
        Route::get('/orders/{orderId}/chat', [OrderChatController::class, 'index'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/orders/{orderId}/chat', [OrderChatController::class, 'store'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/orders/{orderId}/chat/read', [OrderChatController::class, 'markRead'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/orders/{orderId}/chat/typing', [OrderChatController::class, 'typing'])->middleware('restaurant.permission:view_orders,manage_orders');
        Route::post('/orders/{id}/accept', [RestaurantController::class, 'acceptOrder'])->middleware('restaurant.permission:manage_orders,update_order_status');
        Route::post('/orders/{id}/reject', [RestaurantController::class, 'rejectOrder'])->middleware('restaurant.permission:manage_orders,update_order_status');
        Route::post('/orders/{id}/out-of-stock', [RestaurantController::class, 'markOrderItemsOutOfStock'])->middleware('restaurant.permission:manage_orders,update_order_status');
        Route::post('/orders/{id}/status', [RestaurantController::class, 'updateOrderStatus'])->middleware('restaurant.permission:manage_orders,update_order_status');
        Route::post('/orders/{id}/ready', [RestaurantController::class, 'markOrderReady'])->middleware('restaurant.permission:manage_orders,update_order_status');
        Route::post('/orders/{id}/takeaway/verify-otp', [RestaurantController::class, 'verifyTakeawayOtp'])->middleware('restaurant.permission:manage_orders,update_order_status');
        
        // Categories Management
        Route::get('/categories', [RestaurantController::class, 'getCategories'])->middleware('restaurant.permission:view_menu_items,manage_menu');
        Route::post('/categories', [RestaurantController::class, 'createCategory'])->middleware('restaurant.permission:manage_menu');
        Route::put('/categories/{id}', [RestaurantController::class, 'updateCategory'])->middleware('restaurant.permission:manage_menu');
        Route::delete('/categories/{id}', [RestaurantController::class, 'deleteCategory'])->middleware('restaurant.permission:manage_menu');
        
        // Menu Items Management
        Route::get('/menu', [RestaurantMenuController::class, 'index'])->middleware('restaurant.permission:view_menu_items,manage_menu');
        Route::get('/global-menu', [RestaurantMenuController::class, 'globalCatalog'])->middleware('restaurant.permission:view_menu_items,manage_menu');
        Route::get('/global-categories', [RestaurantMenuController::class, 'globalCategories'])->middleware('restaurant.permission:view_menu_items,manage_menu');
        Route::post('/menu', [RestaurantMenuController::class, 'store'])->middleware('restaurant.permission:manage_menu');
        Route::post('/menu/from-global', [RestaurantMenuController::class, 'importFromGlobal'])->middleware('restaurant.permission:manage_menu');
        Route::post('/menu/{id}', [RestaurantMenuController::class, 'update'])->middleware('restaurant.permission:manage_menu');
        Route::put('/menu/{id}', [RestaurantMenuController::class, 'update'])->middleware('restaurant.permission:manage_menu');
        Route::delete('/menu/{id}', [RestaurantMenuController::class, 'destroy'])->middleware('restaurant.permission:manage_menu');
        Route::post('/menu/{id}/delete', [RestaurantMenuController::class, 'destroy'])->middleware('restaurant.permission:manage_menu');
        Route::post('/menu/{id}/toggle', [RestaurantMenuController::class, 'toggleAvailability'])->middleware('restaurant.permission:manage_menu');
        
        // Settings
        Route::get('/info', [RestaurantController::class, 'getRestaurantInfo'])->middleware('restaurant.permission:view_dashboard');
        Route::post('/info', [RestaurantController::class, 'updateRestaurantInfo'])->middleware('role:restaurant_owner');
        Route::get('/settings', [RestaurantController::class, 'getSettings'])->middleware('restaurant.permission:view_dashboard');
        Route::post('/settings', [RestaurantController::class, 'updateSettings'])->middleware('role:restaurant_owner');
        Route::post('/location-change-request', [RestaurantController::class, 'requestLocationChange'])->middleware('role:restaurant_owner');

        // Staff Management
        Route::get('/staff', [RestaurantController::class, 'getStaff'])->middleware('role:restaurant_owner');
        Route::post('/staff', [RestaurantController::class, 'createStaff'])->middleware('role:restaurant_owner');
        Route::put('/staff/{id}', [RestaurantController::class, 'updateStaff'])->middleware('role:restaurant_owner');
        Route::post('/staff/{id}/toggle', [RestaurantController::class, 'toggleStaff'])->middleware('role:restaurant_owner');
        Route::delete('/staff/{id}', [RestaurantController::class, 'deleteStaff'])->middleware('role:restaurant_owner');

        // Promotions
        Route::get('/promos', [RestaurantController::class, 'getPromos'])->middleware('role:restaurant_owner');
        Route::post('/promos', [RestaurantController::class, 'createPromo'])->middleware('role:restaurant_owner');
        Route::get('/promos/options', [RestaurantController::class, 'promoOptions'])->middleware('role:restaurant_owner');
        Route::get('/promos/{id}', [RestaurantController::class, 'showPromo'])->middleware('role:restaurant_owner');
        Route::put('/promos/{id}', [RestaurantController::class, 'updatePromo'])->middleware('role:restaurant_owner');
        Route::post('/promos/{id}/toggle', [RestaurantController::class, 'togglePromo'])->middleware('role:restaurant_owner');
        Route::delete('/promos/{id}', [RestaurantController::class, 'deletePromo'])->middleware('role:restaurant_owner');

        // Ads (CPC sponsored placement)
        Route::get('/ads/wallet', [AdWalletController::class, 'show'])->middleware('role:restaurant_owner');
        Route::post('/ads/wallet/top-up', [AdWalletController::class, 'topUp'])->middleware('role:restaurant_owner');
        Route::post('/ads/wallet/top-up/verify', [AdWalletController::class, 'verifyTopUp'])->middleware('role:restaurant_owner');
        Route::get('/ads/performance', [AdCampaignController::class, 'performance'])->middleware('role:restaurant_owner');
        Route::get('/ads/campaigns', [AdCampaignController::class, 'index'])->middleware('role:restaurant_owner');
        Route::post('/ads/campaigns', [AdCampaignController::class, 'store'])->middleware('role:restaurant_owner');
        Route::get('/ads/campaigns/{id}', [AdCampaignController::class, 'show'])->middleware('role:restaurant_owner');
        Route::put('/ads/campaigns/{id}', [AdCampaignController::class, 'update'])->middleware('role:restaurant_owner');
        Route::post('/ads/campaigns/{id}/submit', [AdCampaignController::class, 'submit'])->middleware('role:restaurant_owner');
        Route::post('/ads/campaigns/{id}/pause', [AdCampaignController::class, 'pause'])->middleware('role:restaurant_owner');
        Route::post('/ads/campaigns/{id}/resume', [AdCampaignController::class, 'resume'])->middleware('role:restaurant_owner');

        // Printers
        Route::get('/printers', [RestaurantController::class, 'getPrinters'])->middleware('role:restaurant_owner');
        Route::post('/printers', [RestaurantController::class, 'createPrinter'])->middleware('role:restaurant_owner');
        Route::post('/printers/settings', [RestaurantController::class, 'updatePrinterSettings'])->middleware('role:restaurant_owner');
        Route::post('/printers/{id}/test', [RestaurantController::class, 'testPrinter'])->middleware('role:restaurant_owner');
        Route::post('/printers/{id}/test-invoice', [RestaurantController::class, 'testPrinterInvoice'])->middleware('role:restaurant_owner');
        Route::post('/printers/{id}/default', [RestaurantController::class, 'setDefaultPrinter'])->middleware('role:restaurant_owner');
        Route::delete('/printers/{id}', [RestaurantController::class, 'deletePrinter'])->middleware('role:restaurant_owner');
        
        // Dining Management
        Route::prefix('dining')->group(function () {
            Route::get('/bookings', [RestaurantDiningController::class, 'getDiningBookings'])->middleware('restaurant.permission:view_orders,manage_orders');
            Route::get('/bookings/{id}', [RestaurantDiningController::class, 'getBookingDetails'])->middleware('restaurant.permission:view_orders,manage_orders');
            Route::post('/bookings/{id}/confirm', [RestaurantDiningController::class, 'confirmBooking'])->middleware('restaurant.permission:manage_orders');
            Route::post('/bookings/{id}/reject', [RestaurantDiningController::class, 'rejectBooking'])->middleware('restaurant.permission:manage_orders');
            Route::post('/bookings/{id}/complete', [RestaurantDiningController::class, 'completeBooking'])->middleware('restaurant.permission:manage_orders');
            Route::get('/stats', [RestaurantDiningController::class, 'getDiningStats'])->middleware('restaurant.permission:view_reports');
            Route::get('/upcoming', [RestaurantDiningController::class, 'getUpcomingBookings'])->middleware('restaurant.permission:view_orders,manage_orders');
            Route::post('/settings', [RestaurantDiningController::class, 'updateDiningSettings'])->middleware('role:restaurant_owner');
        });
        
        // Analytics
        Route::get('/analytics', [RestaurantController::class, 'getAnalytics'])->middleware('restaurant.permission:view_reports');
    });
    
    // Orders (Customer)
    Route::post('/orders/summary', [OrderController::class, 'summary'])->middleware('throttle:20,1');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/cart/sync', [CartController::class, 'sync'])->middleware('throttle:30,1');
    Route::post('/coupons/validate', [PromotionController::class, 'validateCoupon']);
    Route::post('/promotions/calculate', [PromotionController::class, 'calculate']);
    Route::post('/promotions/coupon/validate', [PromotionController::class, 'validateCoupon']);
    Route::post('/promotions/apply-best', [PromotionController::class, 'applyBest']);
    Route::post('/promotions/remove', [PromotionController::class, 'remove']);
    Route::post('/promotions/preview', [PromotionController::class, 'preview']);
    Route::get('/promotions/preview', [PromotionController::class, 'preview']);
    Route::post('/promotions/coupons/generate', [PromotionController::class, 'generateCoupons']);
    Route::get('/promotions/coupons/{code}', [PromotionController::class, 'couponDetails']);
    Route::get('/promotions/analytics/summary', [PromotionController::class, 'analytics']);
    Route::get('/promotions/logs', [PromotionController::class, 'logs']);
    Route::get('/orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{orderId}/chat', [OrderChatController::class, 'index']);
    Route::post('/orders/{orderId}/chat', [OrderChatController::class, 'store']);
    Route::post('/orders/{orderId}/chat/read', [OrderChatController::class, 'markRead']);
    Route::post('/orders/{orderId}/chat/typing', [OrderChatController::class, 'typing']);
    Route::post('/orders/{id}/feedback', [OrderController::class, 'submitFeedback']);
    Route::post('/orders/{id}/notes', [OrderController::class, 'updateNotes'])->middleware('throttle:30,1');
    Route::post('/orders/{id}/tip', [OrderController::class, 'tip'])->middleware('throttle:20,1');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{id}/track', [OrderController::class, 'track']);
    Route::get('/orders/{id}/call-number', [OrderController::class, 'callNumber'])->middleware('throttle:20,1');
    Route::post('/orders/{id}/refund-request', [OrderController::class, 'requestRefund']);
    Route::post('/orders/{id}/pay', [OrderPaymentController::class, 'pay'])->middleware('throttle:20,1');
    Route::post('/orders/{id}/driver/payment-link', [OrderPaymentController::class, 'driverPaymentLink'])->middleware('throttle:20,1');
    Route::post('/orders/{id}/driver/cash', [OrderPaymentController::class, 'driverCash'])->middleware('throttle:20,1');
    Route::get('/orders/{id}/payment-status', [OrderPaymentController::class, 'status'])->middleware('throttle:60,1');
    
    // Payments
    Route::post('/payments/create', [PaymentController::class, 'createPayment']);
    Route::post('/payments/checkout/create', [PaymentController::class, 'createCheckoutPayment'])->middleware('throttle:20,1');
    Route::post('/payments/checkout/verify', [PaymentController::class, 'verifyCheckoutPayment'])->middleware('throttle:20,1');
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);
    Route::post('/payments/cancel', [PaymentController::class, 'cancelPayment']);
    
    // Dining
    Route::prefix('dining')->group(function () {
        Route::get('/celebration-types', [DiningController::class, 'getCelebrationTypes']);
        Route::get('/restaurants', [DiningController::class, 'getRestaurantsForDining']);
        Route::post('/book', [DiningController::class, 'bookTable']);
        Route::get('/my-bookings', [DiningController::class, 'getMyBookings']);
        Route::get('/bookings/{id}', [DiningController::class, 'getBookingDetails']);
        Route::post('/bookings/{id}/payment/create', [DiningController::class, 'createPayment']);
        Route::post('/bookings/{id}/payment/verify', [DiningController::class, 'verifyPayment']);
        Route::post('/bookings/{id}/review', [DiningController::class, 'submitReview']);
        Route::post('/cancel/{id}', [DiningController::class, 'cancelBooking']);
    });
    
    // Delivery OTP
    Route::prefix('delivery')->group(function () {
        Route::post('/verify-otp/{orderId}', [DeliveryController::class, 'verifyOtp'])->middleware('throttle:5,1');
        Route::post('/resend-otp/{orderId}', [DeliveryController::class, 'resendOtp'])->middleware('throttle:2,1');
    });
    
    // Returns
    Route::prefix('returns')->group(function () {
        Route::post('/request/{orderId}', [ReturnController::class, 'requestReturn']);
        Route::get('/status/{orderId}', [ReturnController::class, 'getReturnStatus']);
    });

    // Flash resale
    Route::post('/flash-resale/{orderId}/claim', [FlashResaleController::class, 'claim'])->middleware('throttle:5,1');


    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
    Route::post('/campaigns/{id}/track-click', [CampaignController::class, 'trackClick']);
    Route::post('/campaigns/{id}/track-impression', [CampaignController::class, 'trackImpression']);

    // Ad tracking (sponsored restaurant placement)
    Route::post('/ads/impressions', [AdTrackingController::class, 'trackImpression']);
    Route::post('/ads/clicks', [AdTrackingController::class, 'trackClick'])->middleware('throttle:30,1');
    
    // Driver specific routes
    Route::middleware('role:delivery_partner')->prefix('driver')->group(function () {
        Route::get('/restaurant-onboardings/summary', [DriverRestaurantOnboardingController::class, 'summary']);
        Route::get('/restaurant-onboardings', [DriverRestaurantOnboardingController::class, 'index']);
        Route::post('/restaurant-onboardings', [DriverRestaurantOnboardingController::class, 'store']);
        Route::get('/restaurant-onboardings/{restaurantOnboarding}', [DriverRestaurantOnboardingController::class, 'show']);
        Route::post('/restaurant-onboardings/{restaurantOnboarding}/draft', [DriverRestaurantOnboardingController::class, 'draft']);
        Route::post('/restaurant-onboardings/{restaurantOnboarding}/submit', [DriverRestaurantOnboardingController::class, 'submit']);
        Route::post('/restaurant-onboardings/{restaurantOnboarding}/owner-otp/send', [DriverRestaurantOnboardingController::class, 'sendOwnerOtp']);
        Route::post('/restaurant-onboardings/{restaurantOnboarding}/owner-otp/verify', [DriverRestaurantOnboardingController::class, 'verifyOwnerOtp']);
        Route::post('/location', [DriverController::class, 'updateLocation']);
        Route::get('/orders', [DriverController::class, 'getAssignedOrders']);
        Route::get('/orders/{orderId}', [DriverController::class, 'getOrderDetails']);
        Route::post('/orders/{orderId}/call', [DriverController::class, 'callParticipant'])->middleware('throttle:10,1');
        Route::get('/orders/{orderId}/chat', [OrderChatController::class, 'index']);
        Route::post('/orders/{orderId}/chat', [OrderChatController::class, 'store']);
        Route::post('/orders/{orderId}/chat/read', [OrderChatController::class, 'markRead']);
        Route::post('/orders/{orderId}/chat/typing', [OrderChatController::class, 'typing']);
        Route::post('/orders/{orderId}/accept', [DriverController::class, 'acceptOrder']);
        Route::post('/orders/{orderId}/reject', [DriverController::class, 'rejectOrder']);
        Route::post('/orders/{orderId}/status', [DriverController::class, 'updateOrderStatus']);
        Route::put('/orders/{orderId}/status', [DriverController::class, 'updateOrderStatus']);
        Route::post('/orders/{orderId}/arrived', [DriverController::class, 'markArrivedAtCustomer']);
        Route::post('/orders/{orderId}/report-delivery-failed', [DriverController::class, 'reportDeliveryFailed']);
        Route::post('/orders/{orderId}/confirm-food-returned', [DriverController::class, 'confirmFoodReturned']);
        Route::post('/orders/{id}/payment-link', [OrderPaymentController::class, 'driverPaymentLink'])->middleware('throttle:20,1');
        Route::post('/orders/{id}/cash', [OrderPaymentController::class, 'driverCash'])->middleware('throttle:20,1');
        Route::get('/gigs', [DriverController::class, 'getMyGigs']);
        Route::post('/gigs/{gigId}/book', [DriverController::class, 'bookGig']);
        Route::post('/gigs/{bookingId}/dispute', [DriverController::class, 'disputeGig']);
        Route::get('/earnings', [DriverController::class, 'getEarnings']);
        Route::get('/profile', [DriverController::class, 'profile']);
        Route::post('/profile', [DriverController::class, 'updateProfile']);
        Route::get('/stats', [DriverController::class, 'stats']);
        Route::get('/status', [DriverController::class, 'status']);
        Route::post('/toggle-status', [DriverController::class, 'toggleStatus']);
    });
});



// Inbound webhooks from the standalone Accounts / HRMS apps (HMAC-verified in the controller).
Route::post('/ingest/employee-user', [\App\Http\Controllers\Api\IngestController::class, 'employeeUser']);
