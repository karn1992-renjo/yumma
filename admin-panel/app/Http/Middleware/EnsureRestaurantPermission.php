<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRestaurantPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return $this->deny($request, 401, 'Unauthenticated.');
        }

        if ($user->hasRole('restaurant_owner')) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->hasRestaurantPermission($permission)) {
                return $next($request);
            }
        }

        return $this->deny($request, 403, 'You do not have permission to access this restaurant feature.');
    }

    private function deny(Request $request, int $status, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->route('restaurant.dashboard')->with('error', $message);
    }
}
