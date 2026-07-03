<?php

namespace App\Console\Commands;

use App\Models\PayoutSetting;
use App\Services\PayoutGatewayService;
use Illuminate\Console\Command;

class CheckPayoutBalance extends Command
{
    protected $signature = 'payouts:check-balance {--alert-threshold=10000}';
    protected $description = 'Check active payout gateway balance.';

    public function handle(PayoutGatewayService $gatewayService): int
    {
        $gateway = PayoutSetting::activeGateway();
        $balance = $gatewayService->balance($gateway);
        $this->line(json_encode($balance, JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
