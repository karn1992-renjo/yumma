<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                if (! Schema::hasColumn('restaurants', 'tax_deductee_type')) {
                    $table->string('tax_deductee_type', 12)->default('individual')->after('pan');
                }
                if (! Schema::hasColumn('restaurants', 'tds_pan_verified')) {
                    $table->boolean('tds_pan_verified')->default(false)->after('tax_deductee_type');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'pan')) {
                    $table->string('pan', 15)->nullable();
                }
                if (! Schema::hasColumn('users', 'tax_deductee_type')) {
                    $table->string('tax_deductee_type', 12)->default('individual');
                }
            });

            // Backfill driver PAN from the partner application, restaurant-owner
            // PAN from the restaurant they own. DB-agnostic (SQLite + MySQL).
            if (Schema::hasTable('partner_applications')) {
                $col = Schema::hasColumn('partner_applications', 'pan_number') ? 'pan_number'
                    : (Schema::hasColumn('partner_applications', 'pan_card') ? 'pan_card' : null);
                if ($col) {
                    // partner_applications has no user_id column; the created
                    // user is linked back only by email/phone. Match on user_id
                    // when it exists, otherwise fall back to email then phone.
                    $hasUserId = Schema::hasColumn('partner_applications', 'user_id');
                    $usersHavePhone = Schema::hasColumn('users', 'phone');

                    DB::table('partner_applications')
                        ->when($hasUserId, fn ($q) => $q->whereNotNull('user_id'))
                        ->whereNotNull($col)
                        ->where($col, '<>', '')
                        ->orderBy('id')
                        ->chunk(500, function ($rows) use ($col, $hasUserId, $usersHavePhone) {
                            foreach ($rows as $row) {
                                $match = DB::table('users');
                                if ($hasUserId && ! empty($row->user_id)) {
                                    $match->where('id', $row->user_id);
                                } elseif (! empty($row->email)) {
                                    $match->where('email', $row->email);
                                } elseif ($usersHavePhone && ! empty($row->phone)) {
                                    $match->where('phone', $row->phone);
                                } else {
                                    continue;
                                }

                                $match->where(fn ($q) => $q->whereNull('pan')->orWhere('pan', ''))
                                    ->update(['pan' => $row->{$col}]);
                            }
                        });
                }
            }
            if (Schema::hasColumn('restaurants', 'pan')) {
                DB::table('restaurants')
                    ->whereNotNull('owner_id')
                    ->whereNotNull('pan')
                    ->where('pan', '<>', '')
                    ->orderBy('id')
                    ->chunk(500, function ($rows) {
                        foreach ($rows as $row) {
                            DB::table('users')->where('id', $row->owner_id)
                                ->where(fn ($q) => $q->whereNull('pan')->orWhere('pan', ''))
                                ->update(['pan' => $row->pan]);
                        }
                    });
            }
        }

        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'tan')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('tan', 15)->nullable()->after('pan_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                foreach (['tax_deductee_type', 'tds_pan_verified'] as $c) {
                    if (Schema::hasColumn('restaurants', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['pan', 'tax_deductee_type'] as $c) {
                    if (Schema::hasColumn('users', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'tan')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('tan');
            });
        }
    }
};
