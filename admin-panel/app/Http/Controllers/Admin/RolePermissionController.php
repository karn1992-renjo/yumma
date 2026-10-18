<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Roles the rest of the codebase refers to by name in route middleware,
     * sidebar switches, and seeders -- deleting or renaming one would break
     * those references silently, so this list is the only thing stopping
     * that from a UI click rather than a deliberate code change.
     */
    private const PROTECTED_ROLES = [
        'super_admin', 'admin', 'restaurant_owner', 'restaurant_staff',
        'delivery_partner', 'customer', 'branch_owner', 'branch_manager',
        'branch_staff', 'marketing_manager', 'fleet_manager', 'support',
        'accountant', 'hr_manager', 'employee',
        'theatre_owner', 'theatre_manager', 'theatre_runner',
    ];

    /**
     * super_admin is seeded with every permission (RolePermissionSeeder) and
     * several route groups gate on the role name alone -- editing its
     * permission set here could lock every super admin out at once, so it's
     * shown read-only instead of being just another row.
     */
    private const LOCKED_ROLES = ['super_admin'];

    public const PERMISSION_GROUPS = [
        'Dashboard' => ['view_dashboard'],
        'Restaurants' => ['manage_restaurants', 'create_restaurants', 'edit_restaurants', 'delete_restaurants', 'view_restaurants'],
        'Orders' => ['manage_orders', 'view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details'],
        'Users' => ['manage_users', 'create_users', 'edit_users', 'delete_users', 'view_users', 'block_users'],
        'Drivers' => ['manage_drivers', 'create_drivers', 'edit_drivers', 'delete_drivers', 'view_drivers', 'assign_drivers'],
        'Gigs' => ['manage_gigs', 'create_gigs', 'edit_gigs', 'delete_gigs', 'view_gigs', 'book_gigs'],
        'Payouts' => ['manage_payouts', 'create_payouts', 'process_payouts', 'view_payouts', 'approve_payouts'],
        'Banners' => ['manage_banners', 'create_banners', 'edit_banners', 'delete_banners', 'view_banners'],
        'Categories' => ['manage_categories', 'create_categories', 'edit_categories', 'delete_categories', 'view_categories'],
        // Split from the old single "Menu" group -- global categories, the
        // restaurant-facing listed menu, and the global menu-item catalog
        // are three different pages/controllers and admin wants to be able
        // to grant them independently.
        'Global Categories' => ['view_global_categories', 'create_global_categories', 'edit_global_categories', 'delete_global_categories'],
        'Listed Menu' => ['view_listed_menu', 'create_listed_menu', 'edit_listed_menu', 'delete_listed_menu'],
        'Global Menu Items' => ['manage_menu', 'create_menu_items', 'edit_menu_items', 'delete_menu_items', 'view_menu_items'],
        'Promotions' => ['manage_promos', 'create_promos', 'edit_promos', 'delete_promos', 'view_promos'],
        'Settings' => ['manage_settings', 'view_settings', 'update_settings', 'manage_app_branding', 'manage_payment_gateways'],
        'Reports' => ['view_reports', 'export_reports', 'view_sales_report', 'view_earning_report'],
        'Support Desk' => ['view_support_tickets', 'reply_support_tickets', 'resolve_support_tickets', 'manage_support_tickets'],
        'Branch Management' => [
            'manage_branches', 'manage_branch_restaurants', 'manage_branch_drivers', 'manage_branch_zones',
            'manage_branch_staff', 'view_branch_earnings', 'view_branch_wallet',
            'submit_branch_settlement_requests', 'view_branch_reports',
        ],
        'AI Assistant' => [
            'ai.view', 'ai.configure', 'ai.autonomy.change', 'ai.decisions.view', 'ai.actions.execute',
            'ai.approvals.manage', 'ai.finance.view', 'ai.accounting.view', 'ai.cost.view', 'ai.kill_switch',
        ],
        'Theatres' => ['view_theatres', 'create_theatres', 'edit_theatres', 'delete_theatres', 'manage_theatres'],
        'Point of Sale' => ['view_pos', 'manage_pos'],
        'COD Management' => ['view_cod', 'manage_cod'],
        'Fleet Dashboard' => ['view_fleet'],
        'Delivery Areas' => ['view_delivery_areas', 'create_delivery_areas', 'edit_delivery_areas', 'delete_delivery_areas', 'manage_delivery_areas'],
        'Restaurant Onboardings' => ['view_restaurant_onboardings', 'manage_restaurant_onboardings'],
        'Vendor Bank Details' => ['manage_vendor_bank_details'],
        'Restaurant Approvals' => ['view_restaurant_approvals', 'manage_restaurant_approvals'],
        'Refunds & Returns' => ['view_refunds', 'manage_refunds', 'view_returns', 'manage_returns'],
        'Ads Engine' => ['view_ads', 'manage_ads'],
        'Partner Applications' => ['view_partner_applications', 'manage_partner_applications'],
        'Commissions' => ['manage_commissions'],
        'Cuisines' => ['view_cuisines', 'create_cuisines', 'edit_cuisines', 'delete_cuisines', 'manage_cuisines'],
        'Campaigns' => ['manage_campaigns'],
        'Push Notifications' => ['manage_push_notifications'],
        'Notification Templates' => ['manage_notification_templates'],
        'Email Templates' => ['manage_email_templates'],
        'Notification Log' => ['view_notification_logs'],
        'Call History' => ['view_call_history'],
        'Call Masking' => ['manage_call_masking'],
        'Home Sections' => ['manage_home_sections'],
        'Offline Reasons' => ['manage_offline_reasons'],
        'Cancellation Limits' => ['manage_cancellation_limits'],
        'Celebration Types' => ['manage_celebration_types'],
        'Dining Bookings' => ['view_dining_bookings', 'manage_dining_bookings'],
        'Refund Policies' => ['manage_refund_policies'],
        'Blog Posts' => ['manage_blog_posts'],
        'Customer Reviews' => ['view_reviews', 'manage_reviews'],
        'Delivery Charges' => ['manage_delivery_charges'],
        'Taxes' => ['manage_taxes'],
        'Accounting' => ['view_accounting', 'manage_accounting'],
        // Cross-cutting: bulk import/export/template-download actions exist
        // across many resources (users, restaurants, cuisines, global
        // categories, master menu items, COD, orders). Admin wants these
        // gated as their OWN capability, independent of whether a role can
        // otherwise view/edit that resource -- see routes/web.php, each
        // import/export route stacks this permission on top of the
        // resource's own gate.
        'Import / Export' => ['import_data', 'export_data'],
    ];

    public function index()
    {
        $roles = Role::withCount('permissions')->orderBy('name')->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'protectedRoles' => self::PROTECTED_ROLES,
            'lockedRoles' => self::LOCKED_ROLES,
        ]);
    }

    public function create()
    {
        return view('admin.roles.create', [
            'groups' => self::PERMISSION_GROUPS,
            'checked' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $allowed = $this->allowedPermissionNames();
        $permissions = array_values(array_intersect($validated['permissions'] ?? [], $allowed));

        $this->ensurePermissionsExist($permissions);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" created with " . count($permissions) . ' permission(s).');
    }

    public function edit(Role $role)
    {
        if (in_array($role->name, self::LOCKED_ROLES, true)) {
            return redirect()->route('admin.roles.index')->with('error', 'Super Admin always has full access and is not editable here.');
        }

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => self::PERMISSION_GROUPS,
            'checked' => $role->permissions->pluck('name')->all(),
            'isProtected' => in_array($role->name, self::PROTECTED_ROLES, true),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if (in_array($role->name, self::LOCKED_ROLES, true)) {
            return redirect()->route('admin.roles.index')->with('error', 'Super Admin always has full access and is not editable here.');
        }

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $allowed = $this->allowedPermissionNames();
        $permissions = array_values(array_intersect($validated['permissions'] ?? [], $allowed));

        $this->ensurePermissionsExist($permissions);

        $role->syncPermissions($permissions);

        return redirect()->route('admin.roles.index')->with('success', "Permissions updated for \"{$role->name}\" (" . count($permissions) . ' total).');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()->route('admin.roles.index')->with('error', "\"{$role->name}\" is a system role used throughout the app and can't be deleted here.");
        }

        if ($role->users()->exists()) {
            return redirect()->route('admin.roles.index')->with('error', "\"{$role->name}\" still has users assigned to it. Reassign them first.");
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$roleName}\" deleted.");
    }

    private function allowedPermissionNames(): array
    {
        return array_merge(...array_values(self::PERMISSION_GROUPS));
    }

    private function ensurePermissionsExist(array $names): void
    {
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
