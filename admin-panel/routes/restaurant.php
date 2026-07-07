<?php
// routes/restaurant.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Restaurant\DashboardController;
use App\Http\Controllers\Restaurant\OrderController;
use App\Http\Controllers\Restaurant\HelpSupportController;
use App\Http\Controllers\Restaurant\MenuController;
use App\Http\Controllers\Restaurant\CategoryController;
use App\Http\Controllers\Restaurant\PromoController;
use App\Http\Controllers\Restaurant\AnalyticsController;
use App\Http\Controllers\Restaurant\SettingsController;
use App\Http\Controllers\Restaurant\StoreController;
use App\Http\Controllers\Restaurant\PrinterController;
use App\Http\Controllers\Restaurant\StaffController;
use App\Http\Controllers\Restaurant\WalletController;

Route::middleware(['auth', 'role:restaurant_owner|restaurant_staff'])->prefix('restaurant')->name('restaurant.')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/toggle-status', [DashboardController::class, 'toggleStatus'])
        ->middleware('restaurant.permission:view_dashboard')
        ->name('toggle-status');
    
    // Orders Management
    Route::middleware('restaurant.permission:view_orders,manage_orders')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/check-new', [OrderController::class, 'checkNewOrders'])->name('orders.check-new');
        Route::get('/orders/counts', [OrderController::class, 'getOrderCounts'])->name('orders.counts');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    });
    Route::middleware('restaurant.permission:manage_orders,update_order_status')->group(function () {
        Route::post('/orders/{id}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('/orders/{id}/accept', [OrderController::class, 'acceptOrder'])->name('orders.accept');
        Route::post('/orders/{id}/reject', [OrderController::class, 'rejectOrder'])->name('orders.reject');
    });
    
    // Menu Management
    Route::get('/menu', [MenuController::class, 'index'])
        ->middleware('restaurant.permission:view_menu_items,manage_menu')
        ->name('menu.index');
    Route::middleware('restaurant.permission:manage_menu')->group(function () {
        Route::get('/menu/template', [MenuController::class, 'downloadTemplate'])->name('menu.template');
        Route::get('/menu/create', [MenuController::class, 'create'])->name('menu.create');
        Route::post('/menu', [MenuController::class, 'store'])->name('menu.store');
        Route::post('/menu/from-global', [MenuController::class, 'importFromGlobal'])->name('menu.from-global');
        Route::get('/menu/{menu}/edit', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('/menu/{menu}', [MenuController::class, 'update'])->name('menu.update');
        Route::delete('/menu/{menu}', [MenuController::class, 'destroy'])->name('menu.destroy');
        Route::post('/menu/bulk-upload', [MenuController::class, 'bulkUpload'])->name('menu.bulk-upload');
        Route::post('/menu/adjust-prices', [MenuController::class, 'adjustPrices'])->name('menu.adjust-prices');
        Route::post('/menu/{id}/toggle-availability', [MenuController::class, 'toggleAvailability'])->name('menu.toggle-availability');
    });
    
    // Category Management
    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('restaurant.permission:view_menu_items,manage_menu')
        ->name('categories.index');
    Route::post('/categories/reorder', [CategoryController::class, 'reorder'])
        ->middleware('restaurant.permission:manage_menu')
        ->name('categories.reorder');
    Route::middleware('restaurant.permission:manage_menu')->group(function () {
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
    
    // Analytics
    Route::middleware('restaurant.permission:view_reports')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    });

    // Support
    Route::get('/support', [HelpSupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [HelpSupportController::class, 'create'])->name('support.create');
    Route::post('/support', [HelpSupportController::class, 'store'])->name('support.store');
    Route::get('/support/faq', [HelpSupportController::class, 'faq'])->name('support.faq');
    Route::get('/support/contact', [HelpSupportController::class, 'contact'])->name('support.contact');
    Route::get('/support/{id}', [HelpSupportController::class, 'show'])->whereNumber('id')->name('support.show');
    Route::post('/support/{id}/reply', [HelpSupportController::class, 'reply'])->whereNumber('id')->name('support.reply');
    Route::post('/support/{id}/close', [HelpSupportController::class, 'close'])->whereNumber('id')->name('support.close');

    Route::middleware('role:restaurant_owner')->group(function () {
        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');

        // Promo Codes Management
        Route::resource('promos', PromoController::class);
        Route::post('/promos/{id}/toggle-status', [PromoController::class, 'toggleStatus'])->name('promos.toggle-status');

        // Staff Management
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/timing', [SettingsController::class, 'timing'])->name('settings.timing');
        Route::put('/settings/timing', [SettingsController::class, 'updateTiming'])->name('settings.timing.update');
        Route::post('/settings/go-offline', [SettingsController::class, 'goOffline'])->name('settings.go-offline');
        Route::post('/settings/go-online', [SettingsController::class, 'goOnline'])->name('settings.go-online');
        Route::post('/settings/timing/copy', [SettingsController::class, 'copyTimings'])->name('settings.copy-timings');
        Route::post('/settings/timing/apply-weekday', [SettingsController::class, 'applyWeekdayTimings'])->name('settings.apply-weekday-timings');
        Route::post('/settings/timing/reset', [SettingsController::class, 'resetTimings'])->name('settings.reset-timings');

        // Store Management (Multiple Restaurants)
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::get('/create', [StoreController::class, 'create'])->name('create');
            Route::post('/', [StoreController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::put('/{id}', [StoreController::class, 'update'])->name('update');
            Route::post('/switch', [StoreController::class, 'switchStore'])->name('switch');
            Route::get('/current', [StoreController::class, 'getCurrentStore'])->name('current');
        });

        // Printer Settings
        Route::prefix('printers')->name('printers.')->group(function () {
            Route::get('/discover', [PrinterController::class, 'discover'])->name('discover');
            Route::post('/pair-bluetooth', [PrinterController::class, 'pairBluetooth'])->name('pair-bluetooth');
        });
        Route::resource('printers', PrinterController::class)->names('printers');
        Route::post('/printers/{id}/test', [PrinterController::class, 'test'])->name('printers.test');
        Route::post('/printers/kot/{orderId}', [PrinterController::class, 'printKOT'])->name('printers.kot');
        Route::post('/printers/invoice/{orderId}', [PrinterController::class, 'printInvoice'])->name('printers.invoice');
    });
});

