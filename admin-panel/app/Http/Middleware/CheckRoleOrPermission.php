<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Like CheckRole, but also passes if the user holds any of the given
 * Spatie permission names -- used to let a custom role (support, or any
 * role created via the admin.roles.* screen) into a specific admin section
 * once it's been granted the matching permission, without touching what
 * super_admin/admin can already reach (they keep passing on role alone,
 * regardless of whether every exact permission happens to be assigned to
 * them too).
 */
class CheckRoleOrPermission
{
    public function handle(Request $request, Closure $next, string $rolesOrPermissions)
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $tokens = explode('|', $rolesOrPermissions);

        if ($user->hasAnyRole($tokens) || $user->hasAnyPermission($tokens)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to access this resource.'], 403);
        }

        return redirect()->route('dashboard')->with('error', "You don't have permission to access that page.");
    }
}
