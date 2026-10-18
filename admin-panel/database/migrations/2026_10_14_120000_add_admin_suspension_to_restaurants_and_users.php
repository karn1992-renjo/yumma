<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_verified');
            $table->timestamp('suspended_until')->nullable()->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('suspended_until');
            $table->foreignId('suspended_by')->nullable()->after('suspension_reason')
                ->constrained('users')->nullOnDelete();
            $table->index(['suspended_at', 'suspended_until']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_active');
            $table->timestamp('suspended_until')->nullable()->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('suspended_until');
            $table->foreignId('suspended_by')->nullable()->after('suspension_reason')
                ->constrained('users')->nullOnDelete();
            $table->index(['suspended_at', 'suspended_until']);
        });
    }

    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspended_until', 'suspension_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspended_until', 'suspension_reason']);
        });
    }
};
