<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['user:id,name,email', 'restaurant:id,name', 'order:id,order_number']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', (int) $request->restaurant_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'LIKE', "%{$search}%"));
            });
        }

        $reviews = $query->latest()->paginate(20)->withQueryString();
        $restaurants = Restaurant::orderBy('name')->get(['id', 'name']);

        $stats = [
            'total' => Review::count(),
            'approved' => Review::where('status', 'approved')->count(),
            'pending' => Review::where('status', 'pending')->count(),
            'rejected' => Review::where('status', 'rejected')->count(),
            'average' => round((float) (Review::where('status', 'approved')->avg('rating') ?? 0), 1),
        ];

        return view('admin.reviews.index', compact('reviews', 'restaurants', 'stats'));
    }

    public function updateStatus(Request $request, Review $review)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,pending,rejected',
        ]);

        $review->update([
            'status' => $validated['status'],
            'is_verified' => $validated['status'] === 'approved',
        ]);

        $this->refreshRestaurantRating($review->restaurant_id);

        return back()->with('success', 'Review status updated successfully.');
    }

    private function refreshRestaurantRating(?int $restaurantId): void
    {
        if (!$restaurantId) {
            return;
        }

        $summary = Review::where('restaurant_id', $restaurantId)
            ->where('status', 'approved')
            ->where('is_verified', true)
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_reviews')
            ->first();

        Restaurant::whereKey($restaurantId)->update([
            'rating' => (int) ($summary->total_reviews ?? 0) >= 3
                ? round((float) $summary->average_rating, 1)
                : 0,
            'total_ratings' => (int) ($summary->total_reviews ?? 0),
        ]);
    }
}