<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\SupportNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function __construct(
        protected SupportNotificationService $supportNotifications
    ) {
    }

    /**
     * Display a listing of support tickets for admin
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['restaurant', 'user']);
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        
        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        // Filter by restaurant
        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        // Filter by requester role
        if ($request->filled('requester_role')) {
            $query->where('requester_role', $request->requester_role);
        }
        
        // Search by ticket number or subject
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%");
            });
        }
        
        $tickets = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Statistics
        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'urgent' => SupportTicket::where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'avg_response_time' => $this->calculateAvgResponseTime(),
        ];
        
        $restaurants = Restaurant::orderBy('name')->get(['id', 'name']);
        $requesterRoles = SupportTicket::select('requester_role')
            ->distinct()
            ->orderBy('requester_role')
            ->pluck('requester_role');
        
        return view('admin.support.index', compact('tickets', 'stats', 'restaurants', 'requesterRoles'));
    }
    
    /**
     * Display the specified ticket details
     */
    public function show($id)
    {
        $ticket = SupportTicket::with([
            'restaurant',
            'user',
            'replies' => function($query) {
                $query->with('user')->orderBy('created_at', 'asc');
            }
        ])->findOrFail($id);
        
        // Mark as in_progress if admin is viewing an open ticket
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }
        
        return view('admin.support.show', compact('ticket'));
    }
    
    /**
     * Reply to a support ticket
     */
    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        $validated = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120', // Max 5MB
            'status' => 'nullable|in:open,in_progress,resolved,closed',
        ]);
        
        $reply = $ticket->replies()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin_reply' => true,
        ]);
        
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('support-replies', 'public');
            $reply->update(['attachment' => $path]);
        }
        
        // Update ticket status if provided
        if ($request->filled('status')) {
            $ticket->update(['status' => $request->status]);
        }
        
        $this->supportNotifications->notifyRequesterAboutAdminReply($ticket, $reply);
        
        return redirect()->route('admin.support.show', $ticket->id)
            ->with('success', 'Reply sent successfully!');
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'resolve_notes' => 'nullable|string|required_if:status,resolved',
        ]);
        
        $oldStatus = $ticket->status;
        $ticket->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'resolved' ? now() : null,
            'resolve_notes' => $validated['resolve_notes'] ?? null,
        ]);
        
        // Add system note for status change
        $ticket->replies()->create([
            'user_id' => Auth::id(),
            'message' => "**System Update:** Ticket status changed from {$oldStatus} to {$validated['status']}",
            'is_system_message' => true,
        ]);

        $this->supportNotifications->notifyRequesterAboutStatusUpdate(
            $ticket,
            $oldStatus,
            $validated['status']
        );
        
        $message = $validated['status'] === 'resolved' 
            ? 'Ticket resolved successfully!' 
            : 'Ticket status updated successfully!';
        
        return redirect()->route('admin.support.show', $ticket->id)
            ->with('success', $message);
    }
    
    /**
     * Assign ticket to admin
     */
    public function assign(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);
        
        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'assigned_at' => $validated['assigned_to'] ? now() : null,
        ]);
        
        return redirect()->route('admin.support.show', $ticket->id)
            ->with('success', 'Ticket assigned successfully!');
    }
    
    /**
     * Bulk update tickets
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ticket_ids' => 'required|array',
            'ticket_ids.*' => 'exists:support_tickets,id',
            'action' => 'required|in:resolve,close,assign,in_progress',
            'assigned_to' => 'required_if:action,assign|nullable|exists:users,id',
        ]);
        
        $tickets = SupportTicket::whereIn('id', $validated['ticket_ids'])
            ->with(['user', 'restaurant.owner'])
            ->get();

        switch ($validated['action']) {
            case 'resolve':
                foreach ($tickets as $ticket) {
                    $oldStatus = $ticket->status;
                    $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate(
                        $ticket,
                        $oldStatus,
                        'resolved'
                    );
                }
                $message = count($validated['ticket_ids']) . ' tickets resolved.';
                break;
            case 'close':
                foreach ($tickets as $ticket) {
                    $oldStatus = $ticket->status;
                    $ticket->update(['status' => 'closed']);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate(
                        $ticket,
                        $oldStatus,
                        'closed'
                    );
                }
                $message = count($validated['ticket_ids']) . ' tickets closed.';
                break;
            case 'in_progress':
                foreach ($tickets as $ticket) {
                    $oldStatus = $ticket->status;
                    $ticket->update(['status' => 'in_progress']);
                    $this->supportNotifications->notifyRequesterAboutStatusUpdate(
                        $ticket,
                        $oldStatus,
                        'in_progress'
                    );
                }
                $message = count($validated['ticket_ids']) . ' tickets marked as in progress.';
                break;
            case 'assign':
                foreach ($tickets as $ticket) {
                    $ticket->update([
                        'assigned_to' => $validated['assigned_to'],
                        'assigned_at' => now(),
                    ]);
                }
                $message = count($validated['ticket_ids']) . ' tickets assigned.';
                break;
        }
        
        return redirect()->route('admin.support.index')
            ->with('success', $message);
    }
    
    /**
     * Export tickets to CSV
     */
    public function export(Request $request)
    {
        $query = SupportTicket::with(['restaurant', 'user']);
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $tickets = $query->orderBy('created_at', 'desc')->get();
        
        $filename = 'support-tickets-' . date('Y-m-d-His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        // Add CSV headers
        fputcsv($handle, [
            'Ticket Number', 'Restaurant', 'Customer', 'Subject', 'Category', 
            'Priority', 'Status', 'Created At', 'Resolved At', 'Last Reply'
        ]);
        
        foreach ($tickets as $ticket) {
            fputcsv($handle, [
                $ticket->ticket_number,
                $ticket->restaurant->name ?? 'N/A',
                $ticket->user->name ?? 'N/A',
                $ticket->subject,
                str_replace('_', ' ', ucfirst($ticket->category)),
                ucfirst($ticket->priority),
                ucfirst($ticket->status),
                $ticket->created_at->format('Y-m-d H:i:s'),
                $ticket->resolved_at ? $ticket->resolved_at->format('Y-m-d H:i:s') : '',
                $ticket->replies->last()?->created_at->format('Y-m-d H:i:s') ?? '',
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
    
    /**
     * Delete a ticket (soft delete or permanent)
     */
    public function destroy($id)
    {
        $ticket = SupportTicket::findOrFail($id);
        
        // Optional: Check if ticket can be deleted
        if ($ticket->status !== 'closed') {
            return redirect()->route('admin.support.index')
                ->with('error', 'Only closed tickets can be deleted.');
        }
        
        $ticket->delete();
        
        return redirect()->route('admin.support.index')
            ->with('success', 'Ticket deleted successfully!');
    }
    
    /**
     * Get ticket statistics for dashboard
     */
    public function statistics()
    {
        $stats = [
            'by_status' => SupportTicket::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_priority' => SupportTicket::select('priority', DB::raw('count(*) as count'))
                ->whereNotIn('status', ['resolved', 'closed'])
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'by_category' => SupportTicket::select('category', DB::raw('count(*) as count'))
                ->whereNotIn('status', ['resolved', 'closed'])
                ->groupBy('category')
                ->pluck('count', 'category'),
            'by_day' => SupportTicket::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'avg_resolution_time' => $this->calculateAvgResolutionTime(),
        ];
        
        return response()->json($stats);
    }

    public function notificationSummary()
    {
        $replyIds = SupportTicketReply::query()
            ->selectRaw('MAX(id)')
            ->groupBy('ticket_id');

        $ticketsNeedingReply = SupportTicket::query()
            ->with(['restaurant:id,name', 'user:id,name'])
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('replies', function ($query) use ($replyIds) {
                $query->whereIn('id', $replyIds)
                    ->where('is_admin_reply', false)
                    ->where('is_system_message', false);
            })
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'requester_name' => $ticket->user?->name ?: $ticket->restaurant?->name ?: 'Unknown',
                'requester_role' => $ticket->requester_role,
                'updated_at_human' => optional($ticket->updated_at)?->diffForHumans(),
                'url' => route('admin.support.show', $ticket->id),
            ])
            ->values();

        return response()->json([
            'count' => $ticketsNeedingReply->count(),
            'tickets' => $ticketsNeedingReply,
        ]);
    }
    
    /**
     * Calculate average response time (SQLite compatible)
     */
    private function calculateAvgResponseTime()
    {
        try {
            // Get all tickets that have admin replies
            $tickets = SupportTicket::whereHas('replies', function($query) {
                $query->where('is_admin_reply', true);
            })->with(['replies' => function($query) {
                $query->where('is_admin_reply', true)->orderBy('created_at', 'asc');
            }])->get();
            
            if ($tickets->isEmpty()) {
                return 0;
            }
            
            $totalHours = 0;
            $count = 0;
            
            foreach ($tickets as $ticket) {
                $firstAdminReply = $ticket->replies->first();
                if ($firstAdminReply) {
                    // Calculate difference in hours using diffInHours method
                    $hours = $ticket->created_at->diffInHours($firstAdminReply->created_at);
                    $totalHours += $hours;
                    $count++;
                }
            }
            
            return $count > 0 ? round($totalHours / $count, 1) : 0;
            
        } catch (\Exception $e) {
            Log::error('Error calculating avg response time: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calculate average resolution time (SQLite compatible)
     */
    private function calculateAvgResolutionTime()
    {
        try {
            $resolvedTickets = SupportTicket::whereNotNull('resolved_at')
                ->where('status', 'resolved')
                ->get();
            
            if ($resolvedTickets->isEmpty()) {
                return 0;
            }
            
            $totalHours = 0;
            foreach ($resolvedTickets as $ticket) {
                $hours = $ticket->created_at->diffInHours($ticket->resolved_at);
                $totalHours += $hours;
            }
            
            return round($totalHours / $resolvedTickets->count(), 1);
            
        } catch (\Exception $e) {
            Log::error('Error calculating avg resolution time: ' . $e->getMessage());
            return 0;
        }
    }
    
}
