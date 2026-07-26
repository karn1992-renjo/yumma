<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_gigs', 'capacity')) {
                $table->unsignedInteger('capacity')->default(1)->after('area_id');
            }
        });

        if (! Schema::hasTable('driver_gig_bookings')) {
            Schema::create('driver_gig_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_gig_id')->constrained('driver_gigs')->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['booked', 'completed', 'cancelled'])->default('booked');
                $table->timestamp('booked_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['driver_gig_id', 'driver_id'], 'driver_gig_bookings_gig_driver_unique');
                $table->index(['driver_id', 'status']);
                $table->index(['driver_gig_id', 'status']);
            });
        }

        Schema::table('gig_incentives', function (Blueprint $table) {
            if (! Schema::hasColumn('gig_incentives', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('driver_gig_id');
                $table->index('driver_id', 'gig_incentives_driver_id_index');
            }
        });

        if (Schema::hasTable('driver_gig_bookings')) {
            DB::table('driver_gigs')
                ->whereNotNull('driver_id')
                ->orderBy('id')
                ->chunkById(100, function ($gigs) {
                    foreach ($gigs as $gig) {
                        DB::table('driver_gig_bookings')->updateOrInsert(
                            [
                                'driver_gig_id' => $gig->id,
                                'driver_id' => $gig->driver_id,
                            ],
                            [
                                'status' => in_array($gig->status, ['completed', 'cancelled'], true) ? $gig->status : 'booked',
                                'booked_at' => $gig->booked_at ?? $gig->created_at,
                                'completed_at' => $gig->status === 'completed' ? ($gig->updated_at ?? now()) : null,
                                'cancelled_at' => $gig->status === 'cancelled' ? ($gig->updated_at ?? now()) : null,
                                'created_at' => $gig->created_at ?? now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });

            DB::table('gig_incentives')
                ->whereNull('driver_id')
                ->orderBy('id')
                ->chunkById(100, function ($incentives) {
                    foreach ($incentives as $incentive) {
                        $driverId = DB::table('driver_gigs')
                            ->where('id', $incentive->driver_gig_id)
                            ->value('driver_id');

                        if (! $driverId) {
                            continue;
                        }

                        DB::table('gig_incentives')
                            ->where('id', $incentive->id)
                            ->update(['driver_id' => $driverId]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('gig_incentives', function (Blueprint $table) {
            if (Schema::hasColumn('gig_incentives', 'driver_id')) {
                $table->dropIndex('gig_incentives_driver_id_index');
                $table->dropColumn('driver_id');
            }
        });

        Schema::dropIfExists('driver_gig_bookings');

        Schema::table('driver_gigs', function (Blueprint $table) {
            if (Schema::hasColumn('driver_gigs', 'capacity')) {
                $table->dropColumn('capacity');
            }
        });
    }
};
