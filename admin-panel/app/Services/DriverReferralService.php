<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\UserReferral;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverReferralService
{
    /**
     * Settles a driver's referral bonus the moment their FIRST delivered
     * order finishes payout processing. Must be called from inside the same
     * DB transaction as PayoutCalculationService::processOrderEarnings() --
     * mirrors that method's own idempotency guard (checks the referral row
     * itself, not a wallet-transaction lookup) since this only ever runs once
     * per referred driver by construction (first delivery only).
     */
    public function settleOnFirstDelivery(Order $order): void
    {
        if (! $order->driver_id || ! AppSetting::getValue('driver_referral_enabled', '0')) {
            return;
        }

        $referral = UserReferral::query()
            ->where('referred_user_id', $order->driver_id)
            ->whereIn('status', ['registered', 'qualified'])
            ->lockForUpdate()
            ->first();

        if (! $referral || ! $referral->referrer_id) {
            return;
        }

        $deliveredOrderCount = Order::where('driver_id', $order->driver_id)
            ->where('status', 'delivered')
            ->where('payout_processed', true)
            ->count();

        if ($deliveredOrderCount !== 1) {
            // Not this driver's first completed delivery -- either it
            // already qualified earlier (shouldn't happen given the status
            // guard above, but stay defensive) or something re-ran payout
            // processing out of order.
            return;
        }

        $referrerBonus = round((float) AppSetting::getValue('driver_referral_referrer_bonus', 0), 2);
        $refereeBonus = round((float) AppSetting::getValue('driver_referral_referee_bonus', 0), 2);

        $referrer = User::find($referral->referrer_id);
        $referee = User::find($order->driver_id);

        if ($referrer && $referrerBonus > 0) {
            $this->creditWallet($referrer, $referrerBonus, $order, 'Driver referral bonus for referring a new driver');
        }

        if ($referee && $refereeBonus > 0) {
            $this->creditWallet($referee, $refereeBonus, $order, 'Driver referral bonus for signing up with a referral code');
        }

        $referral->update([
            'status' => 'credited',
            'qualified_order_id' => $order->id,
            'bonus_type' => 'wallet_credit',
            'amount' => $referrerBonus,
            'qualified_at' => $referral->qualified_at ?? now(),
            'credited_at' => now(),
            'metadata' => array_merge((array) $referral->metadata, [
                'program' => 'driver',
                'event' => 'first_delivery',
                'order_id' => $order->id,
                'referrer_bonus' => $referrerBonus,
                'referee_bonus' => $refereeBonus,
            ]),
        ]);
    }

    private function creditWallet(User $user, float $amount, Order $order, string $description): void
    {
        $referenceType = 'driver_referral_bonus';
        $alreadyCredited = WalletTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $order->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyCredited) {
            return;
        }

        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?: Wallet::create(['user_id' => $user->id, 'balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]);

        $wallet->increment('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $order->id,
            'description' => $description,
        ]);

        Log::info('Driver referral bonus credited.', [
            'user_id' => $user->id,
            'amount' => $amount,
            'order_id' => $order->id,
        ]);
    }
}
