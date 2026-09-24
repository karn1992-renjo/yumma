<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('push_broadcasts', 'campaign_type')) {
                $table->string('campaign_type', 40)->nullable()->after('notification_type');
            }
            if (! Schema::hasColumn('push_broadcasts', 'image_status')) {
                // null | queued | ready | skipped | failed
                $table->string('image_status', 20)->nullable()->after('campaign_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            foreach (['campaign_type', 'image_status'] as $column) {
                if (Schema::hasColumn('push_broadcasts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
