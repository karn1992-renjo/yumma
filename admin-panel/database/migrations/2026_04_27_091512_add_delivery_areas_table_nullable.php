<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // For SQLite, we need to use raw SQL to modify column nullability
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we'll skip for now
            // The model will handle null values
            DB::statement('PRAGMA foreign_keys=off');
            
            // Get existing table structure
            $tableInfo = DB::select("PRAGMA table_info(delivery_areas)");
            
            // Check if columns need to be nullable
            $latitudeNullable = false;
            $longitudeNullable = false;
            $radiusNullable = false;
            
            foreach ($tableInfo as $column) {
                if ($column->name === 'latitude' && $column->notnull == 1) {
                    $latitudeNullable = true;
                }
                if ($column->name === 'longitude' && $column->notnull == 1) {
                    $longitudeNullable = true;
                }
                if ($column->name === 'radius_km' && $column->notnull == 1) {
                    $radiusNullable = true;
                }
            }
            
            if ($latitudeNullable || $longitudeNullable || $radiusNullable) {
                // Create temporary table with nullable columns
                DB::statement("CREATE TABLE delivery_areas_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT NULL,
                    latitude REAL NULL,
                    longitude REAL NULL,
                    radius_km REAL NULL,
                    max_daily_bookings INTEGER NOT NULL DEFAULT 0,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )");
                
                // Copy data
                DB::statement("INSERT INTO delivery_areas_new (id, name, description, latitude, longitude, radius_km, max_daily_bookings, is_active, created_at, updated_at)
                    SELECT id, name, description, latitude, longitude, radius_km, max_daily_bookings, is_active, created_at, updated_at FROM delivery_areas");
                
                // Replace tables
                DB::statement("DROP TABLE delivery_areas");
                DB::statement("ALTER TABLE delivery_areas_new RENAME TO delivery_areas");
            }
            
            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // For MySQL/PostgreSQL
            Schema::table('delivery_areas', function ($table) {
                $table->float('latitude')->nullable()->change();
                $table->float('longitude')->nullable()->change();
                $table->float('radius_km')->nullable()->change();
            });
        }
    }
    
    public function down()
    {
        // Cannot revert nullability easily in SQLite
    }
};