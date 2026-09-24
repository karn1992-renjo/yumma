<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stall belongs to exactly one theatre.
 *
 * The original module modelled Theatre<->Restaurant as a free many-to-many
 * so that a mall food-court vendor could serve several screens in one
 * complex. That case is deliberately dropped: a theatre runs its OWN
 * concessions, created by the theatre and approved by an admin, and there
 * is no longer any UI to attach a restaurant that belongs elsewhere.
 *
 * Enforced at the database rather than only in the panel, because the
 * failure it prevents is silent and expensive: one stall answering to two
 * theatres means an order placed at seat F12 in one building can be
 * accepted by a counter in another, and nothing downstream would notice.
 *
 * The composite unique (theatre_id, restaurant_id) becomes redundant once
 * restaurant_id alone is unique, so it is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Refuse to run rather than silently drop a mapping: if any stall is
        // attached to more than one theatre, a human has to decide which one
        // keeps it. Failing loudly here is far better than a unique-index
        // error mid-deploy with no explanation.
        $conflicts = DB::table('theatre_restaurants')
            ->select('restaurant_id', DB::raw('COUNT(*) as total'))
            ->groupBy('restaurant_id')
            ->having('total', '>', 1)
            ->pluck('total', 'restaurant_id');

        if ($conflicts->isNotEmpty()) {
            $detail = $conflicts
                ->map(fn ($total, $restaurantId) => "restaurant {$restaurantId} in {$total} theatres")
                ->implode('; ');

            throw new RuntimeException(
                'Cannot enforce one theatre per stall while these are shared: ' . $detail
                . '. Detach the duplicates in theatre_restaurants first, keeping one theatre each.'
            );
        }

        Schema::table('theatre_restaurants', function (Blueprint $table) {
            $table->unique('restaurant_id', 'theatre_restaurants_restaurant_id_unique');
        });

        if (Schema::hasIndex('theatre_restaurants', 'theatre_restaurants_theatre_id_restaurant_id_unique')) {
            Schema::table('theatre_restaurants', function (Blueprint $table) {
                $table->dropUnique('theatre_restaurants_theatre_id_restaurant_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('theatre_restaurants', function (Blueprint $table) {
            $table->dropUnique('theatre_restaurants_restaurant_id_unique');
        });

        if (! Schema::hasIndex('theatre_restaurants', 'theatre_restaurants_theatre_id_restaurant_id_unique')) {
            Schema::table('theatre_restaurants', function (Blueprint $table) {
                $table->unique(['theatre_id', 'restaurant_id']);
            });
        }
    }
};
