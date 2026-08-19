<?php

namespace App\Http\Controllers\Admin;

use App\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Order;
use App\Services\RefundService;
use App\Services\SupportNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function __construct(
        protected SupportNotificationService $supportNotifications,
        protected RefundService $refundService,
    ) {
    }

    public function index(Request $request)
    {
        $query = SupportConversation::with(['restaurant', 'user', 'order:id,order_number']);

        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('requester_role')) {
            $query->where('requester_role', $request->requester_role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('conversation_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'LIKE', "%{$search}%"));
            });
        }

        $conversations = $query->orderByDesc('last_message_at')->paginate(15);

        $stats = [
            'total' => SupportConversation::count(),
            'bot_resolved' => SupportConversation::where('stage', 'resolved')->whereNull('assigned_to')->count(),
            'escalated_open' => SupportConversation::where('stage', 'human')->whereIn('status', ['open', 'in_progress'])->count(),
            'urgent' => SupportConversation::urgent()->count(),
            'avg_response_time' => $this->calculateAvgResponseTime(),
            'avg_resolution_time' => $this->calculateAvgResolutionTime(),
            'avg_csat' => round((float) SupportConversation::whereNotNull('csat_rating')->avg('csat_rating'), 1),
        ];

        $restaurants = Restaurant::orderBy('name')->get(['id', 'name']);
        $requesterRoles = SupportConversation::select('requester_role')->distinct()->orderBy('requester_role')->pluck('requester_role');

        return view('admin.support.index', compact('conversations', 'stats', 'restaurants', 'requesterRoles'));
    }

    public function show($id)
    {
        $conversation = SupportConversation::with([
            'restaurant',
            'user',
            'order',
            'assignedAdmin',
            'messages' => fn ($query) => $query->with('sender')->orderBy('created_at'),
        ])->findOrFail($id);

        if ($conversation->stage === 'human' && $conversation->status === 'open') {
            $conversation->update(['status' => 'in_progress']);
        }

        return view('admin.support.show', compact('conversation'));
    }

    public function reply(Request $request, $id)
    {
        $conversation = SupportConversation::findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'sender_type' => 'admin',
            'message_type' => 'text',
            'message' => $validated['message'],
            'delivered_at' => now(),
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('support-replies', 'public');
            $message->update(['attachment_path' => $path]);
        }

        $conversation->update([
            'last_message_at' => now(),
            'assigned_to' => $conversation->assigned_to ?: Auth::id(),
            'assigned_at' => $conversation->assigned_at ?: now(),
        ]);

        broadcast(new SupportMessageSent($message))->toOthers();
        $this->supportNotifications->notifyRequesterAboutAdminReply($conversation, $message);

        return redirect()->route('admin.support.show', $conversation->id)->with('success', 'Reply sent successfully!');
    }

    public function updateStatus(Request $request, $id)
    {
        $conversation = SupportConversation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'resolve_notes' => 'nullable|string|required_if:status,resolved',
        ]);

        $oldStatus = $conversation->status;
        $isResolved = $validated['status'] === 'resolved';

        $conversation->update([
            'status' => $validated['status'],
            'stage' => $isResolved ? 'resolved' : $conversation->stage,
            'resolved_at' => $isResolved ? now() : null,
            'resolve_notes' => $validated['resolve_notes'] ?? null,
        ]);

        $system = $conversation->messages()->create([
            'sender_type' => 'system',
            'message_type' => 'system',
            'message' => $isResolved
                ? 'This conversation was marked resolved. How did we do?'
                : "Conversation status changed from {$oldStatus} to {$validated['status']}.",
        ]);

        broadcast(new SupportMessageSent($system))->toOthers();
        $this->supportNotifications->notifyRequesterAboutStatusUpdate($conversation, $oldStatus, $validated['status']);

        $message = $isResolved ? 'Conversation resolved successfully!' : 'Conversation status updated successfully!';

        return redirect()->route('admin.support.show', $conversation->id)->with('success', $message);
    }

    public function assign(Request $request, $id)
    {
        $conversation = SupportConversation::findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $conversation->update([
            'assigned_to' => $validated['assigned_to'],
            'assigned_at' => $validated['assigned_to'] ? now() : null,
        ]);

        return redirect()->route('admin.support.show', $conversation->id)->with('success', 'Conversation assigned successfully!');
    }

    public function resolutionAction(Request $request, $id)
    {
        $conversation = SupportConversation::with('order')->findOrFail($id);
        $order = $conversation->order;

        if (! $order) {
            return redirect()->route('admin.support.show', $conversation->id)->with('error', 'This conversation is not linked to an order.');
        }

        $validated = $request->validate([
            'action' => 'required|in:refund,wallet_credit',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $result = $validated['action'] === 'refund'
            ? $this->runRefundAction($order, $validated)
            : $this->runWalletCreditAction($order, $validated);

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'sender_type' => 'admin',
            'message_type' => 'action',
            'message' => $result['message'],
            'meta' => ['action' => $validated['action'], 'success' => $result['success']],
            'delivered_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        broadcast(new SupportMessageSent($message))->toOthers();

        return redirect()->route('admin.support.show', $conversation->id)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    private function runRefundAction(Order $order, array $data): array
    {
        if ($order->refund_status === 'completed') {
            return ['success' => false, 'message' => 'Refund already processed for this order.'];
        }

        $result = $this->refundService->processRefund($order, $data['reason'], $data['amount'] ?? null);

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? "Refund of \u{20B9}{$result['refund_amount']} issued for order #{$order->order_number}."
                : $result['message'],
        ];
    }

    private function runWalletCreditAction(Order $order, array $data): array
    {
        if (empty($data['amount'])) {
            return ['success' => false, 'message' => 'Enter an amount to credit.'];
        }

        if (! $order->customer_id) {
            return ['success' => false, 'message' => 'This order has no customer to credit.'];
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $order->customer_id],
            ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
        );

        $wallet->increment('balance', $data['amount']);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $order->customer_id,
            'type' => 'support_credit',
            'amount' => $data['amount'],
            'balance_after' => $wallet->balance,
            'reference_type' => 'support_conversation',
            'reference_id' => $order->id,
            'description' => "Goodwill credit: {$data['reason']}",
        ]);

        return ['success' => true, 'message' => "\u{20B9}{$data['amount']} credited to the customer's wallet."];
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array',
            'conversation_ids.*' => 'exists:support_conversations,id',
            'action' => 'required|in:resolve,close,assign,in_progress',
            'assigned_to' => 'required_if:action,assign|nullable|exists:users,id',
        ]);

        $conversations = SupportConversation::whereIn('id', $validated['conversation_ids'])->with('user')->get();

        switch ($validated['action']) {
            case 'resolve':
                foreach ($conversations as $conversation) {
                    $oldStatus = $conversation->status;
                    $conversation->update(['status' => 'resolved', 'stage' => 'resolved', 'resolved_at' => now()]);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate($conversation, $oldStatus, 'resolved');
                }
                $message = count($validated['conversation_ids']) . ' conversations resolved.';
                break;
            case 'close':
                foreach ($conversations as $conversation) {
                    $oldStatus = $conversation->status;
                    $conversation->update(['status' => 'closed']);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate($conversation, $oldStatus, 'closed');
                }
                $message = count($validated['conversation_ids']) . ' conversations closed.';
                break;
            case 'in_progress':
                foreach ($conversations as $conversation) {
                    $oldStatus = $conversation->status;
                    $conversation->update(['status' => 'in_progress']);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate($conversation, $oldStatus, 'in_progress');
                }
                $message = count($validated['conversation_ids']) . ' conversations marked as in progress.';
                break;
            case 'assign':
                foreach ($conversations as $conversation) {
                    $conversation->update([
                        'assigned_to' => $validated['assigned_to'],
                        'assigned_at' => now(),
                    ]);
                }
                $message = count($validated['conversation_ids']) . ' conversations assigned.';
                break;
        }

        return redirect()->route('admin.support.index')->with('success', $message);
    }

    public function export(Request $request)
    {
        $query = SupportConversation::with(['restaurant', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $conversations = $query->orderByDesc('created_at')->get();

        $filename = 'support-conversations-' . date('Y-m-d-His') . '.csv';
        $handle = fopen('php://temp', 'w+');

        fputcsv($handle, [
            'Conversation Number', 'Requester', 'Requester Role', 'Category',
            'Stage', 'Status', 'Priority', 'CSAT', 'Created At', 'Resolved At',
        ]);

        foreach ($conversations as $conversation) {
            fputcsv($handle, [
                $conversation->conversation_number,
                $conversation->user->name ?? 'N/A',
                $conversation->requester_role,
                str_replace('_', ' ', ucfirst($conversation->category)),
                ucfirst($conversation->stage),
                ucfirst($conversation->status),
                ucfirst($conversation->priority),
                $conversation->csat_rating ?? '',
                $conversation->created_at->format('Y-m-d H:i:s'),
                $conversation->resolved_at ? $conversation->resolved_at->format('Y-m-d H:i:s') : '',
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function destroy($id)
    {
        $conversation = SupportConversation::findOrFail($id);

        if ($conversation->status !== 'closed') {
            return redirect()->route('admin.support.index')->with('error', 'Only closed conversations can be deleted.');
        }

        $conversation->delete();

        return redirect()->route('admin.support.index')->with('success', 'Conversation deleted successfully!');
    }

    public function statistics()
    {
        $stats = [
            'by_status' => SupportConversation::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->pluck('count', 'status'),
            'by_stage' => SupportConversation::select('stage', DB::raw('count(*) as count'))
                ->groupBy('stage')->pluck('count', 'stage'),
            'by_category' => SupportConversation::select('category', DB::raw('count(*) as count'))
                ->whereNotIn('status', ['resolved', 'closed'])
                ->groupBy('category')->pluck('count', 'category'),
            'by_day' => SupportConversation::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')->orderBy('date')->get(),
            'avg_resolution_time' => $this->calculateAvgResolutionTime(),
        ];

        return response()->json($stats);
    }

    public function notificationSummary()
    {
        $replyIds = SupportMessage::query()
            ->selectRaw('MAX(id)')
            ->groupBy('conversation_id');

        $conversationsNeedingReply = SupportConversation::query()
            ->with(['restaurant:id,name', 'user:id,name'])
            ->where('stage', 'human')
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('messages', function ($query) use ($replyIds) {
                $query->whereIn('id', $replyIds)
                    ->whereNotIn('sender_type', ['admin', 'system']);
            })
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (SupportConversation $conversation) => [
                'id' => $conversation->id,
                'conversation_number' => $conversation->conversation_number,
                'requester_name' => $conversation->user?->name ?: $conversation->restaurant?->name ?: 'Unknown',
                'requester_role' => $conversation->requester_role,
                'updated_at_human' => optional($conversation->updated_at)?->diffForHumans(),
                'url' => route('admin.support.show', $conversation->id),
            ])
            ->values();

        return response()->json([
            'count' => $conversationsNeedingReply->count(),
            'conversations' => $conversationsNeedingReply,
        ]);
    }

    private function calculateAvgResponseTime()
    {
        try {
            $conversations = SupportConversation::whereHas('messages', function ($query) {
                $query->where('sender_type', 'admin');
            })->with(['messages' => function ($query) {
                $query->where('sender_type', 'admin')->orderBy('created_at', 'asc');
            }])->get();

            if ($conversations->isEmpty()) {
                return 0;
            }

            $totalHours = 0;
            $count = 0;

            foreach ($conversations as $conversation) {
                $firstAdminReply = $conversation->messages->first();
                if ($firstAdminReply) {
                    $totalHours += $conversation->created_at->diffInHours($firstAdminReply->created_at);
                    $count++;
                }
            }

            return $count > 0 ? round($totalHours / $count, 1) : 0;
        } catch (\Exception $e) {
            Log::error('Error calculating avg response time: ' . $e->getMessage());
            return 0;
        }
    }

    private function calculateAvgResolutionTime()
    {
        try {
            $resolved = SupportConversation::whereNotNull('resolved_at')->where('stage', 'resolved')->get();

            if ($resolved->isEmpty()) {
                return 0;
            }

            $totalHours = 0;
            foreach ($resolved as $conversation) {
                $totalHours += $conversation->created_at->diffInHours($conversation->resolved_at);
            }

            return round($totalHours / $resolved->count(), 1);
        } catch (\Exception $e) {
            Log::error('Error calculating avg resolution time: ' . $e->getMessage());
            return 0;
        }
    }
}
