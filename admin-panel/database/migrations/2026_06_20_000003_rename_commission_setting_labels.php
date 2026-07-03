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

        DB::table('commission_settings')->where('type', 'admin')->update([
            'name' => 'Admin Delivery Commission',
        ]);
        DB::table('commission_settings')->where('type', 'restaurant')->update([
            'name' => 'Platform Commission Charged to Restaurant',
        ]);
        DB::table('commission_settings')->where('type', 'delivery_partner')->update([
            'name' => 'Driver Deduction',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('commission_settings')) {
            return;
        }

        DB::table('commission_settings')->where('type', 'admin')->update([
            'name' => 'Admin Commission',
        ]);
        DB::table('commission_settings')->where('type', 'restaurant')->update([
            'name' => 'Restaurant Commission',
        ]);
        DB::table('commission_settings')->where('type', 'delivery_partner')->update([
            'name' => 'Delivery Partner Commission',
        ]);
    }
};
