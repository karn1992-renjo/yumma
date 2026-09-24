<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $accounts = [
            ['1000', 'Bank / settlement account', 'asset', 'bank'],
            ['1100', 'COD receivable (in transit)', 'asset', 'receivable'],
            ['1110', 'Payment gateway receivable (in transit)', 'asset', 'receivable'],
            ['1200', 'Restaurant ad-wallet receivable', 'asset', 'receivable'],
            ['1900', 'Suspense / opening balance', 'asset', 'suspense'],
            ['2000', 'Payable to restaurants', 'liability', 'current_liability'],
            ['2010', 'Payable to drivers', 'liability', 'current_liability'],
            ['2020', 'Payable to branches', 'liability', 'current_liability'],
            ['2100', 'Customer wallet liability', 'liability', 'current_liability'],
            ['2110', 'Gift card / reward liability', 'liability', 'current_liability'],
            ['2200', 'Deferred order revenue', 'liability', 'current_liability'],
            ['2300', 'GST output payable — 9(5) food', 'liability', 'tax_liability'],
            ['2310', 'GST output payable — platform services', 'liability', 'tax_liability'],
            ['2320', 'GST output payable — commission', 'liability', 'tax_liability'],
            ['2330', 'GST TCS payable (Sec 52)', 'liability', 'tax_liability'],
            ['2340', 'TDS payable — 194-O', 'liability', 'tax_liability'],
            ['2350', 'TDS payable — 194-C', 'liability', 'tax_liability'],
            ['2360', 'Gig-worker welfare cess payable', 'liability', 'tax_liability'],
            ['3000', 'Share capital / owner\'s funds', 'equity', 'capital'],
            ['3900', 'Retained earnings', 'equity', 'retained_earnings'],
            ['4000', 'Commission income', 'income', 'operating_income'],
            ['4010', 'Platform-fee income', 'income', 'operating_income'],
            ['4020', 'Delivery income', 'income', 'operating_income'],
            ['4030', 'Advertising income', 'income', 'operating_income'],
            ['4900', 'Other income', 'income', 'other_income'],
            ['5000', 'Driver delivery payout expense', 'expense', 'operating_expense'],
            ['5010', 'Payment gateway fee expense', 'expense', 'operating_expense'],
            ['5020', 'Promotion / delivery subsidy expense', 'expense', 'operating_expense'],
            ['5030', 'Refunds & chargebacks', 'expense', 'operating_expense'],
            ['5900', 'Operating expenses (manual)', 'expense', 'operating_expense'],
        ];

        foreach ($accounts as [$code, $name, $type, $subtype]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'is_system' => true, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chart_of_accounts')) {
            DB::table('chart_of_accounts')->where('is_system', true)->delete();
        }
    }
};
