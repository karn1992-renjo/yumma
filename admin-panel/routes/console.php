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

// Auto-cancel pending orders after 15 minutes
Schedule::call(function () {
    Order::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(15))
        ->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Auto-cancelled: Payment not completed'
        ]);
})->when(fn () => $cronTaskEnabled('auto_cancel_pending_orders'))->everyFiveMinutes();

// Retry unassigned or unanswered delivery assignments.
Schedule::call(function () {
    app(AutoAssignDriverService::class)->retryPendingAssignments();
})->when(fn () => $cronTaskEnabled('retry_pending_driver_assignments'))->everyMinute();

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
Schedule::command('payouts:check-balance --alert-threshold=10000')->when(fn () => $cronTaskEnabled('check_payout_balance'))->dailyAt('08:45');
Schedule::call(fn () => app(\App\Services\PayoutScheduleService::class)->sendAdminSummary())->when(fn () => $cronTaskEnabled('send_payout_admin_summary'))->dailyAt('09:00');
