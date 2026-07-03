<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportPortalController extends Controller
{
    public function redirectToPortal(): RedirectResponse
    {
        return redirect()->route($this->routePrefix() . '.index');
    }

    public function index(Request $request): View
    {
        $tickets = $this->baseQuery()
            ->with('latestReply')
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        $openTickets = $this->baseQuery()
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $resolvedTickets = $this->baseQuery()
            ->where('status', 'resolved')
            ->count();

        return view('support.portal.index', [
            'tickets' => $tickets,
            'openTickets' => $openTickets,
            'resolvedTickets' => $resolvedTickets,
            ...$this->portalViewData(),
        ]);
    }

    public function create(): View
    {
        return view('support.portal.create', $this->portalViewData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:order_issue,payment_issue,technical_support,account_issue,general_inquiry,live_chat',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['requester_role'] = $this->portalRole();
        $validated['status'] = 'open';
        $validated['ticket_number'] = 'TKT-' . strtoupper(uniqid());
        $validated['restaurant_id'] = null;

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('support-tickets', 'public');
        }

        $ticket = SupportTicket::create($validated);
        $ticket->replies()->create([
            'user_id' => Auth::id(),
            'message' => $validated['description'],
            'is_admin_reply' => false,
        ]);

        return redirect()
            ->route($this->routePrefix() . '.show', $ticket->id)
            ->with('success', 'Live support conversation started successfully.');
    }

    public function show(int $id): View
    {
        $ticket = $this->baseQuery()
            ->with(['replies.user' => fn ($query) => $query->orderBy('created_at', 'asc')])
            ->findOrFail($id);

        return view('support.portal.show', [
            'ticket' => $ticket,
            ...$this->portalViewData(),
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->baseQuery()->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin_reply' => false,
        ]);

        if ($request->hasFile('attachment')) {
            $reply->update([
                'attachment' => $request->file('attachment')->store('support-replies', 'public'),
            ]);
        }

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        }

        return redirect()
            ->route($this->routePrefix() . '.show', $ticket->id)
            ->with('success', 'Reply sent successfully.');
    }

    public function close(int $id): RedirectResponse
    {
        $ticket = $this->baseQuery()->findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return redirect()
            ->route($this->routePrefix() . '.index')
            ->with('success', 'Support conversation closed successfully.');
    }

    protected function baseQuery()
    {
        return SupportTicket::where('user_id', Auth::id())
            ->where('requester_role', $this->portalRole());
    }

    protected function portalRole(): string
    {
        return Auth::user()?->hasRole('delivery_partner') ? 'driver' : 'customer';
    }

    protected function routePrefix(): string
    {
        return $this->portalRole() === 'driver' ? 'driver.support' : 'customer.support';
    }

    protected function portalViewData(): array
    {
        $role = $this->portalRole();

        return [
            'portalRole' => $role,
            'portalLabel' => $role === 'driver' ? 'Driver Support' : 'Customer Support',
            'portalDescription' => $role === 'driver'
                ? 'Talk to dispatch and support about payouts, orders, and delivery issues.'
                : 'Talk to support about orders, payments, refunds, and account issues.',
            'routePrefix' => $this->routePrefix(),
        ];
    }
}
