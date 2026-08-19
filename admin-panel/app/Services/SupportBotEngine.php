<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SupportConversation;
use Illuminate\Support\Str;

class SupportBotEngine
{
    /**
     * L1 triage menu. 'wrong_item' and 'other' are never deterministically
     * resolvable, so they always escalate straight to a human (L2).
     */
    public static function categories(): array
    {
        return [
            ['code' => 'order_status', 'label' => 'Where is my order?'],
            ['code' => 'delivery_delay', 'label' => 'My order is delayed'],
            ['code' => 'otp_missing', 'label' => 'Delivery OTP is missing'],
            ['code' => 'driver_contact', 'label' => 'Contact my delivery partner'],
            ['code' => 'cancel_order', 'label' => 'Cancel my order'],
            ['code' => 'refund_status', 'label' => 'Refund status'],
            ['code' => 'payment_issue', 'label' => 'Payment issue'],
            ['code' => 'wrong_item', 'label' => 'Wrong or missing item'],
            ['code' => 'other', 'label' => 'Something else'],
        ];
    }

    public function greeting(?Order $order): string
    {
        if ($order) {
            return "Hi! I can help with order #{$order->order_number}. What's the issue?";
        }

        return 'Hi! What can I help you with today?';
    }

    /**
     * @return array{handled: bool, reply: string, should_escalate: bool}
     */
    public function resolve(SupportConversation $conversation, string $category, ?Order $order): array
    {
        $category = Str::lower(trim($category));

        return match ($category) {
            'order_status' => $this->orderStatus($order),
            'delivery_delay' => $this->deliveryDelay($order),
            'otp_missing' => $this->otpMissing($order),
            'driver_contact' => $this->driverContact($order),
            'cancel_order' => $this->cancelOrder($order),
            'refund_status' => $this->refundStatus($order),
            'payment_issue' => $this->paymentIssue($order),
            default => $this->escalate(),
        };
    }

    private function orderStatus(?Order $order): array
    {
        if (! $order) {
            return $this->reply('Open your Orders list and tap an order to see its live status.');
        }

        $label = Order::getStatuses()[$order->status] ?? Str::headline($order->status);

        return $this->reply("Order #{$order->order_number} is currently: {$label}.");
    }

    private function deliveryDelay(?Order $order): array
    {
        if (! $order) {
            return $this->escalate();
        }

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            return $this->escalate();
        }

        $label = Order::getStatuses()[$order->status] ?? Str::headline($order->status);

        return $this->reply(
            "Sorry for the wait \u{2014} order #{$order->order_number} is at \"{$label}\". " .
            'If it does not move soon, tap "Talk to an agent" and we will chase it up for you.'
        );
    }

    private function otpMissing(?Order $order): array
    {
        if (! $order) {
            return $this->escalate();
        }

        if (filled($order->delivery_otp)) {
            return $this->reply("Your delivery OTP for order #{$order->order_number} is {$order->delivery_otp}.");
        }

        return $this->reply('The OTP appears automatically once your order is close to delivery. Keep the tracking screen open.');
    }

    private function driverContact(?Order $order): array
    {
        if (! $order || ! $order->driver_id) {
            return $this->reply('A delivery partner has not been assigned to this order yet.');
        }

        $order->loadMissing('driver');
        $name = $order->driver?->name ?? 'your delivery partner';

        return $this->reply("{$name} is handling this delivery. Use the order chat to message them directly.");
    }

    private function cancelOrder(?Order $order): array
    {
        if (! $order) {
            return $this->escalate();
        }

        if (in_array($order->status, ['pending', 'confirmed'], true)) {
            return $this->reply('You can still cancel this order from the tracking screen before the restaurant starts preparing it.');
        }

        return $this->reply(
            'This order has already moved past the cancellation window. I am connecting you to an agent to help.',
            shouldEscalate: true
        );
    }

    private function refundStatus(?Order $order): array
    {
        if (! $order || blank($order->refund_status)) {
            return $this->reply('No refund has been initiated for this order yet.');
        }

        $label = Order::getRefundStatuses()[$order->refund_status] ?? Str::headline($order->refund_status);
        $amount = $order->refund_amount ? " of \u{20B9}{$order->refund_amount}" : '';

        return $this->reply("Your refund{$amount} is currently: {$label}.");
    }

    private function paymentIssue(?Order $order): array
    {
        if (! $order) {
            return $this->escalate();
        }

        $label = Order::getPaymentStatuses()[$order->payment_status] ?? Str::headline((string) $order->payment_status);

        return $this->reply("Payment status for order #{$order->order_number}: {$label}.");
    }

    private function escalate(): array
    {
        return $this->reply(
            'I am not able to resolve that automatically. Connecting you to a support agent now.',
            shouldEscalate: true
        );
    }

    private function reply(string $text, bool $shouldEscalate = false): array
    {
        return [
            'handled' => true,
            'reply' => $text,
            'should_escalate' => $shouldEscalate,
        ];
    }
}
