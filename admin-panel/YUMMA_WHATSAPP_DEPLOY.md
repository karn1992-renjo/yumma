# WhatsApp: deploy checklist for yumma.in

All paths are inside `admin-panel/`. Upload in this order. Everything below is already in the swaad code.

## 1. Upload these files as they are (all new)

**App code**
- `app/Console/Commands/WhatsAppBroadcasts.php`
- `app/Console/Commands/WhatsAppReminders.php`
- `app/Events/WhatsAppMessageReceived.php`
- `app/Http/Controllers/Admin/WhatsAppController.php`
- `app/Http/Controllers/Admin/WhatsAppInboxController.php`
- `app/Http/Controllers/Api/WhatsAppWebhookController.php`
- `app/Http/Controllers/PublicPaymentController.php`
- `app/Http/Controllers/PublicTrackingController.php`
- `app/Models/WhatsAppBroadcast.php`
- `app/Models/WhatsAppBroadcastRecipient.php`
- `app/Models/WhatsAppBroadcastTemplate.php`
- `app/Models/WhatsAppContact.php`
- `app/Models/WhatsAppMessage.php`
- `app/Support/PublicPayment.php`
- `app/Support/PublicTracking.php`
- the whole folder `app/Services/WhatsApp/` (including `Providers/`): 10 files + 6 provider files
  `WhatsAppActions, WhatsAppBot, WhatsAppBroadcastService, WhatsAppBroadcastVariables, WhatsAppLogin, WhatsAppNotifier, WhatsAppOrdering, WhatsAppService, WhatsAppTemplateRules, WhatsAppTemplates`
  `Providers/: AiSensy, Gupshup, Interakt, MetaCloud, Twilio, WhatsAppProvider`

**Screens**
- the whole folder `resources/views/admin/whatsapp/` (7 files: _nav, broadcasts, inbox, messages, reports, settings, thread)
- `resources/views/track/public.blade.php`
- `resources/views/pay/public.blade.php`

**Database (3 migrations)**
- `database/migrations/2026_10_26_000002_create_whatsapp_tables.php`
- `database/migrations/2026_10_26_000003_create_whatsapp_broadcasts_tables.php`
- `database/migrations/2026_10_27_000001_create_whatsapp_broadcast_templates_table.php`
- `database/migrations/2026_10_28_000001_create_whatsapp_catalog_items_table.php`
- `database/migrations/2026_10_29_000001_add_channel_to_whatsapp_contacts.php` (second WhatsApp number: also overwrite WhatsAppContact, WhatsAppWebhookController, WhatsAppInboxController)
- `app/Models/WhatsAppCatalogItem.php`, `app/Services/WhatsApp/WhatsAppCatalogSync.php`, `app/Console/Commands/WhatsAppCatalogSyncCommand.php` (in-chat catalog; also overwrite WhatsAppProvider, MetaCloudProvider, WhatsAppService, WhatsAppOrdering, WhatsAppBot, WhatsAppController, settings.blade.php)

**Only if not already on the server (Gold welcome email)**
- `app/Mail/GoldMembershipWelcomeMail.php`, `resources/views/emails/gold-membership-welcome.blade.php`

## 2. Add a few lines to existing files

You can replace each file with the swaad copy **only if your server's copy matches the repo**; otherwise add just these lines.

### routes/api.php (add at the end)
```php
// WhatsApp provider webhooks (inbound messages + delivery receipts). Authenticated in the controller.
Route::get('/webhooks/whatsapp/{provider}/{key?}', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'verify'])->middleware('throttle:600,1');
Route::post('/webhooks/whatsapp/{provider}/{key?}', [\App\Http\Controllers\Api\WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1');
```

