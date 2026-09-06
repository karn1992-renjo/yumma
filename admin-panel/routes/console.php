<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Jobs\AutoMarkOrderPreparingJob;
use App\Services\AutoAssignDriverService;
use App\Services\GigIncentiveService;
use App\Services\GigLifecycleService;
use App\Services\GigDemandForecastService;
use App\Services\GigMlForecastService;
use App\Services\GigExternalSignalService;
use App\Services\GigOperationsBroadcastService;
use App\Models\AppSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\MenuItem;

$cronTaskEnabled = static function (string $key): bool {
    try {
        $disabled = json_decode((string) AppSetting::getValue('disabled_cron_tasks', '[]'), true);
    } catch (\Throwable $exception) {
        return true;
    }

    return !in_array($key, is_array($disabled) ? $disabled : [], true);
};


// Retry unassigned or unanswered delivery assignments.
Schedule::call(function () {
    app(AutoAssignDriverService::class)->retryPendingAssignments();
})->when(fn () => $cronTaskEnabled('retry_pending_driver_assignments'))->everyMinute();

// Exotel fallback: ring the driver / restaurant if an order has been sitting
// unaccepted longer than the configured threshold (no queue worker on this
// host, so this is a minute-granularity scan rather than a delayed job).
Schedule::call(function () {
    if (! \App\Jobs\OrderAcceptanceAlertCallJob::enabled()) {
        return;
    }
    $delay = \App\Jobs\OrderAcceptanceAlertCallJob::delaySeconds();
    $now = now();

    Order::query()
        ->whereNotNull('driver_id')
        ->whereNull('driver_accepted_at')
        ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
        ->whereNotNull('driver_assigned_at')
        ->where('driver_assigned_at', '<=', $now->copy()->subSeconds($delay))
        ->where('driver_assigned_at', '>=', $now->copy()->subMinutes(10))
        ->limit(50)
        ->pluck('id')
        ->each(fn ($id) => \App\Jobs\OrderAcceptanceAlertCallJob::dispatchSync((int) $id, 'driver'));

    Order::query()
        ->where('status', 'pending')
        ->where('created_at', '<=', $now->copy()->subSeconds($delay))
        ->where('created_at', '>=', $now->copy()->subMinutes(15))
        ->limit(50)
        ->pluck('id')
        ->each(fn ($id) => \App\Jobs\OrderAcceptanceAlertCallJob::dispatchSync((int) $id, 'restaurant'));
})->when(fn () => $cronTaskEnabled('exotel_order_acceptance_alert_calls'))->everyMinute();

// Move accepted orders to preparing after the 2-minute customer grace window.
Schedule::call(function () {
    Order::where('status', 'confirmed')
        ->whereNotNull('confirmed_at')
        ->where('confirmed_at', '<=', now()->subMinutes(2))
        ->limit(100)
        ->pluck('id')
        ->each(fn ($orderId) => AutoMarkOrderPreparingJob::dispatch((int) $orderId));
})->when(fn () => $cronTaskEnabled('auto_mark_confirmed_orders_preparing'))->everyMinute();

// Restore menu items after a restaurant-selected out-of-stock period.
Schedule::call(function () {
    MenuItem::query()
        ->where('is_available', false)
        ->whereNotNull('unavailable_until')
        ->where('unavailable_until', '<=', now())
        ->update([
            'is_available' => true,
            'unavailable_until' => null,
            'updated_at' => now(),
        ]);
})->when(fn () => $cronTaskEnabled('restore_timed_menu_items'))->everyMinute();

// Refresh local weather/event/traffic signal baselines for gig forecasts.
Schedule::call(function () {
    app(GigExternalSignalService::class)->refresh(today());
    app(GigExternalSignalService::class)->refresh(today()->addDay());
    app(GigOperationsBroadcastService::class)->broadcast();
})->when(fn () => $cronTaskEnabled('refresh_gig_external_signals'))->hourly();
// Train local ML-style gig predictions from order history and ingested signals.
Schedule::call(function () {
    app(GigMlForecastService::class)->trainAndServe(today());
    app(GigMlForecastService::class)->trainAndServe(today()->addDay());
})->when(fn () => $cronTaskEnabled('train_gig_ml_forecasts'))->hourly();
// Refresh gig demand forecasts and surge recommendations.
Schedule::call(function () {
    app(GigDemandForecastService::class)->forecastDay(today());
    app(GigDemandForecastService::class)->forecastDay(today()->addDay());
})->when(fn () => $cronTaskEnabled('forecast_gig_demand'))->hourly();
// Mark completed/no-show gig bookings and credit eligible incentives.
Schedule::call(function () {
    app(GigLifecycleService::class)->finalizeDueGigs();
})->when(fn () => $cronTaskEnabled('mark_completed_gigs'))->everyFiveMinutes();

