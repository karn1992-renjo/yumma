<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = Wallet::with('user')
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        $transactions = WalletTransaction::with('user')
            ->latest()
            ->limit(15)
            ->get();

        $totalBalance = Wallet::sum('balance');

        return view('admin.wallets.index', compact('wallets', 'transactions', 'totalBalance'));
    }

    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1|max:1000000',
            'description' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $validated['user_id']],
                ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
            );

            $wallet->increment('balance', $validated['amount']);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'admin_credit',
                'amount' => $validated['amount'],
                'balance_after' => $wallet->balance,
                'reference_type' => 'admin_topup',
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()->route('admin.wallets.index')->with('success', 'Wallet topped up successfully.');
    }

    public function users(Request $request)
    {
        $users = User::query()
            ->when($request->q, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['success' => true, 'data' => $users]);
    }
}
