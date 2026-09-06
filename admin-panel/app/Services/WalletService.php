<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

class WalletService
{
    /**
     * Debit a user's wallet balance instantly (no payment-gateway round trip),
     * row-locking the wallet and logging a WalletTransaction. Returns false on
     * insufficient balance -- callers are expected to run this inside their own
     * DB::transaction() so a false result can be turned into a rollback.
     */
    public function debitInstant(
        User $user,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): bool {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        if (! $wallet || (float) $wallet->balance < $amount) {
            return false;
        }

        $wallet->decrement('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);

        return true;
    }
}
