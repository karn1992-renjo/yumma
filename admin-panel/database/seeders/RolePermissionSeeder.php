<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // ==========================================
        // CREATE ALL PERMISSIONS
        // ==========================================
        $permissions = [
            // Dashboard Permissions
            'view_dashboard',
            
            // Restaurant Management Permissions
            'manage_restaurants',
            'create_restaurants',
            'edit_restaurants',
            'delete_restaurants',
            'view_restaurants',
            
            // Order Management Permissions
            'manage_orders',
            'view_orders',
            'create_orders',
            'update_order_status',
            'cancel_orders',
            'view_order_details',
            
            // User Management Permissions
            'manage_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_users',
            'block_users',
            
            // Driver Management Permissions
            'manage_drivers',
            'create_drivers',
            'edit_drivers',
            'delete_drivers',
            'view_drivers',
            'assign_drivers',
            
            // Gig Management Permissions
            'manage_gigs',
            'create_gigs',
            'edit_gigs',
            'delete_gigs',
            'view_gigs',
            'book_gigs',
            
            // Payout Management Permissions
            'manage_payouts',
            'create_payouts',
            'process_payouts',
            'view_payouts',
            'approve_payouts',
            
            // Banner Management Permissions
            'manage_banners',
            'create_banners',
            'edit_banners',
            'delete_banners',
            'view_banners',
            
            // Category Management Permissions
            'manage_categories',
            'create_categories',
            'edit_categories',
            'delete_categories',
            'view_categories',
            
            // Menu Management Permissions
            'manage_menu',
            'create_menu_items',
            'edit_menu_items',
            'delete_menu_items',
            'view_menu_items',
            
            // Promo Code Permissions
            'manage_promos',
            'create_promos',
            'edit_promos',
            'delete_promos',
            'view_promos',
            
            // Settings Permissions
            'manage_settings',
            'view_settings',
            'update_settings',
            'manage_app_branding',
            'manage_payment_gateways',
            
            // Report Permissions
            'view_reports',
            'export_reports',
            'view_sales_report',
            'view_earning_report',
            
            // Support Permissions
            'manage_support_tickets',
            'view_support_tickets',
            'reply_support_tickets',
            'resolve_support_tickets',

            // Branch Management Permissions
            'manage_branches',
            'manage_branch_restaurants',
            'manage_branch_drivers',
            'manage_branch_zones',
            'manage_branch_staff',
            'view_branch_earnings',
            'view_branch_wallet',
            'submit_branch_settlement_requests',
            'view_branch_reports',
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }
        
        // ==========================================
        // CREATE ROLES AND ASSIGN PERMISSIONS
        // ==========================================
        
        // 1. SUPER ADMIN ROLE - Has all permissions
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web'
        ]);
        $superAdminRole->givePermissionTo(Permission::all());
        
        // 2. ADMIN ROLE - Has most permissions except critical ones
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);
        $adminRole->givePermissionTo([
            'view_dashboard',
            'manage_restaurants', 'view_restaurants', 'edit_restaurants',
            'manage_orders', 'view_orders', 'update_order_status', 'cancel_orders', 'view_order_details',
            'manage_users', 'view_users', 'edit_users', 'block_users',
            'manage_drivers', 'view_drivers', 'edit_drivers', 'assign_drivers',
            'manage_gigs', 'view_gigs', 'create_gigs', 'edit_gigs',
            'manage_payouts', 'view_payouts', 'process_payouts',
            'manage_banners', 'view_banners', 'create_banners', 'edit_banners',
            'manage_categories', 'view_categories', 'create_categories', 'edit_categories',
            'manage_menu', 'view_menu_items', 'create_menu_items', 'edit_menu_items',
            'manage_promos', 'view_promos', 'create_promos', 'edit_promos',
            'view_settings', 'view_reports', 'export_reports',
            'manage_support_tickets', 'view_support_tickets', 'reply_support_tickets',
        ]);
        
        // 3. RESTAURANT OWNER ROLE
        $restaurantOwnerRole = Role::firstOrCreate([
            'name' => 'restaurant_owner',
            'guard_name' => 'web'
        ]);
        $restaurantOwnerRole->givePermissionTo([
            'view_dashboard',
            'view_restaurants', 'edit_restaurants',
            'manage_orders', 'view_orders', 'update_order_status', 'view_order_details',
            'manage_categories', 'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'manage_menu', 'view_menu_items', 'create_menu_items', 'edit_menu_items', 'delete_menu_items',
            'manage_promos', 'view_promos', 'create_promos', 'edit_promos', 'delete_promos',
            'view_payouts',
            'view_reports',
        ]);

        $restaurantStaffRole = Role::firstOrCreate([
            'name' => 'restaurant_staff',
            'guard_name' => 'web'
        ]);
        $restaurantStaffRole->givePermissionTo([
            'view_dashboard',
            'view_orders', 'update_order_status', 'view_order_details',
            'view_menu_items',
            'view_promos',
            'view_reports',
            'view_support_tickets', 'reply_support_tickets',
        ]);
        
        // 4. DELIVERY PARTNER ROLE
        $deliveryPartnerRole = Role::firstOrCreate([
            'name' => 'delivery_partner',
            'guard_name' => 'web'
        ]);
        $deliveryPartnerRole->givePermissionTo([
            'view_dashboard',
            'view_orders', 'update_order_status', 'view_order_details',
            'view_gigs', 'book_gigs',
            'view_payouts',
        ]);
        
        // 5. CUSTOMER ROLE
        $customerRole = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web'
        ]);
        $customerRole->givePermissionTo([
            'create_orders',
            'view_orders',
            'cancel_orders',
            'view_order_details',
        ]);

        $branchOwnerRole = Role::firstOrCreate([
            'name' => 'branch_owner',
            'guard_name' => 'web'
        ]);
        $branchOwnerRole->givePermissionTo([
            'view_dashboard',
            'manage_branch_restaurants',
            'manage_branch_drivers',
            'manage_branch_zones',
            'manage_branch_staff',
            'view_orders',
            'view_branch_earnings',
            'view_branch_wallet',
            'manage_promos',
            'view_branch_reports',
            'submit_branch_settlement_requests',
            'manage_support_tickets',
        ]);

        $branchManagerRole = Role::firstOrCreate([
            'name' => 'branch_manager',
            'guard_name' => 'web'
        ]);
        $branchManagerRole->givePermissionTo([
            'view_dashboard',
            'manage_orders',
            'view_orders',
            'manage_branch_drivers',
            'manage_branch_restaurants',
            'manage_branch_zones',
            'view_branch_reports',
            'manage_support_tickets',
        ]);

        $branchStaffRole = Role::firstOrCreate([
            'name' => 'branch_staff',
            'guard_name' => 'web'
        ]);
        $branchStaffRole->givePermissionTo([
            'view_dashboard',
            'view_orders',
            'manage_support_tickets',
        ]);
        
        // ==========================================
        // CREATE DEFAULT USERS
        // ==========================================
        
        // 1. SUPER ADMIN USER
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'phone' => '9999999999',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);
        
        // 2. ADMIN USER
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'phone' => '8888888888',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);
        
        // 3. RESTAURANT OWNER USER
        $restaurantOwner = User::updateOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Restaurant Owner',
                'phone' => '7777777777',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $restaurantOwner->syncRoles(['restaurant_owner']);
        
        // 4. DELIVERY PARTNER USER
        $deliveryPartner = User::updateOrCreate(
            ['email' => 'driver@example.com'],
            [
                'name' => 'Delivery Driver',
                'phone' => '6666666666',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'vehicle_type' => 'Bike',
                'vehicle_number' => 'MH01AB1234',
                'license_number' => 'DL1234567890',
            ]
        );
        $deliveryPartner->syncRoles(['delivery_partner']);
        
        // 5. CUSTOMER USER
        $customer = User::updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Test Customer',
                'phone' => '5555555555',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $customer->syncRoles(['customer']);
        
        // ==========================================
        // OUTPUT SUCCESS MESSAGE
        // ==========================================
        
        $this->command->newLine();
        $this->command->line('==========================================');
        $this->command->line('✅ DATABASE SEEDED SUCCESSFULLY!');
        $this->command->line('==========================================');
        $this->command->newLine();
        
        $this->command->line('📋 PERMISSIONS CREATED: ' . count($permissions));
        $this->command->line('👥 ROLES CREATED: 6');
        $this->command->line('👤 USERS CREATED: 5');
        $this->command->newLine();
        
        $this->command->line('🔐 LOGIN CREDENTIALS:');
        $this->command->line('==========================================');
        $this->command->line('🟣 Super Admin:    superadmin@example.com / password');
        $this->command->line('🔵 Admin:          admin@example.com / password');
        $this->command->line('🟢 Restaurant Owner: owner@example.com / password');
        $this->command->line('🟡 Delivery Driver:  driver@example.com / password');
        $this->command->line('🟠 Customer:       customer@example.com / password');
        $this->command->line('==========================================');
        $this->command->newLine();
        
        $this->command->line('📊 ROLE PERMISSIONS SUMMARY:');
        $this->command->line('==========================================');
        $this->command->line('Super Admin:     ' . Permission::count() . ' permissions');
        $this->command->line('Admin:           ' . $adminRole->permissions->count() . ' permissions');
        $this->command->line('Restaurant Owner: ' . $restaurantOwnerRole->permissions->count() . ' permissions');
        $this->command->line('Restaurant Staff: ' . $restaurantStaffRole->permissions->count() . ' permissions');
        $this->command->line('Delivery Partner: ' . $deliveryPartnerRole->permissions->count() . ' permissions');
        $this->command->line('Customer:        ' . $customerRole->permissions->count() . ' permissions');
        $this->command->line('==========================================');
        $this->command->newLine();
    }
}
