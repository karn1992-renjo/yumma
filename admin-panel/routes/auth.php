<?php

use App\Http\Controllers\ProfileController;
use App\Models\AppSetting;
use App\Rules\UniqueUserContactForRole;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    if (! File::exists(storage_path('app/installed.lock'))) {
        return redirect()->route('install.show');
    }

    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return redirect()->route('restaurant.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
            return redirect()->route('branch.dashboard');
        }

        return redirect()->route('home');
    }

    return view('auth.login');
})->name('login');

Route::get('register', function () {
    if (! File::exists(storage_path('app/installed.lock'))) {
        return redirect()->route('install.show');
    }

    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return redirect()->route('restaurant.dashboard');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
            return redirect()->route('branch.dashboard');
        }

        return redirect()->route('home');
    }

    return view('auth.register');
})->name('register');

Route::post('login/otp/send', function (Request $request) {
    $validated = $request->validate([
        'phone' => ['required', 'string', 'max:20'],
        'role' => ['nullable', 'in:customer,restaurant,restaurant_staff,driver'],
    ]);

    $normalizedPhone = PhoneNumber::normalize($validated['phone'], AppSetting::getValue('default_mobile_country_code', '+91'));

    $role = match ($validated['role'] ?? 'customer') {
        'restaurant' => 'restaurant_owner',
        'restaurant_staff' => 'restaurant_staff',
        'driver' => 'delivery_partner',
        default => 'customer',
    };

    $user = UniqueUserContactForRole::findByPhoneForRole($normalizedPhone, $role);
    $matchesRole = $user && ($role === 'restaurant_owner'
        ? $user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])
        : $user->hasRole($role));

    if (!$matchesRole) {
        return back()->withErrors(['phone' => 'No matching account found for this mobile number and role.']);
    }

    $provider = strtolower(trim((string) AppSetting::getValue('otp_service_provider', '')));
    if (! in_array($provider, ['twilio', 'firebase', 'msg91'], true)) {
        return back()->withErrors([
            'phone' => 'Login OTP provider is not configured in admin settings.',
        ]);
    }

    if ($provider === 'firebase') {
        return back()->withErrors([
            'phone' => 'Firebase OTP must be completed in the mobile app. Configure Twilio or MSG91 for admin web OTP login.',
        ]);
    }

    $otp = (string) random_int(100000, 999999);

    try {
        app(SmsService::class)->sendOtp($normalizedPhone, $otp);
    } catch (\Throwable $e) {
        Log::warning('Web OTP send failed: ' . $e->getMessage());

        return back()->withErrors([
            'phone' => 'OTP send failed. Please check the configured OTP provider.',
        ]);
    }

    if ($provider !== 'msg91') {
        Cache::put('web_login_otp:' . $normalizedPhone . ':' . $role, Hash::make($otp), now()->addMinutes(10));
    }

    return back()->with('otp_phone', $validated['phone'])->with('otp_normalized_phone', $normalizedPhone)->with('otp_role', $validated['role'] ?? 'customer')->with('success', 'OTP sent successfully.');
})->middleware('throttle:5,1')->name('login.otp.send');

Route::post('login/otp/verify', function (Request $request) {
    $validated = $request->validate([
        'phone' => ['required', 'string', 'max:20'],
        'otp' => ['required', 'string', 'min:4', 'max:8'],
        'role' => ['nullable', 'in:customer,restaurant,restaurant_staff,driver'],
    ]);

    $role = match ($validated['role'] ?? 'customer') {
        'restaurant' => 'restaurant_owner',
        'restaurant_staff' => 'restaurant_staff',
        'driver' => 'delivery_partner',
        default => 'customer',
    };

    $normalizedPhone = PhoneNumber::normalize($validated['phone'], AppSetting::getValue('default_mobile_country_code', '+91'));
    $provider = strtolower(trim((string) AppSetting::getValue('otp_service_provider', '')));
    $key = 'web_login_otp:' . $normalizedPhone . ':' . $role;
    $verified = false;
    if ($provider === 'msg91') {
        try {
            $verified = app(SmsService::class)->verifyOtp($normalizedPhone, (string) $validated['otp']);
        } catch (\Throwable $e) {
            Log::warning('Web MSG91 OTP verify failed: ' . $e->getMessage());
        }
    } else {
        $hashedOtp = Cache::get($key);
        $verified = $hashedOtp && Hash::check($validated['otp'], $hashedOtp);
    }

    if (! $verified) {
        return back()->withErrors(['otp' => 'Invalid or expired OTP.'])->with('otp_phone', $validated['phone'])->with('otp_role', $validated['role'] ?? 'customer');
    }

    $user = UniqueUserContactForRole::findByPhoneForRole($normalizedPhone, $role);
    abort_unless($user, 404);
    $matchesRole = $role === 'restaurant_owner'
        ? $user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])
        : $user->hasRole($role);

    if (!$matchesRole) {
        return back()->withErrors(['phone' => 'No matching account found for this mobile number and role.']);
    }

    if ($provider !== 'msg91') {
        Cache::forget($key);
    }
    Auth::login($user, true);

    if ($user->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
        return redirect()->route('restaurant.dashboard');
    }

    if ($user->hasAnyRole(['super_admin', 'admin'])) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff'])) {
        return redirect()->route('branch.dashboard');
    }

    return redirect()->intended(route('dashboard'));
})->middleware('throttle:5,1')->name('login.otp.verify');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/logout', function () {
        auth()->logout();
        return redirect('/');
    })->name('logout');
});
