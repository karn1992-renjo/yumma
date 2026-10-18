<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    public function requiresAdminMfa(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    public function adminRecoveryPhones(): array
    {
        $phones = [];
        foreach ([1, 2] as $slot) {
            if ($this->{"admin_mfa_phone_{$slot}"} && $this->{"admin_mfa_phone_{$slot}_verified_at"}) {
                $phones[$slot] = $this->{"admin_mfa_phone_{$slot}"};
            }
        }

        return $phones;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'reward_points_balance',
        'voice_remaining_seconds',
        'voice_last_recharged_order_id',
        'voice_last_recharged_at',
        'referral_code',
        'referred_by_user_id',
        'referral_registered_at',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'earning_mode',
        'monthly_salary',
        'salary_effective_from',
        'salary_payer',
        'pan',
        'tax_deductee_type',
        'current_restaurant_id',
        'branch_id',
        'address',
        'city',
        'landmark',
        'delivery_area_id',
        'latitude',
        'longitude',
        'date_of_birth',
        'gender',
        'vehicle_model',
        'fuel_type',
        'profile_photo_path',
        'vehicle_image',
        'license_document',
        'aadhar_card',
        'pan_card',
        'vehicle_rc',
        'insurance_document',
        'background_location_enabled',
        'notification_permission_enabled',
        'fcm_token',
        'customer_fcm_token',
        'restaurant_fcm_token',
        'driver_fcm_token',
        'notify_order_updates',
        'notify_offers_promotions',
        'reorder_nudged_at',
        'firebase_uid',
        'social_provider',
        'social_provider_id',
        'social_avatar_url',
        'social_accounts',
        'max_active_orders',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'razorpay_contact_id',
        'razorpay_fund_account_id',
        'stripe_account_id',
        'gateway_account_id',
        'mollie_organization_id',
        'mollie_access_token',
        'mollie_refresh_token',
        'mollie_token_expires_at',
        'mercadopago_collector_id',
        'cashfree_beneficiary_id',
        'routing_code',
        'payout_gateway',
        'payout_country',
        'payout_provider_meta',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'admin_mfa_phone_1',
        'admin_mfa_phone_2',
    ];

    public function fcmTokenForApp(?string $targetApp): ?string
    {
        $targetApp = strtolower((string) $targetApp);

        $token = match ($targetApp) {
            'customer' => $this->customer_fcm_token,
            'restaurant', 'restaurant_owner', 'restaurant_staff' => $this->restaurant_fcm_token,
            'driver', 'delivery_partner' => $this->driver_fcm_token,
            default => null,
        };

        return filled($token) ? $token : null;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'driver_code',
    ];

    /**
     * Human-readable public identifier for a delivery partner, e.g. DRV00042.
     * Derived from the row id so it is always stable without a separate column.
     */
    public function getDriverCodeAttribute(): string
    {
        return 'DRV' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_mfa_required' => 'boolean',
            'admin_mfa_version' => 'integer',
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'suspended_until' => 'datetime',
            'password' => 'hashed',
            'social_accounts' => 'array',
            'reward_points_balance' => 'integer',
            'voice_remaining_seconds' => 'integer',
            'voice_last_recharged_at' => 'datetime',
            'notify_order_updates' => 'boolean',
            'notify_offers_promotions' => 'boolean',
            'reorder_nudged_at' => 'datetime',
            'referral_registered_at' => 'datetime',
            'mollie_token_expires_at' => 'datetime',
            'payout_provider_meta' => 'array',
            'monthly_salary' => 'decimal:2',
            'salary_effective_from' => 'date',
            'date_of_birth' => 'date',
            'background_location_enabled' => 'boolean',
            'notification_permission_enabled' => 'boolean',
        ];
    }

    /**
     * True while an admin-issued suspension is in effect (used for drivers).
     * Deliberately separate from `is_active`, which other flows (e.g. a
     * branch's quick suspend/reactivate) already toggle informally — this
     * is the formal, admin-only suspension with a reason and optional
     * expiry, and only an admin action (or the auto-expiry scheduler) can
     * clear it.
     */
    public function isSuspended(): bool
    {
        if (! $this->suspended_at) {
            return false;
        }

        return $this->suspended_until === null || $this->suspended_until->isFuture();
    }

    public function suspend(?string $reason, ?\Carbon\Carbon $until, int $adminUserId): void
    {
        $this->forceFill([
            'suspended_at' => now(),
            'suspended_until' => $until,
            'suspension_reason' => $reason,
            'suspended_by' => $adminUserId,
        ])->save();
    }

    public function liftSuspension(): void
    {
        $this->forceFill([
            'suspended_at' => null,
            'suspended_until' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();
    }

    /**
     * Encrypt sensitive payout fields to protect banking information.
     *
     * @var array<int, string>
     */
    protected $encrypted = [
        'bank_name',
        'account_number',
        'account_holder_name',
        'ifsc_code',
        'upi_id',
        'stripe_account_id',
        'gateway_account_id',
        'mollie_organization_id',
        'mollie_access_token',
        'mollie_refresh_token',
        'routing_code',
        'razorpay_contact_id',
        'razorpay_fund_account_id',
        'cashfree_beneficiary_id',
        'mercadopago_collector_id',
    ];

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }

    public function currentRestaurant()
    {
        return $this->belongsTo(Restaurant::class, 'current_restaurant_id');
    }

    public function restaurantStaff()
    {
        return $this->hasOne(RestaurantStaff::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function branchMembership()
    {
        return $this->hasOne(BranchUser::class);
    }

    /** swaad-only: movie-theatre in-seat ordering module. */
    public function theatre()
    {
        return $this->belongsTo(Theatre::class);
    }

    public function theatreMembership()
    {
        return $this->hasOne(TheatreUser::class);
    }

    public function referrer()
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referrals()
    {
        return $this->hasMany(UserReferral::class, 'referrer_id');
    }

    public function referralAttribution()
    {
        return $this->hasOne(UserReferral::class, 'referred_user_id');
    }

    public function activeRestaurant(): ?Restaurant
    {
        if ($this->current_restaurant_id) {
            $currentRestaurant = $this->currentRestaurant()->first();

            if ($currentRestaurant) {
                return $currentRestaurant;
            }
        }

        $staffRecord = $this->restaurantStaff()->with('restaurant')->first();
        if ($staffRecord?->restaurant) {
            return $staffRecord->restaurant;
        }

        return $this->restaurants()->first();
    }

    public function isRestaurantOwner(): bool
    {
        return $this->hasRole('restaurant_owner');
    }

    public function isRestaurantStaff(): bool
    {
        return $this->hasRole('restaurant_staff') || $this->restaurantStaff()->exists();
    }

    public function hasRestaurantPermission(string $permission): bool
    {
        if ($this->isRestaurantOwner()) {
            return true;
        }

        if (! $this->isRestaurantStaff()) {
            return false;
        }

        return $this->can($permission);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    public function restaurantOnboardings()
    {
        return $this->hasMany(RestaurantOnboarding::class, 'driver_id');
    }

    public function restaurantOnboardingIncentives()
    {
        return $this->hasMany(RestaurantOnboardingIncentive::class, 'driver_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function gigs()
    {
        return $this->hasMany(DriverGig::class, 'driver_id');
    }

    public function gigBookings()
    {
        return $this->hasMany(DriverGigBooking::class, 'driver_id');
    }

    public function bookedGigs()
    {
        return $this->belongsToMany(DriverGig::class, 'driver_gig_bookings', 'driver_id', 'driver_gig_id')
            ->withPivot(['status', 'booked_at', 'completed_at', 'cancelled_at'])
            ->withTimestamps();
    }

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class, 'delivery_area_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function favoriteRestaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'user_favorites')->withTimestamps();
    }

    public function vendorBankAccounts()
    {
        return $this->hasMany(VendorBankAccount::class);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize(
            $value,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
    }
}
