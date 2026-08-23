<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE app_settings MODIFY value LONGTEXT NOT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left blank to avoid truncating saved policy content.
    }
};