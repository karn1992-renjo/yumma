<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\RefundPolicyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\DeliveryAreaController;
use App\Http\Controllers\Admin\GigController;
use App\Http\Controllers\Admin\FleetController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\PayoutSettingsController;
use App\Http\Controllers\Admin\RestaurantApprovalController;
use App\Http\Controllers\Admin\VendorBankController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\GiftCardController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\PromoController as AdminPromoController;
use App\Http\Controllers\Admin\PartnerApplicationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\CuisineController;
use App\Http\Controllers\Admin\GlobalMenuCategoryController;
use App\Http\Controllers\Admin\MasterMenuItemController;
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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Webhook\CashfreeWebhookController;
use App\Http\Controllers\Webhook\PaystackWebhookController;
use App\Http\Controllers\Webhook\RazorpayWebhookController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\WebVisitTrackController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\DirectChatController;
use App\Http\Controllers\Branch\BranchDashboardController;
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
Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');
Route::get('/legal', function () {
    $settings = AppSetting::all()->pluck('value', 'key')->toArray();
    return view('legal', ['type' => 'legal', 'settings' => $settings]);
})->name('legal.index');

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

    Route::get('/support', [App\Http\Controllers\Customer\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [App\Http\Controllers\Customer\SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [App\Http\Controllers\Customer\SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{id}', [App\Http\Controllers\Customer\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{id}/reply', [App\Http\Controllers\Customer\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/support/{id}/status', [App\Http\Controllers\Customer\SupportController::class, 'updateStatus'])->name('support.update-status');
    Route::post('/support/{id}/assign', [App\Http\Controllers\Customer\SupportController::class, 'assign'])->name('support.assign');
    Route::post('/support/bulk-update', [App\Http\Controllers\Customer\SupportController::class, 'bulkUpdate'])->name('support.bulk-update');
    Route::delete('/support/{id}', [App\Http\Controllers\Customer\SupportController::class, 'destroy'])->name('support.destroy');   
// ==================== RESTAURANT DETAIL PAGE ====================
Route::get('/restaurants/{id}', [HomeController::class, 'showRestaurant'])->name('restaurant.show');

// Partner Registration Routes
Route::get('/partner/register', [PartnerController::class, 'showRegistrationForm'])->name('partner.register');
Route::post('/partner/register-submit', [PartnerController::class, 'submitRegistration'])->name('partner.register.submit');
Route::get('/partner/status/{applicationNumber}', [PartnerController::class, 'getApplicationStatus'])->name('partner.status');

Route::post('/webhooks/razorpay/payout', RazorpayWebhookController::class)->name('webhooks.razorpay.payout');
Route::post('/webhooks/stripe/payout', StripeWebhookController::class)->name('webhooks.stripe.payout');
Route::post('/webhooks/cashfree/payout', CashfreeWebhookController::class)->name('webhooks.cashfree.payout');
Route::post('/webhooks/paystack/payout', PaystackWebhookController::class)->name('webhooks.paystack.payout');

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

    return redirect()->route('home');
})->name('dashboard');

Route::middleware(['auth', 'role:branch_owner|branch_manager|branch_staff'])->prefix('branch-panel')->name('branch.')->group(function () {
    Route::get('/dashboard', [BranchDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [BranchDashboardController::class, 'orders'])->name('orders');
    Route::get('/orders/export', [BranchDashboardController::class, 'exportOrders'])->name('orders.export');
    Route::get('/orders/{order}', [BranchDashboardController::class, 'showOrder'])->name('orders.show');
    Route::post('/orders/{order}/assign-driver', [BranchDashboardController::class, 'assignOrderDriver'])->name('orders.assign-driver');
    Route::get('/restaurants/create', [BranchDashboardController::class, 'createRestaurant'])->name('restaurants.create');
    Route::post('/restaurants', [BranchDashboardController::class, 'storeRestaurant'])->name('restaurants.store');
    Route::get('/restaurants/{restaurant}', [BranchDashboardController::class, 'showRestaurant'])->name('restaurants.show');
    Route::get('/restaurants/{restaurant}/edit', [BranchDashboardController::class, 'editRestaurant'])->name('restaurants.edit');
    Route::put('/restaurants/{restaurant}', [BranchDashboardController::class, 'updateRestaurant'])->name('restaurants.update');
    Route::post('/restaurants/{restaurant}/approve', [BranchDashboardController::class, 'approveRestaurant'])->name('restaurants.approve');
    Route::get('/restaurants', [BranchDashboardController::class, 'restaurants'])->name('restaurants');
    Route::get('/drivers/create', [BranchDashboardController::class, 'createDriver'])->name('drivers.create');
    Route::post('/drivers', [BranchDashboardController::class, 'storeDriver'])->name('drivers.store');
    Route::get('/drivers/{driver}/edit', [BranchDashboardController::class, 'editDriver'])->name('drivers.edit');
    Route::put('/drivers/{driver}', [BranchDashboardController::class, 'updateDriver'])->name('drivers.update');
    Route::get('/drivers', [BranchDashboardController::class, 'drivers'])->name('drivers');
    Route::get('/territories', [BranchDashboardController::class, 'zones'])->name('zones');
    Route::get('/wallet', [BranchDashboardController::class, 'wallet'])->name('wallet');
    Route::get('/wallet/export', [BranchDashboardController::class, 'exportWallet'])->name('wallet.export');
    Route::post('/wallet/withdrawals', [BranchDashboardController::class, 'requestWithdrawal'])->name('wallet.withdrawals.store');
    Route::get('/settlements', [BranchDashboardController::class, 'settlements'])->name('settlements');
    Route::post('/settlements', [BranchDashboardController::class, 'storeSettlement'])->name('settlements.store');
    Route::get('/reports', [BranchDashboardController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [BranchDashboardController::class, 'exportReports'])->name('reports.export');
    Route::get('/settings', [BranchDashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [BranchDashboardController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/staff', [BranchDashboardController::class, 'storeStaff'])->name('settings.staff.store');
    Route::put('/settings/staff/{staff}', [BranchDashboardController::class, 'updateStaff'])->name('settings.staff.update');
    Route::get('/tickets', [BranchDashboardController::class, 'tickets'])->name('tickets');
    Route::post('/tickets', [BranchDashboardController::class, 'storeTicket'])->name('tickets.store');
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

    // Customer support alias route
    Route::redirect('/support', '/support')->name('support.index');
});

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

// ==================== SUPER ADMIN ROUTES ====================
Route::middleware(['auth', 'role:super_admin|admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Branch Management
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
    Route::resource('branches', BranchController::class);
    
    // Restaurants Management
    Route::get('/restaurants/template', [RestaurantController::class, 'downloadTemplate'])->name('restaurants.template');
    Route::post('/restaurants/bulk-upload', [RestaurantController::class, 'bulkUpload'])->name('restaurants.bulk-upload');
    Route::resource('restaurants', RestaurantController::class);
    Route::post('/restaurants/{restaurant}/toggle-status', [RestaurantController::class, 'toggleStatus'])->name('restaurants.toggle-status');
    
    // Orders Management
    Route::get('/orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::post('/orders/bulk-status', [OrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-status');
    Route::resource('orders', OrderController::class);
    Route::resource('reports', ReportController::class);
    Route::get('/web-tracking', [AdminWebVisitTrackController::class, 'index'])->name('web-tracking.index');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/assign-driver', [OrderController::class, 'assignDriver'])->name('orders.assign-driver');
    Route::get('/orders/{order}/available-drivers', [OrderController::class, 'getAvailableDrivers'])->name('orders.available-drivers');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::post('/orders/{order}/refund', [OrderController::class, 'processRefund'])->name('orders.refund');
    
    // Users Management
    Route::get('/users/template', [UserController::class, 'downloadTemplate'])->name('users.template');
    Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
    Route::post('/users/bulk-upload', [UserController::class, 'bulkUpload'])->name('users.bulk-upload');
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    
    // Drivers Management
    Route::resource('drivers', DriverController::class);
    Route::get('/drivers/{driver}/wallet-transactions', [DriverController::class, 'walletTransactions'])->name('drivers.wallet-transactions');
    Route::get('/drivers/{driver}/orders-history', [DriverController::class, 'ordersHistory'])->name('drivers.orders-history');
    Route::get('/drivers/{driver}/orders/{order}', [DriverController::class, 'orderDetails'])->name('drivers.order-details');
    Route::post('/drivers/{driver}/wallet/topup', [DriverController::class, 'topupWallet'])->name('drivers.wallet-topup');
    Route::post('/drivers/{driver}/toggle-status', [DriverController::class, 'toggleStatus'])->name('drivers.toggle-status');

    // Fleet Management
    Route::get('/fleet', [FleetController::class, 'dashboard'])->name('fleet.dashboard');
    
    // Gigs Management
    Route::resource('gigs', GigController::class);
    Route::post('/gigs/bulk', [GigController::class, 'bulkCreate'])->name('gigs.bulk-create');

    // Delivery Areas Management
    Route::resource('delivery-areas', DeliveryAreaController::class);
    
    // Payouts Management
    Route::get('/payouts/data', [PayoutController::class, 'data'])->name('payouts.data');
    Route::get('/payouts/export', [PayoutController::class, 'export'])->name('payouts.export');
    Route::get('/payouts/failed', [PayoutController::class, 'failed'])->name('payouts.failed');
    Route::post('/payouts/bulk-process', [PayoutController::class, 'bulkProcess'])->name('payouts.bulk-process');
    Route::resource('payouts', PayoutController::class);
    Route::post('/payouts/restaurant/generate', [PayoutController::class, 'generateRestaurantPayouts'])->name('payouts.generate-restaurant');
    Route::post('/payouts/driver/generate', [PayoutController::class, 'generateDriverPayouts'])->name('payouts.generate-driver');
    Route::post('/payouts/generate-restaurant', [PayoutController::class, 'generateRestaurantPayouts']);
    Route::post('/payouts/generate-driver', [PayoutController::class, 'generateDriverPayouts']);
    Route::post('/payouts/{payout}/process', [PayoutController::class, 'process'])->name('payouts.process');
    Route::post('/payouts/process/{payout}', [PayoutController::class, 'process']);
    Route::post('/payouts/retry/{payout}', [PayoutController::class, 'retry'])->name('payouts.retry');
    Route::get('/payouts/{payout}/status', [PayoutController::class, 'status'])->name('payouts.status');
    Route::post('/payouts/{payout}/deduction/revoke', [PayoutController::class, 'revokeDeduction'])->name('payouts.deductions.revoke');
    Route::post('/payouts/{payout}/revoke-deduction', [PayoutController::class, 'revokeDeduction']);
    Route::post('/payout-settings', [PayoutController::class, 'updateSettings'])->name('payouts.settings');
    Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('/wallets/top-up', [WalletController::class, 'topUp'])->name('wallets.top-up');
    Route::get('/wallets/users/search', [WalletController::class, 'users'])->name('wallets.users');
    Route::resource('gift-cards', GiftCardController::class)->except(['create', 'show', 'edit']);
    Route::get('/vendors/{vendorType}/{vendorId}/bank-details', [VendorBankController::class, 'show'])->name('vendors.bank-details.show');
    Route::post('/vendors/{vendorType}/{vendorId}/bank-details', [VendorBankController::class, 'store'])->name('vendors.bank-details.store');
    Route::get('/vendors/{vendorId}/bank-details', [VendorBankController::class, 'showGeneric']);
    Route::post('/vendors/{vendorId}/bank-details', [VendorBankController::class, 'storeGeneric']);
    Route::post('/vendor-bank-accounts/{bankAccount}/test-transfer', [VendorBankController::class, 'testTransfer'])->name('vendors.bank-details.test');
    Route::get('/payout-settings', [PayoutSettingsController::class, 'edit'])->name('payout-settings.edit');
    Route::post('/payouts/settings', [PayoutSettingsController::class, 'update'])->name('payout-settings.update');
    Route::get('/payouts/balance/{gateway}', [PayoutSettingsController::class, 'balance'])->name('payout-settings.balance');
    Route::get('/restaurant-approvals', [RestaurantApprovalController::class, 'index'])->name('restaurant-approvals.index');
    Route::get('/restaurant-approvals/{locationRequest}/fssai-document', [RestaurantApprovalController::class, 'document'])->name('restaurant-approvals.document');
    Route::post('/restaurant-approvals/{locationRequest}/approve', [RestaurantApprovalController::class, 'approve'])->name('restaurant-approvals.approve');
    Route::post('/restaurant-approvals/{locationRequest}/reject', [RestaurantApprovalController::class, 'reject'])->name('restaurant-approvals.reject');
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('/refunds', [RefundController::class, 'store'])->name('refunds.store');
    
    // Banners Management
    Route::resource('banners', BannerController::class);
    Route::post('/banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');

    // Admin Promo Management
    Route::resource('promos', AdminPromoController::class)->names('promos');
    Route::post('/promos/{promo}/toggle', [AdminPromoController::class, 'toggle'])->name('promos.toggle');
    
    // Partner Applications
    Route::get('/partner-applications', [PartnerApplicationController::class, 'index'])->name('partner-applications.index');
    Route::get('/partner-applications/create', [PartnerApplicationController::class, 'create'])->name('partner-applications.create');
    Route::post('/partner-applications', [PartnerApplicationController::class, 'store'])->name('partner-applications.store');
    Route::get('/partner-applications/{application}', [PartnerApplicationController::class, 'show'])->name('partner-applications.show');
    Route::get('/partner-applications/{application}/edit', [PartnerApplicationController::class, 'edit'])->name('partner-applications.edit');
    Route::put('/partner-applications/{application}', [PartnerApplicationController::class, 'update'])->name('partner-applications.update');
    Route::post('/partner-applications/{application}/approve', [PartnerApplicationController::class, 'approve'])->name('partner-applications.approve');
    Route::post('/partner-applications/{application}/reject', [PartnerApplicationController::class, 'reject'])->name('partner-applications.reject');
    Route::delete('/partner-applications/{application}', [PartnerApplicationController::class, 'destroy'])->name('partner-applications.destroy');
    
    // Commission Settings
    Route::prefix('commissions')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('commissions');
        Route::put('/settings', [CommissionController::class, 'updateSettings'])->name('commissions.settings');
        Route::get('/payout-history', [CommissionController::class, 'payoutHistory'])->name('payouts.history');
        Route::post('/generate-payouts', [CommissionController::class, 'generatePayouts'])->name('payouts.generate');
        Route::post('/payouts/{id}/complete', [CommissionController::class, 'markPayoutCompleted'])->name('payouts.complete');
    });

    // Cuisine Management Routes
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
        Route::post('/bulk-import', [CuisineController::class, 'bulkImport'])->name('bulk-import');
        Route::get('/export', [CuisineController::class, 'export'])->name('export');
    });
    Route::resource('global-menu-categories', GlobalMenuCategoryController::class);
    Route::prefix('master-menu-items')->name('master-menu-items.')->group(function () {
        Route::get('/template', [MasterMenuItemController::class, 'downloadTemplate'])->name('template');
        Route::post('/bulk-upload', [MasterMenuItemController::class, 'bulkUpload'])->name('bulk-upload');
        Route::get('/', [MasterMenuItemController::class, 'index'])->name('index');
        Route::get('/create', [MasterMenuItemController::class, 'create'])->name('create');
        Route::post('/', [MasterMenuItemController::class, 'store'])->name('store');
        Route::get('/{masterMenuItem}/edit', [MasterMenuItemController::class, 'edit'])->name('edit');
        Route::put('/{masterMenuItem}', [MasterMenuItemController::class, 'update'])->name('update');
        Route::delete('/{masterMenuItem}', [MasterMenuItemController::class, 'destroy'])->name('destroy');
    });
    
    // Delivery Charges Settings
    Route::prefix('delivery-charges')->group(function () {
        Route::get('/', [DeliveryChargeController::class, 'index'])->name('delivery-charges');
        Route::put('/', [DeliveryChargeController::class, 'update'])->name('delivery-charges.update');
    });
    
    // Tax Settings
    Route::prefix('taxes')->group(function () {
        Route::get('/', [TaxController::class, 'index'])->name('taxes');
        Route::post('/', [TaxController::class, 'store'])->name('taxes.store');
        Route::put('/{id}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('/{id}', [TaxController::class, 'destroy'])->name('taxes.destroy');
    });
    
    // Campaigns
    Route::resource('campaigns', CampaignController::class)->names('campaigns');
    Route::post('/settings/notifications/test-push', [PushNotificationController::class, 'sendTest'])
        ->name('settings.notifications.test-push');
    Route::resource('push-notifications', PushNotificationController::class)
        ->only(['index', 'create', 'store'])
        ->names('push-notifications');

    Route::prefix('home-sections')->name('home-sections.')->group(function () {
        Route::get('/', [HomeSectionController::class, 'index'])->name('index');
        Route::get('/create', [HomeSectionController::class, 'create'])->name('create');
        Route::post('/', [HomeSectionController::class, 'store'])->name('store');
        Route::get('/{homeSection}/edit', [HomeSectionController::class, 'edit'])->name('edit');
        Route::put('/{homeSection}', [HomeSectionController::class, 'update'])->name('update');
        Route::delete('/{homeSection}', [HomeSectionController::class, 'destroy'])->name('destroy');
        Route::post('/reorder', [HomeSectionController::class, 'reorder'])->name('reorder');
    });
    
    // Offline Reasons
    Route::resource('offline-reasons', OfflineReasonController::class)->names('offline-reasons');
    
    // Cancellation Limits
    Route::get('/cancellation-limits', [CancellationLimitController::class, 'index'])->name('cancellation-limits');
    Route::put('/cancellation-limits', [CancellationLimitController::class, 'update'])->name('cancellation-limits.update');
    
    // Celebration Types
    Route::resource('celebration-types', CelebrationTypeController::class)->names('celebration-types');
    Route::get('/dining-bookings', [DiningBookingController::class, 'index'])->name('dining-bookings.index');
    Route::put('/dining-bookings/{booking}/status', [DiningBookingController::class, 'updateStatus'])->name('dining-bookings.update-status');
    
    // Refund Policies
    Route::prefix('refund-policies')->name('refund-policies.')->group(function () {
        Route::get('/', [RefundPolicyController::class, 'index'])->name('index');
        Route::get('/create', [RefundPolicyController::class, 'create'])->name('create');
        Route::post('/', [RefundPolicyController::class, 'store'])->name('store');
        Route::get('/{refundPolicy}/edit', [RefundPolicyController::class, 'edit'])->name('edit');
        Route::put('/{refundPolicy}', [RefundPolicyController::class, 'update'])->name('update');
        Route::delete('/{refundPolicy}', [RefundPolicyController::class, 'destroy'])->name('destroy');
        Route::patch('/{refundPolicy}/set-active', [RefundPolicyController::class, 'setActive'])->name('set-active');
    });

    // In web.php - add to admin group
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [SupportController::class, 'index'])->name('index');
        Route::get('/statistics', [SupportController::class, 'statistics'])->name('statistics');
        Route::get('/notification-summary', [SupportController::class, 'notificationSummary'])->name('notification-summary');
        Route::get('/export', [SupportController::class, 'export'])->name('export');
        Route::get('/{id}', [SupportController::class, 'show'])->name('show');
        Route::post('/{id}/reply', [SupportController::class, 'reply'])->name('reply');
        Route::put('/{id}/status', [SupportController::class, 'updateStatus'])->name('update-status');
        Route::post('/{id}/assign', [SupportController::class, 'assign'])->name('assign');
        Route::post('/bulk-update', [SupportController::class, 'bulkUpdate'])->name('bulk-update');
        Route::delete('/{id}', [SupportController::class, 'destroy'])->name('destroy');
    });
    
    // General Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
    Route::get('/settings/payment', [SettingController::class, 'payment'])->name('settings.payment');
    Route::get('/settings/cron', [SettingController::class, 'cron'])->name('settings.cron');
    Route::get('/settings/homepage', [SettingController::class, 'homepage'])->name('settings.homepage');
    Route::get('/settings/privacy', [SettingController::class, 'privacy'])->name('settings.privacy');
    Route::get('/settings/driver-assignment', [SettingController::class, 'driverAssignment'])->name('settings.driver_assignment');
    Route::get('/settings/communication', [SettingController::class, 'communication'])->name('settings.communication');
    Route::get('/settings/notifications', [SettingController::class, 'notifications'])->name('settings.notifications');
    Route::get('/settings/map', [SettingController::class, 'map'])->name('settings.map');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/app-branding', [SettingController::class, 'updateAppBranding'])->name('settings.branding.post');
    Route::post('/settings/payment', [SettingController::class, 'updatePaymentSettings'])->name('settings.payment.post');
    Route::post('/settings/cron/install', [SettingController::class, 'installCron'])->name('settings.cron.install');
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
    }

    return redirect()->route('login');
});
