<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        if ($netDebited >= (float) $payout->amount) {
            $this->releaseLockedFundsIfNeeded($payout);
            return;
        }

        $payee = $this->payeeForPayout($payout);
        if (!$payee) {
            throw new RuntimeException('Payout recipient wallet could not be resolved.');
        }

        $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
        if (!$wallet || $wallet->balance < $payout->amount) {
            throw new RuntimeException('Payout completed externally but wallet settlement is not funded.');
        }

        $wallet->decrement('balance', $payout->amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => 'debit',
            'amount' => $payout->amount,
            'balance_after' => $wallet->balance,
            'reference_type' => 'payout',
            'reference_id' => $payout->id,
            'description' => 'Gateway payout settled',
            'created_by' => $payout->processed_by,
            'meta' => ['gateway' => $gateway],
        ]);
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
                'Payout retry reserved'
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
                if (! str_starts_with((string) $lockedPayout->idempotency_key, 'manual_')
                    && ! str_starts_with((string) $lockedPayout->idempotency_key, 'scheduled_')) {
                    $lockedPayout->update(['idempotency_key' => 'manual_legacy_' . Str::uuid()]);
                }

                if (! $this->reserveFunds(
                    $lockedPayout,
                    (float) $lockedPayout->amount - $netReserved,
                    'Legacy payout funds reserved'
                )) {
                    return false;
                }
            }

            $lockedPayout->update(['status' => 'processing']);
            $payout->setRawAttributes($lockedPayout->getAttributes(), true);

            return true;
        });
    }

    public function reserveFunds(Payout $payout, float $amount, string $description = 'Payout reserved'): bool
    {
        return DB::transaction(function () use ($payout, $amount, $description) {
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
                'meta' => ['source' => 'payout_retry'],
            ]);

            return true;
        });
    }

    public function releaseLockedFundsIfNeeded(Payout $payout, bool $restoreBalance = false): void
    {
        if (! str_starts_with((string) $payout->idempotency_key, 'manual_')
            && ! str_starts_with((string) $payout->idempotency_key, 'scheduled_')) {
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
