<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\GatewayRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function phoneStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $normalizedPhone = $this->normalizePhone($request->input('phone'));
        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $user = $this->findUserByPhone($normalizedPhone);
        $pendingApplication = $role === 'delivery_partner'
            ? PartnerApplication::query()
                ->where('partner_type', 'driver')
                ->where('phone', $normalizedPhone)
                ->latest('id')
                ->first()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'phone' => $normalizedPhone,
                'exists' => (bool) $user,
                'matches_role' => $user ? $this->matchesAuthRole($user, $role) : false,
                'pending_application' => $pendingApplication ? [
                    'application_number' => $pendingApplication->application_number,
                    'status' => $pendingApplication->status,
                ] : null,
                'default_mobile_country_code' => $this->defaultMobileCountryCode(),
            ],
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:40',
            'verified_phone_token' => 'required|string|min:20|max:255',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $normalizedPhone = $this->normalizePhone($request->phone);
        if ($this->findUserByPhone($normalizedPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'An account already exists with this mobile number.'
            ], 422);
        }
        if (! $this->hasValidPhoneVerificationToken($normalizedPhone, $request->verified_phone_token, 'signup')) {
            return response()->json([
                'success' => false,
                'message' => 'Mobile OTP verification is required before signup.'
            ], 422);
        }

        $generatedPassword = Str::password(16);
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'password' => Hash::make((string) $request->input('password', $generatedPassword)),
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if ($this->findUserByPhone($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account already exists with this mobile number.'
                ], 422);
            }

            if (User::where('email', $request->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account already exists with this email address.'
                ], 422);
            }

            throw $e;
        }
        
        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $user->assignRole($role);
        
        $this->repairRestaurantStaffRole($user);
        $user->load('roles');
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $this->buildUserPayload($user, false, $role),
                'token' => $token
            ]
        ], 201);
    }
    
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }
        
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated'
            ], 403);
        }

        $this->repairRestaurantStaffRole($user);

        $role = null;
        if ($request->filled('role')) {
            $role = $this->normalizeRequestedRole($request->input('role'));
            if (! $this->matchesAuthRole($user, $role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not registered for the selected role.',
                ], 403);
            }
            $this->grantCustomerRoleIfRequested($user, $role);
        }
        
        $user->load('roles');
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->buildUserPayload($user, false, $role),
                'token' => $token
            ]
        ]);
    }

    public function loginWithSocial(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:google,apple',
            'firebase_id_token' => 'required|string|min:20',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
            'display_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $provider = strtolower((string) $request->input('provider'));
        if (! $this->socialLoginProviderEnabled($provider)) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($provider) . ' login is not enabled in admin settings.',
            ], 422);
        }

        $identity = $this->verifyFirebaseSocialIdentityToken(
            (string) $request->input('firebase_id_token'),
            $provider
        );

        if ($identity === null) {
            return response()->json([
                'success' => false,
                'message' => 'Social login token is invalid or Firebase social authentication is not configured.',
            ], 422);
        }

        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $email = strtolower(trim((string) $identity['email']));
        $providerId = (string) ($identity['provider_id'] ?: $identity['uid']);

        $user = User::query()
            ->where('social_provider', $provider)
            ->where('social_provider_id', $providerId)
            ->first();

        if (! $user && ! empty($identity['uid'])) {
            $user = User::where('firebase_uid', $identity['uid'])->first();
        }

        if (! $user && $email !== '') {
            $emailUser = User::where('email', $email)->first();
            if ($emailUser && ! $this->socialLoginAutoLinkEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account already exists with this email. Enable verified-email auto linking or use the existing login method.',
                    'code' => 'SOCIAL_LINK_DISABLED',
                ], 409);
            }

            $user = $emailUser;
        }

        if (! $user) {
            if (! $this->socialLoginAutoRegisterEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account is linked to this social login.',
                    'code' => 'ACCOUNT_NOT_FOUND',
                ], 404);
            }

            if ($role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Social sign up is available for customer accounts only.',
                    'code' => 'SOCIAL_SIGNUP_ROLE_NOT_ALLOWED',
                ], 403);
            }

            $user = User::create([
                'name' => $identity['name'] ?: $request->input('display_name') ?: Str::before($email, '@') ?: 'Customer',
                'email' => $email,
                'phone' => $identity['phone'] ?: null,
                'password' => Hash::make(Str::password(32)),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('customer');
        } else {
            $this->repairRestaurantStaffRole($user);

            if (! $this->matchesAuthRole($user, $role)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This social account is not registered for the selected role.',
                ], 403);
            }

            if (! $user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                ], 403);
            }

            $this->grantCustomerRoleIfRequested($user, $role);
        }

        $this->linkSocialIdentity($user, $provider, $identity);

        $user->load('roles');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->buildUserPayload($user->fresh()->load('roles'), false, $role),
                'token' => $token,
                'provider' => $provider,
            ],
        ]);
    }

    public function loginWithPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'firebase_id_token' => 'required|string|min:20',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $firebaseIdentity = $this->verifyFirebaseIdentityToken($request->input('firebase_id_token'));
        if ($firebaseIdentity === null) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase phone authentication is not configured or the ID token is invalid.',
            ], 422);
        }

        $requestPhone = $this->normalizePhone($request->input('phone'));
        $firebasePhone = $this->normalizePhone($firebaseIdentity['phone']);

        if ($requestPhone !== '' && $firebasePhone !== '' && $requestPhone !== $firebasePhone) {
            return response()->json([
                'success' => false,
                'message' => 'The verified Firebase phone number does not match the requested mobile number.',
            ], 422);
        }

        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $user = $this->findUserByPhone($firebasePhone);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this mobile number.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $this->repairRestaurantStaffRole($user);

        if (! $this->matchesAuthRole($user, $role)) {
            return response()->json([
                'success' => false,
                'message' => 'This mobile number is not registered for the selected role.'
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $this->grantCustomerRoleIfRequested($user, $role);
        $user->load('roles');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->buildUserPayload($user, false, $role),
                'token' => $token,
                'phone' => $firebasePhone,
                'firebase_uid' => $firebaseIdentity['uid'],
            ]
        ]);
    }

    public function verifyFirebasePhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'firebase_id_token' => 'required|string|min:20',
            'flow' => 'required|in:signup,forgot_password',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $firebaseIdentity = $this->verifyFirebaseIdentityToken($request->input('firebase_id_token'));
        if ($firebaseIdentity === null) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase phone authentication is not configured or the ID token is invalid.',
            ], 422);
        }

        $requestPhone = $this->normalizePhone($request->input('phone'));
        $firebasePhone = $this->normalizePhone($firebaseIdentity['phone']);

        if ($requestPhone !== '' && $firebasePhone !== '' && $requestPhone !== $firebasePhone) {
            return response()->json([
                'success' => false,
                'message' => 'The verified Firebase phone number does not match the requested mobile number.',
            ], 422);
        }

        $flow = (string) $request->input('flow');
        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $user = $this->findUserByPhone($firebasePhone);

        if ($flow === 'signup') {
            if ($user) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account already exists with this mobile number.',
                    'code' => 'ACCOUNT_EXISTS',
                ], 409);
            }

            if ($role === 'delivery_partner') {
                $existingApplication = PartnerApplication::query()
                    ->where('partner_type', 'driver')
                    ->where('phone', $firebasePhone)
                    ->latest('id')
                    ->first();

                if ($existingApplication) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A driver application already exists for this mobile number.',
                        'code' => 'APPLICATION_EXISTS',
                        'data' => [
                            'phone' => $firebasePhone,
                            'application_number' => $existingApplication->application_number,
                            'status' => $existingApplication->status,
                        ],
                    ], 409);
                }
            }

            $verifiedPhoneToken = $this->issuePhoneVerificationToken($firebasePhone, 'signup');

            return response()->json([
                'success' => true,
                'message' => 'Phone verified successfully',
                'data' => [
                    'phone' => $firebasePhone,
                    'verified_phone_token' => $verifiedPhoneToken,
                    'requires_registration' => true,
                    'firebase_uid' => $firebaseIdentity['uid'],
                ],
            ]);
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this mobile number.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $verifiedPhoneToken = $this->issuePhoneVerificationToken($firebasePhone, 'forgot_password');

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully',
            'data' => [
                'phone' => $firebasePhone,
                'verified_phone_token' => $verifiedPhoneToken,
                'requires_password_reset' => true,
                'firebase_uid' => $firebaseIdentity['uid'],
            ],
        ]);
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'flow' => 'nullable|in:login,signup,forgot_password',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $flow = (string) $request->input('flow', 'login');
        $normalizedPhone = $this->normalizePhone($request->phone);
        $user = $this->findUserByPhone($normalizedPhone);

        if ($flow === 'signup' && $user) {
            return response()->json([
                'success' => false,
                'message' => 'An account already exists with this mobile number.',
                'code' => 'ACCOUNT_EXISTS',
                'data' => ['phone' => $normalizedPhone],
            ], 409);
        }

        if ($flow === 'signup' && $role === 'delivery_partner') {
            $existingApplication = PartnerApplication::query()
                ->where('partner_type', 'driver')
                ->where('phone', $normalizedPhone)
                ->latest('id')
                ->first();

            if ($existingApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'A driver application already exists for this mobile number.',
                    'code' => 'APPLICATION_EXISTS',
                    'data' => [
                        'phone' => $normalizedPhone,
                        'application_number' => $existingApplication->application_number,
                        'status' => $existingApplication->status,
                    ],
                ], 409);
            }
        }

        if (in_array($flow, ['login', 'forgot_password'], true) && !$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this mobile number.',
                'code' => 'ACCOUNT_NOT_FOUND',
                'data' => ['phone' => $normalizedPhone],
            ], 404);
        }

        if ($user && in_array($flow, ['login', 'forgot_password'], true) && !$this->matchesAuthRole($user, $role)) {
            return response()->json([
                'success' => false,
                'message' => 'This mobile number is not registered for the selected role.'
            ], 403);
        }

        if ($user && !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $otp = (string) random_int(100000, 999999);
        $provider = $this->sendOtpMessage($normalizedPhone, $otp);
        $hashedOtp = Hash::make($otp);
        foreach ($this->otpCacheKeys($normalizedPhone, $role, $flow) as $cacheKey) {
            Cache::put($cacheKey, $hashedOtp, now()->addMinutes(10));
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
            'data' => [
                'phone' => $normalizedPhone,
                'flow' => $flow,
                'existing_user' => (bool) $user,
                'provider' => $provider,
                'expires_in' => 600,
            ],
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'otp' => 'required|string|min:4|max:8',
            'flow' => 'nullable|in:login,signup,forgot_password',
            'role' => 'nullable|in:customer,restaurant,driver,restaurant_owner,restaurant_staff,delivery_partner',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $this->normalizeRequestedRole($request->input('role', 'customer'));
        $flow = (string) $request->input('flow', 'login');
        $normalizedPhone = $this->normalizePhone($request->phone);
        $matchedOtp = $this->resolveOtpHash(
            $normalizedPhone,
            $role,
            $flow,
            (string) $request->otp
        );

        if ($matchedOtp === null) {
            Log::warning('OTP verification failed', [
                'phone' => $normalizedPhone,
                'role' => $role,
                'flow' => $flow,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.'
            ], 422);
        }

        foreach ($this->otpCacheKeys($normalizedPhone, $role, $flow) as $cacheKey) {
            Cache::forget($cacheKey);
        }

        if ($flow === 'signup') {
            if ($this->findUserByPhone($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account already exists with this mobile number.',
                    'code' => 'ACCOUNT_EXISTS',
                ], 409);
            }

            $verifiedPhoneToken = $this->issuePhoneVerificationToken($normalizedPhone, 'signup');

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'phone' => $normalizedPhone,
                    'verified_phone_token' => $verifiedPhoneToken,
                    'requires_registration' => true,
                ]
            ]);
        }

        if ($flow === 'forgot_password') {
            $user = $this->findUserByPhone($normalizedPhone);
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this mobile number.',
                    'code' => 'ACCOUNT_NOT_FOUND',
                ], 404);
            }

            $verifiedPhoneToken = $this->issuePhoneVerificationToken($normalizedPhone, 'forgot_password');

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'phone' => $normalizedPhone,
                    'verified_phone_token' => $verifiedPhoneToken,
                    'requires_password_reset' => true,
                ]
            ]);
        }

        $user = $this->findUserByPhone($normalizedPhone);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this mobile number.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }
        $this->repairRestaurantStaffRole($user);

        if (!$this->matchesAuthRole($user, $role)) {
            return response()->json([
                'success' => false,
                'message' => 'This mobile number is not registered for the selected role.'
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $this->grantCustomerRoleIfRequested($user, $role);
        $user->load('roles');
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'data' => [
                'user' => $this->buildUserPayload($user, false, $role),
                'token' => $token,
                'phone' => $normalizedPhone,
            ]
        ]);
    }

    public function sendPasswordResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('phone')) {
            return $this->sendOtp(new Request([
                'phone' => $request->input('phone'),
                'role' => $request->input('role', 'customer'),
                'flow' => 'forgot_password',
            ]));
        }

        $status = Password::sendResetLink([
            'email' => $request->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => __($status),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 422);
    }

    public function resetPasswordByPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:40',
            'verified_phone_token' => 'required|string|min:20|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $normalizedPhone = $this->normalizePhone($request->phone);
        if (! $this->hasValidPhoneVerificationToken($normalizedPhone, $request->verified_phone_token, 'forgot_password')) {
            return response()->json([
                'success' => false,
                'message' => 'Mobile OTP verification is required before password reset.'
            ], 422);
        }

        $user = $this->findUserByPhone($normalizedPhone);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this mobile number.'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    }
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
    
    public function user(Request $request)
    {
        $user = $request->user();
        $this->repairRestaurantStaffRole($user);
        $user->load('roles');

        return response()->json([
            'success' => true,
            'data' => $this->buildUserPayload($user, true)
        ]);
    }

    protected function buildUserPayload(User $user, bool $includeFullUser = false, ?string $preferredRole = null): array
    {
        $user->loadMissing('branch');

        $base = $includeFullUser
            ? $user->toArray()
            : $user->only(['id', 'name', 'email', 'phone', 'roles']);

        $roleNames = $user->roles->pluck('name');
        $primaryRole = $preferredRole && $roleNames->contains($preferredRole)
            ? $preferredRole
            : $roleNames->first();

        return array_merge($base, [
            'role' => $primaryRole,
            'profile_image' => $user->profile_photo_path ? $user->profile_photo_url : ($user->social_avatar_url ?: $user->profile_photo_url),
            'profile_photo_url' => $user->profile_photo_path ? $user->profile_photo_url : ($user->social_avatar_url ?: $user->profile_photo_url),
            'current_restaurant_id' => $user->current_restaurant_id,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'code' => $user->branch->code,
                'city' => $user->branch->city,
                'state' => $user->branch->state,
                'status' => $user->branch->status,
            ] : null,
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'settings' => $this->getAppSettings(),
        ]);
    }

    public function branding()
    {
        $appLogo = AppSetting::getValue('app_logo');
        $appIcon = AppSetting::getValue('app_icon');
        $appFavicon = AppSetting::getValue('app_favicon');
        $frontendBackgroundImage = AppSetting::getValue('frontend_background_image');
        $primaryColor = AppSetting::getValue('primary_color', '#FF5A1F');
        $secondaryColor = AppSetting::getValue('secondary_color', '#2B2A33');

        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => AppSetting::getValue('app_name', config('app.name', 'FoodFlow')),
                'app_logo' => $this->resolvePublicAssetUrl($appLogo),
                'app_icon' => $this->resolvePublicAssetUrl($appIcon),
                'app_favicon' => $this->resolvePublicAssetUrl($appFavicon),
                'frontend_background_image' => $this->resolvePublicAssetUrl($frontendBackgroundImage),
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'restaurant_primary_color' => AppSetting::getValue('restaurant_primary_color', $primaryColor),
                'restaurant_secondary_color' => AppSetting::getValue('restaurant_secondary_color', $secondaryColor),
                'driver_primary_color' => AppSetting::getValue('driver_primary_color', $primaryColor),
                'driver_secondary_color' => AppSetting::getValue('driver_secondary_color', $secondaryColor),
                'customer_play_store_url' => AppSetting::getValue('customer_play_store_url', ''),
                'customer_deeplink_scheme' => AppSetting::getValue('customer_deeplink_scheme', 'foodflow'),
                'customer_deeplink_base_url' => AppSetting::getValue('customer_deeplink_base_url', ''),
                'customer_order_deeplink_template' => AppSetting::getValue('customer_order_deeplink_template', 'foodflow://orders/{order_id}'),
                'customer_restaurant_deeplink_template' => AppSetting::getValue('customer_restaurant_deeplink_template', 'foodflow://restaurants/{restaurant_id}'),
                'customer_wallet_deeplink_template' => AppSetting::getValue('customer_wallet_deeplink_template', 'foodflow://wallet'),
                'support_email' => AppSetting::getValue('support_email', AppSetting::getValue('contact_email', 'support@example.com')),
                'support_phone' => AppSetting::getValue('support_phone', ''),
                'default_mobile_country_code' => $this->defaultMobileCountryCode(),
                'otp_service_provider' => $this->resolveOtpProvider(),
                'cod_enabled' => filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'social_login' => $this->socialLoginSettings(),
                'pusher_app_key' => AppSetting::getValue('pusher_app_key', config('broadcasting.connections.pusher.key', '')),
                'pusher_app_cluster' => AppSetting::getValue('pusher_app_cluster', config('broadcasting.connections.pusher.options.cluster', 'mt1')),
                'header_branding_type' => AppSetting::getValue('header_branding_type', 'text'),
                'onboarding_intro_title' => AppSetting::getValue('onboarding_intro_title', AppSetting::getValue('app_name', config('app.name', 'FoodFlow'))),
                'onboarding_intro_subtitle' => AppSetting::getValue('onboarding_intro_subtitle', 'Food, groceries and everyday cravings delivered fast.'),
                'onboarding_slides' => $this->buildOnboardingSlides(),
            ],
        ]);
    }

    protected function buildOnboardingSlides(): array
    {
        $fallbacks = [
            1 => [
                'title' => 'Choose your favourite dishes from the nearest restaurant or cafe',
                'description' => 'Fresh food from the kitchens you already love.',
                'image' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=80',
            ],
            2 => [
                'title' => 'Taste fresh delicious meals anytime anywhere',
                'description' => 'Fast ordering, clear tracking, and smooth checkout.',
                'image' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=900&q=80',
            ],
            3 => [
                'title' => 'We also deliver food, drinks, groceries from the nearest supermarket',
                'description' => 'One app for meals, essentials, and late-night cravings.',
                'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80',
            ],
        ];

        return collect([1, 2, 3])->map(function (int $index) use ($fallbacks) {
            $fallback = $fallbacks[$index];
            return [
                'title' => AppSetting::getValue("onboarding_slide_{$index}_title", $fallback['title']),
                'description' => AppSetting::getValue("onboarding_slide_{$index}_description", $fallback['description']),
                'image' => $this->resolvePublicAssetUrl(
                    AppSetting::getValue("onboarding_slide_{$index}_image", $fallback['image'])
                ),
            ];
        })->all();
    }

    protected function resolvePublicAssetUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return \App\Services\MediaStorage::url($value);
    }

    protected function getAppSettings(): array
    {
        $paymentGateway = AppSetting::getValue('payment_gateway_provider', 'razorpay');
        $payoutGateway = AppSetting::getValue('payout_gateway_provider', $paymentGateway);
        $paymentGatewayLogo = AppSetting::getValue('payment_gateway_logo');
        $enabledGateways = json_decode(AppSetting::getValue('enabled_payment_gateways', '[]'), true);
        $enabledGateways = is_array($enabledGateways) ? $enabledGateways : [];
        $enabledGateways = array_values(array_filter(
            array_map(fn ($gateway) => strtolower((string) $gateway), $enabledGateways),
            fn ($gateway) => array_key_exists($gateway, GatewayRegistry::customerSelectablePaymentProviders())
        ));

        if (empty($enabledGateways)) {
            $enabledGateways = array_keys(GatewayRegistry::customerSelectablePaymentProviders());
        }

        $gatewayOptions = collect(GatewayRegistry::customerSelectablePaymentProviders())
            ->map(function (string $label, string $key) use ($paymentGateway, $enabledGateways) {
                $logo = AppSetting::getValue('payment_gateway_logo_' . $key)
                    ?: AppSetting::getValue('payment_gateway_logo');

                return [
                    'key' => $key,
                    'label' => $label,
                    'logo' => \App\Services\MediaStorage::url($logo),
                    'enabled' => in_array($key, $enabledGateways, true),
                    'selected' => $key === $paymentGateway,
                ];
            })
            ->values()
            ->all();

        return [
            'currency_code' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
            'currency_symbol' => AppSetting::getValue('currency_symbol', '₹'),
            'currency_decimals' => AppSetting::currencyDecimals(),
            'payment_gateway_provider' => $paymentGateway,
            'payment_gateway_logo' => \App\Services\MediaStorage::url($paymentGatewayLogo),
            'enabled_payment_gateways' => $enabledGateways,
            'payment_gateways' => $gatewayOptions,
            'payout_gateway_provider' => $payoutGateway,
            'customer_deeplink_base_url' => AppSetting::getValue('customer_deeplink_base_url', ''),
            'country_code' => GatewayRegistry::resolveCountryCode(
                AppSetting::getValue('country_code'),
                $payoutGateway
            ),
            'payment_gateway_enabled' => filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'cod_enabled' => filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'otp_service_provider' => $this->resolveOtpProvider(),
            'default_mobile_country_code' => $this->defaultMobileCountryCode(),
            'social_login' => $this->socialLoginSettings(),
        ];
    }

    protected function normalizeRequestedRole(?string $role): string
    {
        return match ($role) {
            'restaurant' => 'restaurant_owner',
            'driver' => 'delivery_partner',
            'restaurant_owner', 'restaurant_staff', 'delivery_partner', 'customer' => $role,
            default => 'customer',
        };
    }

    protected function otpCacheKey(string $phone, string $role, string $flow = 'login'): string
    {
        return 'login_otp:' . preg_replace('/\s+/', '', $phone) . ':' . $role . ':' . $flow;
    }

    protected function otpCacheKeys(string $phone, string $role, string $flow = 'login'): array
    {
        $phone = preg_replace('/\s+/', '', $phone);
        $roles = array_values(array_unique(array_filter([
            $role,
            $this->legacyRoleAlias($role),
        ])));

        $keys = [];

        foreach ($roles as $candidateRole) {
            $keys[] = 'login_otp:' . $phone . ':' . $candidateRole . ':' . $flow;
            $keys[] = 'login_otp:' . $phone . ':' . $candidateRole;
        }

        if ($flow !== 'login') {
            $keys[] = 'login_otp:' . $phone . ':generic:' . $flow;
            $keys[] = 'login_otp:' . $phone . ':' . $flow;
        }

        return array_values(array_unique($keys));
    }

    protected function resolveOtpHash(string $phone, string $role, string $flow, string $otp): ?string
    {
        foreach ($this->otpCacheKeys($phone, $role, $flow) as $cacheKey) {
            $hashedOtp = Cache::get($cacheKey);
            if ($hashedOtp && Hash::check($otp, $hashedOtp)) {
                return $hashedOtp;
            }
        }

        return null;
    }

    protected function legacyRoleAlias(string $role): ?string
    {
        return match ($role) {
            'restaurant_owner' => 'restaurant',
            'delivery_partner' => 'driver',
            'restaurant' => 'restaurant_owner',
            'driver' => 'delivery_partner',
            default => null,
        };
    }

    protected function matchesAuthRole(User $user, string $role): bool
    {
        if ($role === 'customer') {
            return true;
        }

        if ($role === 'restaurant_owner') {
            return $user->hasAnyRole(['restaurant_owner', 'restaurant_staff']) || $user->restaurantStaff()->exists();
        }

        return $user->hasRole($role);
    }

    protected function grantCustomerRoleIfRequested(User $user, ?string $role): void
    {
        if ($role !== 'customer' || $user->hasRole('customer')) {
            return;
        }

        Role::findOrCreate('customer', 'web');
        $user->assignRole('customer');
    }

    protected function repairRestaurantStaffRole(User $user): void
    {
        if (!$user->restaurantStaff()->exists() || $user->hasRole('restaurant_staff')) {
            return;
        }

        Role::findOrCreate('restaurant_staff', 'web');
        $user->assignRole('restaurant_staff');
    }

    protected function sendOtpMessage(string $phone, string $otp): string
    {
        $provider = $this->resolveOtpProvider();
        $template = AppSetting::getValue('message_template_otp', 'Your OTP code is {{otp}}. It is valid for 10 minutes.');
        $message = str_replace(['{{otp}}', '{otp}'], $otp, $template);

        if ($provider === 'twilio') {
            $sid = AppSetting::getValue('twilio_account_sid');
            $token = AppSetting::getValue('twilio_auth_token');
            $from = AppSetting::getValue('twilio_phone_number');

            if (! $sid || ! $token || ! $from) {
                throw new \RuntimeException('OTP provider is set to Twilio, but Twilio settings are incomplete.');
            }

            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from,
                        'To' => $phone,
                        'Body' => $message,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Twilio OTP send failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \RuntimeException('Twilio OTP send failed.');
                }

                return $provider;
            } catch (\Throwable $e) {
                Log::warning('Twilio OTP send failed: ' . $e->getMessage());
                throw new \RuntimeException('Twilio OTP send failed.');
            }
        }

        if ($provider === 'firebase') {
            throw new \RuntimeException('Firebase OTP is not supported for this API login flow. Configure Twilio in admin settings.');
        }

        throw new \RuntimeException('OTP service provider is not configured in admin settings.');
    }

    protected function resolveOtpProvider(): string
    {
        $configured = strtolower(trim((string) AppSetting::getValue('otp_service_provider', '')));
        if (in_array($configured, ['firebase', 'twilio'], true)) {
            return $configured;
        }

        return '';
    }

    protected function normalizePhone(?string $phone): string
    {
        if (preg_match('/[A-Za-z]/', (string) $phone)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422));
        }

        $normalizedPhone = PhoneNumber::normalize($phone, $this->defaultMobileCountryCode());

        if ($normalizedPhone === '') {
            abort(response()->json([
                'success' => false,
                'message' => 'Enter a valid mobile number for the selected country code.',
            ], 422));
        }

        return $normalizedPhone;
    }

    protected function socialLoginSettings(): array
    {
        $enabled = filter_var(AppSetting::getValue('social_login_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $googleEnabled = $enabled && filter_var(AppSetting::getValue('social_login_google_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
        $appleEnabled = $enabled && filter_var(AppSetting::getValue('social_login_apple_enabled', '0'), FILTER_VALIDATE_BOOLEAN);

        return [
            'enabled' => $enabled,
            'auto_register' => filter_var(AppSetting::getValue('social_login_auto_register', '1'), FILTER_VALIDATE_BOOLEAN),
            'auto_link_verified_email' => filter_var(AppSetting::getValue('social_login_auto_link_verified_email', '1'), FILTER_VALIDATE_BOOLEAN),
            'providers' => [
                'google' => [
                    'enabled' => $googleEnabled,
                    'web_client_id' => AppSetting::getValue('social_login_google_web_client_id', ''),
                ],
                'apple' => [
                    'enabled' => $appleEnabled,
                    'services_id' => AppSetting::getValue('social_login_apple_services_id', ''),
                ],
            ],
        ];
    }

    protected function socialLoginProviderEnabled(string $provider): bool
    {
        $settings = $this->socialLoginSettings();

        return (bool) ($settings['providers'][$provider]['enabled'] ?? false);
    }

    protected function socialLoginAutoRegisterEnabled(): bool
    {
        return filter_var(AppSetting::getValue('social_login_auto_register', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function socialLoginAutoLinkEnabled(): bool
    {
        return filter_var(AppSetting::getValue('social_login_auto_link_verified_email', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function firebaseProviderId(string $provider): string
    {
        return match ($provider) {
            'google' => 'google.com',
            'apple' => 'apple.com',
            default => $provider,
        };
    }

    protected function verifyFirebaseIdentityToken(string $idToken): ?array
    {
        $firebaseEnabled = filter_var(config('services.firebase.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $apiKey = (string) config('services.firebase.api_key', '');
        $clientApiKey = (string) config('services.firebase_client.api_key', '');

        if (! $firebaseEnabled || ($apiKey === '' && $clientApiKey === '')) {
            Log::warning('Firebase phone login attempted without Firebase configuration.');
            return null;
        }

        $apiKeys = collect([$apiKey, $clientApiKey])
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values();

        foreach ($apiKeys as $candidateKey) {
            try {
                $response = Http::connectTimeout(3)->timeout(7)->post(
                    'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($candidateKey),
                    ['idToken' => $idToken]
                );

                if (! $response->successful()) {
                    Log::warning('Firebase identity token lookup failed.', [
                        'status' => $response->status(),
                        'firebase_error' => $response->json('error.message'),
                        'key_suffix' => substr($candidateKey, -6),
                    ]);
                    continue;
                }

                $data = $response->json();
                $user = $data['users'][0] ?? null;
                $phone = $user['phoneNumber'] ?? null;
                $uid = $user['localId'] ?? null;

                if (! is_array($user) || ! is_string($phone) || trim($phone) === '' || ! is_string($uid) || trim($uid) === '') {
                    Log::warning('Firebase identity token lookup returned incomplete user data.', [
                        'has_phone' => is_string($phone) && trim($phone) !== '',
                        'has_uid' => is_string($uid) && trim($uid) !== '',
                    ]);
                    continue;
                }

                return [
                    'phone' => $phone,
                    'uid' => $uid,
                ];
            } catch (\Throwable $e) {
                Log::error('Firebase identity token verification failed.', [
                    'error' => $e->getMessage(),
                    'key_suffix' => substr($candidateKey, -6),
                ]);
            }
        }

        return null;
    }

    protected function verifyFirebaseSocialIdentityToken(string $idToken, string $provider): ?array
    {
        $firebaseEnabled = filter_var(config('services.firebase.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $apiKey = (string) config('services.firebase.api_key', '');

        if (! $firebaseEnabled || $apiKey === '') {
            Log::warning('Firebase social login attempted without Firebase configuration.');
            return null;
        }

        try {
            $response = Http::timeout(15)->post(
                'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey),
                ['idToken' => $idToken]
            );

            if (! $response->successful()) {
                Log::warning('Firebase social identity token lookup failed.', [
                    'provider' => $provider,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $firebaseUser = $data['users'][0] ?? null;
            if (! is_array($firebaseUser)) {
                return null;
            }

            $firebaseProvider = $this->firebaseProviderId($provider);
            $providerInfo = collect($firebaseUser['providerUserInfo'] ?? [])
                ->first(fn ($info) => is_array($info) && ($info['providerId'] ?? '') === $firebaseProvider);

            if (! is_array($providerInfo)) {
                Log::warning('Firebase social token provider mismatch.', [
                    'expected_provider' => $firebaseProvider,
                    'providers' => collect($firebaseUser['providerUserInfo'] ?? [])->pluck('providerId')->values()->all(),
                ]);
                return null;
            }

            $email = strtolower(trim((string) ($providerInfo['email'] ?? $firebaseUser['email'] ?? '')));
            $uid = trim((string) ($firebaseUser['localId'] ?? ''));
            $providerId = trim((string) ($providerInfo['rawId'] ?? $providerInfo['federatedId'] ?? ''));
            $emailVerified = filter_var($firebaseUser['emailVerified'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($email === '' || $uid === '' || $providerId === '' || ! $emailVerified) {
                Log::warning('Firebase social token returned incomplete or unverified identity.', [
                    'provider' => $provider,
                    'has_email' => $email !== '',
                    'has_uid' => $uid !== '',
                    'has_provider_id' => $providerId !== '',
                    'email_verified' => $emailVerified,
                ]);
                return null;
            }

            return [
                'uid' => $uid,
                'provider_id' => $providerId,
                'email' => $email,
                'name' => trim((string) ($providerInfo['displayName'] ?? $firebaseUser['displayName'] ?? '')),
                'avatar' => trim((string) ($providerInfo['photoUrl'] ?? $firebaseUser['photoUrl'] ?? '')),
                'phone' => trim((string) ($firebaseUser['phoneNumber'] ?? '')),
            ];
        } catch (\Throwable $e) {
            Log::error('Firebase social identity token verification failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function linkSocialIdentity(User $user, string $provider, array $identity): void
    {
        $accounts = is_array($user->social_accounts) ? $user->social_accounts : [];
        $accounts[$provider] = [
            'provider_id' => $identity['provider_id'],
            'firebase_uid' => $identity['uid'],
            'email' => $identity['email'],
            'linked_at' => now()->toIso8601String(),
        ];

        $updates = [
            'firebase_uid' => $identity['uid'],
            'social_provider' => $provider,
            'social_provider_id' => $identity['provider_id'],
            'social_avatar_url' => $identity['avatar'] ?: $user->social_avatar_url,
            'social_accounts' => $accounts,
        ];

        if (! $user->email_verified_at) {
            $updates['email_verified_at'] = now();
        }

        $user->forceFill($updates)->save();
    }

    protected function findUserByPhone(?string $phone): ?User
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $candidates = $this->phoneLookupCandidates($normalizedPhone);

        foreach ($candidates as $candidate) {
            $user = User::where('phone', $candidate)->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    protected function phoneLookupCandidates(string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $defaultCountryDigits = ltrim($this->defaultMobileCountryCode(), '+');
        $candidates = [
            $phone,
            $digits,
            '+' . $digits,
            ltrim($digits, '0'),
        ];

        if ($defaultCountryDigits !== '') {
            if (str_starts_with($digits, $defaultCountryDigits)) {
                $localDigits = substr($digits, strlen($defaultCountryDigits));
                if ($localDigits !== false && $localDigits !== '') {
                    $candidates[] = $localDigits;
                    $candidates[] = '0' . $localDigits;
                    $candidates[] = '+' . $defaultCountryDigits . $localDigits;
                }
            } else {
                $candidates[] = '+' . $defaultCountryDigits . $digits;
                $candidates[] = $defaultCountryDigits . $digits;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function defaultMobileCountryCode(): string
    {
        return PhoneNumber::normalizeCountryCode(
            AppSetting::getValue('default_mobile_country_code', '+91')
        ) ?: '+91';
    }

    protected function issuePhoneVerificationToken(string $phone, string $flow): string
    {
        $token = Str::random(64);
        Cache::put(
            'verified_phone:' . $flow . ':' . sha1($token),
            $phone,
            now()->addMinutes(20)
        );

        return $token;
    }

    protected function hasValidPhoneVerificationToken(string $phone, string $token, string $flow): bool
    {
        $cacheKey = 'verified_phone:' . $flow . ':' . sha1($token);
        $cachedPhone = Cache::get($cacheKey);

        if ($cachedPhone !== $phone) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    public function registerFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'target_app' => 'nullable|in:customer,restaurant,driver',
            'role' => 'nullable|string',
        ]);

        $user = $request->user();
        $targetApp = $request->input('target_app')
            ?: $this->targetAppForRole($request->input('role'));

        $updates = ['fcm_token' => $request->fcm_token];
        if ($targetApp) {
            $updates[$targetApp . '_fcm_token'] = $request->fcm_token;
        }

        $user->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'FCM token registered successfully.'
        ]);
    }

    private function targetAppForRole(?string $role): ?string
    {
        $role = strtolower(str_replace('-', '_', (string) $role));

        if (str_contains($role, 'restaurant') || str_contains($role, 'owner') || str_contains($role, 'staff')) {
            return 'restaurant';
        }

        if (str_contains($role, 'driver') || str_contains($role, 'delivery')) {
            return 'driver';
        }

        if ($role === '' || str_contains($role, 'customer') || str_contains($role, 'user')) {
            return 'customer';
        }

        return null;
    }

    // Add to AuthController.php

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if ($request->filled('phone')) {
            $request->merge([
                'phone' => $this->normalizePhone($request->input('phone')),
            ]);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('profile_image')) {
            $user->updateProfilePhoto($request->file('profile_image'));
            $user->refresh();
        }

        $user->update($request->only(['name', 'phone']));
        $user->load('roles');
        
        return response()->json([
            'success' => true,
            'data' => $this->buildUserPayload($user->fresh()->load('roles'), true),
            'message' => 'Profile updated successfully'
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }
        
        $user->password = Hash::make($request->password);
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}
