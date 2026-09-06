<?php

use App\Models\AppSetting;
use App\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $orderPlaceholders = ['order_number', 'restaurant_name', 'status'];

        $customerStatuses = [
            'pending' => 'Your order #{{order_number}} has been placed.',
            'confirmed' => 'Your order #{{order_number}} has been confirmed by {{restaurant_name}}.',
            'preparing' => 'Your order #{{order_number}} is now being prepared.',
            'ready_for_pickup' => 'Your order #{{order_number}} is ready for pickup.',
            'reached_pickup' => 'Your order #{{order_number}} driver has reached the restaurant.',
            'picked_up' => 'Your order #{{order_number}} has been picked up.',
            'on_the_way' => 'Your order #{{order_number}} is on the way.',
            'delivered' => 'Your order #{{order_number}} has been delivered.',
            'cancelled' => 'Your order #{{order_number}} has been cancelled.',
        ];

        foreach ($customerStatuses as $status => $body) {
            $label = ucwords(str_replace('_', ' ', $status));
            $this->upsert(
                "order.customer.{$status}",
                'push',
                "Order {$label} - Customer",
                'order_status',
                'Order ' . $label,
                $body,
                $orderPlaceholders
            );
        }

        $this->upsert(
            'order.restaurant.status_changed',
            'push',
            'Order Status Changed - Restaurant',
            'order_status',
            'Order #{{order_number}} {{status}}',
            'Order #{{order_number}} status changed to {{status}}.',
            $orderPlaceholders
        );

        $this->upsert(
            'order.driver.status_changed',
            'push',
            'Order Status Changed - Driver',
            'order_status',
            'Order #{{order_number}} {{status}}',
            'Order #{{order_number}} status changed to {{status}}.',
            $orderPlaceholders
        );

        $this->upsert(
            'sms.otp',
            'sms',
            'OTP Verification',
            'sms',
            null,
            (string) AppSetting::getValue('message_template_otp', 'Your OTP code is {{otp}}. It is valid for 10 minutes.'),
            ['otp']
        );

        $this->upsert(
            'sms.order_confirmation',
            'sms',
            'Order Confirmation',
            'sms',
            null,
            (string) AppSetting::getValue('message_template_order_confirmation', 'Your order has been confirmed. Order number: {{order_number}}.'),
            ['order_number', 'restaurant_name', 'customer_name', 'total', 'app_name']
        );

        $this->upsert(
            'sms.delivery_update',
            'sms',
            'Delivery Update',
            'sms',
            null,
            (string) AppSetting::getValue('message_template_delivery_update', 'Your order is on the way. Order number: {{order_number}}.'),
            ['order_number', 'restaurant_name', 'status_message', 'delivery_otp', 'app_name']
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        NotificationTemplate::whereIn('key', [
            'order.customer.pending',
            'order.customer.confirmed',
            'order.customer.preparing',
            'order.customer.ready_for_pickup',
            'order.customer.reached_pickup',
            'order.customer.picked_up',
            'order.customer.on_the_way',
            'order.customer.delivered',
            'order.customer.cancelled',
            'order.restaurant.status_changed',
            'order.driver.status_changed',
            'sms.otp',
            'sms.order_confirmation',
            'sms.delivery_update',
        ])->delete();
    }

    private function upsert(
        string $key,
        string $channel,
        string $label,
        string $group,
        ?string $title,
        string $body,
        array $placeholders
    ): void {
        NotificationTemplate::updateOrCreate(
            ['key' => $key],
            [
                'channel' => $channel,
                'label' => $label,
                'group' => $group,
                'title' => $title,
                'body' => $body,
                'placeholders' => $placeholders,
                'is_active' => true,
            ]
        );
    }
};
