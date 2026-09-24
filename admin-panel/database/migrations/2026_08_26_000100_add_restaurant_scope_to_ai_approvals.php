<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_approvals', 'approver_type')) {
                $table->string('approver_type')->default('admin')->after('status');
            }
            if (! Schema::hasColumn('ai_approvals', 'restaurant_id')) {
                $table->unsignedBigInteger('restaurant_id')->nullable()->after('approver_type')->index();
            }
            if (! Schema::hasColumn('ai_approvals', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_approvals', function (Blueprint $table) {
            $table->dropColumn(['approver_type', 'restaurant_id', 'last_reminded_at']);
        });
    }
};
