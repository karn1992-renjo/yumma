<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'firebase_uid')) {
                $table->string('firebase_uid')->nullable()->after('fcm_token')->index();
            }

            if (! Schema::hasColumn('users', 'social_provider')) {
                $table->string('social_provider', 32)->nullable()->after('firebase_uid')->index();
            }

            if (! Schema::hasColumn('users', 'social_provider_id')) {
                $table->string('social_provider_id', 191)->nullable()->after('social_provider')->index();
            }

            if (! Schema::hasColumn('users', 'social_avatar_url')) {
                $table->string('social_avatar_url', 2048)->nullable()->after('social_provider_id');
            }

            if (! Schema::hasColumn('users', 'social_accounts')) {
                $table->json('social_accounts')->nullable()->after('social_avatar_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'social_accounts',
                'social_avatar_url',
                'social_provider_id',
                'social_provider',
                'firebase_uid',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
