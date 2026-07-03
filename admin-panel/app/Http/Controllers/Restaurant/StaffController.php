<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\RestaurantStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StaffController extends Controller
{
    use ResolvesRestaurantContext;

    public function index()
    {
        $restaurant = $this->currentRestaurant();

        if (! $restaurant) {
            return redirect()->route('restaurant.dashboard')
                ->with('error', 'No restaurant selected for staff management.');
        }

        $staffMembers = RestaurantStaff::with('user.roles')
            ->where('restaurant_id', $restaurant->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('restaurant.staff.index', [
            'restaurant' => $restaurant,
            'staffMembers' => $staffMembers,
            'permissionOptions' => [
                'orders' => 'Orders',
                'menu' => 'Menu',
                'reports' => 'Reports',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return back()->with('error', 'No restaurant selected.');
        }

        $validated = $this->validateStaff($request);
        $validated['restaurant_id'] = $restaurant->id;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['permissions'] = array_values($validated['permissions'] ?? []);
        $staffRole = $this->ensureStaffAccessControlSeeded($validated['permissions']);

        DB::beginTransaction();

        try {
            $temporaryPassword = $validated['password'] ?? $this->generateTemporaryPassword();
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($temporaryPassword),
                'is_active' => $validated['is_active'],
                'current_restaurant_id' => $restaurant->id,
            ]);
            $user->syncRoles([$staffRole]);
            $user->syncPermissions($this->mapPermissionsToRbac($validated['permissions']));

            $validated['user_id'] = $user->id;
            RestaurantStaff::create($validated);

            DB::commit();

            return redirect()->route('restaurant.staff.index')
                ->with('success', 'Staff member created successfully.')
                ->with('staff_credentials', [
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'temporary_password' => $temporaryPassword,
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create staff account: ' . $e->getMessage());
        }
    }

    public function update(Request $request, RestaurantStaff $staff)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless($restaurant && $staff->restaurant_id === $restaurant->id, 404);

        $validated = $this->validateStaff($request, $staff);
        $validated['is_active'] = $request->boolean('is_active', $staff->is_active);
        $validated['permissions'] = array_values($validated['permissions'] ?? []);
        $staffRole = $this->ensureStaffAccessControlSeeded($validated['permissions']);

        DB::beginTransaction();

        try {
            $staff->update($validated);

            if ($staff->user) {
                $staff->user->update([
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'is_active' => $staff->is_active,
                    'current_restaurant_id' => $restaurant->id,
                ]);
                if (!empty($validated['password'])) {
                    $staff->user->update([
                        'password' => Hash::make($validated['password']),
                    ]);
                }
                $staff->user->syncRoles([$staffRole]);
                $staff->user->syncPermissions($this->mapPermissionsToRbac($validated['permissions']));
            }

            DB::commit();

            return redirect()->route('restaurant.staff.index')->with('success', 'Staff member updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update staff member: ' . $e->getMessage());
        }
    }

    public function toggle(RestaurantStaff $staff)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless($restaurant && $staff->restaurant_id === $restaurant->id, 404);

        $staff->is_active = ! $staff->is_active;
        $staff->save();
        $staff->user?->update(['is_active' => $staff->is_active]);

        return back()->with('success', 'Staff status updated successfully.');
    }

    public function destroy(RestaurantStaff $staff)
    {
        $restaurant = $this->currentRestaurant();
        abort_unless($restaurant && $staff->restaurant_id === $restaurant->id, 404);

        DB::beginTransaction();

        try {
            if ($staff->user) {
                $staff->user->tokens()->delete();
                $staff->user->delete();
            }

            $staff->delete();
            DB::commit();

            return back()->with('success', 'Staff member deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete staff member: ' . $e->getMessage());
        }
    }

    private function validateStaff(Request $request, ?RestaurantStaff $staff = null): array
    {
        $userId = $staff?->user_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,' . ($userId ?? 'NULL')],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . ($userId ?? 'NULL')],
            'role' => ['required', 'string', 'max:100'],
            'shift' => ['nullable', 'string', 'max:100'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:orders,menu,reports'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function mapPermissionsToRbac(array $permissions): array
    {
        $mapped = ['view_dashboard'];

        if (in_array('orders', $permissions, true)) {
            array_push($mapped, 'view_orders', 'manage_orders', 'update_order_status', 'view_order_details');
        }

        if (in_array('menu', $permissions, true)) {
            array_push($mapped, 'view_menu_items', 'manage_menu', 'manage_categories', 'create_menu_items', 'edit_menu_items', 'delete_menu_items');
        }

        if (in_array('reports', $permissions, true)) {
            array_push($mapped, 'view_reports');
        }

        return array_values(array_unique($mapped));
    }

    private function ensureStaffAccessControlSeeded(array $permissions = []): Role
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('restaurant_staff', 'web');
        $requiredPermissions = $this->mapPermissionsToRbac($permissions);

        foreach ($requiredPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        if (!empty($requiredPermissions)) {
            $role->givePermissionTo($requiredPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $role;
    }

    private function generateTemporaryPassword(): string
    {
        return Str::upper(Str::random(4)) . random_int(1000, 9999);
    }
}
