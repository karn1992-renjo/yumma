<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'driver_assignment_attempts')) {
                $table->unsignedSmallInteger('driver_assignment_attempts')->default(0)->after('driver_id');
            }

            if (!Schema::hasColumn('orders', 'rejected_driver_ids')) {
                $table->json('rejected_driver_ids')->nullable()->after('driver_assignment_attempts');
            }

            if (!Schema::hasColumn('orders', 'driver_assigned_at')) {
                $table->timestamp('driver_assigned_at')->nullable()->after('rejected_driver_ids');
            }

            if (!Schema::hasColumn('orders', 'driver_accepted_at')) {
                $table->timestamp('driver_accepted_at')->nullable()->after('driver_assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'driver_accepted_at',
                'driver_assigned_at',
                'rejected_driver_ids',
                'driver_assignment_attempts',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
