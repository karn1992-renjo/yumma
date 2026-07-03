<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'locked_balance' => 0,
                'currency' => AppSetting::getValue('currency_code', 'INR'),
                'is_active' => true,
            ]
        );

        $transactions = $wallet->transactions()
            ->latest()
            ->paginate(20);

        return view('restaurant.wallet.index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'currencySymbol' => AppSetting::getValue('currency_symbol', '?'),
        ]);
    }
}
