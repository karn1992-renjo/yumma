<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Services\PayoutGatewayService;
use App\Services\PayoutSettlementService;
use Illuminate\Console\Command;

class SyncPayoutStatus extends Command
{
    protected $signature = 'payouts:sync-status {--hours=24}';
    protected $description = 'Sync recent payout statuses from gateways.';

    public function handle(PayoutGatewayService $gatewayService, PayoutSettlementService $settlementService): int
    {
        $hours = (int) $this->option('hours');
        $count = 0;
        Payout::whereNotNull('gateway_reference_id')
            ->where('updated_at', '>=', now()->subHours($hours))
            ->whereIn('status', ['pending', 'processing', 'queued'])
            ->get()
            ->each(function (Payout $payout) use ($gatewayService, $settlementService, &$count) {
                try {
                    $status = $gatewayService->checkStatus($payout);
                    $settlementService->settleFromStatusPayload(
                        $payout->loadMissing(['restaurant.owner', 'driver']),
                        $status,
                        $payout->gateway
                    );
                    $count++;
                } catch (\Throwable $e) {
                    $payout->update(['failure_reason' => $e->getMessage()]);
                }
            });

        $this->info("Synced {$count} payouts.");
        return self::SUCCESS;
    }
}
