<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\PayoutSetting;

class BulkPayoutService
{
    public function __construct(
        private PayoutGatewayService $gatewayService,
        private PayoutSettlementService $settlementService,
    )
    {
    }

    public function process(iterable $payouts): array
    {
        $report = ['success' => 0, 'failed' => 0, 'items' => []];

        foreach ($payouts as $payout) {
            $payout = $payout instanceof Payout ? $payout : Payout::find($payout);
            if (!$payout || $payout->status !== 'pending') {
                continue;
            }

            $provider = $payout->gateway ?: PayoutSetting::activeGateway();
            if (!$this->gatewayService->supportsAutomatedProcessing($provider)) {
                $report['failed']++;
                $report['items'][$payout->id] = [
                    'success' => false,
                    'error' => $this->gatewayService->unsupportedAutomationMessage($provider),
                ];
                continue;
            }

            if (! $this->settlementService->ensureFundsReserved(
                $payout->loadMissing(['restaurant.owner', 'driver'])
            )) {
                $report['failed']++;
                $report['items'][$payout->id] = [
                    'success' => false,
                    'error' => 'Payout was already claimed or has insufficient wallet balance.',
                ];
                continue;
            }

            try {
                $result = $this->gatewayService->process($payout, $provider);
                $status = $this->settlementService->settleFromGatewayResult(
                    $payout->loadMissing(['restaurant.owner', 'driver']),
                    $result,
                    auth()->id()
                );

                if ($status === 'failed') {
                    $report['failed']++;
                    $report['items'][$payout->id] = [
                        'success' => false,
                        'error' => $payout->fresh()->failure_reason ?: 'Gateway rejected the payout.',
                    ];
                } else {
                    $report['success']++;
                    $report['items'][$payout->id] = ['success' => true, 'status' => $status];
                }
            } catch (\Throwable $e) {
                $retryCount = (int) $payout->retry_count + 1;
                $this->settlementService->releaseLockedFundsIfNeeded(
                    $payout->loadMissing(['restaurant.owner', 'driver']),
                    true
                );
                $payout->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'retry_count' => $retryCount,
                    'next_retry_at' => now()->addMinutes(2 ** min($retryCount, 8)),
                ]);

                $report['failed']++;
                $report['items'][$payout->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $report;
    }
}
