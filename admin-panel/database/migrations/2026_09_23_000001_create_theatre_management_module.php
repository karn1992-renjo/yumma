<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-seat food ordering for movie theatres. Built here first and since
 * ported to Swado as well (24 Sep 2026) — the two implementations are kept
 * deliberately identical so a fix can move either way. Modeled closely on the
 * Branch management module (2026_06_12_000001_create_branch_management_module.php)
 * but Theatre<->Restaurant is many-to-many (a stall can serve more than one
 * theatre, e.g. a mall food-court vendor) rather than the branch's
 * one-restaurant-belongs-to-one-branch column, and there is deliberately no
 * wallet/settlement/payout module — a theatre owner doesn't earn commission
 * the way a branch does, they just host stalls and runners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theatres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo')->nullable();
            $table->string('owner_name');
            $table->string('owner_email')->unique();
            $table->string('owner_phone')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Server-enforced presence check radius for the "must be inside
            // the theatre to order" gate -- see TheatreManagementService::withinGeofence().
            $table->unsignedInteger('geofence_radius_meters')->default(120);
            // Whether runners hand off at the row/aisle end instead of
            // walking into a dark, playing auditorium -- a per-theatre
            // operational policy the owner sets, not a global rule.
            $table->boolean('aisle_handoff_only')->default(false);
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('theatre_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->index();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['theatre_id', 'user_id']);
        });

        Schema::create('theatre_screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->string('name');
            // Full drag-and-drop layout (row labels, seat counts, curve
            // control points, aisle gaps, section tags) -- built in the
            // seat-map-builder phase; null until an owner has saved a
            // layout for this screen. Never tracks seat availability, only
            // the shape used to render/validate a delivery-location label.
            $table->json('seat_map_config')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['theatre_id', 'name']);
        });

        // Many-to-many: a theatre hosts several stalls, and in principle a
        // stall (e.g. a mall food-court vendor) could serve more than one
        // theatre -- unlike Branch<->Restaurant, this is NOT a branch_id-style
        // column on restaurants.
        Schema::create('theatre_restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['theatre_id', 'restaurant_id']);
        });

        // Persistent "this user is a runner at this theatre" roster,
        // separate from their per-shift DriverGig rows -- mirrors
        // branch_drivers (a persistent roster entry distinct from a gig
        // booking).
        Schema::create('theatre_runners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->foreignId('runner_id')->constrained('users')->cascadeOnDelete();
            $table->string('approval_status')->default('active')->index();
            $table->timestamps();
            $table->unique(['theatre_id', 'runner_id']);
        });

        Schema::create('theatre_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->nullable()->constrained('theatres')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        // Parent record for a checkout that spans multiple stalls in one
        // theatre -- one payment, fanned out into one real `orders` row per
        // stall (each tagged with this group's id). See
        // movie-theatre-in-seat-ordering memory / plan for the full design.
        Schema::create('order_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('theatre_screens')->cascadeOnDelete();
            $table->string('seat_label');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('pending')->index();
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });

        Schema::table('driver_gigs', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_gigs', 'theatre_id')) {
                $table->foreignId('theatre_id')->nullable()->after('branch_id')->constrained('theatres')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'theatre_id')) {
                $table->foreignId('theatre_id')->nullable()->after('branch_id')->constrained('theatres')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'screen_id')) {
                $table->foreignId('screen_id')->nullable()->after('theatre_id')->constrained('theatre_screens')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'seat_label')) {
                $table->string('seat_label')->nullable()->after('screen_id');
            }
            if (! Schema::hasColumn('orders', 'order_group_id')) {
                $table->foreignId('order_group_id')->nullable()->after('seat_label')->constrained('order_groups')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'theatre_id')) {
                $table->foreignId('theatre_id')->nullable()->after('branch_id')->constrained('theatres')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'theatre_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('theatre_id'));
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['order_group_id', 'seat_label', 'screen_id', 'theatre_id'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        if ($column === 'seat_label') {
                            $table->dropColumn($column);
                        } else {
                            $table->dropConstrainedForeignId($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('driver_gigs') && Schema::hasColumn('driver_gigs', 'theatre_id')) {
            Schema::table('driver_gigs', fn (Blueprint $table) => $table->dropConstrainedForeignId('theatre_id'));
        }

        Schema::dropIfExists('order_groups');
        Schema::dropIfExists('theatre_audit_logs');
        Schema::dropIfExists('theatre_runners');
        Schema::dropIfExists('theatre_restaurants');
        Schema::dropIfExists('theatre_screens');
        Schema::dropIfExists('theatre_users');
        Schema::dropIfExists('theatres');
    }
};
