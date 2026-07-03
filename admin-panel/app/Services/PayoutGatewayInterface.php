<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\User;
use App\Models\VendorBankAccount;
use Illuminate\Http\Request;

interface PayoutGatewayInterface
{
    public function createContact(User $vendor): array;

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array;

    public function processPayout(Payout $payout): array;

    public function checkStatus(Payout $payout): array;

    public function handleWebhook(Request $request): array;
}
