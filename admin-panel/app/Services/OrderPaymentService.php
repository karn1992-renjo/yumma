<?php

namespace App\Services;

use App\Events\PaymentStatusUpdatedEvent;
use App\Helpers\FirebaseHelper;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Transaction;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrderPaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly FirebaseHelper $firebase,
    ) {
    }

    public function createCustomerPayment(Order $order, string $gateway, int $userId): PaymentAttempt
    {
        $attempt = DB::transaction(function () use ($order, $gateway, $userId) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($lockedOrder);

            $attempt = $this->createAttempt($lockedOrder, $gateway, PaymentAttempt::SOURCE_CUSTOMER_APP, $userId);
            $created = $this->gateways->gateway($gateway)->createCustomerPayment($lockedOrder->loadMissing('customer'), $attempt);
            $this->applyGatewayCreation($attempt, $created, PaymentAttempt::STATUS_PENDING);

            $lockedOrder->increment('payment_attempts_count');

            return $attempt->fresh();
        });

        $this->broadcastPayment($attempt->order, 'payment.pending', $attempt);

        return $attempt;
    }

    public function createDriverPaymentLink(Order $order, string $gateway, int $driverId): PaymentAttempt
    {
        $createdNewAttempt = false;

        $attempt = DB::transaction(function () use ($order, $gateway, $driverId, &$createdNewAttempt) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertDriverOwnsOrder($lockedOrder, $driverId);
            $this->assertPayable($lockedOrder);

            $this->expireExpiredDriverQrAttempts($lockedOrder);

            $activeAttempt = $this->activeDriverQrAttempt($lockedOrder);
            if ($activeAttempt) {
                $this->cancelDuplicateDriverQrAttempts($lockedOrder, $activeAttempt->id);
                $lockedOrder->forceFill([
                    'payment_gateway' => $activeAttempt->gateway,
                    'payment_link_id' => $activeAttempt->payment_link_id,
                ])->save();

                return $activeAttempt->fresh();
            }

            $attempt = $this->createAttempt($lockedOrder, $gateway, PaymentAttempt::SOURCE_DRIVER_QR, $driverId, $driverId);
            $created = $this->gateways->gateway($gateway)->createPaymentLink($lockedOrder->loadMissing('customer'), $attempt);
            $this->applyGatewayCreation($attempt, $created, PaymentAttempt::STATUS_ACTIVE);
            $createdNewAttempt = true;
            $createdNewAttempt = true;

            $lockedOrder->forceFill([
                'payment_gateway' => $gateway,
                'payment_link_id' => $attempt->payment_link_id,
            ])->save();
            $lockedOrder->increment('payment_attempts_count');

            return $attempt->fresh();
        });

        if ($attempt->source === PaymentAttempt::SOURCE_DRIVER_QR && $attempt->status === PaymentAttempt::STATUS_ACTIVE) {
            $attempt = $this->ensureDriverQrRenderable($attempt);
        }

        if ($createdNewAttempt) {
            $this->broadcastPayment($attempt->order, 'driver.collection.started', $attempt);
        }

        return $attempt;
    }

    public function markDriverCashReceived(Order $order, float $amount, ?string $notes, int $driverId): PaymentAttempt
    {
        $attempt = DB::transaction(function () use ($order, $amount, $notes, $driverId) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertDriverOwnsOrder($lockedOrder, $driverId);
            $this->assertPayable($lockedOrder);

            $expected = round((float) $lockedOrder->total, 2);
            if (round($amount, 2) !== $expected) {
                throw new UnprocessableEntityHttpException('Cash amount must match the order total.');
            }

            $this->expireActiveDriverQrAttempts($lockedOrder);

            $attempt = PaymentAttempt::create([
                'order_id' => $lockedOrder->id,
                'gateway' => 'cash',
                'source' => PaymentAttempt::SOURCE_DRIVER_CASH,
                'status' => PaymentAttempt::STATUS_SUCCESS,
                'amount' => $expected,
                'currency' => $this->currency(),
                'transaction_id' => 'cash_' . $lockedOrder->id . '_' . now()->timestamp,
                'created_by' => $driverId,
                'driver_id' => $driverId,
                'collection_notes' => $notes,
                'paid_at' => now(),
                'idempotency_key' => 'driver_cash_' . $lockedOrder->id . '_' . Str::uuid(),
            ]);

            $this->markOrderPaid($lockedOrder, $attempt, [
                'payment_method' => 'cash',
                'payment_source' => PaymentAttempt::SOURCE_DRIVER_CASH,
                'payment_gateway' => 'cash',
                'cash_collected_amount' => $expected,
                'cash_collected_at' => now(),
            ]);
            $lockedOrder->increment('payment_attempts_count');

            return $attempt->fresh();
        });

        $this->broadcastPayment($attempt->order, 'payment.completed', $attempt);

        return $attempt;
    }

    public function completeFromWebhook(string $gateway, array $event): ?PaymentAttempt
    {
        $releaseOrderId = null;

        $attempt = DB::transaction(function () use ($gateway, $event, &$releaseOrderId) {
            $attempt = $this->resolveAttempt($gateway, $event);
            if (! $attempt) {
                return null;
            }

            $attempt = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (! $attempt) {
                return null;
            }

            if ($attempt->status === PaymentAttempt::STATUS_SUCCESS) {
                return $attempt;
            }

            $order = Order::whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status === 'success') {
                $attempt->forceFill([
                    'status' => PaymentAttempt::STATUS_CANCELLED,
                    'webhook_event_id' => $event['event_id'] ?? $attempt->webhook_event_id,
                    'gateway_payload' => $event['payload'] ?? $attempt->gateway_payload,
                ])->save();

                return $attempt;
            }

            $isSuccessfulPayment = $this->isSuccessfulGatewayStatus($gateway, (string) ($event['status'] ?? ''), (string) ($event['event'] ?? ''));

            if ($attempt->isExpired() && ! $isSuccessfulPayment) {
                $attempt->forceFill([
                    'status' => PaymentAttempt::STATUS_EXPIRED,
                    'webhook_event_id' => $event['event_id'] ?? null,
                    'gateway_payload' => $event['payload'] ?? null,
                ])->save();
                $this->broadcastPayment($order, 'payment.expired', $attempt);

                return $attempt;
            }

            if (! $isSuccessfulPayment) {
                $attempt->forceFill([
                    'status' => PaymentAttempt::STATUS_FAILED,
                    'webhook_event_id' => $event['event_id'] ?? null,
                    'gateway_payload' => $event['payload'] ?? null,
                ])->save();

                return $attempt;
            }

            if (isset($event['amount']) && round((float) $event['amount'], 2) !== round((float) $attempt->amount, 2)) {
                throw new UnprocessableEntityHttpException('Webhook amount does not match payment attempt.');
            }

            $attempt->forceFill([
                'status' => PaymentAttempt::STATUS_SUCCESS,
                'transaction_id' => $event['transaction_id'] ?? $attempt->transaction_id,
                'webhook_event_id' => $event['event_id'] ?? $attempt->webhook_event_id,
                'gateway_payload' => $event['payload'] ?? $attempt->gateway_payload,
                'paid_at' => now(),
            ])->save();

            $this->expireOtherOpenAttempts($order, $attempt->id);
            $this->markOrderPaid($order, $attempt, [
                'payment_method' => $gateway,
                'payment_source' => $attempt->source,
                'payment_gateway' => $gateway,
                'online_payment_verified_at' => now(),
            ]);
            if ($attempt->source === PaymentAttempt::SOURCE_CUSTOMER_APP) {
                $releaseOrderId = $order->id;
            }

            return $attempt->fresh();
        });

        if ($attempt?->status === PaymentAttempt::STATUS_SUCCESS) {
            if ($releaseOrderId) {
                $order = Order::with(['restaurant.owner', 'customer'])->find($releaseOrderId);
                if ($order) {
                    app(OrderReleaseService::class)->releaseToRestaurant($order);
                    app(ScratchCardService::class)->issueForRecordedUsage($order, 'payment_success');
                    app(PromotionRewardSettlementService::class)->settleForOrder($order, 'payment_success');
                }
            }

            $eventName = $attempt->source === PaymentAttempt::SOURCE_CUSTOMER_APP
                ? 'customer.paid.online'
                : 'payment.completed';
            $this->broadcastPayment($attempt->order, $eventName, $attempt);
        }

        return $attempt;
    }

    public function statusPayload(Order $order): array
    {
        $this->reconcileOpenGatewayAttempts($order);
        $order = $order->fresh() ?: $order;

        PaymentAttempt::where('order_id', $order->id)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => PaymentAttempt::STATUS_EXPIRED, 'updated_at' => now()]);

        $order->loadMissing([
            'paymentAttempts' => fn ($query) => $query->latest(),
            'activePaymentAttempt',
        ]);

        return [
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_source' => $order->payment_source,
            'payment_gateway' => $order->payment_gateway,
            'transaction_id' => $order->payment_id,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'active_attempt' => $order->activePaymentAttempt ? $this->attemptPayload($order->activePaymentAttempt) : null,
            'attempts' => $order->paymentAttempts->map(fn (PaymentAttempt $attempt) => $this->attemptPayload($attempt))->values(),
        ];
    }

    public function attemptPayload(PaymentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'gateway' => $attempt->gateway,
            'source' => $attempt->source,
            'status' => $attempt->status,
            'amount' => (float) $attempt->amount,
            'currency' => $attempt->currency,
            'transaction_id' => $attempt->transaction_id,
            'payment_link' => $attempt->payment_link,
            'qr_reference' => $attempt->qr_reference,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'created_at' => $attempt->created_at?->toIso8601String(),
            'paid_at' => $attempt->paid_at?->toIso8601String(),
            'driver_id' => $attempt->driver_id,
            'collection_notes' => $attempt->collection_notes,
        ];
    }

    private function createAttempt(Order $order, string $gateway, string $source, int $createdBy, ?int $driverId = null): PaymentAttempt
    {
        return PaymentAttempt::create([
            'order_id' => $order->id,
            'gateway' => strtolower($gateway),
            'source' => $source,
            'status' => PaymentAttempt::STATUS_PENDING,
            'amount' => round((float) $order->total, 2),
            'currency' => $this->currency(),
            'created_by' => $createdBy,
            'driver_id' => $driverId,
            'expires_at' => now()->addMinutes($this->attemptTtlMinutes($gateway)),
            'idempotency_key' => $source . '_' . $order->id . '_' . Str::uuid(),
        ]);
    }

    private function attemptTtlMinutes(string $gateway): int
    {
        return 10;
    }

    private function applyGatewayCreation(PaymentAttempt $attempt, array $created, string $status): void
    {
        $attempt->forceFill([
            'status' => $status,
            'gateway_reference' => $created['gateway_reference'] ?? null,
            'payment_link_id' => $created['payment_link_id'] ?? null,
            'payment_link' => $created['payment_link'] ?? null,
            'qr_reference' => $created['qr_reference'] ?? null,
            'gateway_payload' => [
                'provider' => $created['payload'] ?? null,
                'client' => $created['response'] ?? [],
            ],
        ])->save();
    }

    private function markOrderPaid(Order $order, PaymentAttempt $attempt, array $attributes): void
    {
        $transactionId = $attempt->transaction_id ?: $attempt->gateway_reference ?: ('attempt_' . $attempt->id);

        $order->forceFill(array_merge([
            'payment_status' => 'success',
            'payment_id' => $transactionId,
            'payment_link_id' => $attempt->payment_link_id,
            'paid_at' => now(),
        ], $attributes))->save();

        Transaction::firstOrCreate(
            [
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
            ],
            [
                'user_id' => $attempt->created_by ?: $order->customer_id,
                'amount' => $order->total,
                'type' => 'payment',
                'status' => 'success',
                'payment_method' => $attributes['payment_method'] ?? $attempt->gateway,
            ]
        );
    }

    private function assertPayable(Order $order): void
    {
        if ($order->payment_status === 'success') {
            throw new ConflictHttpException('Already Paid');
        }

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            throw new UnprocessableEntityHttpException('This order can no longer accept payment.');
        }
    }

    private function assertDriverOwnsOrder(Order $order, int $driverId): void
    {
        if ((int) $order->driver_id !== $driverId) {
            abort(403, 'This order is not assigned to this driver.');
        }
    }

    private function expireActiveDriverQrAttempts(Order $order): void
    {
        PaymentAttempt::where('order_id', $order->id)
            ->where('source', PaymentAttempt::SOURCE_DRIVER_QR)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
            ->update(['status' => PaymentAttempt::STATUS_EXPIRED, 'updated_at' => now()]);
    }

    private function expireExpiredDriverQrAttempts(Order $order): void
    {
        $attempts = PaymentAttempt::where('order_id', $order->id)
            ->where('source', PaymentAttempt::SOURCE_DRIVER_QR)
            ->where('status', PaymentAttempt::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        if ($attempts->isEmpty()) {
            return;
        }

        PaymentAttempt::whereKey($attempts->pluck('id'))
            ->update(['status' => PaymentAttempt::STATUS_EXPIRED, 'updated_at' => now()]);
    }

    private function activeDriverQrAttempt(Order $order): ?PaymentAttempt
    {
        return PaymentAttempt::where('order_id', $order->id)
            ->where('source', PaymentAttempt::SOURCE_DRIVER_QR)
            ->where('status', PaymentAttempt::STATUS_ACTIVE)
            ->whereNotNull('gateway_reference')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->lockForUpdate()
            ->first();
    }

    private function cancelDuplicateDriverQrAttempts(Order $order, int $activeAttemptId): void
    {
        $attempts = PaymentAttempt::where('order_id', $order->id)
            ->where('source', PaymentAttempt::SOURCE_DRIVER_QR)
            ->where('status', PaymentAttempt::STATUS_ACTIVE)
            ->whereKeyNot($activeAttemptId)
            ->lockForUpdate()
            ->get();

        if ($attempts->isEmpty()) {
            return;
        }

        PaymentAttempt::whereKey($attempts->pluck('id'))
            ->update(['status' => PaymentAttempt::STATUS_CANCELLED, 'updated_at' => now()]);
    }

    private function expireOtherOpenAttempts(Order $order, int $paidAttemptId): void
    {
        PaymentAttempt::where('order_id', $order->id)
            ->whereKeyNot($paidAttemptId)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
            ->update(['status' => PaymentAttempt::STATUS_CANCELLED, 'updated_at' => now()]);
    }

    private function reconcileOpenGatewayAttempts(Order $order): void
    {
        $attempts = PaymentAttempt::where('order_id', $order->id)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
            ->whereNotNull('gateway_reference')
            ->get();

        foreach ($attempts as $attempt) {
            try {
                $event = $this->gateways->gateway($attempt->gateway)->fetchPaymentForAttempt($attempt);
                if ($event !== null) {
                    $this->completeFromWebhook($attempt->gateway, $event);
                }
            } catch (\Throwable $exception) {
                \Log::warning('Payment attempt reconciliation failed.', [
                    'order_id' => $order->id,
                    'payment_attempt_id' => $attempt->id,
                    'gateway' => $attempt->gateway,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function ensureDriverQrRenderable(PaymentAttempt $attempt): PaymentAttempt
    {
        try {
            $gateway = $this->gateways->gateway($attempt->gateway);
            if (method_exists($gateway, 'preparePaymentAttemptForDisplay')) {
                $gateway->preparePaymentAttemptForDisplay($attempt);
            }
        } catch (\Throwable $exception) {
            \Log::warning('Driver QR display hydration failed.', [
                'order_id' => $attempt->order_id,
                'payment_attempt_id' => $attempt->id,
                'gateway' => $attempt->gateway,
                'message' => $exception->getMessage(),
            ]);
        }

        return $attempt->fresh() ?: $attempt;
    }

    private function resolveAttempt(string $gateway, array $event): ?PaymentAttempt
    {
        if (! empty($event['payment_attempt_id'])) {
            return PaymentAttempt::whereKey($event['payment_attempt_id'])->first();
        }

        return PaymentAttempt::query()
            ->where('gateway', strtolower($gateway))
            ->where(function ($query) use ($event) {
                $query->when($event['gateway_reference'] ?? null, fn ($builder, $value) => $builder->orWhere('gateway_reference', $value))
                    ->when($event['payment_link_id'] ?? null, fn ($builder, $value) => $builder->orWhere('payment_link_id', $value))
                    ->when($event['transaction_id'] ?? null, fn ($builder, $value) => $builder->orWhere('transaction_id', $value));
            })
            ->latest()
            ->first();
    }

    private function isSuccessfulGatewayStatus(string $gateway, string $status, string $event): bool
    {
        $status = strtolower($status);
        $event = strtolower($event);

        return match (strtolower($gateway)) {
            'razorpay' => in_array($status, ['captured', 'paid'], true) || str_contains($event, 'paid') || str_contains($event, 'captured'),
            'cashfree' => in_array($status, ['success', 'paid'], true) || str_contains($event, 'success'),
            'stripe' => in_array($status, ['paid', 'succeeded', 'complete'], true) || in_array($event, ['checkout.session.completed', 'payment_intent.succeeded'], true),
            default => false,
        };
    }

    private function broadcastPayment(Order $order, string $event, ?PaymentAttempt $attempt = null): void
    {
        $order = $order->fresh(['customer', 'driver', 'restaurant']);
        if (! $order) {
            return;
        }

        try {
            broadcast(new PaymentStatusUpdatedEvent($order, $event, $attempt));
        } catch (\Throwable $exception) {
            \Log::warning('Payment broadcast failed.', [
                'order_id' => $order->id,
                'event' => $event,
                'message' => $exception->getMessage(),
            ]);
        }

        $title = $order->payment_status === 'success' ? 'Payment received' : 'Payment update';
        $body = $order->payment_status === 'success'
            ? "Payment for order #{$order->order_number} is complete."
            : "Payment for order #{$order->order_number} is pending.";
        $payload = [
            'type' => 'payment_status',
            'event' => $event,
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) $order->payment_method,
            'payment_source' => (string) ($order->payment_source ?? ''),
            'android_channel_id' => 'order_status_channel_custom',
        ];

        foreach ([$order->customer?->fcmTokenForApp('customer'), $order->driver?->fcmTokenForApp('driver')] as $token) {
            if (filled($token)) {
                $this->firebase->sendToDevice($token, $title, $body, $payload);
            }
        }
    }

    private function currency(): string
    {
        return strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
    }
}
