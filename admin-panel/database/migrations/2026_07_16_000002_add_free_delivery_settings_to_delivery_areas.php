<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_areas')) {
            return;
        }

        Schema::table('delivery_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_areas', 'free_delivery_enabled')) {
                $table->boolean('free_delivery_enabled')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('delivery_areas', 'free_delivery_threshold')) {
                $table->decimal('free_delivery_threshold', 10, 2)->nullable()->after('free_delivery_enabled');
            }
        });

        if (! Schema::hasTable('delivery_charge_settings')
            || ! Schema::hasColumn('delivery_charge_settings', 'free_delivery_global')
            || ! Schema::hasColumn('delivery_charge_settings', 'free_delivery_threshold')) {
            return;
        }

        $setting = DB::table('delivery_charge_settings')->oldest('id')->first();
        if (! $setting || ! $setting->free_delivery_global || $setting->free_delivery_threshold === null) {
            return;
        }

        $areaIds = [];
        if (Schema::hasColumn('delivery_charge_settings', 'free_delivery_area_ids') && $setting->free_delivery_area_ids) {
            $decoded = json_decode((string) $setting->free_delivery_area_ids, true);
            $areaIds = is_array($decoded)
                ? array_values(array_filter(array_map('intval', $decoded)))
                : [];
        }

        $query = DB::table('delivery_areas');
        if (Schema::hasColumn('delivery_areas', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($areaIds) {
            $query->whereIn('id', $areaIds);
        }

        $query->update([
            'free_delivery_enabled' => true,
            'free_delivery_threshold' => $setting->free_delivery_threshold,
        ]);

        DB::table('delivery_charge_settings')->update([
            'free_delivery_global' => false,
            'free_delivery_threshold' => null,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_areas')) {
            return;
        }

        Schema::table('delivery_areas', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_areas', 'free_delivery_threshold')) {
                $table->dropColumn('free_delivery_threshold');
            }

            if (Schema::hasColumn('delivery_areas', 'free_delivery_enabled')) {
                $table->dropColumn('free_delivery_enabled');
            }
        });
    }
};
