<?php

namespace App\Services\Ai;

use App\Models\FinancialException;
use App\Models\Order;

class AiFinanceExceptionService
{
    public function detect(bool $createExceptions = false): array
    {
        $codQuery = Order::query()
            ->whereIn('payment_method', ['cod', 'cash'])
            ->where('status', 'delivered')
            ->whereNull('cod_deposited_at')
            ->where('delivered_at', '<', now()->subDay());

        $codPending = [
            'type' => 'cod_deposit_pending',
            'severity' => (clone $codQuery)->sum('cash_collected_amount') > 10000 ? 'high' : 'medium',
            'orders' => (clone $codQuery)->count(),
            'amount' => round((float) (clone $codQuery)->sum('cash_collected_amount'), 2),
        ];

        if ($createExceptions && $codPending['orders'] > 0) {
            FinancialException::firstOrCreate(
                [
                    'exception_type' => 'cod_deposit_pending',
                    'status' => 'open',
                ],
                [
                    'severity' => $codPending['severity'],
                    'expected_amount' => $codPending['amount'],
                    'actual_amount' => 0,
                    'variance_amount' => $codPending['amount'],
                    'context' => $codPending,
                ]
            );
        }

        return ['exceptions' => array_values(array_filter([$codPending], fn ($item) => $item['orders'] > 0))];
    }
}
