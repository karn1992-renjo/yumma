<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\PayoutSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PayoutScheduleService
{
    public function __construct(
        private PayoutCalculationService $calculator,
        private BulkPayoutService $bulkPayoutService
    ) {
    }

    public function shouldRunToday(?PayoutSetting $setting = null): bool
    {
        $setting ??= PayoutSetting::where('is_active', true)->first();
        $frequency = $setting?->schedule_frequency ?: AppSetting::getValue('payout_frequency', 'weekly');
        $day = strtolower($setting?->schedule_day ?: AppSetting::getValue('payout_day', 'monday'));

        return match ($frequency) {
            'daily' => true,
            'weekly' => strtolower(now()->format('l')) === $day,
            'biweekly' => now()->weekOfYear % 2 === 0 && strtolower(now()->format('l')) === $day,
            'monthly' => now()->isSameDay(now()->copy()->startOfMonth()),
            default => false,
        };
    }

    public function generateDuePayouts(
        ?string $type = 'all',
        $startDate = null,
        $endDate = null,
        bool $includeEarlierUnpaid = false
    ): array
    {
        $startDate ??= now()->subDay()->startOfDay();
        $endDate ??= now()->subDay()->endOfDay();
        $batchId = 'BATCH_' . now()->format('YmdHis') . '_' . Str::upper(Str::random(6));
        $created = [];

        if (in_array($type, ['all', 'restaurant'], true)) {
            foreach ($this->calculator->aggregateRestaurantPayouts($startDate, $endDate, $includeEarlierUnpaid) as $row) {
                if ($payout = $this->calculator->createPayoutFromAggregate($row, $startDate, $endDate, $batchId)) {
                    $created[] = $payout;
                }
            }
        }

        if (in_array($type, ['all', 'driver'], true)) {
            foreach ($this->calculator->aggregateDriverPayouts($startDate, $endDate, $includeEarlierUnpaid) as $row) {
                if ($payout = $this->calculator->createPayoutFromAggregate($row, $startDate, $endDate, $batchId)) {
                    $created[] = $payout;
                }
            }
        }

        return ['batch_id' => $batchId, 'created' => count($created), 'payouts' => $created];
    }

    public function processScheduled(?string $gateway = null, int $limit = 50): array
    {
        $setting = PayoutSetting::where('is_active', true)->first();
        if (! $setting?->auto_process_enabled) {
            return ['success' => 0, 'failed' => 0, 'items' => []];
        }

        $query = Payout::where('status', 'pending')
            ->where('period_end', '<=', now())
            ->limit($limit);

        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        return $this->bulkPayoutService->process($query->get());
    }

    public function sendAdminSummary(): void
    {
        $email = AppSetting::getValue('admin_email');
        if (!$email) {
            return;
        }

        $summary = [
            'pending_count' => Payout::where('status', 'pending')->count(),
            'pending_amount' => Payout::where('status', 'pending')->sum('amount'),
            'failed_count' => Payout::where('status', 'failed')->count(),
        ];

        Mail::raw(
            "Pending payouts: {$summary['pending_count']}\nPending amount: {$summary['pending_amount']}\nFailed payouts: {$summary['failed_count']}",
            fn ($message) => $message->to($email)->subject('Daily payout summary')
        );
    }
}
