<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->seedDistanceSettings();

        if (Schema::hasTable('restaurants')
            && Schema::hasColumn('restaurants', 'latitude')
            && Schema::hasColumn('restaurants', 'longitude')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'restaurants_lat_lng_lookup_index');
                $table->index(['is_verified', 'is_open', 'latitude', 'longitude'], 'restaurants_discovery_geo_index');
            });
        }

        if (Schema::hasTable('users')
            && Schema::hasColumn('users', 'is_active')
            && Schema::hasColumn('users', 'latitude')
            && Schema::hasColumn('users', 'longitude')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['is_active', 'latitude', 'longitude'], 'users_driver_geo_lookup_index');
            });
        }

        if (Schema::hasTable('addresses')
            && Schema::hasColumn('addresses', 'user_id')
            && Schema::hasColumn('addresses', 'latitude')
            && Schema::hasColumn('addresses', 'longitude')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->index(['user_id', 'latitude', 'longitude'], 'addresses_user_geo_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropIndex('addresses_user_geo_lookup_index');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_driver_geo_lookup_index');
            });
        }

        if (Schema::hasTable('restaurants')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropIndex('restaurants_discovery_geo_index');
                $table->dropIndex('restaurants_lat_lng_lookup_index');
            });
        }

        DB::table('app_settings')
            ->whereIn('key', [
                'google_maps_distance_matrix_enabled',
                'google_maps_distance_matrix_cache_minutes',
                'haversine_eta_cache_minutes',
                'estimated_delivery_speed_kmph',
                'estimated_delivery_traffic_multiplier',
                'estimated_delivery_min_minutes',
            ])
            ->delete();
    }

    private function seedDistanceSettings(): void
    {
        $settings = [
            [
                'key' => 'google_maps_distance_matrix_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable billable Google Distance Matrix calls only for explicit confirmed-order route warmups.',
            ],
            [
                'key' => 'google_maps_distance_matrix_cache_minutes',
                'value' => '360',
                'type' => 'number',
                'description' => 'How long exact Google road-distance ETA results remain cached.',
            ],
            [
                'key' => 'haversine_eta_cache_minutes',
                'value' => '15',
                'type' => 'number',
                'description' => 'How long local Haversine ETA estimates remain cached.',
            ],
            [
                'key' => 'estimated_delivery_speed_kmph',
                'value' => '25',
                'type' => 'number',
                'description' => 'Average delivery speed used for local Haversine ETA estimates.',
            ],
            [
                'key' => 'estimated_delivery_traffic_multiplier',
                'value' => '1.2',
                'type' => 'number',
                'description' => 'Multiplier applied to local Haversine ETA estimates for traffic and route bends.',
            ],
            [
                'key' => 'estimated_delivery_min_minutes',
                'value' => '5',
                'type' => 'number',
                'description' => 'Minimum travel minutes returned by local ETA estimates.',
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('app_settings')->where('key', $setting['key'])->exists();

            if ($exists) {
                DB::table('app_settings')
                    ->where('key', $setting['key'])
                    ->update([
                        'type' => $setting['type'],
                        'description' => $setting['description'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('app_settings')->insert([
                ...$setting,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
