<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payout_settings')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payout_settings MODIFY credentials LONGTEXT NULL');
            DB::statement('ALTER TABLE payout_settings MODIFY webhook_config LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payout_settings')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payout_settings MODIFY credentials JSON NULL');
            DB::statement('ALTER TABLE payout_settings MODIFY webhook_config JSON NULL');
        }
    }
};
