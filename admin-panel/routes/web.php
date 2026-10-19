<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PetpoojaConfigController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\TheatreController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentRecoveryController;
use App\Http\Controllers\Admin\RefundPolicyController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\CodManagementController;
use App\Http\Controllers\Admin\DeliveryAreaController;
use App\Http\Controllers\Admin\GigController;
use App\Http\Controllers\Branch\BranchFleetController;
use App\Http\Controllers\Branch\BranchGigController;
use App\Http\Controllers\Admin\CountryTaxController;
use App\Http\Controllers\Admin\FleetController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\PayoutSettingsController;
use App\Http\Controllers\Admin\RestaurantApprovalController;
use App\Http\Controllers\Admin\RestaurantOnboardingController;
use App\Http\Controllers\Admin\VendorBankController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\GiftCardController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\PromotionEngineController;
use App\Http\Controllers\Admin\AdsEngineController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PartnerApplicationController;
use App\Http\Controllers\Admin\AppDownloadSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AiControlCenterController;
use App\Http\Controllers\Admin\VoiceAiSettingsController;
use App\Http\Controllers\Admin\CuisineController;
use App\Http\Controllers\Admin\SplashCampaignController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\GlobalMenuCategoryController;
use App\Http\Controllers\Admin\MasterMenuItemController;
use App\Http\Controllers\Admin\ListedMenuController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\PushNotificationController;
use App\Http\Controllers\Admin\DeliveryChargeController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\OfflineReasonController;
use App\Http\Controllers\Admin\CancellationLimitController;
use App\Http\Controllers\Admin\CelebrationTypeController;
use App\Http\Controllers\Admin\DiningBookingController;
use App\Http\Controllers\Admin\WebVisitTrackController as AdminWebVisitTrackController;
use App\Http\Controllers\AppDownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Webhook\CashfreeWebhookController;
use App\Http\Controllers\Webhook\PaystackWebhookController;
use App\Http\Controllers\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\Webhook\OrderPaymentWebhookController;
use App\Http\Controllers\Webhook\PetpoojaWebhookController;
use App\Http\Controllers\WebVisitTrackController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\DirectChatController;
use App\Http\Controllers\Branch\BranchDashboardController;
use App\Http\Controllers\Theatre\TheatreDashboardController;
use App\Http\Controllers\Theatre\SeatOrderController;
use App\Http\Controllers\AppLinksController;
use App\Services\HomeSectionService;

// Public routes
Route::get('/', function (HomeSectionService $homeSectionService) {
    if (! File::exists(storage_path('app/installed.lock'))) {
        return redirect()->route('install.show');
    }

    return app(HomeController::class)->index($homeSectionService);
})->name('home');
Route::get('/terms', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    return view('legal', ['type' => 'terms', 'settings' => $settings]);
})->name('legal.terms');
Route::get('/privacy', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    return view('legal', ['type' => 'privacy', 'settings' => $settings]);
})->name('legal.privacy');
Route::get('/privacy-policy', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    return view('legal', ['type' => 'privacy', 'settings' => $settings]);
})->name('privacy-policy');
Route::get('/legal', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    return view('legal', ['type' => 'legal', 'settings' => $settings]);
})->name('legal.index');

// One printed QR per app -> device detected here -> Play Store / App Store.
// Settings -> App Download QR drives every link below.
Route::get('/app', [AppDownloadController::class, 'show'])->name('app.download');
Route::get('/app/qr.svg', [AppDownloadController::class, 'qrSvg'])->name('app.download.qr');
Route::get('/app/qr.png', [AppDownloadController::class, 'qrPng'])->name('app.download.qr-png');
Route::get('/app/{app}', [AppDownloadController::class, 'show'])
    ->where('app', 'customer|restaurant|driver')
    ->name('app.download.for');

Route::view('/about', 'about')->name('about');
Route::view('/careers', 'careers')->name('careers');

Route::middleware('auth')->prefix('direct-chat')->name('direct-chat.')->group(function () {
    Route::get('/conversations', [DirectChatController::class, 'index'])->name('conversations');
    Route::get('/users', [DirectChatController::class, 'searchUsers'])->name('users');
    Route::post('/conversations', [DirectChatController::class, 'start'])->name('start');
    Route::get('/conversations/{conversation}', [DirectChatController::class, 'show'])->name('show');
    Route::post('/conversations/{conversation}/messages', [DirectChatController::class, 'store'])->name('messages.store');
    Route::post('/conversations/{conversation}/read', [DirectChatController::class, 'markRead'])->name('read');
});
Route::view('/blog', 'blog')->name('blog');
Route::view('/help', 'help')->name('help');
Route::view('/contact', 'contact')->name('contact');
Route::view('/faqs', 'faqs')->name('faqs');

