<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpSupportController extends Controller
{
    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();
        
        $tickets = SupportTicket::where('restaurant_id', $restaurant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        $openTickets = SupportTicket::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
            
        $resolvedTickets = SupportTicket::where('restaurant_id', $restaurant->id)
            ->where('status', 'resolved')
            ->count();
            
        return view('restaurant.support.index', compact('tickets', 'openTickets', 'resolvedTickets'));
    }
    
    public function create()
    {
        return view('restaurant.support.create');
    }
    
    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:order_issue,payment_issue,technical_support,account_issue,general_inquiry,live_chat',
            'priority' => 'required|in:low,medium,high,urgent',
            'description' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);
        
        $validated['restaurant_id'] = $restaurant->id;
        $validated['user_id'] = Auth::id();
        $validated['requester_role'] = 'restaurant';
        $validated['status'] = 'open';
        $validated['ticket_number'] = 'TKT-' . strtoupper(uniqid());
        
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('support-tickets', 'public');
            $validated['attachment'] = $path;
        }
        
        SupportTicket::create($validated);
        
        return redirect()->route('restaurant.support.index')
            ->with('success', 'Support ticket created successfully! We\'ll get back to you soon.');
    }
    
    public function show($id)
    {
        $restaurant = $this->currentRestaurant();
        $ticket = SupportTicket::where('restaurant_id', $restaurant->id)
            ->with('replies.user')
            ->findOrFail($id);
            
        return view('restaurant.support.show', compact('ticket'));
    }
    
    public function reply(Request $request, $id)
    {
        $restaurant = $this->currentRestaurant();
        $ticket = SupportTicket::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $validated = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:5120',
        ]);
        
        $reply = $ticket->replies()->create([
            'user_id' => Auth::id(),
            'message' => $validated['message'],
        ]);
        
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('support-replies', 'public');
            $reply->update(['attachment' => $path]);
        }
        
        return redirect()->back()->with('success', 'Reply sent successfully!');
    }
    
    public function close($id)
    {
        $restaurant = $this->currentRestaurant();
        $ticket = SupportTicket::where('restaurant_id', $restaurant->id)->findOrFail($id);
        
        $ticket->update(['status' => 'closed']);
        
        return redirect()->route('restaurant.support.index')
            ->with('success', 'Ticket closed successfully!');
    }
    
    public function faq()
    {
        $faqs = [
            [
                'question' => 'How do I update my menu items?',
                'answer' => 'Go to Menu Items from the sidebar, click on any item to edit it. You can update prices, descriptions, availability, and more.',
                'category' => 'Menu Management'
            ],
            [
                'question' => 'How do I process an order?',
                'answer' => 'When a new order comes in, you\'ll see it in the Orders section. You can confirm, start preparing, and mark it as ready for pickup.',
                'category' => 'Orders'
            ],
            [
                'question' => 'How do I create a promo code?',
                'answer' => 'Navigate to Promo Codes and click "Create Promo Code". You can set discount types, values, minimum order amounts, and validity periods.',
                'category' => 'Promotions'
            ],
            [
                'question' => 'How do I view my sales analytics?',
                'answer' => 'Visit the Analytics section to see detailed reports including revenue, orders, popular items, and hourly distribution.',
                'category' => 'Analytics'
            ],
            [
                'question' => 'How do I change my restaurant status?',
                'answer' => 'Use the toggle switch in the header to change your restaurant between Online and Offline status.',
                'category' => 'Settings'
            ],
            [
                'question' => 'What payment methods are supported?',
                'answer' => 'We support cash on delivery, credit/debit cards, UPI, and net banking. Payment settings can be configured by the admin.',
                'category' => 'Payments'
            ],
            [
                'question' => 'How do I handle customer complaints?',
                'answer' => 'For order-related issues, you can cancel orders with a reason. For other issues, create a support ticket and our team will assist you.',
                'category' => 'Customer Support'
            ],
            [
                'question' => 'How do I update my restaurant profile?',
                'answer' => 'Go to Settings to update your restaurant name, address, contact information, and other profile details.',
                'category' => 'Settings'
            ],
        ];
        
        $categories = collect($faqs)->pluck('category')->unique();
        
        return view('restaurant.support.faq', compact('faqs', 'categories'));
    }
    
    public function contact()
    {
        return view('restaurant.support.contact');
    }
}
