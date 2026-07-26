<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Jobs\AutoMarkOrderPreparingJob;
use App\Services\AutoAssignDriverService;
use App\Services\GigIncentiveService;
use App\Models\AppSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;

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

// Mark completed gigs
Schedule::call(function () {
    DriverGig::whereIn('status', ['available', 'booked'])
        ->where('end_time', '<', now())
        ->get()
        ->each(function (DriverGig $gig) {
            DB::transaction(function () use ($gig) {
                $gig->update(['status' => 'completed']);

                DriverGigBooking::where('driver_gig_id', $gig->id)
                    ->where('status', 'booked')
                    ->get()
                    ->each(function (DriverGigBooking $booking) use ($gig) {
                        $booking->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);

                        $incentive = app(GigIncentiveService::class)->calculateGigEarnings($gig->fresh(), $booking->driver_id);
                        $amount = (float) ($incentive->total_earned ?? 0);
                        if ($amount <= 0) {
                            return;
                        }

                        $wallet = Wallet::where('user_id', $booking->driver_id)->lockForUpdate()->first()
                            ?: Wallet::create([
                                'user_id' => $booking->driver_id,
                                'balance' => 0,
                                'locked_balance' => 0,
                                'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                                'is_active' => true,
                            ]);

                        $exists = WalletTransaction::where('wallet_id', $wallet->id)
                            ->where('reference_type', 'driver_gig_booking_incentive')
                            ->where('reference_id', $booking->id)
                            ->exists();

                        if ($exists) {
                            return;
                        }

                        $wallet->increment('balance', $amount);
                        $wallet->refresh();

                        WalletTransaction::create([
                            'wallet_id' => $wallet->id,
                            'user_id' => $wallet->user_id,
                            'type' => 'credit',
                            'amount' => $amount,
                            'balance_after' => $wallet->balance,
                            'reference_type' => 'driver_gig_booking_incentive',
                            'reference_id' => $booking->id,
                            'description' => 'Gig incentive for ' . ($gig->title ?: 'scheduled gig'),
                            'meta' => [
                                'source' => 'gig',
                                'driver_gig_id' => $gig->id,
                                'orders_completed' => $incentive->orders_completed ?? [],
                            ],
                        ]);
                    });
            });
        });
})->when(fn () => $cronTaskEnabled('mark_completed_gigs'))->hourly();

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
