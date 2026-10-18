<?php

namespace App\Http\Middleware;

use App\Support\AdminMfa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdminMfa
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->requiresAdminMfa() && ! $request->routeIs('logout') && ! AdminMfa::verified($request, $user)) {
            // Remembered, alternate-login and stale sessions must supply a real password.
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Please log in again.'], 401)
                : redirect()->route('login');
        }

        if ($user && $user->requiresAdminMfa()) {
            abort_if($request->routeIs('two-factor.*'), 403, 'Manage admin MFA from Settings > Security.');
            if (! $user->adminRecoveryPhones() && ! $request->routeIs('admin.security.*', 'admin-mfa.security.*', 'logout')) {
                return redirect()->route('admin.security.index');
            }
        }

        return $next($request);
    }
}
