<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('push_broadcasts', 'notification_type')) {
                $column = $table->string('notification_type', 30)->nullable();
                if (Schema::hasColumn('push_broadcasts', 'role_group')) {
                    $column->after('role_group');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            if (Schema::hasColumn('push_broadcasts', 'notification_type')) {
                $table->dropColumn('notification_type');
            }
        });
    }
};
