<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GiftCardController extends Controller
{
    public function index(Request $request)
    {
        $giftCards = GiftCard::withCount('redemptions')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        $stats = [
            'active' => GiftCard::where('is_active', true)->count(),
            'total_redemptions' => GiftCardRedemption::count(),
            'redeemed_value' => GiftCardRedemption::sum('amount'),
        ];

        $recentRedemptions = GiftCardRedemption::with([
                'giftCard:id,code,title',
                'user:id,name,email,phone',
                'walletTransaction:id,balance_after',
            ])
            ->latest('redeemed_at')
            ->limit(12)
            ->get();

        return view('admin.gift-cards.index', compact(
            'giftCards',
            'stats',
            'recentRedemptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:gift_cards,code',
            'title' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1|max:1000000',
            'max_redemptions' => 'nullable|integer|min:1|max:1000000',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code'] ?? ''));
        if ($validated['code'] === '') {
            $validated['code'] = $this->generateCode();
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        GiftCard::create($validated);

        return redirect()->route('admin.gift-cards.index')
            ->with('success', 'Gift card created successfully.');
    }

    public function update(Request $request, GiftCard $giftCard)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:1|max:1000000',
            'max_redemptions' => 'nullable|integer|min:1|max:1000000',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $giftCard->update($validated);

        return redirect()->route('admin.gift-cards.index')
            ->with('success', 'Gift card updated successfully.');
    }

    public function destroy(GiftCard $giftCard)
    {
        if ($giftCard->redeemed_count > 0) {
            return redirect()->route('admin.gift-cards.index')
                ->with('error', 'Gift cards with redemptions cannot be deleted. Disable it instead.');
        }

        $giftCard->delete();

        return redirect()->route('admin.gift-cards.index')
            ->with('success', 'Gift card deleted successfully.');
    }

    private function generateCode(): string
    {
        do {
            $code = 'GC-' . strtoupper(Str::random(8));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }
}
