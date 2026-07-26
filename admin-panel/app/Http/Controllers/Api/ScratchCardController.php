<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CustomerScratchCard;
use App\Models\PromotionCouponCode;
use App\Models\RewardPointTransaction;
use App\Models\RewardRedemption;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\ScratchCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScratchCardController extends Controller
{
    public function __construct(private readonly ScratchCardService $scratchCards)
    {
    }

    public function index(Request $request)
    {
        $cards = CustomerScratchCard::query()
            ->with(['promotion', 'order.restaurant', 'restaurant'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CustomerScratchCard $card) => $this->scratchCards->payload($card))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    public function reveal(Request $request, CustomerScratchCard $scratchCard)
    {
        abort_unless((int) $scratchCard->user_id === (int) $request->user()->id, 403);

        $card = $this->scratchCards->reveal($scratchCard);

        return response()->json([
            'success' => true,
            'data' => $this->scratchCards->payload($card),
            'message' => 'Scratch card revealed.',
        ]);
    }

    public function view(Request $request, CustomerScratchCard $scratchCard)
    {
        abort_unless((int) $scratchCard->user_id === (int) $request->user()->id, 403);

        $card = $this->scratchCards->markViewed($scratchCard);

        return response()->json([
            'success' => true,
            'data' => $this->scratchCards->payload($card),
        ]);
    }

    public function rewardHistory(Request $request)
    {
        $rewards = RewardRedemption::query()
            ->with(['promotion:id,title,promotion_type', 'couponCode:id,code,used_count,usage_limit,ends_at', 'giftCard:id,code,title,amount,expires_at'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rewards,
        ]);
    }

    public function generatedCoupons(Request $request)
    {
        $coupons = PromotionCouponCode::query()
            ->with('promotion:id,title,description,promotion_type,rewards,conditions,starts_at,ends_at,status')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (PromotionCouponCode $coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'status' => $coupon->ends_at && $coupon->ends_at->isPast() ? 'expired' : ($coupon->used_count > 0 ? 'used' : 'unused'),
                'used_count' => $coupon->used_count,
                'usage_limit' => $coupon->usage_limit,
                'expires_at' => $coupon->ends_at,
                'metadata' => $coupon->metadata,
                'promotion' => $coupon->promotion?->toPublicPayload(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    public function rewardPoints(Request $request)
    {
        $transactions = RewardPointTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (int) ($request->user()->reward_points_balance ?? 0),
                'transactions' => $transactions,
                'redemption' => $this->rewardPointRedemptionSettings(),
            ],
        ]);
    }

    public function redeemRewardPoints(Request $request)
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $settings = $this->rewardPointRedemptionSettings();

        if (! $settings['configured']) {
            return response()->json([
                'success' => false,
                'message' => 'Reward point redemption settings are not configured by admin.',
            ], 422);
        }

        if (! $settings['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Reward point redemption is currently disabled.',
            ], 422);
        }

        $minimumPoints = (int) $settings['minimum_points'];
        $pointsPerCurrency = (float) $settings['points_per_currency'];
        $points = (int) $validated['points'];

        if ($points < $minimumPoints) {
            return response()->json([
                'success' => false,
                'message' => "Redeem at least {$minimumPoints} points.",
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $points, $pointsPerCurrency) {
            $user = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ((int) ($user->reward_points_balance ?? 0) < $points) {
                abort(422, 'Insufficient reward points.');
            }

            $amount = round($points / $pointsPerCurrency, 2);
            if ($amount <= 0) {
                abort(422, 'Reward points cannot be redeemed for wallet credit.');
            }

            $user->decrement('reward_points_balance', $points);
            $user->refresh();

            $pointTransaction = RewardPointTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'points' => $points,
                'balance_after' => (int) $user->reward_points_balance,
                'reference_type' => 'wallet_redemption',
                'reference_id' => $user->id,
                'description' => 'Reward points redeemed to wallet',
                'meta' => [
                    'wallet_amount' => $amount,
                    'points_per_currency' => $pointsPerCurrency,
                ],
            ]);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
            );
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            $walletTransaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'reward_points_redemption',
                'reference_id' => $pointTransaction->id,
                'description' => 'Reward points redeemed',
                'meta' => [
                    'points' => $points,
                    'points_per_currency' => $pointsPerCurrency,
                    'reward_point_transaction_id' => $pointTransaction->id,
                ],
            ]);

            $redemption = RewardRedemption::create([
                'user_id' => $user->id,
                'wallet_transaction_id' => $walletTransaction->id,
                'reward_type' => 'reward_points_redemption',
                'status' => 'redeemed',
                'amount' => $amount,
                'points' => $points,
                'settlement_key' => 'points_redeem:' . $user->id . ':' . Str::uuid(),
                'payload' => [
                    'points_per_currency' => $pointsPerCurrency,
                    'reward_point_transaction_id' => $pointTransaction->id,
                ],
                'issued_at' => now(),
                'redeemed_at' => now(),
            ]);

            return [
                'wallet' => $wallet,
                'wallet_transaction' => $walletTransaction,
                'reward_point_transaction' => $pointTransaction,
                'redemption' => $redemption,
                'amount' => $amount,
                'points' => $points,
                'balance' => (int) $user->reward_points_balance,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Reward points redeemed successfully.',
                'data' => $result,
        ]);
    }

    private function rewardPointRedemptionSettings(): array
    {
        $enabledValue = AppSetting::getValue('reward_points_redemption_enabled');
        $minimumValue = AppSetting::getValue('reward_points_min_redeem');
        $pointsValue = AppSetting::getValue('reward_points_per_currency');

        $minimumConfigured = $minimumValue !== null && $minimumValue !== '' && is_numeric($minimumValue);
        $pointsConfigured = $pointsValue !== null && $pointsValue !== '' && is_numeric($pointsValue) && (float) $pointsValue > 0;

        return [
            'configured' => $enabledValue !== null && $enabledValue !== '' && $minimumConfigured && $pointsConfigured,
            'enabled' => filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN),
            'minimum_points' => $minimumConfigured ? max(1, (int) $minimumValue) : null,
            'points_per_currency' => $pointsConfigured ? (float) $pointsValue : null,
        ];
    }
}
