<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\RestaurantOnboardingIncentive;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayoutSettlementService
{
    public function settleFromGatewayResult(Payout $payout, array $gatewayResult, ?int $processedBy = null): string
    {
        $status = $this->resolveLocalStatus(
            $gatewayResult['gateway'] ?? $payout->gateway,
            $gatewayResult['gateway_status'] ?? null,
            'processing'
        );

        $settledStatus = $status;
        DB::transaction(function () use ($payout, $gatewayResult, $status, $processedBy, &$settledStatus) {
            $lockedPayout = Payout::lockForUpdate()->findOrFail($payout->id);
            if ($lockedPayout->status === 'completed' && $status !== 'completed') {
                $settledStatus = 'completed';
                return;
            }

            $lockedPayout->update([
                'status' => $status,
                'processed_at' => in_array($status, ['processing', 'completed'], true) ? now() : $lockedPayout->processed_at,
                'processed_by' => $processedBy ?: $lockedPayout->processed_by,
                'gateway' => $gatewayResult['gateway'] ?? $lockedPayout->gateway,
                'transaction_id' => $gatewayResult['transaction_id'] ?? $lockedPayout->transaction_id,
                'gateway_reference_id' => $gatewayResult['gateway_reference_id'] ?? $lockedPayout->gateway_reference_id,
                'gateway_status' => $gatewayResult['gateway_status'] ?? $lockedPayout->gateway_status,
                'idempotency_key' => $gatewayResult['idempotency_key'] ?? $lockedPayout->idempotency_key,
                'gateway_response' => $gatewayResult['response'] ?? $lockedPayout->gateway_response,
                'failure_reason' => $status === 'failed'
                    ? ($gatewayResult['failure_reason'] ?? $lockedPayout->failure_reason)
                    : null,
            ]);

            if ($status === 'completed') {
                $this->debitWalletIfNeeded($lockedPayout, $gatewayResult['gateway'] ?? $lockedPayout->gateway);
            } elseif ($status === 'failed') {
                $this->releaseLockedFundsIfNeeded($lockedPayout, true);
            }

            $this->syncOnboardingIncentives($lockedPayout, $status);

            $payout->setRawAttributes($lockedPayout->fresh()->getAttributes(), true);
        });

        return $settledStatus;
    }

    public function settleFromStatusPayload(Payout $payout, array $statusPayload, ?string $provider = null): string
    {
        $status = $this->resolveLocalStatus(
            $provider ?: $payout->gateway,
            $statusPayload['status'] ?? $statusPayload['gateway_status'] ?? $statusPayload['event'] ?? null,
            $payout->status
        );

        $settledStatus = $status;
        DB::transaction(function () use ($payout, $statusPayload, $status, $provider, &$settledStatus) {
            $lockedPayout = Payout::lockForUpdate()->findOrFail($payout->id);
            if ($lockedPayout->status === 'completed' && $status !== 'completed') {
                $settledStatus = 'completed';
                return;
            }

            $lockedPayout->update([
                'status' => $status,
                'processed_at' => $status === 'completed' ? ($lockedPayout->processed_at ?: now()) : $lockedPayout->processed_at,
                'gateway_status' => $statusPayload['status'] ?? $statusPayload['gateway_status'] ?? $statusPayload['event'] ?? $lockedPayout->gateway_status,
                'gateway_response' => $statusPayload['payload'] ?? $statusPayload,
                'failure_reason' => $status === 'failed'
                    ? ($statusPayload['message'] ?? $lockedPayout->failure_reason)
                    : $lockedPayout->failure_reason,
            ]);

            if ($status === 'completed') {
                $this->debitWalletIfNeeded($lockedPayout, $provider ?: $lockedPayout->gateway);
            } elseif ($status === 'failed') {
                $this->releaseLockedFundsIfNeeded($lockedPayout, true);
            }

            $this->syncOnboardingIncentives($lockedPayout, $status);

            $payout->setRawAttributes($lockedPayout->fresh()->getAttributes(), true);
        });

        return $settledStatus;
    }

    public function resolveLocalStatus(?string $provider, ?string $gatewayStatus, string $default = 'processing'): string
    {
        $status = strtolower(trim((string) $gatewayStatus));

        if ($status === '') {
            return $default;
        }

        if (in_array($status, [
            'success',
            'successful',
            'processed',
            'completed',
            'paid',
            'transfer.created',
            'transfer.paid',
            'payout.processed',
        ], true)) {
            return 'completed';
        }

        if (in_array($status, [
            'failed',
            'failure',
            'rejected',
            'reversed',
            'cancelled',
            'canceled',
            'transfer.failed',
            'transfer.reversed',
            'payout.failed',
            'payout.rejected',
            'payout.reversed',
        ], true)) {
            return 'failed';
        }

        if (in_array($status, [
            'queued',
            'queue',
            'pending',
            'processing',
            'processing_transfer',
            'received',
            'otp',
            'manual_review_required',
        ], true)) {
            return 'processing';
        }

        return $default;
    }

    private function debitWalletIfNeeded(Payout $payout, ?string $gateway = null): void
    {
        $transactions = WalletTransaction::where('reference_type', 'payout')
            ->where('reference_id', $payout->id)
            ->get(['type', 'amount']);
        $netDebited = (float) $transactions->where('type', 'debit')->sum('amount')
            - (float) $transactions->where('type', 'credit')->sum('amount');

        $payee = $this->payeeForPayout($payout);
        if (!$payee) {
            throw new RuntimeException('Payout recipient wallet could not be resolved.');
        }

        $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
        if (!$wallet) {
            throw new RuntimeException('Payout recipient wallet could not be resolved.');
        }

        $payoutAmount = (float) $payout->amount;
        $reservedAmount = min(max(0, $netDebited), $payoutAmount);
        $remainingAmount = max(0, $payoutAmount - $reservedAmount);

        if ($remainingAmount > 0 && (float) $wallet->balance < $remainingAmount) {
            throw new RuntimeException('Payout completed externally but wallet settlement is not funded.');
        }

        if ($reservedAmount > 0) {
            $this->releaseCompletedPayoutLockedFunds($wallet, $payout, $reservedAmount);
        }

        if ($remainingAmount > 0) {
            $wallet->decrement('balance', $remainingAmount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'debit',
                'amount' => $remainingAmount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'payout',
                'reference_id' => $payout->id,
                'description' => $gateway === 'cash' ? 'Cash payout settled' : 'Gateway payout settled',
                'created_by' => $payout->processed_by,
                'meta' => ['gateway' => $gateway, 'source' => 'payout_completion'],
            ]);
        }
    }

    private function releaseCompletedPayoutLockedFunds(Wallet $wallet, Payout $payout, float $reservedAmount): void
    {
        $amount = min((float) $wallet->locked_balance, $reservedAmount);

        if ($amount <= 0) {
            return;
        }

        $wallet->decrement('locked_balance', $amount);
        $wallet->refresh();
    }

    public function settlePartialCash(Payout $payout, float $amountPaid, array $meta = [], ?int $processedBy = null): string
    {
        return DB::transaction(function () use ($payout, $amountPaid, $meta, $processedBy) {
            $lockedPayout = Payout::lockForUpdate()->findOrFail($payout->id);

            if ($lockedPayout->status === 'completed') {
                return 'completed';
            }

            $payee = $this->payeeForPayout($lockedPayout->loadMissing(['restaurant.owner', 'driver']));
            if (! $payee) {
                throw new RuntimeException('Payout recipient wallet could not be resolved.');
            }

            $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
            if (! $wallet) {
                throw new RuntimeException('Payout recipient wallet could not be resolved.');
            }

            $payoutAmount = (float) $lockedPayout->amount;
            $alreadyPaid = (float) $lockedPayout->paid_amount;
            $remaining = max(0, $payoutAmount - $alreadyPaid);
            $amountPaid = min(max(0, $amountPaid), $remaining);

            if ($amountPaid <= 0) {
                throw new RuntimeException('This payout has no outstanding balance to settle.');
            }

            // Reservation-aware settlement (mirrors debitWalletIfNeeded): the
            // portion already reserved at payout generation was ALREADY debited
            // from wallet.balance and is held in locked_balance -- settling it is
            // just releasing the lock, NOT a second balance debit. Only the part
            // that was never reserved (legacy / manually created payouts) comes
            // out of balance now. This prevents the double-debit that made a
            // later payout for the same wallet look under-funded.
            $fromReserved = min($amountPaid, max(0, (float) $wallet->locked_balance));
            $fromBalance = round($amountPaid - $fromReserved, 2);

            if ($fromReserved > 0) {
                $this->releaseCompletedPayoutLockedFunds($wallet, $lockedPayout, $fromReserved);
            }

            if ($fromBalance > 0) {
                if ((float) $wallet->balance < $fromBalance) {
                    throw new RuntimeException('Vendor wallet does not have enough balance to settle this cash payout.');
                }
                $wallet->decrement('balance', $fromBalance);
                $wallet->refresh();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'type' => 'debit',
                    'amount' => $fromBalance,
                    'balance_after' => $wallet->balance,
                    'reference_type' => 'payout',
                    'reference_id' => $lockedPayout->id,
                    'description' => 'Cash payout settled from wallet balance',
                    'created_by' => $processedBy,
                    'meta' => array_merge(['source' => 'partial_cash_settlement'], $meta),
                ]);
            }

            $newPaidAmount = $alreadyPaid + $amountPaid;
            $isFullyPaid = $newPaidAmount >= $payoutAmount - 0.005;
            $status = $isFullyPaid ? 'completed' : 'partially_paid';

            $lockedPayout->update([
                'paid_amount' => $newPaidAmount,
                'status' => $status,
                'processed_at' => $isFullyPaid ? ($lockedPayout->processed_at ?: now()) : $lockedPayout->processed_at,
                'processed_by' => $processedBy ?: $lockedPayout->processed_by,
                'gateway' => 'cash',
                'transaction_id' => $meta['reference'] ?? $lockedPayout->transaction_id,
                'gateway_reference_id' => $meta['reference'] ?? $lockedPayout->gateway_reference_id,
                'gateway_status' => $isFullyPaid ? 'paid' : 'partially_paid',
                'failure_reason' => null,
            ]);

            if ($status === 'completed') {
                $this->syncOnboardingIncentives($lockedPayout, $status);
            }

            $payout->setRawAttributes($lockedPayout->fresh()->getAttributes(), true);

            return $status;
        });
    }

    public function reserveFundsForRetry(Payout $payout): bool
    {
        return DB::transaction(function () use ($payout) {
            $lockedPayout = Payout::with(['restaurant.owner', 'driver'])
                ->lockForUpdate()
                ->find($payout->id);
            if (! $lockedPayout || $lockedPayout->status !== 'failed') {
                return false;
            }

            $transactions = WalletTransaction::where('reference_type', 'payout')
                ->where('reference_id', $lockedPayout->id)
                ->get(['type', 'amount']);
            $netReserved = (float) $transactions->where('type', 'debit')->sum('amount')
                - (float) $transactions->where('type', 'credit')->sum('amount');
            $amountToReserve = max(0, (float) $lockedPayout->amount - $netReserved);

            if ($amountToReserve > 0 && ! $this->reserveFunds(
                $lockedPayout,
                $amountToReserve,
                'Payout retry reserved',
                'payout_retry'
            )) {
                return false;
            }

            $lockedPayout->update(['status' => 'pending', 'failure_reason' => null, 'next_retry_at' => null]);
            $payout->setRawAttributes($lockedPayout->getAttributes(), true);

            return true;
        });
    }

    public function ensureFundsReserved(Payout $payout): bool
    {
        return DB::transaction(function () use ($payout) {
            $lockedPayout = Payout::with(['restaurant.owner', 'driver'])
                ->lockForUpdate()
                ->find($payout->id);
            if (! $lockedPayout || $lockedPayout->status !== 'pending') {
                return false;
            }

            $transactions = WalletTransaction::where('reference_type', 'payout')
                ->where('reference_id', $lockedPayout->id)
                ->get(['type', 'amount']);
            $netReserved = (float) $transactions->where('type', 'debit')->sum('amount')
                - (float) $transactions->where('type', 'credit')->sum('amount');

            if ($netReserved < (float) $lockedPayout->amount) {
                if ($lockedPayout->source === null) {
                    $lockedPayout->update(['source' => 'legacy']);
                }

                if (! $this->reserveFunds(
                    $lockedPayout,
                    (float) $lockedPayout->amount - $netReserved,
                    'Legacy payout funds reserved',
                    'legacy_reservation'
                )) {
                    return false;
                }
            }

            $lockedPayout->update(['status' => 'processing']);
            $payout->setRawAttributes($lockedPayout->getAttributes(), true);

            return true;
        });
    }

    public function reserveFunds(Payout $payout, float $amount, string $description = 'Payout reserved', string $metaSource = 'payout_retry'): bool
    {
        return DB::transaction(function () use ($payout, $amount, $description, $metaSource) {
            $amount = max(0, $amount);
            $payee = $this->payeeForPayout($payout->loadMissing(['restaurant.owner', 'driver']));
            if (! $payee) {
                return false;
            }

            $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
            if (! $wallet || (float) $wallet->balance < $amount) {
                return false;
            }

            $wallet->decrement('balance', $amount);
            $wallet->increment('locked_balance', $amount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'payout',
                'reference_id' => $payout->id,
                'description' => $description,
                'created_by' => auth()->id(),
                'meta' => ['source' => $metaSource],
            ]);

            return true;
        });
    }

    public function releaseLockedFundsIfNeeded(Payout $payout, bool $restoreBalance = false): void
    {
        // Only skip when there is genuinely nothing to undo. A failure reversal
        // ($restoreBalance = true) must always run, including for legacy
        // (null-source) payouts, so money never stays stuck in locked_balance.
        if ($payout->source === null && ! $restoreBalance) {
            return;
        }

        $payee = $this->payeeForPayout($payout);
        if (! $payee) {
            return;
        }

        $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
        if (! $wallet || $wallet->locked_balance <= 0) {
            return;
        }

        $amount = min((float) $wallet->locked_balance, (float) $payout->amount);
        $wallet->decrement('locked_balance', $amount);

        if ($restoreBalance) {
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'payout',
                'reference_id' => $payout->id,
                'description' => 'Manual withdrawal funds released after payout failure',
                'created_by' => $payout->processed_by,
            ]);
        }
    }


    private function syncOnboardingIncentives(Payout $payout, string $status): void
    {
        if (! $payout->driver_id) {
            return;
        }

        $baseQuery = RestaurantOnboardingIncentive::where('payout_id', $payout->id);
        $onboardingIds = (clone $baseQuery)->pluck('restaurant_onboarding_id')->values()->all();

        if ($onboardingIds === []) {
            return;
        }

        if ($status === 'completed') {
            (clone $baseQuery)->update([
                'status' => RestaurantOnboardingIncentive::STATUS_PAID,
                'paid_at' => now(),
            ]);

            \App\Models\RestaurantOnboarding::whereIn('id', $onboardingIds)->update([
                'incentive_status' => RestaurantOnboardingIncentive::STATUS_PAID,
            ]);

            \App\Models\RestaurantOnboarding::with('driver')
                ->whereIn('id', $onboardingIds)
                ->get()
                ->each(fn ($onboarding) => app(RestaurantOnboardingNotificationService::class)->driver(
                    $onboarding,
                    'Onboarding incentive paid',
                    'Your restaurant onboarding incentive payout has been marked paid.',
                    'restaurant_onboarding_incentive_paid'
                ));

            return;
        }

        if ($status === 'failed') {
            (clone $baseQuery)->update([
                'status' => RestaurantOnboardingIncentive::STATUS_EARNED,
                'payout_id' => null,
                'paid_at' => null,
            ]);

            \App\Models\RestaurantOnboarding::whereIn('id', $onboardingIds)->update([
                'incentive_status' => RestaurantOnboardingIncentive::STATUS_EARNED,
            ]);
        }
    }
    private function payeeForPayout(Payout $payout)
    {
        if ($payout->driver_id && $payout->driver) {
            return $payout->driver;
        }

        if ($payout->restaurant && $payout->restaurant->owner) {
            return $payout->restaurant->owner;
        }

        return null;
    }
}
