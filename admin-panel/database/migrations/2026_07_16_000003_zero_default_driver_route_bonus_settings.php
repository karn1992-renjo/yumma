<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seededDefaults = [
            'multiple_order_bonus_two_orders' => '10',
            'multiple_order_bonus_three_plus_orders' => '20',
            'multiple_order_bonus_extra_order' => '5',
        ];

        foreach ($seededDefaults as $key => $oldValue) {
            DB::table('app_settings')
                ->where('key', $key)
                ->where('value', $oldValue)
                ->update(['value' => '0']);
        }
    }

    public function down(): void
    {
        // Do not restore incentive values automatically. Admin should opt in
        // through settings when driver route bonuses are required.
    }
};
