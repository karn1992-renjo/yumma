<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'view_dashboard',
            'view_restaurants',
            'create_restaurants',
            'manage_restaurants',
            'view_drivers',
            'create_drivers',
            'manage_drivers',
        ];

        $permissions = collect($permissionNames)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::firstOrCreate(['name' => 'marketing_manager', 'guard_name' => 'web'])
            ->syncPermissions($permissions->whereIn('name', [
                'view_dashboard',
                'view_restaurants',
                'create_restaurants',
                'manage_restaurants',
            ]));

        Role::firstOrCreate(['name' => 'fleet_manager', 'guard_name' => 'web'])
            ->syncPermissions($permissions->whereIn('name', [
                'view_dashboard',
                'view_drivers',
                'create_drivers',
                'manage_drivers',
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Role::whereIn('name', ['marketing_manager', 'fleet_manager'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
