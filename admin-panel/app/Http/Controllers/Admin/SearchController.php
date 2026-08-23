<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across multiple entities.
     * Returns JSON for the header search autocomplete.
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $limit = (int) $request->input('limit', 5);

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'query' => $query,
                'results' => [],
                'recent' => $this->getRecentSearches($request),
            ]);
        }

        $results = [];

        // Search Orders
        $orders = Order::where(function ($q) use ($query) {
            $q->where('order_number', 'like', "%{$query}%")
              ->orWhere('customer_name', 'like', "%{$query}%")
              ->orWhere('customer_phone', 'like', "%{$query}%");
        })
            ->with(['restaurant', 'customer'])
            ->latest()
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            $results[] = [
                'type' => 'order',
                'label' => 'Order',
                'id' => $order->id,
                'title' => '#' . ($order->order_number ?? $order->id),
                'subtitle' => ($order->customer_name ?? 'Guest') . ' • ' . ($order->restaurant->name ?? 'Restaurant'),
                'amount' => $order->total,
                'status' => $order->status,
                'url' => route('admin.orders.show', $order->id),
            ];
        }

        // Search Restaurants
        $restaurants = Restaurant::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('phone', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
            ->limit($limit)
            ->get();

        foreach ($restaurants as $restaurant) {
            $results[] = [
                'type' => 'restaurant',
                'label' => 'Restaurant',
                'id' => $restaurant->id,
                'title' => $restaurant->name,
                'subtitle' => $restaurant->city ?? $restaurant->address ?? '',
                'status' => $restaurant->is_open ? 'open' : 'closed',
                'url' => route('admin.restaurants.show', $restaurant->id),
            ];
        }

        // Search Users (customers)
        $users = User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        })
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        foreach ($users as $user) {
            $results[] = [
                'type' => 'user',
                'label' => 'Customer',
                'id' => $user->id,
                'title' => $user->name,
                'subtitle' => $user->email ?? $user->phone ?? '',
                'url' => route('admin.users.show', $user->id),
            ];
        }

        // Search Drivers
        $drivers = User::role('delivery_partner')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        foreach ($drivers as $driver) {
            $results[] = [
                'type' => 'driver',
                'label' => 'Driver',
                'id' => $driver->id,
                'title' => $driver->name,
                'subtitle' => $driver->email ?? $driver->phone ?? '',
                'url' => route('admin.drivers.show', $driver->id),
            ];
        }

        // Save to recent searches (session-based)
        $this->saveRecentSearch($request, $query);

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results,
            'recent' => $this->getRecentSearches($request),
        ]);
    }

    /**
     * Get recent searches from session.
     */
    protected function getRecentSearches(Request $request): array
    {
        return $request->session()->get('admin_recent_searches', []);
    }

    /**
     * Save a search query to session (max 5 recent).
     */
    protected function saveRecentSearch(Request $request, string $query): void
    {
        $recent = $request->session()->get('admin_recent_searches', []);

        // Remove if already exists, then prepend
        $recent = array_filter($recent, fn ($item) => $item !== $query);
        array_unshift($recent, $query);

        // Keep only last 5
        $recent = array_slice($recent, 0, 5);

        $request->session()->put('admin_recent_searches', $recent);
    }

    /**
     * Clear recent searches.
     */
    public function clearRecent(Request $request)
    {
        $request->session()->forget('admin_recent_searches');

        return response()->json(['success' => true]);
    }
}
