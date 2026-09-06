<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantAdWallet;
use App\Models\RestaurantAdWalletTransaction;
use Illuminate\Support\Facades\DB;

class AdWalletService
{
    /**
     * Debit a restaurant's ad wallet balance instantly, row-locking the wallet
     * and logging a RestaurantAdWalletTransaction. Returns null on insufficient
     * balance -- callers are expected to run this inside their own
     * DB::transaction() so a null result can be turned into a rollback.
     */
    public function debit(
        Restaurant $restaurant,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): ?RestaurantAdWalletTransaction {
        $wallet = RestaurantAdWallet::where('restaurant_id', $restaurant->id)->lockForUpdate()->first();

        if (! $wallet || (float) $wallet->balance < $amount) {
            return null;
        }

        $wallet->decrement('balance', $amount);
        $wallet->refresh();

        return RestaurantAdWalletTransaction::create([
            'restaurant_ad_wallet_id' => $wallet->id,
            'restaurant_id' => $restaurant->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);
    }

    /**
     * Credit a restaurant's ad wallet, guarded by an idempotency check on
     * (reference_type, reference_id) so a retried top-up webhook never
     * double-credits the wallet. Creates the wallet row on first use.
     */
    public function credit(
        Restaurant $restaurant,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): ?RestaurantAdWalletTransaction {
        return DB::transaction(function () use ($restaurant, $amount, $referenceType, $referenceId, $description) {
            $wallet = RestaurantAdWallet::where('restaurant_id', $restaurant->id)->lockForUpdate()->first()
                ?: RestaurantAdWallet::create([
                    'restaurant_id' => $restaurant->id,
                    'balance' => 0,
                    'currency' => strtoupper(\App\Models\AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                    'is_active' => true,
                ]);

            $exists = RestaurantAdWalletTransaction::where('restaurant_ad_wallet_id', $wallet->id)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->exists();

            if ($exists) {
                return null;
            }

            $wallet->increment('balance', $amount);
            $wallet->refresh();

            return RestaurantAdWalletTransaction::create([
                'restaurant_ad_wallet_id' => $wallet->id,
                'restaurant_id' => $restaurant->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
            ]);
        });
    }
}
