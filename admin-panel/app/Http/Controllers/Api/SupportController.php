<?php

namespace App\Http\Controllers\Api;

use App\Events\SupportConversationEscalated;
use App\Events\SupportMessageSent;
use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use App\Services\MediaStorage;
use App\Services\SupportBotEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function __construct(
        protected SupportBotEngine $bot
    ) {
    }

    public function index(Request $request)
    {
        $query = SupportConversation::with(['order:id,order_number', 'latestMessage'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }

        $conversations = $query->orderByDesc('last_message_at')->get();

        return response()->json([
            'success' => true,
            'data' => $conversations->map(fn (SupportConversation $c) => $this->transformConversation($c)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|integer|exists:orders,id',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'category' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $order = isset($validated['order_id']) ? Order::find($validated['order_id']) : null;

        if ($order) {
            $this->authorizeOrderAccess($user, $order);
        }

        $existing = SupportConversation::where('user_id', $user->id)
            ->where('order_id', $order?->id)
            ->whereNotIn('stage', ['resolved'])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $this->transformConversation($existing->load('order')),
                'messages' => $existing->messages()->with('sender:id,name')->orderBy('created_at')->get()
                    ->map(fn (SupportMessage $m) => $this->transformMessage($m)),
            ], 200);
        }

        $conversation = SupportConversation::create([
            'conversation_number' => 'SUP-' . Str::upper(Str::random(8)),
            'order_id' => $order?->id,
            'user_id' => $user->id,
            'requester_role' => $this->resolveRequesterRole($user, $order),
            'restaurant_id' => $order?->restaurant_id ?? $validated['restaurant_id'] ?? null,
            'category' => $validated['category'] ?? 'general',
            'stage' => 'bot',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $this->postBotMessage($conversation, $this->bot->greeting($order), [
            'quick_replies' => SupportBotEngine::categories(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->transformConversation($conversation->load('order')),
            'messages' => $conversation->messages()->with('sender:id,name')->orderBy('created_at')->get()
                ->map(fn (SupportMessage $m) => $this->transformMessage($m)),
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $conversation = $this->authorizedConversation($request, $id);

        return response()->json([
            'success' => true,
            'data' => $this->transformConversation($conversation->load('order')),
            'messages' => $conversation->messages()->with('sender:id,name')->orderBy('created_at')->get()
                ->map(fn (SupportMessage $m) => $this->transformMessage($m)),
        ]);
    }

    public function sendMessage(Request $request, int $id)
    {
        $conversation = $this->authorizedConversation($request, $id);

        if ($conversation->stage === 'resolved') {
            return response()->json([
                'success' => false,
                'message' => 'This conversation is resolved. Start a new chat if you need more help.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:50',
            'attachment' => 'nullable|file|max:15360',
        ]);

        $messageBody = trim((string) ($validated['message'] ?? ''));
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = MediaStorage::store($file, 'support/media');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
        }

        if ($messageBody === '' && ! $attachmentPath) {
            return response()->json([
                'success' => false,
                'message' => 'Type a message or attach a file before sending.',
            ], 422);
        }

        $userMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'sender_type' => $conversation->requester_role,
            'message_type' => $attachmentPath ? 'image' : 'text',
            'message' => $messageBody !== '' ? $messageBody : 'Shared an attachment',
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
            'delivered_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        broadcast(new SupportMessageSent($userMessage))->toOthers();

        if ($conversation->stage === 'bot') {
            $this->runBotTriage($conversation, $validated['category'] ?? $messageBody);
        } else {
            $this->notifyAdmins($conversation, $userMessage);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformMessage($userMessage),
        ], 201);
    }

    public function escalate(Request $request, int $id)
    {
        $conversation = $this->authorizedConversation($request, $id);

        $this->escalateConversation($conversation, 'Connecting you to a support agent now.');

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, int $id)
    {
        $conversation = $this->authorizedConversation($request, $id);

        $messageIds = $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->pluck('id')
            ->all();

        if (! empty($messageIds)) {
            SupportMessage::whereIn('id', $messageIds)->update(['read_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => ['message_ids' => $messageIds]]);
    }

    public function submitCsat(Request $request, int $id)
    {
        $conversation = $this->authorizedConversation($request, $id);

        if ($conversation->stage !== 'resolved') {
            return response()->json(['success' => false, 'message' => 'This conversation is not resolved yet.'], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $conversation->update([
            'csat_rating' => $validated['rating'],
            'csat_comment' => $validated['comment'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    private function runBotTriage(SupportConversation $conversation, string $categoryOrText): void
    {
        $normalized = $this->normalizeCategory($categoryOrText);

        if ($normalized === 'satisfied') {
            $this->postBotMessage($conversation, 'Glad I could help! Feel free to reach out again anytime.');
            $conversation->update(['stage' => 'resolved', 'status' => 'resolved', 'resolved_at' => now()]);
            return;
        }

        if ($normalized === 'escalate') {
            $this->escalateConversation($conversation, 'Connecting you to a support agent now.');
            return;
        }

        $result = $this->bot->resolve($conversation, $normalized, $conversation->order);

        $this->postBotMessage($conversation, $result['reply'], $result['should_escalate'] ? [] : [
            'quick_replies' => [
                ['code' => 'satisfied', 'label' => 'That helped'],
                ['code' => 'escalate', 'label' => 'Still need help'],
            ],
        ]);

        if ($result['should_escalate']) {
            $this->escalateConversation($conversation, null);
        }
    }

    private function normalizeCategory(string $input): string
    {
        $codes = collect(SupportBotEngine::categories())->pluck('code')->all();
        $normalized = Str::lower(trim($input));

        if (in_array($normalized, ['satisfied', 'escalate'], true)) {
            return $normalized;
        }

        if (in_array($normalized, $codes, true)) {
            return $normalized;
        }

        return match (true) {
            str_contains($normalized, 'status') || str_contains($normalized, 'where') || str_contains($normalized, 'track') => 'order_status',
            str_contains($normalized, 'delay') || str_contains($normalized, 'late') => 'delivery_delay',
            str_contains($normalized, 'otp') => 'otp_missing',
            str_contains($normalized, 'driver') || str_contains($normalized, 'rider') => 'driver_contact',
            str_contains($normalized, 'cancel') => 'cancel_order',
            str_contains($normalized, 'refund') => 'refund_status',
            str_contains($normalized, 'payment') || str_contains($normalized, 'paid') => 'payment_issue',
            str_contains($normalized, 'wrong') || str_contains($normalized, 'missing item') => 'wrong_item',
            default => 'other',
        };
    }

    private function escalateConversation(SupportConversation $conversation, ?string $note): void
    {
        if ($conversation->stage === 'human') {
            return;
        }

        $conversation->update(['stage' => 'human', 'status' => 'open']);

        $system = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'message_type' => 'system',
            'message' => $note ?? 'Escalated to a support agent.',
        ]);

        broadcast(new SupportMessageSent($system))->toOthers();
        broadcast(new SupportConversationEscalated($conversation));
        $this->notifyAdmins($conversation, $system);
    }

    private function postBotMessage(SupportConversation $conversation, string $text, array $meta = []): SupportMessage
    {
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'message_type' => empty($meta) ? 'text' : 'quick_reply',
            'message' => $text,
            'meta' => empty($meta) ? null : $meta,
            'delivered_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        broadcast(new SupportMessageSent($message))->toOthers();

        return $message;
    }

    private function notifyAdmins(SupportConversation $conversation, SupportMessage $message): void
    {
        $adminIds = $conversation->assigned_to
            ? [$conversation->assigned_to]
            : User::role(['super_admin', 'admin'])->pluck('id')->all();

        $requesterName = $conversation->user?->name ?? 'A customer';
        $preview = $message->message_type === 'image' ? 'Shared an image' : $message->message;

        foreach (User::whereIn('id', $adminIds)->get() as $admin) {
            try {
                $payload = [
                    'type' => 'support_message',
                    'deep_link' => '/admin/support/' . $conversation->id,
                    'conversation_id' => $conversation->id,
                    'conversation_number' => $conversation->conversation_number,
                ];

                $admin->notify(new AppDatabaseNotification(
                    'New support message',
                    $requesterName . ': ' . $preview,
                    $payload
                ));
            } catch (\Throwable $e) {
                \Log::warning('Support admin notification failed: ' . $e->getMessage());
            }
        }
    }

    private function authorizedConversation(Request $request, int $id): SupportConversation
    {
        $conversation = SupportConversation::with('order')->findOrFail($id);

        abort_unless(
            (int) $conversation->user_id === (int) $request->user()->id,
            403,
            'You are not allowed to access this conversation.'
        );

        return $conversation;
    }

    private function authorizeOrderAccess(User $user, Order $order): void
    {
        $isCustomer = (int) $order->customer_id === (int) $user->id;
        $isDriver = (int) $order->driver_id === (int) $user->id && $user->hasRole('delivery_partner');
        $isRestaurant = $user->restaurants()->whereKey($order->restaurant_id)->exists();

        abort_unless($isCustomer || $isDriver || $isRestaurant, 403, 'You are not allowed to start support for this order.');
    }

    private function resolveRequesterRole(User $user, ?Order $order): string
    {
        if ($order) {
            if ((int) $order->driver_id === (int) $user->id && $user->hasRole('delivery_partner')) {
                return 'driver';
            }
            if ($user->restaurants()->whereKey($order->restaurant_id)->exists()) {
                return 'restaurant';
            }
            if ((int) $order->customer_id === (int) $user->id) {
                return 'customer';
            }
        }

        if ($user->hasRole('delivery_partner')) {
            return 'driver';
        }

        if ($user->restaurants()->exists()) {
            return 'restaurant';
        }

        return 'customer';
    }

    private function transformConversation(SupportConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'conversation_number' => $conversation->conversation_number,
            'order_id' => $conversation->order_id,
            'order_number' => $conversation->order?->order_number,
            'requester_role' => $conversation->requester_role,
            'category' => $conversation->category,
            'stage' => $conversation->stage,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'csat_rating' => $conversation->csat_rating,
            'resolved_at' => optional($conversation->resolved_at)->toIso8601String(),
            'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
            'created_at' => optional($conversation->created_at)->toIso8601String(),
        ];
    }

    private function transformMessage(SupportMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender?->name,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'attachment_url' => MediaStorage::url($message->attachment_path),
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
            'meta' => $message->meta,
            'delivered_at' => optional($message->delivered_at)->toIso8601String(),
            'read_at' => optional($message->read_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
