<?php

namespace App\Http\Controllers\Api;

use App\Services\MediaStorage;

use App\Events\OrderChatMessageSent;
use App\Events\OrderChatReadReceiptUpdated;
use App\Events\OrderChatTypingUpdated;
use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderChatMessage;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderChatController extends Controller
{
    public function index(Request $request, int $orderId)
    {
        [$order, $role] = $this->resolveAuthorizedOrder($request, $orderId);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_role' => $role,
                'participants' => $this->participants($order),
                'summary' => $this->orderSummary($order),
                'messages' => $order->chatMessages()
                    ->with('sender:id,name')
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (OrderChatMessage $message) => $this->transformMessage($message))
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request, int $orderId)
    {
        [$order, $role] = $this->resolveAuthorizedOrder($request, $orderId);

        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
            'recipient_role' => 'nullable|in:customer,restaurant,driver',
            'message_type' => 'nullable|in:text,image,location,voice,system',
            'attachment' => 'nullable|file|max:15360',
            'location_lat' => 'nullable|numeric|between:-90,90',
            'location_lng' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
        ]);

        $messageType = (string) ($validated['message_type'] ?? 'text');
        $messageBody = trim((string) ($validated['message'] ?? ''));
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;
        $meta = [];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $directory = match ($messageType) {
                'voice' => 'order-chat/voice',
                default => 'order-chat/media',
            };

            $attachmentPath = $file->store($directory, 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
        }

        if ($messageType === 'location') {
            if (! isset($validated['location_lat'], $validated['location_lng'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location latitude and longitude are required for location sharing.',
                ], 422);
            }

            $meta = array_filter([
                'location_lat' => (float) $validated['location_lat'],
                'location_lng' => (float) $validated['location_lng'],
                'location_label' => $validated['location_label'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            if ($messageBody === '') {
                $messageBody = $validated['location_label'] ?? 'Shared a live location';
            }
        }

        if ($messageType === 'image' && $attachmentPath && $messageBody === '') {
            $messageBody = 'Shared an image';
        }

        if ($messageType === 'voice' && $attachmentPath && $messageBody === '') {
            $messageBody = 'Shared a voice note';
        }

        if ($messageBody === '' && ! $attachmentPath) {
            return response()->json([
                'success' => false,
                'message' => 'Type a message or attach a file before sending.',
            ], 422);
        }

        $chatMessage = OrderChatMessage::create([
            'order_id' => $order->id,
            'sender_id' => $request->user()->id,
            'sender_role' => $role,
            'recipient_role' => $validated['recipient_role'] ?? null,
            'message_type' => $messageType,
            'message' => $messageBody,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
            'meta' => empty($meta) ? null : $meta,
            'delivered_at' => now(),
        ]);

        $chatMessage->load('sender:id,name');

        broadcast(new OrderChatMessageSent($chatMessage))->toOthers();
        $this->notifyParticipants($order, $chatMessage, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $this->transformMessage($chatMessage),
        ], 201);
    }

    public function markRead(Request $request, int $orderId)
    {
        [$order, $role] = $this->resolveAuthorizedOrder($request, $orderId);

        $messageIds = $order->chatMessages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->pluck('id')
            ->all();

        if (empty($messageIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message_ids' => [],
                    'read_at' => null,
                ],
            ]);
        }

        $readAt = now();

        OrderChatMessage::query()
            ->whereIn('id', $messageIds)
            ->update(['read_at' => $readAt]);

        broadcast(new OrderChatReadReceiptUpdated(
            $order->id,
            $role,
            (int) $request->user()->id,
            $messageIds,
            $readAt->toIso8601String()
        ))->toOthers();

        return response()->json([
            'success' => true,
            'data' => [
                'message_ids' => $messageIds,
                'read_at' => $readAt->toIso8601String(),
            ],
        ]);
    }

    public function typing(Request $request, int $orderId)
    {
        [$order, $role] = $this->resolveAuthorizedOrder($request, $orderId);

        $validated = $request->validate([
            'recipient_role' => 'nullable|in:customer,restaurant,driver',
            'is_typing' => 'required|boolean',
        ]);

        broadcast(new OrderChatTypingUpdated(
            $order->id,
            (int) $request->user()->id,
            $role,
            $validated['recipient_role'] ?? null,
            (bool) $validated['is_typing']
        ))->toOthers();

        return response()->json([
            'success' => true,
        ]);
    }

    public function assistant(Request $request, int $orderId)
    {
        [$order, $role] = $this->resolveAuthorizedOrder($request, $orderId);

        $validated = $request->validate([
            'question' => 'required|string|max:500',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $validated['question'],
                'answer' => $this->buildAssistantReply($order, $role, $validated['question']),
            ],
        ]);
    }

    private function resolveAuthorizedOrder(Request $request, int $orderId): array
    {
        $user = $request->user();
        $order = Order::with(['restaurant.owner', 'driver', 'customer'])
            ->findOrFail($orderId);

        if ((int) $order->customer_id === (int) $user->id) {
            return [$order, 'customer'];
        }

        if ((int) $order->driver_id === (int) $user->id && $user->hasRole('delivery_partner')) {
            return [$order, 'driver'];
        }

        if ($user->restaurants()->whereKey($order->restaurant_id)->exists()) {
            return [$order, 'restaurant'];
        }

        abort(403, 'You are not allowed to access this order chat.');
    }

    private function participants(Order $order): array
    {
        return [
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
            ],
            'restaurant' => [
                'name' => $order->restaurant?->name,
                'phone' => $order->restaurant?->phone,
            ],
            'driver' => [
                'name' => $order->driver?->name,
                'phone' => $order->driver?->phone,
            ],
        ];
    }

    private function orderSummary(Order $order): array
    {
        $driverLocation = $order->driver_id ? cache('driver_location_' . $order->driver_id) : null;

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => Order::getStatuses()[$order->status] ?? Str::headline($order->status),
            'restaurant_name' => $order->restaurant?->name,
            'driver_name' => $order->driver?->name,
            'driver_phone' => $order->driver?->phone,
            'customer_name' => $order->customer_name,
            'delivery_otp' => $order->delivery_otp,
            'special_instructions' => $order->special_instructions,
            'driver_location' => $driverLocation,
            'delivery_address' => $order->delivery_address,
        ];
    }

    private function transformMessage(OrderChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'order_id' => $message->order_id,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'attachment_url' => MediaStorage::url($message->attachment_path),
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
            'meta' => $message->meta,
            'sender_id' => $message->sender_id,
            'sender_role' => $message->sender_role,
            'recipient_role' => $message->recipient_role,
            'sender_name' => $message->sender?->name,
            'delivered_at' => optional($message->delivered_at)->toIso8601String(),
            'read_at' => optional($message->read_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }

    private function notifyParticipants(Order $order, OrderChatMessage $message, int $senderId): void
    {
        $senderLabel = match ($message->sender_role) {
            'restaurant' => $order->restaurant?->name ?? 'Restaurant',
            'driver' => $message->sender?->name ?? 'Driver',
            default => $message->sender?->name ?? 'Customer',
        };

        $preview = match ($message->message_type) {
            'image' => 'Shared an image',
            'location' => 'Shared a live location',
            'voice' => 'Shared a voice note',
            default => $message->message,
        };

        $targets = collect([
            $order->customer_id ? User::find($order->customer_id) : null,
            $order->driver_id ? User::find($order->driver_id) : null,
            $order->restaurant?->owner,
        ])->filter(fn ($user) => $user && (int) $user->id !== $senderId);

        foreach ($targets as $user) {
            try {
                $payload = [
                    'type' => 'order_chat_message',
                    'deep_link' => '/orders/' . $order->id . '/chat',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'sender_role' => $message->sender_role,
                    'sender_name' => $senderLabel,
                    'message' => $preview,
                    'message_type' => $message->message_type,
                ];

                $user->notify(new AppDatabaseNotification(
                    'New order chat message',
                    $senderLabel . ': ' . $preview,
                    $payload
                ));

                if (! empty($user->fcm_token)) {
                    (new FirebaseHelper())->sendToDevice(
                        $user->fcm_token,
                        'New order chat message',
                        $senderLabel . ': ' . $preview,
                        $payload
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('Order chat notification failed: ' . $e->getMessage());
            }
        }
    }

    private function buildAssistantReply(Order $order, string $role, string $question): string
    {
        $q = Str::lower($question);
        $status = Order::getStatuses()[$order->status] ?? Str::headline($order->status);
        $driverName = trim((string) $order->driver?->name);
        $restaurantName = trim((string) $order->restaurant?->name);

        if (Str::contains($q, ['status', 'where', 'progress'])) {
            return "Current order status: {$status}.";
        }

        if (Str::contains($q, ['eta', 'arrive', 'delivery'])) {
            return match ($order->status) {
                'delivered' => 'This order has already been delivered.',
                'on_the_way', 'picked_up' => 'The order is on the way. You can also open live tracking for the latest driver movement.',
                'ready_for_pickup' => 'The order is ready and waiting for pickup.',
                default => 'The order is still being prepared.',
            };
        }

        if (Str::contains($q, ['driver', 'rider'])) {
            if ($driverName !== '') {
                return "Assigned rider: {$driverName}.";
            }

            return 'A rider has not been assigned yet.';
        }

        if (Str::contains($q, ['restaurant', 'seller', 'store'])) {
            if ($restaurantName !== '') {
                return "Restaurant partner: {$restaurantName}.";
            }

            return 'Restaurant details are not available right now.';
        }

        if (Str::contains($q, ['otp'])) {
            return $order->delivery_otp
                ? 'Delivery OTP: ' . $order->delivery_otp . '.'
                : 'Delivery OTP will appear once the order is close to delivery.';
        }

        if ($role === 'restaurant' && Str::contains($q, ['customer request', 'instruction'])) {
            return $order->special_instructions
                ? 'Customer instructions: ' . $order->special_instructions
                : 'There are no special customer instructions on this order.';
        }

        return 'I can help with order status, delivery progress, rider details, restaurant details, live tracking context, and delivery OTP for this order.';
    }
}
