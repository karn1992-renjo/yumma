<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $roleRedirect = $this->roleRedirectPath($request->user());

        if ($roleRedirect) {
            return redirect()->to($roleRedirect);
        }

        if ($request->filled('redirect') && $this->isSafeInternalRedirect($request)) {
            return redirect()->to($request->input('redirect'));
        }

        return redirect()->intended($this->redirectPath($request->user()));
    }

    protected function roleRedirectPath($user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return route('restaurant.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return route('admin.dashboard');
        }

        if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
            return route('branch.dashboard');
        }

        return null;
    }

    protected function redirectPath($user)
    {
        if (! $user) {
            return Fortify::redirects('login');
        }

        if ($roleRedirect = $this->roleRedirectPath($user)) {
            return $roleRedirect;
        }

        return Fortify::redirects('login');
    }

    protected function isSafeInternalRedirect(Request $request): bool
    {
        $redirect = trim((string) $request->input('redirect'));

        return str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//');
    }
}

