<?php

namespace App\Services\Petpooja;

use App\Models\Order;
use App\Models\PetpoojaOrderLog;
use Illuminate\Support\Facades\Log;

/**
 * Relays a newly placed order into Petpooja's POS (the outbound Save
 * Order call). Called additively from OrderReleaseService::releaseToRestaurant()
 * -- never replaces the normal notifyRestaurant() flow, since the
 * restaurant's in-house dashboard/printer flow is unaffected by Petpooja
 * relay.
 *
 * Builds the payload from the order + each OrderItem's petpooja_snapshot
 * (captured at order-creation time), never by re-deriving from the
 * current menu, which may have since changed.
 */
class PetpoojaOrderRelayService
{
    public function __construct(private readonly PetpoojaApiClient $client)
    {
    }

    public function relay(Order $order): bool
    {
        $config = $order->restaurant?->petpoojaConfig;
        if (! $config || ! $config->is_active || ! $config->hasCredentials()) {
            return false;
        }

        $orderItems = $order->orderItems()->whereNotNull('petpooja_snapshot')->get();
        if ($orderItems->isEmpty()) {
            // Nothing in this order is Petpooja-sourced -- nothing to relay.
            return false;
        }

        $payload = $this->buildOrderInfo($order, $orderItems, $config);

        try {
            $response = $this->client->saveOrder($config, $payload);
            $body = $response->json() ?? [];
            $success = $response->successful() && (string) ($body['success'] ?? '0') === '1';

            $order->pos_provider = 'petpooja';
            $order->pos_order_id = (string) ($body['orderID'] ?? '');
            $order->pos_sync_status = $success ? 'sent' : 'failed';
            $order->pos_sync_error = $success ? null : ($body['message'] ?? 'Unknown error');
            $order->pos_last_synced_at = now();
            $order->save();

            PetpoojaOrderLog::create([
                'order_id' => $order->id,
                'direction' => 'save_order',
                'request_payload' => $payload,
                'response_payload' => $body,
                'http_status' => $response->status(),
                'success' => $success,
                'message' => $body['message'] ?? null,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Petpooja Save Order relay failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            $order->pos_provider = 'petpooja';
            $order->pos_sync_status = 'failed';
            $order->pos_sync_error = $e->getMessage();
            $order->pos_last_synced_at = now();
            $order->save();

            PetpoojaOrderLog::create([
                'order_id' => $order->id,
                'direction' => 'save_order',
                'request_payload' => $payload,
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function cancel(Order $order, string $reason): bool
    {
        $config = $order->restaurant?->petpoojaConfig;
        if (! $config || $order->pos_provider !== 'petpooja' || ! $config->hasCredentials()) {
            return false;
        }

        try {
            $response = $this->client->cancelOrder($config, (string) $order->id, $reason);
            $body = $response->json() ?? [];
            $success = $response->successful() && (string) ($body['success'] ?? '0') === '1';

            PetpoojaOrderLog::create([
                'order_id' => $order->id,
                'direction' => 'update_order_status',
                'request_payload' => ['clientorderID' => (string) $order->id, 'cancelReason' => $reason, 'status' => '-1'],
                'response_payload' => $body,
                'http_status' => $response->status(),
                'success' => $success,
                'message' => $body['message'] ?? null,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Petpooja cancel-order relay failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\OrderItem> $orderItems
     */
    private function buildOrderInfo(Order $order, $orderItems, $config): array
    {
        $restaurant = $order->restaurant;
        $customer = $order->customer;

        $items = $orderItems->map(function ($orderItem) {
            $snapshot = $orderItem->petpooja_snapshot ?? [];

            return [
                'id' => $snapshot['petpooja_item_id'] ?? (string) $orderItem->menu_item_id,
                'name' => $orderItem->menuItem?->name ?? ($snapshot['name'] ?? 'Item'),
                'tax_inclusive' => (bool) ($snapshot['tax_inclusive'] ?? false),
                'gst_liability' => $snapshot['gst_liability'] ?? 'vendor',
                'item_tax' => $snapshot['item_tax'] ?? [],
                'item_discount' => (string) ($snapshot['item_discount'] ?? '0'),
                'price' => (string) $orderItem->unit_price,
                'final_price' => (string) $orderItem->total_price,
                'quantity' => (string) $orderItem->quantity,
                'description' => $orderItem->special_instructions ?? '',
                'variation_name' => $snapshot['variation_name'] ?? '',
                'variation_id' => $snapshot['petpooja_variation_id'] ?? '',
                'AddonItem' => ['details' => $snapshot['addons'] ?? []],
            ];
        })->values()->all();

        return [
            'OrderInfo' => [
                'Restaurant' => [
                    'details' => [
                        'res_name' => $restaurant?->name ?? '',
                        'address' => $restaurant?->address ?? '',
                        'contact_information' => $restaurant?->phone ?? '',
                        'restID' => $config->rest_id,
                    ],
                ],
                'Customer' => [
                    'details' => [
                        'email' => $customer?->email ?? '',
                        'name' => $order->customer_name ?? $customer?->name ?? '',
                        'address' => is_array($order->customer_address) ? ($order->customer_address['address'] ?? '') : (string) $order->delivery_address,
                        'phone' => $order->customer_phone ?? $customer?->phone ?? '',
                        'latitude' => (string) ($order->delivery_lat ?? ''),
                        'longitude' => (string) ($order->delivery_lng ?? ''),
                    ],
                ],
                'Order' => [
                    'details' => [
                        'orderID' => (string) $order->id,
                        'preorder_date' => $order->created_at->format('Y-m-d'),
                        'preorder_time' => $order->created_at->format('H:i:s'),
                        'service_charge' => '0',
                        'sc_tax_amount' => '0',
                        'delivery_charges' => (string) ($order->delivery_fee ?? 0),
                        'dc_tax_percentage' => '0',
                        'dc_tax_amount' => '0',
                        // No dedicated order-level packaging-charge column
                        // in this schema -- any packing cost is currently
                        // folded into item pricing, not broken out here.
                        'packing_charges' => '0',
                        'pc_tax_amount' => '0',
                        'pc_tax_percentage' => '0',
                        'order_type' => $order->order_type === 'takeaway' ? 'P' : 'H',
                        'advanced_order' => $order->scheduled_time ? 'Y' : 'N',
                        'payment_type' => $this->mapPaymentType($order->payment_method),
                        'table_no' => '',
                        'no_of_persons' => '0',
                        'discount_total' => (string) ($order->discount ?? 0),
                        'tax_total' => (string) ($order->tax ?? 0),
                        'discount_type' => 'F',
                        'total' => (string) $order->total,
                        'description' => $order->special_instructions ?? '',
                        'created_on' => $order->created_at->format('Y-m-d H:i:s'),
                        'enable_delivery' => $order->order_type === 'takeaway' ? 0 : 1,
                        'callback_url' => route('webhooks.petpooja.order-callback', $order),
                    ],
                ],
                'OrderItem' => ['details' => $items],
                'Tax' => ['details' => []],
                'Discount' => ['details' => []],
            ],
            'udid' => '',
            'device_type' => 'Web',
        ];
    }

    private function mapPaymentType(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'cod' => 'COD',
            'card' => 'CARD',
            default => 'ONLINE',
        };
    }
}
