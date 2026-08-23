<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Models\GigIncentive;
use App\Models\GigPayoutApproval;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GigPayoutApprovalService
{
    public function stageOrRelease(DriverGig $gig, DriverGigBooking $booking, GigIncentive $incentive): ?GigPayoutApproval
    {
        if (! Schema::hasTable('gig_payout_approvals')) {
            $this->creditWallet($gig, $booking, $incentive);
            return null;
        }

        $risk = app(GigFraudDetectionService::class)->summaryForBooking($booking);
        $requiresApproval = (bool) AppSetting::getValue('gig_incentive_requires_approval', false)
            || ($risk['status'] ?? 'clear') === 'review_required';

        $approval = GigPayoutApproval::updateOrCreate(
            ['driver_gig_booking_id' => $booking->id],
            [
                'gig_incentive_id' => $incentive->id,
                'driver_gig_id' => $gig->id,
                'driver_id' => $booking->driver_id,
                'amount' => $incentive->total_earned,
                'status' => $requiresApproval ? 'pending' : 'approved',
                'risk_summary' => $risk,
                'approved_at' => $requiresApproval ? null : now(),
                'approved_by' => null,
            ]
        );

        if (Schema::hasColumn('gig_incentives', 'approval_status')) {
            $incentive->forceFill(['approval_status' => $approval->status])->save();
        }
        if (Schema::hasColumn('gig_incentives', 'fraud_status')) {
            $incentive->forceFill(['fraud_status' => $risk['status'] ?? 'clear'])->save();
        }

        if (! $requiresApproval) {
            $this->creditWallet($gig, $booking, $incentive);
        }

        return $approval;
    }

    public function approve(GigPayoutApproval $approval, ?int $adminId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($approval, $adminId, $note) {
            $approval->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $adminId,
                'admin_note' => $note,
            ])->save();

            $incentive = $approval->incentive;
            $booking = $approval->booking;
            $gig = $approval->gig;
            if ($incentive && $booking && $gig) {
                if (Schema::hasColumn('gig_incentives', 'approval_status')) {
                    $incentive->forceFill(['approval_status' => 'approved'])->save();
                }
                $this->creditWallet($gig, $booking, $incentive);
            }
        });
    }

    public function reject(GigPayoutApproval $approval, ?int $adminId = null, ?string $note = null): void
    {
        $approval->forceFill([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $adminId,
            'admin_note' => $note,
        ])->save();

        if ($approval->incentive && Schema::hasColumn('gig_incentives', 'approval_status')) {
            $approval->incentive->forceFill(['approval_status' => 'rejected'])->save();
        }
    }

    public function creditWallet(DriverGig $gig, DriverGigBooking $booking, GigIncentive $incentive): void
    {
        $amount = (float) ($incentive->total_earned ?? 0);
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($gig, $booking, $incentive, $amount) {
            $wallet = Wallet::where('user_id', $booking->driver_id)->lockForUpdate()->first()
                ?: Wallet::create([
                    'user_id' => $booking->driver_id,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                    'is_active' => true,
                ]);

            $exists = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('reference_type', 'driver_gig_booking_incentive')
                ->where('reference_id', $booking->id)
                ->exists();

            if ($exists) {
                return;
            }

            $wallet->increment('balance', $amount);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'driver_gig_booking_incentive',
                'reference_id' => $booking->id,
                'description' => 'Gig incentive for ' . ($gig->title ?: 'scheduled gig'),
                'meta' => [
                    'source' => 'gig',
                    'driver_gig_id' => $gig->id,
                    'gig_incentive_id' => $incentive->id,
                    'orders_completed' => $incentive->orders_completed ?? [],
                    'online_minutes' => $incentive->active_minutes ?? 0,
                    'surge_multiplier' => $incentive->surge_multiplier ?? 1,
                ],
            ]);

            if (Schema::hasColumn('driver_gig_bookings', 'incentive_paid_at')) {
                $booking->forceFill(['incentive_paid_at' => now()])->save();
            }
        });
    }
}