// Remind drivers before booked gig slots begin.
Schedule::call(function () {
    app(GigLifecycleService::class)->sendUpcomingReminders();
})->when(fn () => $cronTaskEnabled('send_gig_reminders'))->everyFiveMinutes();
// Cleanup old notifications
Schedule::call(function () {
    DB::table('notifications')
        ->where('created_at', '<', now()->subDays(30))
        ->delete();
})->when(fn () => $cronTaskEnabled('cleanup_old_notifications'))->daily();

Schedule::command('payouts:generate --auto')->when(fn () => $cronTaskEnabled('generate_auto_payouts'))->dailyAt('02:00');
Schedule::command('payouts:process-scheduled')->when(fn () => $cronTaskEnabled('process_scheduled_payouts'))->everyThirtyMinutes();
Schedule::command('payouts:sync-status')->when(fn () => $cronTaskEnabled('sync_payout_status'))->hourly();
Schedule::command('payouts:retry-failed --max-retries=3')->when(fn () => $cronTaskEnabled('retry_failed_payouts'))->hourly();
Schedule::command('payouts:release-stranded-locks')->when(fn () => $cronTaskEnabled('release_stranded_payout_locks'))->hourly();
Schedule::command('payouts:check-balance --alert-threshold=10000')->when(fn () => $cronTaskEnabled('check_payout_balance'))->dailyAt('08:45');
Schedule::command('ai:management-cycle operations --trigger=scheduled')->when(fn () => $cronTaskEnabled('ai_management_cycle'))->everyFifteenMinutes();
// Review whether customers, drivers, and restaurants should get an AI-generated push notification this hour.
Schedule::call(function () {
    app(\App\Services\Ai\Managers\AiNotificationManager::class)->run();
})->when(fn () => $cronTaskEnabled('ai_role_notification_review'))->hourly();
// Safety net: deliver AI push broadcasts stuck "pending" because their artwork
// job was never processed (no queue worker). Also sends text-only after a while.
Schedule::command('ai:flush-stale-notifications')
    ->when(fn () => $cronTaskEnabled('ai_flush_stale_notifications'))
    ->everyTwoMinutes()
    ->withoutOverlapping(10); // 10-min lock TTL so a killed run can't wedge it for 24h
// Remind customers who left items in their cart without ordering.
Schedule::call(function () {
    app(\App\Services\CartRecoveryService::class)->run();
})->when(fn () => $cronTaskEnabled('cart_recovery_reminders'))->everyFifteenMinutes();
// Nudge customers who haven't ordered in a while to reorder their favorite.
Schedule::call(function () {
    app(\App\Services\ReorderNudgeService::class)->run();
})->when(fn () => $cronTaskEnabled('reorder_nudges'))->dailyAt('11:00');
Schedule::call(fn () => app(\App\Services\PayoutScheduleService::class)->sendAdminSummary())->when(fn () => $cronTaskEnabled('send_payout_admin_summary'))->dailyAt('09:00');
// Send restaurant business reports to owners based on admin-selected frequency.
Schedule::command('restaurants:business-reports')->when(fn () => $cronTaskEnabled('send_restaurant_business_reports'))->dailyAt((string) AppSetting::getValue('restaurant_business_report_time', '08:00'));

// Nag restaurant owners once a day about AI proposals still pending their approval.
Schedule::call(function () {
    app(\App\Services\RestaurantApprovalReminderService::class)->run();
})->when(fn () => $cronTaskEnabled('restaurant_ai_approval_reminders'))->dailyAt('10:00');
// Weekly item-wise demand review -- propose menu price changes for restaurant approval.
Schedule::call(function () {
    app(\App\Services\Ai\Managers\AiMenuPricingManager::class)->run();
})->when(fn () => $cronTaskEnabled('ai_menu_pricing_review'))->weeklyOn(1, '07:00');
// Auto-create gig slots ahead of forecasted driver shortages (opt-in: Settings > AI).
Schedule::call(function () {
    app(\App\Services\Ai\Managers\AiGigProvisioningManager::class)->run();
})->when(fn () => $cronTaskEnabled('ai_gig_autoprovision'))->hourly();
Schedule::command('sitemap:generate')->dailyAt('03:00');
// Auto-apply / clear the zone surge fee based on live weather in each delivery area (opt-in via Settings).
Schedule::command('weather:apply-surge')
    ->when(fn () => $cronTaskEnabled('apply_weather_surge'))
    ->everyThirtyMinutes()
    ->withoutOverlapping(20);

