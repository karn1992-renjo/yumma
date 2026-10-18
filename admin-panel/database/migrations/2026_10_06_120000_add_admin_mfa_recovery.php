<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin_mfa_required')->default(true);
            $table->unsignedInteger('admin_mfa_version')->default(0);
            foreach ([1, 2] as $slot) {
                $table->string("admin_mfa_phone_{$slot}", 20)->nullable();
                $table->timestamp("admin_mfa_phone_{$slot}_verified_at")->nullable();
            }
        });
        Schema::create('admin_mfa_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64);
            $table->unsignedInteger('security_version');
            $table->string('password_hash', 64);
            $table->string('purpose', 32);
            $table->text('payload');
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_mfa_challenges');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_mfa_required', 'admin_mfa_version', 'admin_mfa_phone_1', 'admin_mfa_phone_1_verified_at', 'admin_mfa_phone_2', 'admin_mfa_phone_2_verified_at']);
        });
    }
};