Route::get('/sitemap.xml', function () {
    $sitemapPath = public_path('sitemap.xml');

    if (! File::exists($sitemapPath)) {
        Artisan::call('sitemap:generate');
    }

    abort_unless(File::exists($sitemapPath), 404);

    return response()->file($sitemapPath, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->name('sitemap');

Route::get('/media/branding/{file}', function (string $file) {
    $path = 'branding/' . basename($file);

    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('file', '[^/]+')->name('media.branding');

Route::post('/set-location', function(Request $request) {
    session(['delivery_location' => $request->location]);
    session(['delivery_lat' => $request->lat]);
    session(['delivery_lng' => $request->lng]);
    return response()->json(['success' => true]);
})->name('set.location');

Route::post('/visitor-track', [WebVisitTrackController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('visitor-track.store');

// ==================== API ROUTES (Public) ====================
Route::prefix('api')->group(function () {
    // Categories and Collections
    Route::get('/categories', [HomeController::class, 'getCategories']);
    Route::get('/collections', [HomeController::class, 'getCollections']);
    Route::get('/popular-cities', [HomeController::class, 'getPopularCities']);

    // Restaurant Search
    Route::get('/restaurants/search', [HomeController::class, 'searchRestaurants']);
    Route::get('/restaurants/featured', [HomeController::class, 'getFeaturedRestaurants']);

    // Geolocation
    Route::get('/geocode', [HomeController::class, 'geocode']);
});

// ==================== RESTAURANT DETAIL PAGE ====================
Route::get('/restaurants/{slug}', [HomeController::class, 'showRestaurant'])->name('restaurant.show');
Route::get('/restaurants-in-{citySlug}', [HomeController::class, 'showCity'])->name('city.restaurants');

// Partner Registration Routes
Route::get('/partner/register', [PartnerController::class, 'showRegistrationForm'])->name('partner.register');
Route::post('/partner/register-submit', [PartnerController::class, 'submitRegistration'])->name('partner.register.submit');
Route::get('/partner/status/{applicationNumber}', [PartnerController::class, 'getApplicationStatus'])->name('partner.status');

Route::post('/webhooks/razorpay/payout', RazorpayWebhookController::class)->name('webhooks.razorpay.payout');
Route::post('/webhooks/razorpay/subscription', \App\Http\Controllers\Webhook\RazorpaySubscriptionWebhookController::class)->name('webhooks.razorpay.subscription');
Route::post('/webhooks/cashfree/subscription', \App\Http\Controllers\Webhook\CashfreeSubscriptionWebhookController::class)->name('webhooks.cashfree.subscription');
Route::post('/webhooks/stripe/payout', StripeWebhookController::class)->name('webhooks.stripe.payout');
Route::post('/webhook/{gateway}', OrderPaymentWebhookController::class)
    ->whereIn('gateway', ['razorpay', 'cashfree', 'stripe'])
    ->name('webhooks.order-payments');
Route::post('/webhooks/cashfree/payout', CashfreeWebhookController::class)->name('webhooks.cashfree.payout');

Route::middleware(['throttle:60,1'])->prefix('webhooks/petpooja')->name('webhooks.petpooja.')->group(function () {
    Route::post('/push-menu', [PetpoojaWebhookController::class, 'pushMenu'])->name('push-menu');
    Route::post('/order-callback/{order}', [PetpoojaWebhookController::class, 'orderCallback'])->name('order-callback');
    Route::post('/stock', [PetpoojaWebhookController::class, 'stock'])->name('stock');
    Route::post('/store-status', [PetpoojaWebhookController::class, 'getStoreStatus'])->name('store-status');
    Route::post('/store-status/update', [PetpoojaWebhookController::class, 'updateStoreStatus'])->name('store-status.update');
});
Route::post('/webhooks/paystack/payout', PaystackWebhookController::class)->name('webhooks.paystack.payout');
Route::post('/webhooks/exotel/call-status', \App\Http\Controllers\Webhook\ExotelWebhookController::class)->name('webhooks.exotel.call-status');

// Auth routes
require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install/license', [InstallController::class, 'verifyLicense'])->name('install.license');
Route::post('/install/database', [InstallController::class, 'setupDatabase'])->name('install.database');
Route::get('/install/complete', [InstallController::class, 'complete'])->name('install.complete');
});

Route::middleware(['auth'])->get('/dashboard', function () {
    $user = auth()->user();

    if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
        return redirect()->route('restaurant.dashboard');
    }

    if ($user->hasAnyRole(['super_admin', 'admin'])) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
        return redirect()->route('branch.dashboard');
    }

    if ($user->hasAnyRole(['theatre_owner', 'theatre_manager'])) {
        return redirect()->route('theatre.dashboard');
    }

    if ($user->hasRole('support')) {
        return redirect()->route('support.dashboard');
    }

    // Catch-all for a custom role made via admin.roles.* -- see LoginResponse
    // for why checking view_dashboard specifically makes this loop-proof.
    if ($user->can('view_dashboard')) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('home');
})->name('dashboard');

Route::middleware(['auth', 'role:branch_owner|branch_manager|branch_staff'])->prefix('branch-panel')->name('branch.')->group(function () {
    Route::get('/dashboard', [BranchDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [BranchDashboardController::class, 'orders'])->name('orders');
    Route::get('/orders/export', [BranchDashboardController::class, 'exportOrders'])->name('orders.export');
    Route::get('/orders/live', [BranchDashboardController::class, 'ordersLive'])->name('orders.live');
    Route::get('/orders/live-data', [BranchDashboardController::class, 'ordersLiveData'])->name('orders.live-data');
    Route::get('/orders/check-new', [BranchDashboardController::class, 'ordersCheckNew'])->name('orders.check-new');
    Route::get('/orders/notification-counts', [BranchDashboardController::class, 'ordersNotificationCounts'])->name('orders.notification-counts');
    Route::get('/orders/{order}', [BranchDashboardController::class, 'showOrder'])->name('orders.show');
    Route::put('/orders/{order}/status', [BranchDashboardController::class, 'updateOrderStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/verify-payment', [BranchDashboardController::class, 'verifyOrderPayment'])->name('orders.verify-payment');
    Route::post('/orders/{order}/assign-driver', [BranchDashboardController::class, 'assignOrderDriver'])->name('orders.assign-driver');
    Route::get('/orders/{order}/available-drivers', [BranchDashboardController::class, 'availableDriversForOrder'])->name('orders.available-drivers');
    Route::get('/restaurants/create', [BranchDashboardController::class, 'createRestaurant'])->name('restaurants.create');
    Route::post('/restaurants', [BranchDashboardController::class, 'storeRestaurant'])->name('restaurants.store');
    Route::get('/restaurants/{restaurant}', [BranchDashboardController::class, 'showRestaurant'])->name('restaurants.show');
    Route::get('/restaurants/{restaurant}/edit', [BranchDashboardController::class, 'editRestaurant'])->name('restaurants.edit');
    Route::put('/restaurants/{restaurant}', [BranchDashboardController::class, 'updateRestaurant'])->name('restaurants.update');
    Route::post('/restaurants/{restaurant}/approve', [BranchDashboardController::class, 'approveRestaurant'])->name('restaurants.approve');
    Route::post('/restaurants/{restaurant}/toggle-status', [BranchDashboardController::class, 'toggleRestaurantStatus'])->name('restaurants.toggle-status');
    Route::get('/restaurants', [BranchDashboardController::class, 'restaurants'])->name('restaurants');
    Route::get('/drivers/create', [BranchDashboardController::class, 'createDriver'])->name('drivers.create');
    Route::post('/drivers', [BranchDashboardController::class, 'storeDriver'])->name('drivers.store');
    Route::get('/drivers/{driver}/edit', [BranchDashboardController::class, 'editDriver'])->name('drivers.edit');
    Route::put('/drivers/{driver}', [BranchDashboardController::class, 'updateDriver'])->name('drivers.update');
    Route::post('/drivers/{driver}/toggle-status', [BranchDashboardController::class, 'toggleDriverStatus'])->name('drivers.toggle-status');
    Route::get('/drivers', [BranchDashboardController::class, 'drivers'])->name('drivers');
    Route::get('/cod', [BranchDashboardController::class, 'cod'])->name('cod');
    Route::post('/cod/settle', [BranchDashboardController::class, 'codSettle'])->name('cod.settle');
    Route::get('/cod/history', [BranchDashboardController::class, 'codHistory'])->name('cod.history');
    Route::get('/cod/export', [BranchDashboardController::class, 'codExport'])->name('cod.export');
    Route::get('/territories', [BranchDashboardController::class, 'zones'])->name('zones');
    Route::get('/wallet', [BranchDashboardController::class, 'wallet'])->name('wallet');
    Route::get('/wallet/export', [BranchDashboardController::class, 'exportWallet'])->name('wallet.export');
    Route::get('/delivery-charges', [BranchDashboardController::class, 'deliveryCharges'])->name('delivery-charges');
    Route::post('/wallet/withdrawals', [BranchDashboardController::class, 'requestWithdrawal'])->name('wallet.withdrawals.store');
    Route::get('/settlements', [BranchDashboardController::class, 'settlements'])->name('settlements');
    Route::post('/settlements', [BranchDashboardController::class, 'storeSettlement'])->name('settlements.store');
    Route::get('/tax-settlements', [BranchDashboardController::class, 'taxSettlements'])->name('tax-settlements');
    Route::get('/reports', [BranchDashboardController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [BranchDashboardController::class, 'exportReports'])->name('reports.export');
    Route::get('/reports/income', [BranchDashboardController::class, 'incomeDetails'])->name('reports.income');
    Route::get('/reports/income/export', [BranchDashboardController::class, 'exportIncomeDetails'])->name('reports.income.export');
    Route::get('/settings', [BranchDashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [BranchDashboardController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/staff', [BranchDashboardController::class, 'storeStaff'])->name('settings.staff.store');
    Route::put('/settings/staff/{staff}', [BranchDashboardController::class, 'updateStaff'])->name('settings.staff.update');
    Route::get('/tickets', [BranchDashboardController::class, 'tickets'])->name('tickets');
    Route::post('/tickets', [BranchDashboardController::class, 'storeTicket'])->name('tickets.store');
    Route::get('/support', [BranchDashboardController::class, 'support'])->name('support');
    Route::post('/support', [BranchDashboardController::class, 'supportStore'])->name('support.store');
    Route::get('/support/{conversation}', [BranchDashboardController::class, 'supportShow'])->name('support.show');
    Route::post('/support/{conversation}/reply', [BranchDashboardController::class, 'supportReply'])->name('support.reply');

    Route::prefix('fleet-map')->name('fleet.')->group(function () {
        Route::get('/', [BranchFleetController::class, 'dashboard'])->name('dashboard');
        Route::get('/markers', [BranchFleetController::class, 'markers'])->name('markers');
    });

    Route::prefix('fleet')->name('gigs.')->group(function () {
        Route::get('/', [BranchGigController::class, 'index'])->name('index');
        Route::get('/create', [BranchGigController::class, 'create'])->name('create');
        Route::post('/', [BranchGigController::class, 'store'])->name('store');
        Route::post('/bulk', [BranchGigController::class, 'bulkCreate'])->name('bulk-create');
        Route::get('/payout-approvals', [BranchGigController::class, 'payoutApprovals'])->name('payout-approvals');
        Route::post('/payout-approvals/{approval}', [BranchGigController::class, 'approvePayout'])->name('payout-approvals.action');
        Route::get('/{gig}/edit', [BranchGigController::class, 'edit'])->name('edit');
        Route::put('/{gig}', [BranchGigController::class, 'update'])->name('update');
        Route::delete('/{gig}', [BranchGigController::class, 'destroy'])->name('destroy');
        Route::post('/{gig}/status', [BranchGigController::class, 'updateStatus'])->name('update-status');
    });
});

// Theatre panel (swaad-only: movie-theatre in-seat food ordering)
Route::middleware(['auth', 'role:theatre_owner|theatre_manager'])->prefix('theatre-panel')->name('theatre.')->group(function () {
    Route::get('/dashboard', [TheatreDashboardController::class, 'index'])->name('dashboard');
    Route::get('/restaurants', [TheatreDashboardController::class, 'restaurants'])->name('restaurants');
    Route::get('/restaurants/create', [TheatreDashboardController::class, 'createRestaurant'])->name('restaurants.create');
    Route::post('/restaurants/create', [TheatreDashboardController::class, 'storeRestaurant'])->name('restaurants.store');
    Route::post('/restaurants/{restaurant}/unmap', [TheatreDashboardController::class, 'unmapRestaurant'])->name('restaurants.unmap');
    Route::get('/runners', [TheatreDashboardController::class, 'runners'])->name('runners');
    Route::post('/runners', [TheatreDashboardController::class, 'storeRunner'])->name('runners.store');
    Route::post('/runners/{runner}/remove', [TheatreDashboardController::class, 'removeRunner'])->name('runners.remove');
    Route::get('/screens', [TheatreDashboardController::class, 'screens'])->name('screens');
    Route::post('/screens', [TheatreDashboardController::class, 'storeScreen'])->name('screens.store');
    Route::get('/screens/{screen}/seat-map', [TheatreDashboardController::class, 'editSeatMap'])->name('screens.seat-map.edit');
    Route::put('/screens/{screen}/seat-map', [TheatreDashboardController::class, 'updateSeatMap'])->name('screens.seat-map.update');
    Route::get('/screens/{screen}/qr-stickers', [TheatreDashboardController::class, 'seatQrStickers'])->name('screens.qr-stickers');
    Route::get('/shows', [TheatreDashboardController::class, 'shows'])->name('shows');
    Route::post('/shows', [TheatreDashboardController::class, 'storeShow'])->name('shows.store');
    Route::post('/shows/{show}/toggle', [TheatreDashboardController::class, 'toggleShow'])->name('shows.toggle');
    Route::delete('/shows/{show}', [TheatreDashboardController::class, 'destroyShow'])->name('shows.destroy');
    Route::get('/orders', [TheatreDashboardController::class, 'orders'])->name('orders');
});

// Android App Links / iOS Universal Links verification files. Fetched by
// the operating system itself, so: no auth, no redirect, domain root.
// These are what let a scanned seat sticker open the app instead of the
// browser -- see config/applinks.php.
Route::get('/.well-known/assetlinks.json', [AppLinksController::class, 'assetLinks'])
    ->name('well-known.assetlinks');
Route::get('/.well-known/apple-app-site-association', [AppLinksController::class, 'appleAppSiteAssociation'])
    ->name('well-known.apple-app-site-association');

// Public, no-login web ordering reached by scanning the QR sticker on a
// cinema seat (swaad-only). Signature-gated rather than auth-gated: the
// whole point is that a customer mid-film can order without installing
// anything -- see Theatre\SeatOrderController.
Route::prefix('t/{theatre}/{screen}/{seat}/{sig}')->name('seat-order.')->group(function () {
    Route::get('/', [SeatOrderController::class, 'show'])->name('show');
    Route::get('/stalls', [SeatOrderController::class, 'stalls'])->name('stalls');
    Route::get('/shows', [SeatOrderController::class, 'shows'])->name('shows');
    Route::get('/stalls/{restaurant}/menu', [SeatOrderController::class, 'menu'])->name('menu');
    Route::post('/otp', [SeatOrderController::class, 'sendOtp'])->middleware('throttle:10,1')->name('otp.send');
    Route::post('/otp/verify', [SeatOrderController::class, 'verifyOtp'])->middleware('throttle:20,1')->name('otp.verify');
});

// Customer routes
Route::middleware(['auth'])->prefix('customer')->name('customer.')->group(function () {
    // Orders
    Route::get('/orders', [App\Http\Controllers\Customer\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [App\Http\Controllers\Customer\OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/track', [App\Http\Controllers\Customer\OrderController::class, 'track'])->name('orders.track');
    Route::post('/orders/{id}/reorder', [App\Http\Controllers\Customer\OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('/orders/{id}/cancel', [App\Http\Controllers\Customer\OrderController::class, 'cancel'])->name('orders.cancel');
    
    // Addresses
    Route::get('/addresses', [App\Http\Controllers\Customer\AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [App\Http\Controllers\Customer\AddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{id}', [App\Http\Controllers\Customer\AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{id}', [App\Http\Controllers\Customer\AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{id}/default', [App\Http\Controllers\Customer\AddressController::class, 'setDefault'])->name('addresses.set-default');
});

// Cart (public -- guests can review their cart; login is only enforced on proceeding to checkout)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

// Checkout routes
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::post('/checkout/summary', [CheckoutController::class, 'summary'])->name('checkout.summary');
    Route::post('/checkout/payment/verify', [CheckoutController::class, 'verifyPayment'])->name('checkout.payment.verify');
    Route::post('/checkout/payment/fail', [CheckoutController::class, 'markPaymentFailed'])->name('checkout.payment.fail');
    Route::get('/payment/stripe/success/{order}', [CheckoutController::class, 'stripeSuccess'])->name('payment.stripe.success');
    Route::get('/payment/stripe/cancel/{order}', [CheckoutController::class, 'stripeCancel'])->name('payment.stripe.cancel');
});

Route::match(['get', 'post'], '/payment/web/{provider}/{order}/return', [CheckoutController::class, 'webGatewayReturn'])
    ->name('payment.web.return');
Route::match(['get', 'post'], '/payment/web/{provider}/{order}/cancel', [CheckoutController::class, 'webGatewayCancel'])
    ->name('payment.web.cancel');

Route::post('/checkout/coupon/apply', [CheckoutController::class, 'applyCoupon'])
    ->middleware('auth')
    ->name('coupon.apply');

// ==================== ADMIN SECTIONS WITH NO DEDICATED PERMISSION ====================
// Intentionally empty now -- every section that used to sit in one big
// super_admin|admin-only block has its own role_or_permission group below
// (see "PERMISSION-GATED ADMIN SECTIONS"), so a custom role can be granted
// any single one of them without needing the admin/super_admin role at all.



// ==================== PERMISSION-GATED ADMIN SECTIONS ====================
// Pulled out of the super_admin|admin-only group above so a custom role
// (support, or any role created via admin.roles.*) can reach a section once
// granted the matching permission from Admin\RolePermissionController -- the
// super_admin|admin role check stays as an OR fallback so their access is
// completely unaffected either way. Sections NOT listed here (accounting/GST,
// COD, fleet, delivery-areas, vendor bank details, restaurant approvals,
// refunds/returns, ads-engine, notification/email templates, call masking,
// partner applications, commissions, cuisines, campaigns, push notifications,
// home sections, offline reasons, cancellation limits, celebration types,
// dining bookings, refund policies, reviews, theatres, delivery charges,
// taxes) have no dedicated permission yet and remain super_admin|admin-only.

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_dashboard'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_branches|manage_branch_restaurants|manage_branch_drivers|manage_branch_zones|manage_branch_staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/branches/users', [BranchController::class, 'users'])->name('branches.users');
    Route::post('/branches/users', [BranchController::class, 'storeUser'])->name('branches.users.store');
    Route::put('/branches/users/{membership}', [BranchController::class, 'updateUser'])->name('branches.users.update');
    Route::get('/branches/wallets', [BranchController::class, 'wallets'])->name('branches.wallets');
    Route::get('/branches/settlements', [BranchController::class, 'settlements'])->name('branches.settlements');
    Route::post('/branches/settlements/generate', [BranchController::class, 'generateSettlement'])->name('branches.settlements.generate');
    Route::post('/branches/settlements/{settlement}/approve', [BranchController::class, 'approveSettlement'])->name('branches.settlements.approve');
    Route::get('/branches/payouts', [BranchController::class, 'payouts'])->name('branches.payouts');
    Route::post('/branches/payouts/{payout}/paid', [BranchController::class, 'markPayoutPaid'])->name('branches.payouts.paid');
    Route::get('/branches/territories', [BranchController::class, 'zones'])->name('branches.zones');
    Route::post('/branches/territories', [BranchController::class, 'storeZone'])->name('branches.zones.store');
    Route::get('/branches/reports', [BranchController::class, 'reports'])->name('branches.reports');
    Route::get('/branches/audit-logs', [BranchController::class, 'auditLogs'])->name('branches.audit-logs');
    Route::post('/branches/transfer', [BranchController::class, 'transfer'])->name('branches.transfer');
    Route::get('/branches/tickets', [BranchController::class, 'tickets'])->name('branches.tickets');
    Route::post('/branches/tickets', [BranchController::class, 'storeTicket'])->name('branches.tickets.store');
    Route::post('/branches/{branch}/resync-owner-permissions', [BranchController::class, 'resyncOwnerPermissions'])->name('branches.resync-owner-permissions');
    Route::resource('branches', BranchController::class);
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_restaurants|create_restaurants|edit_restaurants|delete_restaurants'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['role_or_permission:super_admin|admin|import_data'])->group(function () {
        Route::get('/restaurants/template', [RestaurantController::class, 'downloadTemplate'])->name('restaurants.template');
        Route::post('/restaurants/bulk-upload', [RestaurantController::class, 'bulkUpload'])->name('restaurants.bulk-upload');
    });
    Route::post('/restaurants/{restaurant}/increase-menu-prices', [RestaurantController::class, 'increaseMenuPrices'])->name('restaurants.increase-menu-prices');
    Route::get('/restaurants/{restaurant}/petpooja', [PetpoojaConfigController::class, 'show'])->name('restaurants.petpooja.show');
    Route::post('/restaurants/{restaurant}/petpooja', [PetpoojaConfigController::class, 'store'])->name('restaurants.petpooja.store');
    Route::post('/restaurants/{restaurant}/petpooja/sync-menu', [PetpoojaConfigController::class, 'syncMenu'])->name('restaurants.petpooja.sync-menu');
    Route::get('/restaurants/{restaurant}/petpooja/syncs', [PetpoojaConfigController::class, 'syncHistory'])->name('restaurants.petpooja.syncs');
    Route::resource('restaurants', RestaurantController::class);
    Route::post('/restaurants/{restaurant}/toggle-status', [RestaurantController::class, 'toggleStatus'])->name('restaurants.toggle-status');
    Route::post('/restaurants/{restaurant}/suspend', [RestaurantController::class, 'suspend'])->name('restaurants.suspend');
    Route::post('/restaurants/{restaurant}/reactivate', [RestaurantController::class, 'reactivate'])->name('restaurants.reactivate');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_orders|create_orders|update_order_status|cancel_orders|view_order_details|manage_orders'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::get('/orders/live', [OrderController::class, 'live'])->name('orders.live');
    Route::get('/orders/live-data', [OrderController::class, 'liveData'])->name('orders.live-data');
    Route::get('/orders/check-new', [OrderController::class, 'checkNewOrders'])->name('orders.check-new');
    Route::get('/orders/notification-counts', [OrderController::class, 'notificationCounts'])->name('orders.notification-counts');
    Route::middleware(['role_or_permission:super_admin|admin|export_data'])->group(function () {
        Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    });
    Route::post('/orders/bulk-status', [OrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-status');
    Route::resource('orders', OrderController::class);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancelOrder'])->name('orders.cancel');
    Route::post('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment'])->name('orders.verify-payment');
    Route::post('/orders/{order}/assign-driver', [OrderController::class, 'assignDriver'])->name('orders.assign-driver');
    Route::get('/orders/{order}/available-drivers', [OrderController::class, 'getAvailableDrivers'])->name('orders.available-drivers');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{order}/einvoice', [OrderController::class, 'einvoiceStore'])->name('orders.einvoice.store');
    Route::delete('/orders/{order}/einvoice', [OrderController::class, 'einvoiceClear'])->name('orders.einvoice.clear');
    Route::post('/orders/{order}/refund', [OrderController::class, 'processRefund'])->name('orders.refund');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payment-recovery', [PaymentRecoveryController::class, 'index'])->name('payment-recovery.index');
    Route::post('/payment-recovery/{paymentRecoveryQueue}/resolve', [PaymentRecoveryController::class, 'resolve'])->name('payment-recovery.resolve');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_reports|export_reports|view_sales_report|view_earning_report'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/analytics', [ReportController::class, 'index'])->name('analytics');
    Route::resource('reports', ReportController::class);
    Route::get('/web-tracking', [AdminWebVisitTrackController::class, 'index'])->name('web-tracking.index');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_users|create_users|edit_users|delete_users|block_users|manage_users'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['role_or_permission:super_admin|admin|import_data'])->group(function () {
        Route::get('/users/template', [UserController::class, 'downloadTemplate'])->name('users.template');
        Route::post('/users/bulk-upload', [UserController::class, 'bulkUpload'])->name('users.bulk-upload');
    });
    Route::middleware(['role_or_permission:super_admin|admin|export_data'])->group(function () {
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    });
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_drivers|create_drivers|edit_drivers|delete_drivers|assign_drivers|manage_drivers'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('drivers', DriverController::class);
    Route::get('/drivers/{driver}/wallet-transactions', [DriverController::class, 'walletTransactions'])->name('drivers.wallet-transactions');
    Route::get('/drivers/{driver}/orders-history', [DriverController::class, 'ordersHistory'])->name('drivers.orders-history');
    Route::get('/drivers/{driver}/orders/{order}', [DriverController::class, 'orderDetails'])->name('drivers.order-details');
    Route::post('/drivers/{driver}/wallet/topup', [DriverController::class, 'topupWallet'])->name('drivers.wallet-topup');
    Route::post('/drivers/{driver}/cash-collection', [DriverController::class, 'collectCash'])->name('drivers.cash-collection');
    Route::post('/drivers/{driver}/toggle-status', [DriverController::class, 'toggleStatus'])->name('drivers.toggle-status');
    Route::post('/drivers/{driver}/suspend', [DriverController::class, 'suspend'])->name('drivers.suspend');
    Route::post('/drivers/{driver}/reactivate', [DriverController::class, 'reactivate'])->name('drivers.reactivate');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_gigs|create_gigs|edit_gigs|delete_gigs|book_gigs|manage_gigs'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/gigs/analytics', [GigController::class, 'analytics'])->name('gigs.analytics');
    Route::get('/gigs/bulk-create', [GigController::class, 'bulk'])->name('gigs.bulk');
    Route::get('/gigs/payout-approvals', [GigController::class, 'payoutApprovals'])->name('gigs.payout-approvals');
    Route::get('/gigs/fraud-signals', [GigController::class, 'fraudSignals'])->name('gigs.fraud-signals');
    Route::get('/gigs/disputes', [GigController::class, 'disputes'])->name('gigs.disputes');
    Route::get('/gigs/operations', [GigController::class, 'operations'])->name('gigs.operations');
    Route::post('/gigs/forecast', [GigController::class, 'forecast'])->name('gigs.forecast');
    Route::get('/gigs/forecast-lookup', [GigController::class, 'forecastLookup'])->name('gigs.forecast-lookup');
    Route::post('/gigs/forecast-settings', [GigController::class, 'updateForecastSettings'])->name('gigs.forecast-settings.update');
    Route::post('/gigs/signals', [GigController::class, 'ingestSignal'])->name('gigs.signals.ingest');
    Route::post('/gigs/payout-approvals/{approval}', [GigController::class, 'approvePayout'])->name('gigs.payout-approvals.update');
    Route::post('/gigs/fraud-signals/{signal}', [GigController::class, 'resolveFraudSignal'])->name('gigs.fraud-signals.update');
    Route::post('/gigs/disputes/{dispute}', [GigController::class, 'resolveDispute'])->name('gigs.disputes.update');
    Route::get('/gigs/heatmap', [GigController::class, 'heatmap'])->name('gigs.heatmap');
    Route::resource('gigs', GigController::class)->except(['show']);
    Route::post('/gigs/bulk', [GigController::class, 'bulkCreate'])->name('gigs.bulk-create');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_payouts|create_payouts|process_payouts|approve_payouts|manage_payouts'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payouts/data', [PayoutController::class, 'data'])->name('payouts.data');
    Route::get('/payouts/export', [PayoutController::class, 'export'])->name('payouts.export');
    Route::get('/payouts/failed', [PayoutController::class, 'failed'])->name('payouts.failed');
    Route::get('/payouts/vendor-wallet', [PayoutController::class, 'vendorWallet'])->name('payouts.vendor-wallet');
    Route::post('/payouts/bulk-process', [PayoutController::class, 'bulkProcess'])->name('payouts.bulk-process');
    Route::resource('payouts', PayoutController::class);
    Route::post('/payouts/restaurant/generate', [PayoutController::class, 'generateRestaurantPayouts'])->name('payouts.generate-restaurant');
    Route::post('/payouts/driver/generate', [PayoutController::class, 'generateDriverPayouts'])->name('payouts.generate-driver');
    Route::post('/payouts/generate-restaurant', [PayoutController::class, 'generateRestaurantPayouts']);
    Route::post('/payouts/generate-driver', [PayoutController::class, 'generateDriverPayouts']);
    Route::post('/payouts/{payout}/process', [PayoutController::class, 'process'])->name('payouts.process');
    Route::post('/payouts/process/{payout}', [PayoutController::class, 'process']);
    Route::post('/payouts/{payout}/cash-paid', [PayoutController::class, 'markCashPaid'])->name('payouts.cash-paid');
    Route::post('/payouts/{payout}/cancel', [PayoutController::class, 'cancel'])->name('payouts.cancel');
    Route::post('/payouts/retry/{payout}', [PayoutController::class, 'retry'])->name('payouts.retry');
    Route::get('/payouts/{payout}/status', [PayoutController::class, 'status'])->name('payouts.status');
    Route::post('/payouts/{payout}/deduction/revoke', [PayoutController::class, 'revokeDeduction'])->name('payouts.deductions.revoke');
    Route::post('/payouts/{payout}/revoke-deduction', [PayoutController::class, 'revokeDeduction']);
    Route::post('/payout-settings', [PayoutController::class, 'updateSettings'])->name('payouts.settings');
    Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('/wallets/top-up', [WalletController::class, 'topUp'])->name('wallets.top-up');
    Route::get('/wallets/users/search', [WalletController::class, 'users'])->name('wallets.users');
    Route::resource('gift-cards', GiftCardController::class)->except(['create', 'show', 'edit']);
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_banners|create_banners|edit_banners|delete_banners|manage_banners'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/banners/settings', [BannerController::class, 'updateSettings'])->name('banners.settings');
    Route::resource('banners', BannerController::class);
    Route::post('/banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_promos|create_promos|edit_promos|delete_promos|manage_promos'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('promotion-engine')->name('promotion-engine.')->group(function () {
        Route::get('/', [PromotionEngineController::class, 'index'])->name('index');
        Route::get('/create', [PromotionEngineController::class, 'create'])->name('create');
        Route::post('/', [PromotionEngineController::class, 'store'])->name('store');
        Route::get('/analytics', [PromotionEngineController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/export', [PromotionEngineController::class, 'campaignExport'])->name('analytics.export');
        Route::get('/fraud-attempts', [PromotionEngineController::class, 'fraudAttempts'])->name('fraud-attempts');
        Route::get('/fraud-attempts/export', [PromotionEngineController::class, 'fraudAttemptsExport'])->name('fraud-attempts.export');
        Route::get('/bank-partner-settlements', [PromotionEngineController::class, 'bankPartnerSettlements'])->name('bank-partner-settlements');
        Route::get('/bank-partner-settlements/api', [PromotionEngineController::class, 'bankPartnerSettlementsApi'])->name('bank-partner-settlements.api');
        Route::get('/bank-partner-settlements/export', [PromotionEngineController::class, 'bankPartnerSettlementsExport'])->name('bank-partner-settlements.export');
        Route::get('/coupons', [PromotionEngineController::class, 'coupons'])->name('coupons');
        Route::get('/scratch-cards', [PromotionEngineController::class, 'scratchCards'])->name('scratch-cards');
        Route::get('/referrals', [PromotionEngineController::class, 'referrals'])->name('referrals');
        Route::get('/logs', [PromotionEngineController::class, 'logs'])->name('logs');
        Route::get('/{promotion}/edit', [PromotionEngineController::class, 'edit'])->name('edit');
        Route::put('/{promotion}', [PromotionEngineController::class, 'update'])->name('update');
        Route::post('/{promotion}/toggle', [PromotionEngineController::class, 'toggle'])->name('toggle');
        Route::delete('/{promotion}', [PromotionEngineController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_global_categories|create_global_categories|edit_global_categories|delete_global_categories'])->prefix('admin')->name('admin.')->group(function () {
    // Registered before the resource() below so /export and /import don't get
    // swallowed by the resource's GET global-menu-categories/{globalMenuCategory} show route.
    Route::middleware(['role_or_permission:super_admin|admin|export_data'])->group(function () {
        Route::get('global-menu-categories/export', [GlobalMenuCategoryController::class, 'export'])->name('global-menu-categories.export');
    });
    Route::middleware(['role_or_permission:super_admin|admin|import_data'])->group(function () {
        Route::get('global-menu-categories/import/template', [GlobalMenuCategoryController::class, 'downloadTemplate'])->name('global-menu-categories.import.template');
        Route::post('global-menu-categories/import', [GlobalMenuCategoryController::class, 'import'])->name('global-menu-categories.import');
    });
    Route::resource('global-menu-categories', GlobalMenuCategoryController::class);
    Route::get('global-menu-categories/{globalMenuCategory}/subcategories/create', [GlobalMenuCategoryController::class, 'createSubcategory'])->name('global-menu-categories.subcategories.create');
    Route::post('global-menu-categories/{globalMenuCategory}/subcategories', [GlobalMenuCategoryController::class, 'storeSubcategory'])->name('global-menu-categories.subcategories.store');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_listed_menu|create_listed_menu|edit_listed_menu|delete_listed_menu'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('listed-menu')->name('listed-menu.')->group(function () {
        Route::post('/from-global', [ListedMenuController::class, 'storeFromGlobal'])->name('from-global');
        Route::post('/{listedMenu}/toggle-availability', [ListedMenuController::class, 'toggleAvailability'])->name('toggle-availability');
        Route::get('/', [ListedMenuController::class, 'index'])->name('index');
        Route::get('/create', [ListedMenuController::class, 'create'])->name('create');
        Route::post('/', [ListedMenuController::class, 'store'])->name('store');
        Route::get('/{listedMenu}', [ListedMenuController::class, 'show'])->name('show');
        Route::get('/{listedMenu}/edit', [ListedMenuController::class, 'edit'])->name('edit');
        Route::put('/{listedMenu}', [ListedMenuController::class, 'update'])->name('update');
        Route::delete('/{listedMenu}', [ListedMenuController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_menu_items|create_menu_items|edit_menu_items|delete_menu_items|manage_menu'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('master-menu-items')->name('master-menu-items.')->group(function () {
        Route::middleware(['role_or_permission:super_admin|admin|import_data'])->group(function () {
            Route::get('/template', [MasterMenuItemController::class, 'downloadTemplate'])->name('template');
            Route::post('/bulk-upload', [MasterMenuItemController::class, 'bulkUpload'])->name('bulk-upload');
        });
        Route::get('/', [MasterMenuItemController::class, 'index'])->name('index');
        Route::get('/create', [MasterMenuItemController::class, 'create'])->name('create');
        Route::post('/', [MasterMenuItemController::class, 'store'])->name('store');
        Route::get('/{masterMenuItem}/edit', [MasterMenuItemController::class, 'edit'])->name('edit');
        Route::put('/{masterMenuItem}', [MasterMenuItemController::class, 'update'])->name('update');
        Route::delete('/{masterMenuItem}', [MasterMenuItemController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_theatres|create_theatres|edit_theatres|delete_theatres|manage_theatres'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/theatres/{theatre}/resync-owner-permissions', [TheatreController::class, 'resyncOwnerPermissions'])->name('theatres.resync-owner-permissions');
    Route::resource('theatres', TheatreController::class);
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_pos|manage_pos'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::redirect('/pos-terminal', '/admin/pos')->name('pos-terminal.index');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_accounting|manage_accounting'])->prefix('admin')->name('admin.')->group(function () {
    // GST reporting — the standalone page is retired; GST reports now live under
    // Business Accounting. The index redirects there; raw exports are kept.
    Route::get('/gst-reports', fn () => redirect()->route('admin.accounting.gst'))->name('gst-reports.index');
    Route::get('/gst-reports/gstr1', [\App\Http\Controllers\Admin\GstReportController::class, 'exportGstr1'])->name('gst-reports.gstr1');
    Route::get('/gst-reports/gstr1.json', [\App\Http\Controllers\Admin\GstReportController::class, 'exportJson'])->name('gst-reports.gstr1-json');

    // Business Accounting (GST 9(5) + TCS + TDS 194-O / 194-C). Gated in the
    // controller on business_gst_enabled.
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AccountingController::class, 'overview'])->name('overview');
        Route::get('/gst', [\App\Http\Controllers\Admin\AccountingController::class, 'gst'])->name('gst');
        Route::get('/tds', [\App\Http\Controllers\Admin\AccountingController::class, 'tds'])->name('tds');
        Route::get('/tcs', [\App\Http\Controllers\Admin\AccountingController::class, 'tcs'])->name('tcs');
        Route::get('/settlements', [\App\Http\Controllers\Admin\AccountingController::class, 'settlements'])->name('settlements');
        Route::get('/ledger', [\App\Http\Controllers\Admin\AccountingController::class, 'ledger'])->name('ledger');
        Route::get('/documents', [\App\Http\Controllers\Admin\AccountingController::class, 'documents'])->name('documents');
        Route::get('/compliance', [\App\Http\Controllers\Admin\AccountingController::class, 'compliance'])->name('compliance');
        Route::get('/compliance/export', [\App\Http\Controllers\Admin\AccountingController::class, 'complianceExport'])->name('compliance.export');
        Route::post('/compliance', [\App\Http\Controllers\Admin\AccountingController::class, 'updateCompliance'])->name('compliance.update');
        Route::get('/cess', [\App\Http\Controllers\Admin\AccountingController::class, 'cess'])->name('cess');
        Route::post('/mark-filed', [\App\Http\Controllers\Admin\AccountingController::class, 'markFiled'])->name('mark-filed');
        Route::get('/export/{doc}', [\App\Http\Controllers\Admin\AccountingController::class, 'export'])->name('export');

        // General ledger + financial statements
        Route::prefix('gl')->name('gl.')->group(function () {
            $gl = \App\Http\Controllers\Admin\GeneralLedgerController::class;
            Route::get('/chart', [$gl, 'chart'])->name('chart');
            Route::post('/chart', [$gl, 'storeAccount'])->name('chart.store');
            Route::get('/journals', [$gl, 'journals'])->name('journals');
            Route::post('/journals', [$gl, 'storeJournal'])->name('journals.store');
            Route::post('/journals/{journal}/void', [$gl, 'voidJournal'])->name('journals.void');
            Route::get('/trial-balance', [$gl, 'trialBalance'])->name('trial-balance');
            Route::get('/balance-sheet', [$gl, 'balanceSheet'])->name('balance-sheet');
            Route::get('/profit-loss', [$gl, 'profitLoss'])->name('profit-loss');
            Route::get('/cash-flow', [$gl, 'cashFlow'])->name('cash-flow');
            Route::post('/close-period', [$gl, 'closePeriod'])->name('close-period');
            Route::get('/export/{doc}', [$gl, 'export'])->name('export');
        });
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_restaurant_onboardings|manage_restaurant_onboardings'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/driver-restaurant-onboardings/settings', [RestaurantOnboardingController::class, 'settings'])->name('restaurant-onboardings.settings');
    Route::post('/driver-restaurant-onboardings/settings', [RestaurantOnboardingController::class, 'updateSettings'])->name('restaurant-onboardings.settings.update');
    Route::get('/restaurant-onboardings', [RestaurantOnboardingController::class, 'index'])->name('restaurant-onboardings.index');
    Route::get('/restaurant-onboardings/{restaurantOnboarding}', [RestaurantOnboardingController::class, 'show'])->name('restaurant-onboardings.show');
    Route::post('/restaurant-onboardings/{restaurantOnboarding}/correction', [RestaurantOnboardingController::class, 'requestCorrection'])->name('restaurant-onboardings.correction');
    Route::post('/restaurant-onboardings/{restaurantOnboarding}/reject', [RestaurantOnboardingController::class, 'reject'])->name('restaurant-onboardings.reject');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_cod|manage_cod'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/cod', [CodManagementController::class, 'index'])->name('cod.index');
    Route::post('/cod/settle', [CodManagementController::class, 'settle'])->name('cod.settle');
    Route::get('/cod/history', [CodManagementController::class, 'history'])->name('cod.history');
    Route::middleware(['role_or_permission:super_admin|admin|export_data'])->group(function () {
        Route::get('/cod/export', [CodManagementController::class, 'export'])->name('cod.export');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_fleet'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/fleet', [FleetController::class, 'dashboard'])->name('fleet.dashboard');
    Route::get('/fleet/markers', [FleetController::class, 'markers'])->name('fleet.markers');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_delivery_areas|create_delivery_areas|edit_delivery_areas|delete_delivery_areas|manage_delivery_areas'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('delivery-areas', DeliveryAreaController::class);
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_vendor_bank_details'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/vendors/{vendorType}/{vendorId}/bank-details', [VendorBankController::class, 'show'])->name('vendors.bank-details.show');
    Route::post('/vendors/{vendorType}/{vendorId}/bank-details', [VendorBankController::class, 'store'])->name('vendors.bank-details.store');
    Route::get('/vendors/{vendorId}/bank-details', [VendorBankController::class, 'showGeneric']);
    Route::post('/vendors/{vendorId}/bank-details', [VendorBankController::class, 'storeGeneric']);
    Route::post('/vendor-bank-accounts/{bankAccount}/test-transfer', [VendorBankController::class, 'testTransfer'])->name('vendors.bank-details.test');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_restaurant_approvals|manage_restaurant_approvals'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/restaurant-approvals', [RestaurantApprovalController::class, 'index'])->name('restaurant-approvals.index');
    Route::get('/restaurant-approvals/{locationRequest}/fssai-document', [RestaurantApprovalController::class, 'document'])->name('restaurant-approvals.document');
    Route::post('/restaurant-approvals/{locationRequest}/approve', [RestaurantApprovalController::class, 'approve'])->name('restaurant-approvals.approve');
    Route::post('/restaurant-approvals/{locationRequest}/reject', [RestaurantApprovalController::class, 'reject'])->name('restaurant-approvals.reject');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_refunds|manage_refunds|view_returns|manage_returns'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('/refunds', [RefundController::class, 'store'])->name('refunds.store');
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::post('/returns/{order}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{order}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_ads|manage_ads'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('ads-engine')->name('ads-engine.')->group(function () {
        Route::get('/', [AdsEngineController::class, 'index'])->name('index');
        Route::post('/{campaign}/approve', [AdsEngineController::class, 'approve'])->name('approve');
        Route::post('/{campaign}/reject', [AdsEngineController::class, 'reject'])->name('reject');
        Route::get('/click-log', [AdsEngineController::class, 'clickLog'])->name('click-log');
        Route::get('/fraud-signals', [AdsEngineController::class, 'fraudSignals'])->name('fraud-signals');
        Route::post('/fraud-signals/{signal}', [AdsEngineController::class, 'resolveFraudSignal'])->name('fraud-signals.update');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_notification_templates'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('notification-templates')->name('notification-templates.')->group(function () {
        Route::get('/', [NotificationTemplateController::class, 'index'])->name('index');
        Route::get('/{notificationTemplate}/edit', [NotificationTemplateController::class, 'edit'])->name('edit');
        Route::put('/{notificationTemplate}', [NotificationTemplateController::class, 'update'])->name('update');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_email_templates'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('email-templates')->name('email-templates.')->group(function () {
        Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
        Route::post('/upload-image', [EmailTemplateController::class, 'uploadImage'])->name('upload-image');
        Route::get('/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('edit');
        Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('update');
        Route::post('/{emailTemplate}/send-test', [EmailTemplateController::class, 'sendTest'])->name('send-test');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_notification_logs'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/notification-logs', [NotificationLogController::class, 'index'])->name('notification-logs.index');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_call_history'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/call-history', [\App\Http\Controllers\Admin\CallHistoryController::class, 'index'])->name('call-history.index');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_call_masking'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('call-masking')->name('call-masking.')->group(function () {
        Route::get('/pool', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'index'])->name('pool.index');
        Route::post('/pool', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'store'])->name('pool.store');
        Route::put('/pool/{pool}', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'update'])->name('pool.update');
        Route::delete('/pool/{pool}', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'destroy'])->name('pool.destroy');
        Route::post('/pool/{pool}/disable', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'disable'])->name('pool.disable');
        Route::post('/pool/{pool}/enable', [\App\Http\Controllers\Admin\CallMaskingPoolController::class, 'enable'])->name('pool.enable');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_partner_applications|manage_partner_applications'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/partner-applications', [PartnerApplicationController::class, 'index'])->name('partner-applications.index');
    Route::get('/partner-applications/create', [PartnerApplicationController::class, 'create'])->name('partner-applications.create');
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store'])->name('partner-applications.store');
    Route::get('/partner-applications/{application}', [PartnerApplicationController::class, 'show'])->name('partner-applications.show');
    Route::get('/partner-applications/{application}/edit', [PartnerApplicationController::class, 'edit'])->name('partner-applications.edit');
    Route::put('/partner-applications/{application}', [PartnerApplicationController::class, 'update'])->name('partner-applications.update');
    Route::post('/partner-applications/{application}/reverify', [PartnerApplicationController::class, 'reverify'])->name('partner-applications.reverify');
    Route::post('/partner-applications/{application}/approve', [PartnerApplicationController::class, 'approve'])->name('partner-applications.approve');
    Route::post('/partner-applications/{application}/reject', [PartnerApplicationController::class, 'reject'])->name('partner-applications.reject');
    Route::delete('/partner-applications/{application}', [PartnerApplicationController::class, 'destroy'])->name('partner-applications.destroy');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_commissions'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('commissions')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('commissions');
        Route::put('/settings', [CommissionController::class, 'updateSettings'])->name('commissions.settings');
        Route::post('/generate-payouts', [CommissionController::class, 'generatePayouts'])->name('payouts.generate');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_commissions'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('subscription-plans')->name('subscription-plans.')->group(function () {
        Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
        Route::get('/create', [SubscriptionPlanController::class, 'create'])->name('create');
        Route::post('/', [SubscriptionPlanController::class, 'store'])->name('store');
        Route::get('/{subscriptionPlan}/edit', [SubscriptionPlanController::class, 'edit'])->name('edit');
        Route::put('/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('update');
        Route::delete('/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy'])->name('destroy');
        Route::post('/{subscriptionPlan}/toggle-status', [SubscriptionPlanController::class, 'toggleStatus'])->name('toggle-status');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_splash_campaigns'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('splash-campaigns')->name('splash-campaigns.')->group(function () {
        Route::get('/', [SplashCampaignController::class, 'index'])->name('index');
        Route::get('/create', [SplashCampaignController::class, 'create'])->name('create');
        Route::post('/', [SplashCampaignController::class, 'store'])->name('store');
        Route::get('/{splashCampaign}/edit', [SplashCampaignController::class, 'edit'])->name('edit');
        Route::put('/{splashCampaign}', [SplashCampaignController::class, 'update'])->name('update');
        Route::delete('/{splashCampaign}', [SplashCampaignController::class, 'destroy'])->name('destroy');
        Route::post('/{splashCampaign}/toggle-status', [SplashCampaignController::class, 'toggleStatus'])->name('toggle-status');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_cuisines|create_cuisines|edit_cuisines|delete_cuisines|manage_cuisines'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('cuisines')->name('cuisines.')->group(function () {
        Route::get('/', [CuisineController::class, 'index'])->name('index');
        Route::get('/create', [CuisineController::class, 'create'])->name('create');
        Route::post('/', [CuisineController::class, 'store'])->name('store');
        Route::get('/{cuisine}/edit', [CuisineController::class, 'edit'])->name('edit');
        Route::put('/{cuisine}', [CuisineController::class, 'update'])->name('update');
        Route::delete('/{cuisine}', [CuisineController::class, 'destroy'])->name('destroy');
        Route::post('/{cuisine}/toggle-status', [CuisineController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{cuisine}/toggle-popular', [CuisineController::class, 'togglePopular'])->name('toggle-popular');
        Route::post('/reorder', [CuisineController::class, 'reorder'])->name('reorder');
        Route::middleware(['role_or_permission:super_admin|admin|import_data'])->group(function () {
            Route::post('/bulk-import', [CuisineController::class, 'bulkImport'])->name('bulk-import');
        });
        Route::middleware(['role_or_permission:super_admin|admin|export_data'])->group(function () {
            Route::get('/export', [CuisineController::class, 'export'])->name('export');
        });
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_delivery_charges'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('delivery-charges')->group(function () {
        Route::get('/', [DeliveryChargeController::class, 'index'])->name('delivery-charges');
        Route::put('/', [DeliveryChargeController::class, 'update'])->name('delivery-charges.update');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_taxes'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('taxes')->group(function () {
        Route::get('/', [TaxController::class, 'index'])->name('taxes');
        Route::post('/', [TaxController::class, 'store'])->name('taxes.store');
        Route::put('/{id}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('/{id}', [TaxController::class, 'destroy'])->name('taxes.destroy');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_campaigns'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('campaigns', CampaignController::class)->names('campaigns');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_push_notifications'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/settings/notifications/test-push', [PushNotificationController::class, 'sendTest'])
        ->name('settings.notifications.test-push');
    Route::resource('push-notifications', PushNotificationController::class)
        ->only(['index', 'create', 'store'])
        ->names('push-notifications');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_home_sections'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('home-sections')->name('home-sections.')->group(function () {
        Route::get('/', [HomeSectionController::class, 'index'])->name('index');
        Route::get('/create', [HomeSectionController::class, 'create'])->name('create');
        Route::post('/', [HomeSectionController::class, 'store'])->name('store');
        Route::post('/built-in/{token}/content', [HomeSectionController::class, 'updateBuiltIn'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('built-in.content');
        Route::post('/built-in/{token}/toggle', [HomeSectionController::class, 'toggleBuiltIn'])
            ->where('token', '[A-Za-z0-9_-]+')
            ->name('built-in.toggle');
        Route::get('/{homeSection}/edit', [HomeSectionController::class, 'edit'])->name('edit');
        Route::put('/{homeSection}', [HomeSectionController::class, 'update'])->name('update');
        Route::delete('/{homeSection}', [HomeSectionController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [HomeSectionController::class, 'reorder'])->name('reorder');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_offline_reasons'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('offline-reasons', OfflineReasonController::class)->names('offline-reasons');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_cancellation_limits'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/cancellation-limits', [CancellationLimitController::class, 'index'])->name('cancellation-limits');
    Route::put('/cancellation-limits', [CancellationLimitController::class, 'update'])->name('cancellation-limits.update');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_celebration_types'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('celebration-types', CelebrationTypeController::class)->names('celebration-types');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_dining_bookings|manage_dining_bookings'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dining-bookings', [DiningBookingController::class, 'index'])->name('dining-bookings.index');
    Route::put('/dining-bookings/{booking}/status', [DiningBookingController::class, 'updateStatus'])->name('dining-bookings.update-status');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_refund_policies'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('refund-policies')->name('refund-policies.')->group(function () {
        Route::get('/', [RefundPolicyController::class, 'index'])->name('index');
        Route::get('/create', [RefundPolicyController::class, 'create'])->name('create');
        Route::post('/', [RefundPolicyController::class, 'store'])->name('store');
        Route::get('/{refundPolicy}/edit', [RefundPolicyController::class, 'edit'])->name('edit');
        Route::put('/{refundPolicy}', [RefundPolicyController::class, 'update'])->name('update');
        Route::delete('/{refundPolicy}', [RefundPolicyController::class, 'destroy'])->name('destroy');
        Route::patch('/{refundPolicy}/set-active', [RefundPolicyController::class, 'setActive'])->name('set-active');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|manage_blog_posts'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('blog')->name('blog.')->group(function () {
        Route::get('/', [BlogPostController::class, 'index'])->name('index');
        Route::get('/create', [BlogPostController::class, 'create'])->name('create');
        Route::post('/', [BlogPostController::class, 'store'])->name('store');
        Route::get('/{blog}/edit', [BlogPostController::class, 'edit'])->name('edit');
        Route::put('/{blog}', [BlogPostController::class, 'update'])->name('update');
        Route::delete('/{blog}', [BlogPostController::class, 'destroy'])->name('destroy');
        Route::patch('/{blog}/toggle-publish', [BlogPostController::class, 'togglePublish'])->name('toggle-publish');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_reviews|manage_reviews'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::put('/{review}/status', [ReviewController::class, 'updateStatus'])->name('update-status');
    });

    Route::prefix('restaurant-stories')->name('restaurant-stories.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RestaurantStoryController::class, 'index'])->name('index');
        Route::delete('/{restaurantStory}', [\App\Http\Controllers\Admin\RestaurantStoryController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|view_settings|update_settings|manage_settings|manage_app_branding|manage_payment_gateways'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payout-settings', [PayoutSettingsController::class, 'edit'])->name('payout-settings.edit');
    Route::post('/payouts/settings', [PayoutSettingsController::class, 'update'])->name('payout-settings.update');
    Route::get('/payouts/balance/{gateway}', [PayoutSettingsController::class, 'balance'])->name('payout-settings.balance');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
    Route::get('/settings/app-download', [AppDownloadSettingController::class, 'edit'])->name('settings.app-download');
    Route::post('/settings/app-download', [AppDownloadSettingController::class, 'update'])->name('settings.app-download.update');
    Route::get('/settings/payment', [SettingController::class, 'payment'])->name('settings.payment');
    Route::get('/settings/rewards', [SettingController::class, 'rewards'])->name('settings.rewards');
    Route::get('/settings/cron', [SettingController::class, 'cron'])->name('settings.cron');
    Route::get('/settings/homepage', [SettingController::class, 'homepage'])->name('settings.homepage');
    Route::get('/settings/seo', [SettingController::class, 'seo'])->name('settings.seo');
    Route::post('/settings/seo/sitemap', [SettingController::class, 'regenerateSitemap'])->name('settings.seo.sitemap');
    Route::get('/settings/privacy', [SettingController::class, 'privacy'])->name('settings.privacy');
    Route::get('/settings/driver-assignment', [SettingController::class, 'driverAssignment'])->name('settings.driver_assignment');
    Route::get('/settings/communication', [SettingController::class, 'communication'])->name('settings.communication');
    Route::get('/settings/business', [SettingController::class, 'business'])->name('settings.business');
    Route::get('/settings/tax-charges', [SettingController::class, 'taxCharges'])->name('settings.tax-charges');
    Route::get('/settings/integrations', [SettingController::class, 'integrations'])->name('settings.integrations');
    Route::post('/settings/integrations/ping/{target}', [SettingController::class, 'integrationsPing'])->name('settings.integrations.ping');
    Route::post('/settings/integrations/retry/{delivery}', [SettingController::class, 'integrationsRetry'])->name('settings.integrations.retry');
    Route::post('/settings/integrations/sync/{target}', [SettingController::class, 'integrationsSync'])->name('settings.integrations.sync');
    Route::get('/settings/taxation-setup', [SettingController::class, 'taxationSetup'])->name('settings.taxation-setup');
    Route::post('/settings/taxation-setup', [SettingController::class, 'saveTaxationSetup'])->name('settings.taxation-setup.save');
    Route::get('/settings/country-tax', [CountryTaxController::class, 'index'])->name('settings.country-tax');
    Route::post('/settings/country-tax/platform-default', [CountryTaxController::class, 'updatePlatformDefault'])->name('settings.country-tax.platform-default');
    Route::post('/settings/country-tax/profiles', [CountryTaxController::class, 'storeProfile'])->name('settings.country-tax.profiles.store');
    Route::put('/settings/country-tax/profiles/{profile}', [CountryTaxController::class, 'updateProfile'])->name('settings.country-tax.profiles.update');
    Route::post('/settings/country-tax/profiles/{profile}/toggle', [CountryTaxController::class, 'toggleProfile'])->name('settings.country-tax.profiles.toggle');
    Route::get('/settings/us-sales-tax', [CountryTaxController::class, 'usSalesTax'])->name('settings.us-sales-tax');
    Route::post('/settings/us-sales-tax', [CountryTaxController::class, 'updateUsSalesTax'])->name('settings.us-sales-tax.update');
    Route::get('/settings/notifications', [SettingController::class, 'notifications'])->name('settings.notifications');
    Route::get('/settings/map', [SettingController::class, 'map'])->name('settings.map');
    Route::get('/settings/voice-ai', [VoiceAiSettingsController::class, 'edit'])->name('settings.voice-ai');
    Route::post('/settings/voice-ai', [VoiceAiSettingsController::class, 'update'])->name('settings.voice-ai.update');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/app-branding', [SettingController::class, 'updateAppBranding'])->name('settings.branding.post');
    Route::post('/settings/payment', [SettingController::class, 'updatePaymentSettings'])->name('settings.payment.post');
    Route::post('/settings/cron/install', [SettingController::class, 'installCron'])->name('settings.cron.install');
    Route::post('/settings/cron/tasks', [SettingController::class, 'updateCronTasks'])->name('settings.cron.tasks');
    Route::post('/settings/business-reports', [SettingController::class, 'updateBusinessReportSettings'])->name('settings.business-reports');
});

Route::middleware(['auth', 'role_or_permission:super_admin|admin|ai.view|ai.configure|ai.autonomy.change|ai.decisions.view|ai.actions.execute|ai.approvals.manage|ai.finance.view|ai.accounting.view|ai.cost.view|ai.kill_switch'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('ai')->name('ai.')->middleware('ai.enabled')->group(function () {
        Route::get('/', [AiControlCenterController::class, 'index'])->name('index');
        Route::post('/run', [AiControlCenterController::class, 'run'])->name('run');
        Route::get('/settings', [AiControlCenterController::class, 'settings'])->name('settings');
        Route::post('/settings', [AiControlCenterController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/test-connection', [AiControlCenterController::class, 'testConnection'])->name('settings.test-connection');
        Route::post('/kill-switch', [AiControlCenterController::class, 'killSwitch'])->name('kill-switch');
        Route::post('/resume', [AiControlCenterController::class, 'resumeAi'])->name('resume');
        Route::get('/decisions', [AiControlCenterController::class, 'decisions'])->name('decisions.index');
        Route::get('/decisions/{decision}', [AiControlCenterController::class, 'showDecision'])->name('decisions.show');
        Route::get('/approvals', [AiControlCenterController::class, 'approvals'])->name('approvals.index');
        Route::post('/approvals/{approval}/approve', [AiControlCenterController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [AiControlCenterController::class, 'reject'])->name('approvals.reject');
        Route::get('/chat', [AiControlCenterController::class, 'chat'])->name('chat');
        Route::post('/chat', [AiControlCenterController::class, 'chatAsk'])->name('chat.ask');
        Route::get('/reports', [AiControlCenterController::class, 'reports'])->name('reports');
    });
});

// ==================== SUPPORT DESK ROUTES ====================
// Its own panel at /support, not nested under /admin -- a support agent
// should never see an "admin/..." URL. super_admin and admin keep using the
// exact same routes/controller (this is one shared ticket queue, not a
// separate copy), they just reach it at this URL too now.
Route::middleware(['auth', 'role:super_admin|admin|support'])->prefix('support')->name('support.')->group(function () {
    Route::get('/dashboard', [SupportController::class, 'dashboard'])->name('dashboard');
    Route::get('/', [SupportController::class, 'index'])->name('index');
    Route::get('/statistics', [SupportController::class, 'statistics'])->name('statistics');
    Route::get('/notification-summary', [SupportController::class, 'notificationSummary'])->name('notification-summary');
    Route::get('/export', [SupportController::class, 'export'])->name('export');
    Route::get('/{id}', [SupportController::class, 'show'])->name('show');
    Route::post('/{id}/reply', [SupportController::class, 'reply'])->name('reply');
    Route::put('/{id}/status', [SupportController::class, 'updateStatus'])->name('update-status');
    Route::post('/{id}/assign', [SupportController::class, 'assign'])->name('assign');
    Route::post('/{id}/actions', [SupportController::class, 'resolutionAction'])->name('actions');
    Route::post('/bulk-update', [SupportController::class, 'bulkUpdate'])->name('bulk-update');
    Route::delete('/{id}', [SupportController::class, 'destroy'])->name('destroy');
});

// Role & permission management: only super_admin may change who can do what,
// since granting this to 'admin' too would let an admin escalate itself.
Route::middleware(['auth', 'role:super_admin'])->prefix('admin/settings/roles')->name('admin.roles.')->group(function () {
    Route::get('/', [RolePermissionController::class, 'index'])->name('index');
    Route::get('/create', [RolePermissionController::class, 'create'])->name('create');
    Route::post('/', [RolePermissionController::class, 'store'])->name('store');
    Route::get('/{role}/edit', [RolePermissionController::class, 'edit'])->name('edit');
    Route::put('/{role}', [RolePermissionController::class, 'update'])->name('update');
    Route::delete('/{role}', [RolePermissionController::class, 'destroy'])->name('destroy');
});

// ==================== RESTAURANT OWNER ROUTES ====================
require __DIR__.'/restaurant.php';

// ==================== PROFILE ROUTES ====================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::fallback(function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return redirect()->route('restaurant.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
            return redirect()->route('branch.dashboard');
        }

        if ($user->hasRole('support')) {
            return redirect()->route('support.dashboard');
        }

        if ($user->can('view_dashboard')) {
            return redirect()->route('admin.dashboard');
        }
    }

    return redirect()->route('login');
});