### routes/web.php
Public pages (put them near `Route::get('/restaurants/{slug}', ...)`):
```php
// Login-free live order tracking (signed, expiring link shared on WhatsApp).
Route::middleware(['signed', 'throttle:120,1'])->group(function () {
    Route::get('/track/{order}', [\App\Http\Controllers\PublicTrackingController::class, 'show'])->name('track.public');
    Route::get('/track/{order}/data', [\App\Http\Controllers\PublicTrackingController::class, 'data'])->name('track.public.data');
});

// Pay for a cart started in WhatsApp (signed link; same pay-then-order checkout as the app).
Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('/pay/{token}', [\App\Http\Controllers\PublicPaymentController::class, 'show'])->name('order.pay');
    Route::post('/pay/{token}/start', [\App\Http\Controllers\PublicPaymentController::class, 'start'])->name('order.pay.start');
    Route::post('/pay/{token}/complete', [\App\Http\Controllers\PublicPaymentController::class, 'complete'])->name('order.pay.complete');
});
```
Admin pages (inside the same admin group as `settings/...` routes, e.g. after `settings/integrations`/`settings/communication`):
```php
Route::get('/whatsapp', [\App\Http\Controllers\Admin\WhatsAppController::class, 'settings'])->name('whatsapp.settings');
Route::post('/whatsapp', [\App\Http\Controllers\Admin\WhatsAppController::class, 'save'])->name('whatsapp.save');
Route::post('/whatsapp/test', [\App\Http\Controllers\Admin\WhatsAppController::class, 'test'])->name('whatsapp.test');
Route::post('/whatsapp/catalog/sync', [\App\Http\Controllers\Admin\WhatsAppController::class, 'catalogSync'])->name('whatsapp.catalog.sync');
Route::post('/whatsapp/catalog/cart', [\App\Http\Controllers\Admin\WhatsAppController::class, 'catalogCart'])->name('whatsapp.catalog.cart');
Route::get('/whatsapp/messages', [\App\Http\Controllers\Admin\WhatsAppController::class, 'messages'])->name('whatsapp.messages');
Route::get('/whatsapp/inbox', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'inbox'])->name('whatsapp.inbox');
Route::get('/whatsapp/inbox/{contact}', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'thread'])->name('whatsapp.thread');
Route::post('/whatsapp/inbox/{contact}/reply', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'reply'])->name('whatsapp.reply');
Route::post('/whatsapp/inbox/{contact}/hand-back', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'handBack'])->name('whatsapp.hand-back');
Route::get('/whatsapp/broadcasts', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'broadcasts'])->name('whatsapp.broadcasts');
Route::post('/whatsapp/broadcasts', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'storeBroadcast'])->name('whatsapp.broadcasts.store');
Route::post('/whatsapp/broadcasts/{broadcast}/cancel', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'cancelBroadcast'])->name('whatsapp.broadcasts.cancel');
Route::post('/whatsapp/broadcast-templates', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'storeTemplate'])->name('whatsapp.templates.store');
Route::post('/whatsapp/broadcast-templates/{template}', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'updateTemplate'])->name('whatsapp.templates.update');
Route::delete('/whatsapp/broadcast-templates/{template}', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'destroyTemplate'])->name('whatsapp.templates.destroy');
Route::get('/whatsapp/reports', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'reports'])->name('whatsapp.reports');
```
(The admin group's URL prefix and name prefix make these `/admin/whatsapp` and `admin.whatsapp.*`.)

### routes/console.php (add at the end)
```php
Schedule::command('whatsapp:reminders')->name('whatsapp-reminders')->dailyAt('10:00')->withoutOverlapping()
    ->when(fn () => app(\App\Services\WhatsApp\WhatsAppService::class)->enabled());
Schedule::command('whatsapp:broadcasts')->name('whatsapp-broadcasts')->everyMinute()->withoutOverlapping()
    ->when(fn () => app(\App\Services\WhatsApp\WhatsAppService::class)->enabled());
```

### resources/views/admin/settings/_tabs.blade.php
Add one line in the `$settingsTabs` list (for example after Communication):
```php
['route' => 'admin.whatsapp.settings', 'icon' => 'fab fa-whatsapp', 'label' => 'WhatsApp'],
```

### app/Models/Order.php — inside `boot()`
At the top of `static::updated(function ($order) {`:
```php
try {
    app(\App\Services\WhatsApp\WhatsAppNotifier::class)->orderUpdated($order);
} catch (\Throwable $e) {
    \Illuminate\Support\Facades\Log::warning('WhatsApp order hook failed.', ['error' => $e->getMessage()]);
}
```
At the top of `static::created(function ($order) {` the same, with `->orderCreated($order)`.

### app/Models/Restaurant.php — inside `boot()`, just before `static::updating(`
```php
static::updated(function ($restaurant) {
    if ($restaurant->wasChanged('is_open')) {
        try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->restaurantStatusChanged($restaurant); } catch (\Throwable $e) {}
    }
    if ($restaurant->wasChanged('is_verified') && $restaurant->is_verified) {
        try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->restaurantApproved($restaurant); } catch (\Throwable $e) {}
    }
});
```

### Three models: add this method at the end of the class
`app/Models/Payout.php`
```php
protected static function booted(): void
{
    static::updated(function (Payout $payout) {
        if ($payout->wasChanged('status') && $payout->status === 'completed') {
            try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->payoutCompleted($payout); } catch (\Throwable $e) {}
        }
    });
}
```
`app/Models/WalletTransaction.php`
```php
protected static function booted(): void
{
    static::created(function (WalletTransaction $tx) {
        try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->walletCredited($tx); } catch (\Throwable $e) {}
    });
}
```
`app/Models/PaymentAttempt.php`
```php
protected static function booted(): void
{
    static::updated(function (PaymentAttempt $attempt) {
        if ($attempt->wasChanged('status') && $attempt->status === self::STATUS_FAILED) {
            try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->paymentFailed($attempt); } catch (\Throwable $e) {}
        }
    });
}
```
(If a model already has a `booted()` or `boot()` method, put the lines inside it instead.)

### app/Services/RefundService.php — right after `app(BranchManagementService::class)->reverseRefund(...)`
```php
try {
    app(\App\Services\WhatsApp\WhatsAppNotifier::class)->refundIssued($order->fresh(), (float) $refundAmount);
} catch (\Throwable $e) {
    Log::warning('WhatsApp refund message failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
}
```

### app/Services/GoldSubscriptionService.php — right after `$this->sendWelcomeEmail($subscription, $user);`
```php
try {
    app(\App\Services\WhatsApp\WhatsAppNotifier::class)->goldWelcome($subscription);
} catch (\Throwable $e) {
    Log::warning('Gold WhatsApp welcome failed.', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
}
```

### app/Http/Controllers/Api/DriverController.php
In `toggleStatus()`, right after `Cache::put("driver_status_{$driverId}", $status, now()->addDays(7));` (the one followed by `if ($isOnline) {`):
```php
if (($previousStatus['is_online'] ?? false) !== $isOnline) {
    try { app(\App\Services\WhatsApp\WhatsAppNotifier::class)->driverStatusChanged($user, $isOnline); } catch (\Throwable $e) {}
}
```
In `status()`, right after `app(GigLifecycleService::class)->checkOutOpenBooking($driverId);`:
```php
try {
    app(\App\Services\WhatsApp\WhatsAppNotifier::class)->driverStatusChanged(auth()->user(), false, 'You were taken offline because you have no active booked gig. Book a gig to go online again.');
} catch (\Throwable $e) {}
```

## 3. On the server, after uploading
```
php artisan migrate
php artisan route:clear      # new routes are invisible while routes are cached
php artisan view:clear
php artisan config:clear
```
Make sure the Laravel scheduler cron runs every minute (`php artisan schedule:run`), otherwise broadcasts and reminders will not send.

## 4. Check it worked
1. Open `https://yumma.in/admin/whatsapp`. The page loads and shows the provider forms.
2. Gupshup: enter API key, app name and number, turn WhatsApp on, press **Save**.
3. Copy the Callback URL shown. Open it in a browser: it must show `{"success":true,...}` (a 404 means the api.php routes are missing).
4. In Gupshup → Webhooks, add that URL, payload format **Gupshup format (v2)**.
5. Send "Hi" to your Gupshup number from your phone: the menu comes back.
6. Order a cheap item with **Cash on delivery**, then one with **Pay online**. Check the order in the admin and the live map link.
7. In Broadcasts, create templates (text must pass the checks), get them approved in Gupshup, and paste the Template IDs.

### Catalog schedule (add to routes/console.php with the others)

```php
Schedule::command('whatsapp:catalog-sync')->name('whatsapp-catalog-sync')->everyFifteenMinutes()->withoutOverlapping()
    ->when(fn () => app(\App\Services\WhatsApp\WhatsAppCatalogSync::class)->ready());
```

Meta setup: Commerce Manager catalog connected to the WhatsApp account, Catalog ID in the Meta form, token with `catalog_management`, then Settings → In-chat catalog → Switch on cart → Sync menu now.
