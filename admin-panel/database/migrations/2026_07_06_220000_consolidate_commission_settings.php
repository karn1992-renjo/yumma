<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_settings')) {
            return;
        }

        $legacyDriver = DB::table('commission_settings')
            ->whereIn('type', ['delivery_partner', 'admin'])
            ->orderByRaw("CASE WHEN type = 'delivery_partner' THEN 0 ELSE 1 END")
            ->first();

        DB::table('commission_settings')->updateOrInsert(
            ['type' => 'driver'],
            [
                'name' => 'Driver Earning Commission',
                'rate' => (float) ($legacyDriver->rate ?? 5),
                'calculation_type' => $legacyDriver->calculation_type ?? 'percentage',
                'is_active' => true,
                'created_at' => $legacyDriver->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        DB::table('commission_settings')->where('type', 'restaurant')->update([
            'name' => 'Restaurant Earning Commission',
            'updated_at' => now(),
        ]);
        DB::table('commission_settings')->whereIn('type', ['admin', 'delivery_partner'])->delete();

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->whereIn('key', ['commission_rate', 'commission_percentage'])->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('commission_settings')) {
            return;
        }

        $driver = DB::table('commission_settings')->where('type', 'driver')->first();
        DB::table('commission_settings')->updateOrInsert(
            ['type' => 'delivery_partner'],
            [
                'name' => 'Driver Deduction',
                'rate' => (float) ($driver->rate ?? 5),
                'calculation_type' => $driver->calculation_type ?? 'percentage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('commission_settings')->updateOrInsert(
            ['type' => 'admin'],
            [
                'name' => 'Admin Delivery Commission',
                'rate' => 15,
                'calculation_type' => 'percentage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('commission_settings')->where('type', 'driver')->delete();
    }
};
