<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * swaad-only: show times, and with them the second way to order in a
 * cinema -- "bring it at the interval" rather than "bring it now".
 *
 * A show is scheduling data, never ticketing: swaad does not sell tickets
 * (see the movie-theatre-in-seat-ordering project memory), so there is no
 * seat inventory, no holds and no per-show locking here. A show exists
 * only to answer three operational questions: when is the interval, when
 * does ordering close, and which film is a given order attached to.
 *
 * Interval orders are the reason `scheduled_for` / `released_at` land on
 * `orders`. An interval order is paid for and real the moment it is
 * placed, but must NOT hit the kitchen yet -- otherwise a dozen of them
 * are cooked an hour early and arrive cold. So the order is created
 * held, and a scheduled scan releases it to the restaurant a prep-time
 * before the interval. `released_at` is what "the kitchen has been told
 * about this" means; a normal (non-theatre, or deliver-now) order is
 * released the instant it is placed, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theatre_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theatre_id')->constrained('theatres')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('theatre_screens')->cascadeOnDelete();
            $table->string('movie_title');
            $table->string('language')->nullable();
            // dateTime, not timestamp: a second NOT NULL timestamp column
            // with no explicit default (ends_at, after starts_at) is
            // rejected outright by MySQL strict mode ("Invalid default
            // value") -- confirmed live against swado.online's production
            // MySQL, though it never surfaced against the local SQLite dev
            // DB since SQLite doesn't enforce this. dateTime has no such
            // default-value rule.
            $table->dateTime('starts_at');
            // Nullable: plenty of screenings run without an interval, and
            // those simply do not offer interval delivery.
            $table->timestamp('interval_at')->nullable();
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // The hot query is "what is playing on this screen right now",
            // run on every seat-page load.
            $table->index(['screen_id', 'starts_at']);
            $table->index(['theatre_id', 'starts_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'show_id')) {
                $table->foreignId('show_id')->nullable()->after('screen_id')->constrained('theatre_shows')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_timing')) {
                // 'asap' | 'interval'. Null for every non-theatre order.
                $table->string('delivery_timing')->nullable()->after('seat_label');
            }
            if (! Schema::hasColumn('orders', 'scheduled_for')) {
                // When the food must REACH the seat (the interval), not
                // when to start cooking -- the release scan subtracts prep
                // time from this.
                $table->timestamp('scheduled_for')->nullable()->after('delivery_timing');
            }
            if (! Schema::hasColumn('orders', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('scheduled_for');
            }
        });

        // Backfill: every order that already exists was released to its
        // restaurant at the moment it was placed, so saying so keeps
        // "unreleased" meaning exactly "held for an interval" and nothing
        // else. Without this, every historical order would look held.
        \Illuminate\Support\Facades\DB::table('orders')
            ->whereNull('released_at')
            ->update(['released_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);

        Schema::table('order_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('order_groups', 'show_id')) {
                $table->foreignId('show_id')->nullable()->after('screen_id')->constrained('theatre_shows')->nullOnDelete();
            }
            if (! Schema::hasColumn('order_groups', 'delivery_timing')) {
                $table->string('delivery_timing')->nullable()->after('seat_label');
            }
            if (! Schema::hasColumn('order_groups', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('delivery_timing');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_groups')) {
            Schema::table('order_groups', function (Blueprint $table) {
                if (Schema::hasColumn('order_groups', 'show_id')) {
                    $table->dropConstrainedForeignId('show_id');
                }
                foreach (['delivery_timing', 'scheduled_for'] as $column) {
                    if (Schema::hasColumn('order_groups', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'show_id')) {
                    $table->dropConstrainedForeignId('show_id');
                }
                foreach (['delivery_timing', 'scheduled_for', 'released_at'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('theatre_shows');
    }
};
