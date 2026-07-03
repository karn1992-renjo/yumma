<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\RestaurantStaff;
use App\Models\AppSetting;
use App\Support\PhoneNumber;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

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
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'current_restaurant_id',
        'branch_id',
        'address',
        'delivery_area_id',
        'latitude',
        'longitude',
        'fcm_token',
        'customer_fcm_token',
        'restaurant_fcm_token',
        'driver_fcm_token',
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
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'social_accounts' => 'array',
            'mollie_token_expires_at' => 'datetime',
            'payout_provider_meta' => 'array',
        ];
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

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function gigs()
    {
        return $this->hasMany(DriverGig::class, 'driver_id');
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
