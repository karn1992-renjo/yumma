<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\DriverGig;
use App\Jobs\AutoMarkOrderPreparingJob;
use App\Services\AutoAssignDriverService;
use App\Services\GigIncentiveService;
use App\Models\AppSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;

// Auto-cancel pending orders after 15 minutes
Schedule::call(function () {
    Order::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(15))
        ->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Auto-cancelled: Payment not completed'
        ]);
})->everyFiveMinutes();

// Retry unassigned or unanswered delivery assignments.
Schedule::call(function () {
    app(AutoAssignDriverService::class)->retryPendingAssignments();
})->everyMinute();

// Move accepted orders to preparing after the 2-minute customer grace window.
Schedule::call(function () {
    Order::where('status', 'confirmed')
        ->whereNotNull('confirmed_at')
        ->where('confirmed_at', '<=', now()->subMinutes(2))
        ->limit(100)
        ->pluck('id')
        ->each(fn ($orderId) => AutoMarkOrderPreparingJob::dispatch((int) $orderId));
})->everyMinute();

// Mark completed gigs
Schedule::call(function () {
    DriverGig::where('status', 'booked')
        ->where('end_time', '<', now())
        ->get()
        ->each(function (DriverGig $gig) {
            DB::transaction(function () use ($gig) {
                $gig->update(['status' => 'completed']);

                $incentive = app(GigIncentiveService::class)->calculateGigEarnings($gig->fresh());
                $amount = (float) ($incentive->total_earned ?? 0);
                if ($amount <= 0 || !$gig->driver_id) {
                    return;
                }

                $wallet = Wallet::where('user_id', $gig->driver_id)->lockForUpdate()->first()
                    ?: Wallet::create([
                        'user_id' => $gig->driver_id,
                        'balance' => 0,
                        'locked_balance' => 0,
                        'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                        'is_active' => true,
                    ]);

                $exists = WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('reference_type', 'driver_gig_incentive')
                    ->where('reference_id', $gig->id)
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
                    'reference_type' => 'driver_gig_incentive',
                    'reference_id' => $gig->id,
                    'description' => 'Gig incentive for ' . ($gig->title ?: 'scheduled gig'),
                    'meta' => [
                        'source' => 'gig',
                        'orders_completed' => $incentive->orders_completed ?? [],
                    ],
                ]);
            });
        });
})->hourly();

// Cleanup old notifications
Schedule::call(function () {
    DB::table('notifications')
        ->where('created_at', '<', now()->subDays(30))
        ->delete();
})->daily();

Schedule::command('payouts:generate --auto')->dailyAt('02:00');
Schedule::command('payouts:process-scheduled')->everyThirtyMinutes();
Schedule::command('payouts:sync-status')->hourly();
Schedule::command('payouts:retry-failed --max-retries=3')->hourly();
Schedule::command('payouts:check-balance --alert-threshold=10000')->dailyAt('08:45');
Schedule::call(fn () => app(\App\Services\PayoutScheduleService::class)->sendAdminSummary())->dailyAt('09:00');
