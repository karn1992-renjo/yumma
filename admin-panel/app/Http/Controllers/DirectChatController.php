<?php

namespace App\Http\Controllers;

use App\Events\DirectChatMessageSent;
use App\Models\DirectChatConversation;
use App\Models\DirectChatMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DirectChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = DirectChatConversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->with(['participants:id,name,email,phone', 'latestMessage.sender:id,name', 'order:id,order_number,status'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DirectChatConversation $conversation) => $this->conversationPayload($conversation, $user));

        return response()->json([
            'success' => true,
            'data' => $conversations,
            'unread_count' => $conversations->sum('unread_count'),
        ]);
    }

    public function searchUsers(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->input('q', ''));

        $allowedUserIds = $this->allowedStartUserIds($user);

        $users = User::query()
            ->whereKeyNot($user->id)
            ->whereIn('id', $allowedUserIds)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->with('roles:id,name')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'roles' => $item->roles->pluck('name')->values(),
            ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $user = $request->user();
        abort_if((int) $validated['user_id'] === (int) $user->id, 422, 'Select another user to chat with.');

        $otherUser = User::where('is_active', true)->findOrFail($validated['user_id']);
        $order = isset($validated['order_id'])
            ? $this->authorizedOrderForDirectChat($user, (int) $validated['order_id'])
            : $this->latestSharedOrder($user, $otherUser);

        $this->authorizeStart($user, $otherUser, $order);

        $conversation = $order
            ? $this->findOrCreateOrderConversation($user, $otherUser, $order)
            : $this->findOrCreateDirectConversation($user, $otherUser);

        return response()->json([
            'success' => true,
            'data' => $this->conversationPayload(
                $conversation->load(['participants:id,name,email,phone', 'latestMessage.sender:id,name', 'order:id,order_number,status']),
                $user
            ),
        ]);
    }

    public function show(Request $request, DirectChatConversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);
        $user = $request->user();

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->latest()
            ->limit(60)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (DirectChatMessage $message) => $this->messagePayload($message));

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload(
                $conversation->load(['participants:id,name,email,phone', 'latestMessage.sender:id,name', 'order:id,order_number,status']),
                $user
            ),
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, DirectChatConversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = DB::transaction(function () use ($request, $conversation, $validated) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'message' => $validated['message'],
                'message_type' => 'text',
            ]);

            $conversation->update(['last_message_at' => now()]);
            $conversation->participants()->updateExistingPivot($request->user()->id, [
                'last_read_at' => now(),
            ]);

            return $message->load('sender:id,name');
        });

        broadcast(new DirectChatMessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => $this->messagePayload($message),
        ]);
    }

    public function markRead(Request $request, DirectChatConversation $conversation)
    {
        $this->authorizeParticipant($request, $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'last_read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    private function findOrCreateDirectConversation(User $user, User $otherUser): DirectChatConversation
    {
        $existing = DirectChatConversation::query()
            ->whereNull('order_id')
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->whereHas('participants', fn ($query) => $query->whereKey($otherUser->id))
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $otherUser) {
            $conversation = DirectChatConversation::create([
                'title' => null,
                'context_type' => 'direct',
                'last_message_at' => now(),
            ]);

            $conversation->participants()->attach([
                $user->id => ['last_read_at' => now()],
                $otherUser->id => ['last_read_at' => null],
            ]);

            return $conversation;
        });
    }

    private function findOrCreateOrderConversation(User $user, User $otherUser, Order $order): DirectChatConversation
    {
        $participantIds = $this->orderParticipantIds($order, [$user->id, $otherUser->id]);

        $existing = DirectChatConversation::query()
            ->where('order_id', $order->id)
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->whereHas('participants', fn ($query) => $query->whereKey($otherUser->id))
            ->first();

        if ($existing) {
            $missingIds = $participantIds->diff($existing->participants()->pluck('users.id'));
            if ($missingIds->isNotEmpty()) {
                $existing->participants()->attach(
                    $missingIds->mapWithKeys(fn ($id) => [(int) $id => ['last_read_at' => null]])->all()
                );
            }

            return $existing;
        }

        return DB::transaction(function () use ($user, $order, $participantIds) {
            $conversation = DirectChatConversation::create([
                'title' => 'Order #' . $order->order_number,
                'order_id' => $order->id,
                'context_type' => 'order',
                'last_message_at' => now(),
            ]);

            $conversation->participants()->attach(
                $participantIds->mapWithKeys(fn ($id) => [
                    (int) $id => ['last_read_at' => (int) $id === (int) $user->id ? now() : null],
                ])->all()
            );

            return $conversation;
        });
    }

    private function authorizeParticipant(Request $request, DirectChatConversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->whereKey($request->user()->id)->exists(),
            403,
            'You are not part of this chat.'
        );
    }

    private function authorizeStart(User $user, User $otherUser, ?Order $order = null): void
    {
        if ($this->isAdmin($user) || $this->isAdmin($otherUser)) {
            return;
        }

        if ($this->existingConversationBetween($user, $otherUser)) {
            return;
        }

        if ($this->isRestaurantUser($user) && $this->isCustomer($otherUser)) {
            abort(403, 'Customers must start the restaurant chat first.');
        }

        if ($order && $this->orderParticipantIds($order)->contains((int) $otherUser->id)) {
            return;
        }

        abort(403, 'You are not allowed to start this chat.');
    }

    private function allowedStartUserIds(User $user): array
    {
        if ($this->isAdmin($user)) {
            return User::query()->whereKeyNot($user->id)->pluck('id')->all();
        }

        $ids = collect($this->adminUserIds());
        $existingParticipantIds = DirectChatConversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->with('participants:id')
            ->get()
            ->flatMap(fn (DirectChatConversation $conversation) => $conversation->participants->pluck('id'));

        $ids = $ids->merge($existingParticipantIds);

        if ($this->isCustomer($user)) {
            $orders = Order::query()
                ->where('customer_id', $user->id)
                ->with('restaurant.staff.user:id')
                ->latest()
                ->limit(30)
                ->get(['id', 'restaurant_id', 'driver_id', 'customer_id']);
            $ids = $ids->merge($orders->flatMap(fn (Order $order) => $this->orderParticipantIds($order)));
        } elseif ($this->isDriver($user)) {
            $orders = Order::query()
                ->where('driver_id', $user->id)
                ->with('restaurant.staff.user:id')
                ->latest()
                ->limit(30)
                ->get(['id', 'restaurant_id', 'driver_id', 'customer_id']);
            $ids = $ids->merge($orders->flatMap(fn (Order $order) => $this->orderParticipantIds($order)));
        }

        return $ids->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->unique()
            ->values()
            ->all();
    }

    private function authorizedOrderForDirectChat(User $user, int $orderId): Order
    {
        $order = Order::with(['restaurant.owner:id,name,email,phone', 'restaurant.staff.user:id', 'driver:id', 'customer:id'])
            ->findOrFail($orderId);

        abort_unless($this->orderParticipantIds($order)->contains((int) $user->id) || $this->isAdmin($user), 403, 'You are not allowed to access this order chat.');

        return $order;
    }

    private function latestSharedOrder(User $user, User $otherUser): ?Order
    {
        if ($this->isAdmin($user) || $this->isAdmin($otherUser)) {
            return null;
        }

        return Order::with(['restaurant.owner:id', 'restaurant.staff.user:id', 'driver:id', 'customer:id'])
            ->where(function ($query) use ($user) {
                $query->where('customer_id', $user->id)
                    ->orWhere('driver_id', $user->id)
                    ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('owner_id', $user->id))
                    ->orWhereHas('restaurant.staff', fn ($staff) => $staff->where('user_id', $user->id));
            })
            ->where(function ($query) use ($otherUser) {
                $query->where('customer_id', $otherUser->id)
                    ->orWhere('driver_id', $otherUser->id)
                    ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('owner_id', $otherUser->id))
                    ->orWhereHas('restaurant.staff', fn ($staff) => $staff->where('user_id', $otherUser->id));
            })
            ->latest()
            ->first();
    }

    private function orderParticipantIds(Order $order, array $fallbackIds = []): Collection
    {
        $order->loadMissing(['restaurant.owner:id', 'restaurant.staff.user:id', 'driver:id', 'customer:id']);

        return collect($fallbackIds)
            ->push($order->customer_id)
            ->push($order->driver_id)
            ->push($order->restaurant?->owner_id)
            ->merge($order->restaurant?->staff?->pluck('user_id') ?? collect())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function existingConversationBetween(User $user, User $otherUser): bool
    {
        return DirectChatConversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->whereHas('participants', fn ($query) => $query->whereKey($otherUser->id))
            ->exists();
    }

    private function adminUserIds(): array
    {
        return User::role(['super_admin', 'admin'])->pluck('id')->all();
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    private function isCustomer(User $user): bool
    {
        return $user->hasRole('customer');
    }

    private function isDriver(User $user): bool
    {
        return $user->hasRole('delivery_partner');
    }

    private function isRestaurantUser(User $user): bool
    {
        return $user->hasAnyRole(['restaurant_owner', 'restaurant_staff']);
    }

    private function conversationPayload(DirectChatConversation $conversation, User $viewer): array
    {
        $participant = $conversation->participants->firstWhere('id', $viewer->id);
        $others = $conversation->participants->where('id', '!=', $viewer->id)->values();
        $lastReadAt = $participant?->pivot?->last_read_at;
        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', $viewer->id)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title ?: $others->pluck('name')->filter()->join(', '),
            'context_type' => $conversation->context_type ?? 'direct',
            'order' => $conversation->order ? [
                'id' => $conversation->order->id,
                'order_number' => $conversation->order->order_number,
                'status' => $conversation->order->status,
            ] : null,
            'participants' => $conversation->participants->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])->values(),
            'last_message' => $conversation->latestMessage ? $this->messagePayload($conversation->latestMessage) : null,
            'unread_count' => $unreadCount,
            'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
        ];
    }

    private function messagePayload(DirectChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'meta' => $message->meta,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
