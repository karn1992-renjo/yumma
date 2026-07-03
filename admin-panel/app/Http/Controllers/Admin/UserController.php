<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');
        
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        
        if ($request->role) {
            $query->role($request->role);
        }
        
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }
        
        $users = $query->latest()->paginate(20);
        $roles = Role::all();
        
        return view('admin.users.index', compact('users', 'roles'));
    }
    
    public function create()
    {
        $roles = Role::where('name', '!=', 'super_admin')->get();
        return view('admin.users.create', compact('roles'));
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|exists:roles,name',
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);
        
        $user->assignRole($request->role);
        
        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully!');
    }
    
    public function edit(User $user)
    {
        $roles = Role::where('name', '!=', 'super_admin')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }
    
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20|unique:users,phone,' . $user->id,
            'role' => 'required|exists:roles,name',
        ]);
        
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);
        
        if ($request->password) {
            $request->validate(['password' => 'min:6|confirmed']);
            $user->update(['password' => Hash::make($request->password)]);
        }
        
        $user->syncRoles([$request->role]);
        
        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully!');
    }
    
    public function destroy(Request $request, User $user)
    {
        if ($user->hasRole('super_admin')) {
            return redirect()->back()->with('error', 'Cannot delete super admin!');
        }

        if ($request->user()->is($user)) {
            return redirect()->back()->with('error', 'You cannot delete your own account while logged in.');
        }

        if (Order::where('customer_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete this user because customer orders are linked to the account.');
        }

        $ownedRestaurantIds = Restaurant::where('owner_id', $user->id)->pluck('id');

        if ($ownedRestaurantIds->isNotEmpty() && Order::whereIn('restaurant_id', $ownedRestaurantIds)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete this user because their restaurant has order history.');
        }
        
        try {
            DB::transaction(function () use ($user) {
                Order::where('driver_id', $user->id)->update(['driver_id' => null]);

                if (Schema::hasTable('payouts') && Schema::hasColumn('payouts', 'driver_id')) {
                    DB::table('payouts')->where('driver_id', $user->id)->update(['driver_id' => null]);
                }

                if (Schema::hasTable('driver_gigs') && Schema::hasColumn('driver_gigs', 'driver_id')) {
                    DB::table('driver_gigs')->where('driver_id', $user->id)->update(['driver_id' => null]);
                }

                if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'owner_user_id')) {
                    DB::table('branches')->where('owner_user_id', $user->id)->update(['owner_user_id' => null]);
                }

                if (Schema::hasTable('restaurants') && Schema::hasColumn('restaurants', 'owner_id')) {
                    Restaurant::where('owner_id', $user->id)->delete();
                }

                $user->syncRoles([]);
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException $exception) {
            return redirect()->back()->with('error', 'This user is still linked to other records and could not be deleted.');
        }
        
        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted from database successfully!');
    }
    
    public function toggleStatus(User $user)
    {
        if ($user->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => 'Cannot deactivate super admin!']);
        }
        
        $user->update(['is_active' => !$user->is_active]);
        
        return response()->json([
            'success' => true,
            'is_active' => $user->is_active
        ]);
    }

    public function downloadTemplate()
    {
        $filename = 'user-bulk-upload-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'Name',
            'Email',
            'Phone',
            'Password',
            'Role',
            'Active',
            'Verified',
        ];

        $sampleRow = [
            'John Doe',
            'john.doe@example.com',
            '+1234567890',
            'Password@123',
            'customer',
            'Yes',
            'Yes',
        ];

        return response()->stream(function () use ($columns, $sampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            fputcsv($handle, $sampleRow);
            fclose($handle);
        }, 200, $headers);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'upload_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $rows = $this->readUploadedRows($request->file('upload_file'));
        if (empty($rows)) {
            return back()->with('error', 'Uploaded file is empty or incorrectly formatted.');
        }

        $allowedRoles = Role::where('name', '!=', 'super_admin')->pluck('name')->toArray();
        $created = 0;
        $errors = [];

        foreach ($rows as $index => $record) {
            $rowNumber = $index + 2;
            $record = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $record);
            $record['role'] = Str::of($record['role'] ?? '')->trim()->lower()->replace(' ', '_')->toString();

            if (!empty($record['email']) && User::where('email', $record['email'])->exists()) {
                $errors[] = "Row {$rowNumber}: skipped because email already exists.";
                continue;
            }

            if (!empty($record['phone']) && User::where('phone', $record['phone'])->exists()) {
                $errors[] = "Row {$rowNumber}: skipped because phone number already exists.";
                continue;
            }

            $validator = Validator::make($record, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20|unique:users,phone',
                'password' => 'required|string|min:6',
                'role' => ['required', 'string', Rule::in($allowedRoles)],
                'active' => 'nullable|string',
                'verified' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                continue;
            }

            try {
                $user = User::create([
                    'name' => $record['name'],
                    'email' => $record['email'],
                    'phone' => $record['phone'],
                    'password' => Hash::make($record['password']),
                    'is_active' => $this->truthy($record['active'] ?? 'yes'),
                    'email_verified_at' => $this->truthy($record['verified'] ?? 'yes') ? now() : null,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $errors[] = "Row {$rowNumber}: skipped because email or phone number already exists.";
                continue;
            }

            $user->assignRole($record['role']);
            $created++;
        }

        $redirect = redirect()->route('admin.users.index')
            ->with('success', "Imported {$created} user" . ($created === 1 ? '' : 's') . ".");

        return empty($errors) ? $redirect : $redirect->with('upload_errors', $errors);
    }

    public function export(Request $request)
    {
        $query = User::with('roles');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        if ($request->role) {
            $query->role($request->role);
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $filename = 'users-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Active', 'Verified', 'Joined At']);

            $query->chunk(200, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone,
                        ucfirst(str_replace('_', ' ', $user->roles->first()->name ?? 'customer')),
                        $user->is_active ? 'Active' : 'Inactive',
                        $user->email_verified_at ? 'Yes' : 'No',
                        $user->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function readUploadedRows($file): array
    {
        $sheet = IOFactory::load($file->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);
        $header = array_shift($sheet);

        if (!$header) {
            return [];
        }

        $header = array_map(fn ($column) => Str::of((string) $column)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString(), $header);

        $rows = [];

        foreach ($sheet as $row) {
            if (empty(array_filter($row, fn ($value) => $value !== null && $value !== ''))) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($row, count($header), null));
        }

        return $rows;
    }

    private function truthy($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'y', 'on'], true);
    }
}
