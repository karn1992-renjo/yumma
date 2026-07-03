<?php

namespace App\Services\Concerns;

use App\Models\Payout;
use App\Models\User;

trait ResolvesPayoutPayee
{
    protected function payee(Payout $payout): User
    {
        if ($payout->driver_id && $payout->driver) {
            return $payout->driver;
        }

        if ($payout->restaurant && $payout->restaurant->owner) {
            return $payout->restaurant->owner;
        }

        throw new \RuntimeException('Payout payee not found.');
    }

    protected function bankAccount(Payout $payout)
    {
        return $payout->bankAccount
            ?: $this->payee($payout)->vendorBankAccounts()
                ->where('is_default', true)
                ->first();
    }
}
