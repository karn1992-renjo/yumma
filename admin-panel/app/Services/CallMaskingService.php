<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CallLog;
use App\Models\CallMaskingPool;
use App\Models\Order;
use App\Models\OrderCallMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * All call-masking business logic lives here. ExotelService is a dumb HTTP
 * client; this class decides *whether* to mask, which pool number to use,
 * and how to degrade gracefully when masking can't happen -- masking must
 * never block order creation/assignment/delivery or an in-progress call.
 */
class CallMaskingService
{
    public function __construct(private ExotelService $exotel)
    {
    }

    public function maskingMode(): string
    {
        $mode = strtolower(trim((string) AppSetting::getValue('phone_masking_mode', 'raw')));

        return $mode === 'exotel' ? 'exotel' : 'raw';
    }

    public function isMaskingActive(): bool
    {
        return $this->maskingMode() === 'exotel';
    }

    /**
     * Redaction choke point used by every order-serializing controller.
     * Returns null when masking is active so callers never leak a real
     * number into an API response; passes through unchanged in raw mode.
     */
    public function redactPhone(?string $realPhone): ?string
    {
        return $this->isMaskingActive() ? null : $realPhone;
    }

    /**
     * Idempotent: creates the mapping row (checking out a pool number) if
     * none exists yet for this order, or fills in the driver leg on an
     * existing mapping once a driver is assigned. Never throws -- a masking
     * failure here must not block order creation/assignment.
     */
    public function ensureMappingForOrder(Order $order): ?OrderCallMapping
    {
        if (! $this->isMaskingActive()) {
            return null;
        }

        try {
            $order->loadMissing(['customer', 'restaurant', 'driver']);

            $mapping = OrderCallMapping::where('order_id', $order->id)->first();

            if (! $mapping) {
                $pool = $this->checkoutPoolNumber($order->id);

                $mapping = OrderCallMapping::create([
                    'order_id' => $order->id,
                    'call_masking_pool_id' => $pool?->id,
                    'exophone' => $pool?->exophone,
                    'customer_id' => $order->customer_id,
                    'customer_phone_snapshot' => $order->customer_phone ?: $order->customer?->phone,
                    'restaurant_id' => $order->restaurant_id,
                    'restaurant_phone_snapshot' => $order->restaurant?->phone,
                    'status' => $pool ? 'active' : 'pool_exhausted',
                ]);
            }

            if ($order->driver_id && ! $mapping->driver_id) {
                $mapping->update([
                    'driver_id' => $order->driver_id,
                    'driver_phone_snapshot' => $order->driver?->phone,
                ]);
            }

            return $mapping;
        } catch (\Throwable $e) {
            Log::warning('Call masking: failed to create/update order mapping.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function releaseMappingForOrder(Order $order): void
    {
        try {
            $mapping = OrderCallMapping::where('order_id', $order->id)->where('status', 'active')->first();
            if (! $mapping) {
                return;
            }

            $mapping->update(['status' => 'expired', 'released_at' => now()]);

            if ($mapping->call_masking_pool_id) {
                CallMaskingPool::whereKey($mapping->call_masking_pool_id)->update([
                    'status' => 'available',
                    'current_order_id' => null,
                    'released_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Call masking: failed to release order mapping.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * For the customer dial-in endpoint. $target is 'restaurant' or
     * 'driver'. Returns the number the customer app should dial, falling
     * back to the real number on any failure so the call always works.
     *
     * @return array{number: ?string, provider: string}
     */
    public function numberForDialIn(Order $order, string $target): array
    {
        $realNumber = $this->realNumberFor($order, $target);

        if (! $this->isMaskingActive()) {
            return ['number' => $realNumber, 'provider' => 'raw'];
        }

        $mapping = $this->ensureMappingForOrder($order);
        $exophone = $mapping?->exophone;

        if (! $exophone) {
            $this->logCall($order, 'dial_in', 'customer', null, 'customer', $target, null, null, 'raw_fallback', 'skipped_no_pool_number');

            return ['number' => $realNumber, 'provider' => 'raw_fallback'];
        }

        $this->logCall($order, 'dial_in', 'customer', null, 'customer', $target, $mapping->id, $exophone, 'exotel', 'mapped');

        return ['number' => $exophone, 'provider' => 'exotel'];
    }

    /**
     * For restaurant/driver click-to-call endpoints. Resolves both real
     * numbers server-side and asks Exotel to bridge them, masked by the
     * order's pool Exophone as caller ID. On any Exotel failure or an
     * exhausted pool, retries once unmasked (real number as CallerId) so
     * the call still completes -- confirmed acceptable trade-off over
     * failing the call outright.
     *
     * @return array{success: bool, message: ?string}
     */
    public function initiateClickToCall(Order $order, string $fromRole, string $toRole, ?int $initiatorUserId): array
    {
        $fromNumber = $this->realNumberFor($order, $fromRole);
        $toNumber = $this->realNumberFor($order, $toRole);

        if (! $fromNumber || ! $toNumber) {
            return ['success' => false, 'message' => 'A phone number is not available for this call.'];
        }

        $mapping = $this->isMaskingActive() ? $this->ensureMappingForOrder($order) : null;
        $exophone = $mapping?->exophone;

        $attempt = $this->attemptConnectCall($order, $fromRole, $toRole, $fromNumber, $toNumber, $exophone ?: $fromNumber);
        $providerUsed = $exophone ? 'exotel' : 'raw_fallback';
        $failureReason = null;

        if (! $attempt['success'] && $exophone) {
            Log::warning('Call masking: masked click-to-call failed, retrying unmasked.', [
                'order_id' => $order->id,
                'from_role' => $fromRole,
                'to_role' => $toRole,
                'message' => $attempt['error'],
            ]);

            $failureReason = $attempt['error'];
            $attempt = $this->attemptConnectCall($order, $fromRole, $toRole, $fromNumber, $toNumber, $fromNumber);
            $providerUsed = 'raw_fallback';
        }

        if ($attempt['success']) {
            $this->logCall(
                $order, 'click_to_call', $fromRole, $initiatorUserId, $fromRole, $toRole,
                $mapping?->id, $exophone, $providerUsed, $attempt['status'] ?? 'queued', $attempt['sid'] ?? null
            );

            return ['success' => true, 'message' => null];
        }

        Log::warning('Call masking: click-to-call failed, order flow unaffected.', [
            'order_id' => $order->id,
            'from_role' => $fromRole,
            'to_role' => $toRole,
            'message' => $attempt['error'],
        ]);

        $reason = $failureReason ? "{$failureReason}; unmasked retry: {$attempt['error']}" : $attempt['error'];

        $this->logCall(
            $order, 'click_to_call', $fromRole, $initiatorUserId, $fromRole, $toRole,
            $mapping?->id, $exophone, 'raw_fallback', 'failed', null, $reason
        );

        return ['success' => false, 'message' => 'Could not place the call. Please try again.'];
    }

    /**
     * @return array{success: bool, sid?: ?string, status?: ?string, error?: string}
     */
    private function attemptConnectCall(Order $order, string $fromRole, string $toRole, string $fromNumber, string $toNumber, string $callerId): array
    {
        try {
            $result = $this->exotel->connectCall($fromNumber, $toNumber, $callerId, [
                'order_id' => $order->id,
                'from_role' => $fromRole,
                'to_role' => $toRole,
            ]);

            return ['success' => true, 'sid' => $result['sid'] ?? null, 'status' => $result['status'] ?? 'queued'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function realNumberFor(Order $order, string $role): ?string
    {
        $order->loadMissing(['customer', 'restaurant', 'driver']);

        return match ($role) {
            'customer' => $order->customer_phone ?: $order->customer?->phone,
            'restaurant' => $order->restaurant?->phone,
            'driver' => $order->driver?->phone,
            default => null,
        };
    }

    /**
     * Atomic pool checkout under a row lock so two orders can never claim
     * the same Exophone concurrently.
     */
    private function checkoutPoolNumber(int $orderId): ?CallMaskingPool
    {
        return DB::transaction(function () use ($orderId) {
            $pool = CallMaskingPool::where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                return null;
            }

            $pool->update([
                'status' => 'in_use',
                'current_order_id' => $orderId,
                'assigned_at' => now(),
                'released_at' => null,
            ]);

            return $pool;
        });
    }

    private function logCall(
        Order $order,
        string $direction,
        string $initiatorRole,
        ?int $initiatorUserId,
        string $legFrom,
        string $legTo,
        ?int $mappingId,
        ?string $exophone,
        string $providerUsed,
        ?string $status,
        ?string $callSidOrFailureReason = null
    ): void {
        try {
            CallLog::create([
                'order_id' => $order->id,
                'order_call_mapping_id' => $mappingId,
                'direction' => $direction,
                'initiator_role' => $initiatorRole,
                'initiator_user_id' => $initiatorUserId,
                'leg_from' => $legFrom,
                'leg_to' => $legTo,
                'exotel_call_sid' => $status === 'failed' ? null : $callSidOrFailureReason,
                'exophone' => $exophone,
                'provider_used' => $providerUsed,
                'status' => $status,
                'failure_reason' => $status === 'failed' ? $callSidOrFailureReason : null,
                'initiated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Call masking: failed to write call log.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
