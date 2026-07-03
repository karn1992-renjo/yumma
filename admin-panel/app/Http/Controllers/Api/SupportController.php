<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $requesterRole = $this->requesterRole($request);

        $tickets = SupportTicket::with('replies.user')
            ->where('user_id', $request->user()->id)
            ->where('requester_role', $requesterRole)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'category' => 'nullable|in:order_issue,payment_issue,technical_support,account_issue,general_inquiry,live_chat',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'requester_role' => 'nullable|in:customer,restaurant,driver',
            'target_app' => 'nullable|in:customer,restaurant,driver',
        ]);

        $requesterRole = $this->requesterRole($request);

        $ticket = SupportTicket::create([
            'restaurant_id' => $validated['restaurant_id'] ?? null,
            'user_id' => $request->user()->id,
            'requester_role' => $requesterRole,
            'ticket_number' => 'TKT' . now()->format('YmdHis') . random_int(100, 999),
            'subject' => $validated['subject'],
            'description' => $validated['message'],
            'category' => $validated['category'] ?? 'general_inquiry',
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin_reply' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support chat started.',
            'data' => $ticket->load('replies.user'),
        ], 201);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless(
            $ticket->user_id === $request->user()->id
                && $ticket->requester_role === $this->requesterRole($request),
            403
        );

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin_reply' => false,
        ]);

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket->fresh('replies.user'),
        ]);
    }

    private function requesterRole(Request $request): string
    {
        $role = strtolower((string) (
            $request->input('requester_role')
            ?: $request->input('target_app')
            ?: $request->header('X-Target-App')
            ?: ''
        ));

        if (in_array($role, ['customer', 'restaurant', 'driver'], true)) {
            return $role;
        }

        if ($request->filled('restaurant_id')) {
            return 'restaurant';
        }

        return 'customer';
    }
}
