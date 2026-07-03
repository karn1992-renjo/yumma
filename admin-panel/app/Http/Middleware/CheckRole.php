<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return redirect()->route('login');
        }
        
        if (!$user->hasAnyRole($roles)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to access this resource.',
                ], 403);
            }

            return redirect($this->getDashboardRedirect($user));
        }
        
        return $next($request);
    }

    protected function getDashboardRedirect($user)
    {
        if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return route('restaurant.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return route('admin.dashboard');
        }

        if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
            return route('branch.dashboard');
        }

        return route('login');
    }
}
