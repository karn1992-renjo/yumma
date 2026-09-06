<?php

namespace App\Services;

use App\Models\PartnerApplication;
use App\Models\Restaurant;
use App\Models\RestaurantOnboarding;
use App\Models\RestaurantOnboardingEvent;
use App\Models\RestaurantOnboardingIncentive;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantOnboardingService
{
    public function __construct(
        private readonly RestaurantOnboardingSettings $settings,
        private readonly RestaurantApplicationService $applications,
        private readonly RestaurantOnboardingNotificationService $notifications
    ) {
    }

    public function settings(): array
    {
        return $this->settings->all();
    }

    public function assertDriverEligible(User $driver): void
    {
        if (! $this->settings->driverIsEligible($driver)) {
            throw ValidationException::withMessages([
                'driver' => 'Restaurant onboarding is not available for this driver.',
            ]);
        }
    }

    public function createDraft(User $driver): RestaurantOnboarding
    {
        $this->assertDriverEligible($driver);
        $this->assertLimit($driver);

        return DB::transaction(function () use ($driver) {
            $onboarding = RestaurantOnboarding::create([
                'application_number' => $this->nextApplicationNumber(),
                'driver_id' => $driver->id,
                'registration_source' => 'driver',
                'status' => RestaurantOnboarding::STATUS_DRAFT,
                'incentive_amount' => 0,
                'incentive_status' => RestaurantOnboardingIncentive::STATUS_PENDING,
                'onboarding_started_at' => now(),
            ]);
            $this->event($onboarding, 'restaurant_onboarding_created', $driver);

            return $onboarding;
        });
    }

    public function saveDraft(RestaurantOnboarding $onboarding, User $driver, array $payload): RestaurantOnboarding
    {
        $this->authorizeDriver($onboarding, $driver);
        if (! in_array($onboarding->status, [
            RestaurantOnboarding::STATUS_DRAFT,
            RestaurantOnboarding::STATUS_CORRECTION_REQUIRED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or correction-required applications can be edited.']);
        }

        $onboarding->update([
            'draft_payload' => $this->safeDraftPayload($payload),
        ]);
        $this->event($onboarding, 'driver_saved_draft', $driver);

        return $onboarding->fresh(['partnerApplication', 'restaurant', 'incentive', 'events']);
    }

    public function submit(RestaurantOnboarding $onboarding, User $driver, Request $request): RestaurantOnboarding
    {
        $this->authorizeDriver($onboarding, $driver);
        $this->assertDriverEligible($driver);

        if (! in_array($onboarding->status, [
            RestaurantOnboarding::STATUS_DRAFT,
            RestaurantOnboarding::STATUS_CORRECTION_REQUIRED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'This onboarding cannot be submitted right now.']);
        }

        $application = $onboarding->partnerApplication;
        $request->validate($this->applications->driverSubmissionRules($application));
        $this->assertOwnerOtp($onboarding);
        $this->assertGps($onboarding, $request);
        $this->assertDuplicateFree($onboarding, $request);
        $this->assertLimit($driver, $onboarding);

        return DB::transaction(function () use ($onboarding, $driver, $request, $application) {
            $extra = [
                'source' => 'driver',
                'restaurant_onboarding_id' => $onboarding->id,
                'onboarded_by_driver_id' => $driver->id,
            ];

            $partnerApplication = $application
                ? $this->applications->updateFromRequest($request, $application, $extra)
                : $this->applications->createFromRequest($request, $extra);

            $newStatus = $onboarding->status === RestaurantOnboarding::STATUS_CORRECTION_REQUIRED
                ? RestaurantOnboarding::STATUS_RESUBMITTED
                : RestaurantOnboarding::STATUS_SUBMITTED;

            $onboarding->update([
                'partner_application_id' => $partnerApplication->id,
                'status' => $newStatus,
                'incentive_amount' => $onboarding->submitted_at ? $onboarding->incentive_amount : $this->settings->amount(),
                'incentive_status' => RestaurantOnboardingIncentive::STATUS_PENDING,
                'restaurant_latitude' => $request->input('latitude'),
                'restaurant_longitude' => $request->input('longitude'),
                'driver_latitude' => $request->input('driver_latitude'),
                'driver_longitude' => $request->input('driver_longitude'),
                'location_captured_at' => $request->filled('driver_latitude') ? now() : $onboarding->location_captured_at,
                'submitted_at' => $onboarding->submitted_at ?: now(),
                'draft_payload' => $this->safeDraftPayload($request->except(['password', 'password_confirmation'])),
                'correction_notes' => null,
                'correction_fields' => null,
            ]);

            RestaurantOnboardingIncentive::firstOrCreate(
                [
                    'restaurant_onboarding_id' => $onboarding->id,
                    'earning_type' => RestaurantOnboardingIncentive::TYPE,
                ],
                [
                    'driver_id' => $driver->id,
                    'restaurant_id' => $onboarding->restaurant_id,
                    'amount' => $onboarding->fresh()->incentive_amount,
                    'status' => RestaurantOnboardingIncentive::STATUS_PENDING,
                ]
            );

            $this->event($onboarding->fresh(), $newStatus === RestaurantOnboarding::STATUS_RESUBMITTED ? 'driver_resubmitted' : 'driver_submitted_application', $driver);
            $this->notifications->driver($onboarding->fresh('driver'), 'Restaurant onboarding submitted', ($partnerApplication->business_name ?: 'Restaurant') . ' is now under verification.', 'restaurant_onboarding_submitted');

            return $onboarding->fresh(['partnerApplication', 'restaurant', 'incentive', 'events']);
        });
    }

    public function sendOwnerOtp(RestaurantOnboarding $onboarding, User $driver, string $phone, ?string $appSignature = null): array
    {
        $this->authorizeDriver($onboarding, $driver);
        $phone = $this->applications->normalizePhone($phone);
        $otp = (string) random_int(100000, 999999);
        $provider = app(SmsService::class)->sendOtp($phone, $otp, $appSignature);

        if ($provider !== 'msg91') {
            Cache::put($this->ownerOtpCacheKey($onboarding, $phone), Hash::make($otp), now()->addMinutes(10));
        }

        $this->event($onboarding, 'owner_otp_sent', $driver, null, ['phone' => $phone]);

        return ['phone' => $phone, 'provider' => $provider, 'expires_in' => 600];
    }

    public function verifyOwnerOtp(RestaurantOnboarding $onboarding, User $driver, string $phone, string $otp): RestaurantOnboarding
    {
        $this->authorizeDriver($onboarding, $driver);
        $phone = $this->applications->normalizePhone($phone);
        $cacheKey = $this->ownerOtpCacheKey($onboarding, $phone);
        $hash = Cache::get($cacheKey);
        $verified = $hash ? Hash::check($otp, $hash) : false;

        if (! $verified) {
            $sms = app(SmsService::class);
            $verified = $sms->otpProvider() === 'msg91' && $sms->verifyOtp($phone, $otp);
        }

        if (! $verified) {
            throw ValidationException::withMessages(['otp' => 'Invalid or expired OTP.']);
        }

        Cache::forget($cacheKey);
        $onboarding->update([
            'owner_mobile_verified' => true,
            'owner_mobile_verified_at' => now(),
        ]);
        $this->event($onboarding->fresh(), 'owner_mobile_verified', $driver);

        return $onboarding->fresh(['partnerApplication', 'restaurant', 'incentive', 'events']);
    }

    public function requestCorrection(RestaurantOnboarding $onboarding, User $admin, string $notes, array $fields = []): RestaurantOnboarding
    {
        if (! in_array($onboarding->status, [
            RestaurantOnboarding::STATUS_SUBMITTED,
            RestaurantOnboarding::STATUS_UNDER_REVIEW,
            RestaurantOnboarding::STATUS_RESUBMITTED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Only submitted applications can be sent for correction.']);
        }

        $old = $onboarding->only(['status', 'correction_notes', 'correction_fields']);
        $onboarding->update([
            'status' => RestaurantOnboarding::STATUS_CORRECTION_REQUIRED,
            'correction_notes' => $notes,
            'correction_fields' => $fields,
        ]);
        $onboarding->partnerApplication?->update(['admin_notes' => $notes]);
        $this->event($onboarding->fresh(), 'admin_requested_correction', $admin, $old, $onboarding->only(['status', 'correction_notes', 'correction_fields']), $notes);
        $this->notifications->driver($onboarding->fresh('driver'), 'Action required', 'Please update the requested information for ' . $this->restaurantName($onboarding) . '.', 'restaurant_onboarding_correction_required');

        return $onboarding->fresh(['partnerApplication', 'restaurant', 'driver', 'incentive', 'events']);
    }

    public function reject(RestaurantOnboarding $onboarding, User $admin, string $reason): RestaurantOnboarding
    {
        $old = $onboarding->only(['status', 'incentive_status']);
        $onboarding->update([
            'status' => RestaurantOnboarding::STATUS_REJECTED,
            'incentive_status' => RestaurantOnboardingIncentive::STATUS_CANCELLED,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);
        $onboarding->partnerApplication?->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'admin_notes' => $reason,
        ]);
        $onboarding->incentive?->update(['status' => RestaurantOnboardingIncentive::STATUS_CANCELLED]);
        $this->event($onboarding->fresh(), 'admin_rejected', $admin, $old, $onboarding->only(['status', 'incentive_status']), $reason);
        $this->notifications->driver($onboarding->fresh('driver'), 'Restaurant onboarding rejected', $reason, 'restaurant_onboarding_rejected');

        return $onboarding->fresh(['partnerApplication', 'restaurant', 'driver', 'incentive', 'events']);
    }

    public function markApproved(PartnerApplication $application, Restaurant $restaurant, ?User $actor = null): void
    {
        $onboarding = RestaurantOnboarding::where('partner_application_id', $application->id)->first();
        if (! $onboarding) {
            return;
        }

        $onboarding->update([
            'restaurant_id' => $restaurant->id,
            'status' => RestaurantOnboarding::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $onboarding->incentive?->update(['restaurant_id' => $restaurant->id]);
        $this->event($onboarding->fresh(), 'restaurant_approved', $actor);
        $this->notifications->driver($onboarding->fresh('driver'), $restaurant->name . ' approved', $restaurant->name . ' has been approved.', 'restaurant_onboarding_approved');

        if ($this->settings->trigger() === RestaurantOnboardingSettings::TRIGGER_APPROVED) {
            $this->earnIncentive($onboarding->fresh(['driver', 'restaurant', 'incentive']), $actor);
        }
    }

    public function markActivatedByRestaurant(Restaurant $restaurant, ?User $actor = null): void
    {
        if (! $restaurant) {
            return;
        }

        $onboarding = RestaurantOnboarding::where('restaurant_id', $restaurant->id)->first();
        if (! $onboarding || $onboarding->activated_at) {
            return;
        }

        $onboarding->update([
            'status' => RestaurantOnboarding::STATUS_ACTIVATED,
            'activated_at' => now(),
        ]);
        $this->event($onboarding->fresh(), 'restaurant_activated', $actor);

        if ($this->settings->trigger() === RestaurantOnboardingSettings::TRIGGER_ACTIVATED) {
            $this->earnIncentive($onboarding->fresh(['driver', 'restaurant', 'incentive']), $actor);
        }
    }

    public function markFirstSuccessfulOrder(?Restaurant $restaurant, ?User $actor = null): void
    {
        if (! $restaurant) {
            return;
        }

        $onboarding = RestaurantOnboarding::where('restaurant_id', $restaurant->id)->first();
        if ($onboarding && $this->settings->trigger() === RestaurantOnboardingSettings::TRIGGER_FIRST_ORDER) {
            $this->earnIncentive($onboarding->fresh(['driver', 'restaurant', 'incentive']), $actor);
        }
    }

    public function earnIncentive(RestaurantOnboarding $onboarding, ?User $actor = null): ?RestaurantOnboardingIncentive
    {
        if (! $onboarding->driver || (float) $onboarding->incentive_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($onboarding, $actor) {
            $locked = RestaurantOnboarding::with(['driver', 'restaurant', 'incentive'])->lockForUpdate()->find($onboarding->id);
            if (! $locked || in_array($locked->incentive_status, [
                RestaurantOnboardingIncentive::STATUS_EARNED,
                RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                RestaurantOnboardingIncentive::STATUS_PAID,
                RestaurantOnboardingIncentive::STATUS_CANCELLED,
            ], true)) {
                return $locked?->incentive;
            }

            $incentive = RestaurantOnboardingIncentive::firstOrCreate(
                [
                    'restaurant_onboarding_id' => $locked->id,
                    'earning_type' => RestaurantOnboardingIncentive::TYPE,
                ],
                [
                    'driver_id' => $locked->driver_id,
                    'restaurant_id' => $locked->restaurant_id,
                    'amount' => $locked->incentive_amount,
                    'status' => RestaurantOnboardingIncentive::STATUS_PENDING,
                ]
            );

            if (in_array($incentive->status, [
                RestaurantOnboardingIncentive::STATUS_EARNED,
                RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                RestaurantOnboardingIncentive::STATUS_PAID,
            ], true)) {
                return $incentive;
            }

            $wallet = Wallet::where('user_id', $locked->driver_id)->lockForUpdate()->first()
                ?: Wallet::create(['user_id' => $locked->driver_id, 'balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]);

            $existingTransaction = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('reference_type', RestaurantOnboardingIncentive::TYPE)
                ->where('reference_id', $locked->id)
                ->first();

            if (! $existingTransaction) {
                $wallet->increment('balance', (float) $locked->incentive_amount);
                $wallet->refresh();
                $existingTransaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'type' => 'credit',
                    'amount' => (float) $locked->incentive_amount,
                    'balance_after' => $wallet->balance,
                    'reference_type' => RestaurantOnboardingIncentive::TYPE,
                    'reference_id' => $locked->id,
                    'description' => 'Restaurant onboarding incentive for ' . $this->restaurantName($locked),
                    'created_by' => $actor?->id,
                    'meta' => ['source' => 'income', 'application_number' => $locked->application_number, 'restaurant_id' => $locked->restaurant_id],
                ]);
            }

            $incentive->update([
                'driver_id' => $locked->driver_id,
                'restaurant_id' => $locked->restaurant_id,
                'amount' => $locked->incentive_amount,
                'wallet_transaction_id' => $existingTransaction->id,
                'status' => RestaurantOnboardingIncentive::STATUS_EARNED,
                'earned_at' => $incentive->earned_at ?: now(),
            ]);
            $locked->update(['incentive_status' => RestaurantOnboardingIncentive::STATUS_EARNED]);
            $this->event($locked->fresh(), 'restaurant_onboarding_incentive_generated', $actor);
            $this->notifications->driver($locked->fresh(['driver', 'restaurant']), 'Onboarding incentive earned', $this->restaurantName($locked) . ' has been successfully onboarded. ' . number_format((float) $locked->incentive_amount, 2) . ' has been added to your earnings.', 'restaurant_onboarding_incentive_earned');

            return $incentive->fresh();
        });
    }

    public function format(RestaurantOnboarding $onboarding, bool $withTimeline = false): array
    {
        $application = $onboarding->partnerApplication;
        $restaurant = $onboarding->restaurant;
        $data = [
            'id' => $onboarding->id,
            'application_number' => $onboarding->application_number,
            'status' => $onboarding->status,
            'status_label' => $this->statusLabel($onboarding->status),
            'incentive_amount' => (float) $onboarding->incentive_amount,
            'incentive_status' => $onboarding->incentive_status,
            'owner_mobile_verified' => (bool) $onboarding->owner_mobile_verified,
            'correction_notes' => $onboarding->correction_notes,
            'correction_fields' => $onboarding->correction_fields ?: [],
            'rejection_reason' => $onboarding->rejection_reason,
            'verification' => $this->verificationSummary($application),
            'submitted_at' => optional($onboarding->submitted_at)->toIso8601String(),
            'approved_at' => optional($onboarding->approved_at)->toIso8601String(),
            'activated_at' => optional($onboarding->activated_at)->toIso8601String(),
            'created_at' => optional($onboarding->created_at)->toIso8601String(),
            'restaurant' => [
                'id' => $restaurant?->id,
                'name' => $restaurant?->name ?? $application?->business_name ?? data_get($onboarding->draft_payload, 'business_name'),
                'phone' => $restaurant?->phone ?? $application?->business_phone ?? data_get($onboarding->draft_payload, 'business_phone'),
                'image' => $restaurant?->logo_image ?? data_get($application?->onboarding_meta, 'logo_image'),
            ],
            'partner_application_id' => $onboarding->partner_application_id,
            'documents' => $this->documentPresence($application),
        ];

        if ($withTimeline) {
            $data['timeline'] = $onboarding->events()
                ->latest('id')
                ->get()
                ->reverse()
                ->values()
                ->map(fn (RestaurantOnboardingEvent $event) => [
                    'event' => $event->event,
                    'label' => Str::headline($event->event),
                    'notes' => $event->notes,
                    'created_at' => optional($event->created_at)->toIso8601String(),
                ]);
            $data['draft_payload'] = $onboarding->draft_payload ?: [];
        }

        return $data;
    }

    private function documentPresence(?PartnerApplication $application): array
    {
        $meta = is_array($application?->onboarding_meta) ? $application->onboarding_meta : [];

        return [
            'logo_image' => ! empty($meta['logo_image']),
            'banner_image' => ! empty($meta['banner_image']) || ! empty($meta['cover_image']),
            'menu_photo' => ! empty($meta['menu_photo']),
            'fssai_license' => ! empty($application?->fssai_license),
        ];
    }

    private function verificationSummary(?PartnerApplication $application): array
    {
        if (! $application) {
            return [
                'overall_status' => 'pending',
                'bank_account_status' => null,
                'document_statuses' => [],
            ];
        }

        $verification = is_array($application->document_verification) ? $application->document_verification : [];
        $documentStatuses = collect($verification)
            ->mapWithKeys(fn ($result, $key) => [$key => is_array($result) ? ($result['status'] ?? 'checked') : 'checked'])
            ->all();
        $statuses = collect($documentStatuses)->filter()->values();
        $overallStatus = match (true) {
            $statuses->isEmpty() => 'pending',
            $statuses->contains(fn ($status) => in_array($status, ['invalid', 'error'], true)) => 'needs_review',
            $statuses->every(fn ($status) => $status === 'verified') => 'verified',
            default => 'checked',
        };

        return [
            'overall_status' => $overallStatus,
            'bank_account_status' => data_get($verification, 'bank_account.status'),
            'document_statuses' => $documentStatuses,
        ];
    }

    private function assertDuplicateFree(RestaurantOnboarding $onboarding, Request $request): void
    {
        $phone = $this->applications->normalizePhone($request->input('business_phone') ?: $request->input('contact_phone'));
        $gstin = strtoupper(trim((string) $request->input('gstin_number', $request->input('gst_number'))));
        $pan = strtoupper(trim((string) $request->input('pan_number')));
        $name = trim((string) $request->input('business_name'));
        $address = trim((string) $request->input('address'));

        $existingRestaurant = Restaurant::query()
            ->where(function ($query) use ($phone, $name, $address) {
                $query->where('phone', $phone)
                    ->orWhere(function ($inner) use ($name, $address) {
                        $inner->where('name', $name)->where('address', $address);
                    });
            })
            ->exists();
        if ($existingRestaurant) {
            throw ValidationException::withMessages(['restaurant' => 'This restaurant is already registered on the platform.']);
        }

        $existingApplication = PartnerApplication::query()
            ->where('partner_type', 'restaurant')
            ->where('id', '!=', $onboarding->partner_application_id ?: 0)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($phone, $gstin, $pan, $name, $address) {
                $query->where('business_phone', $phone)
                    ->orWhere('contact_phone', $phone)
                    ->when($gstin !== '', fn ($inner) => $inner->orWhere('gstin_number', $gstin))
                    ->when($pan !== '', fn ($inner) => $inner->orWhere('pan_number', $pan))
                    ->orWhere(function ($inner) use ($name, $address) {
                        $inner->where('business_name', $name)->where('address', $address);
                    });
            })
            ->exists();
        if ($existingApplication) {
            throw ValidationException::withMessages(['restaurant' => 'This restaurant is already under onboarding and cannot be submitted again.']);
        }

        $activeOnboarding = RestaurantOnboarding::query()
            ->where('id', '!=', $onboarding->id)
            ->whereIn('status', [
                RestaurantOnboarding::STATUS_SUBMITTED,
                RestaurantOnboarding::STATUS_UNDER_REVIEW,
                RestaurantOnboarding::STATUS_RESUBMITTED,
                RestaurantOnboarding::STATUS_APPROVED,
                RestaurantOnboarding::STATUS_ACTIVATED,
            ])
            ->whereHas('partnerApplication', function ($query) use ($phone, $gstin, $pan, $name, $address) {
                $query->where('business_phone', $phone)
                    ->orWhere('contact_phone', $phone)
                    ->when($gstin !== '', fn ($inner) => $inner->orWhere('gstin_number', $gstin))
                    ->when($pan !== '', fn ($inner) => $inner->orWhere('pan_number', $pan))
                    ->orWhere(function ($inner) use ($name, $address) {
                        $inner->where('business_name', $name)->where('address', $address);
                    });
            })
            ->exists();
        if ($activeOnboarding) {
            throw ValidationException::withMessages(['restaurant' => 'This restaurant is already under onboarding and cannot be submitted again.']);
        }
    }

    private function assertGps(RestaurantOnboarding $onboarding, Request $request): void
    {
        $settings = $this->settings->all();
        if (! $settings['gps_required']) {
            return;
        }

        $driverLat = $request->input('driver_latitude');
        $driverLng = $request->input('driver_longitude');
        $restaurantLat = $request->input('latitude');
        $restaurantLng = $request->input('longitude');
        if ($driverLat === null || $driverLng === null || $restaurantLat === null || $restaurantLng === null) {
            throw ValidationException::withMessages(['location' => 'You must be near the restaurant location to submit this onboarding.']);
        }

        $distance = $this->distanceMeters((float) $driverLat, (float) $driverLng, (float) $restaurantLat, (float) $restaurantLng);
        $onboarding->forceFill(['distance_from_restaurant' => $distance])->save();
        if ($distance > (int) $settings['gps_radius_meters']) {
            throw ValidationException::withMessages(['location' => 'You must be near the restaurant location to submit this onboarding.']);
        }
    }

    private function assertOwnerOtp(RestaurantOnboarding $onboarding): void
    {
        if ($this->settings->ownerOtpMode() === 'required' && ! $onboarding->owner_mobile_verified) {
            throw ValidationException::withMessages(['owner_otp' => 'Restaurant owner mobile OTP verification is required before submission.']);
        }
    }

    private function assertLimit(User $driver, ?RestaurantOnboarding $ignore = null): void
    {
        $settings = $this->settings->all();
        foreach ([['daily_limit', now()->startOfDay()], ['monthly_limit', now()->startOfMonth()]] as [$key, $start]) {
            if (! $settings[$key]) {
                continue;
            }
            $count = RestaurantOnboarding::where('driver_id', $driver->id)
                ->where('id', '!=', $ignore?->id ?: 0)
                ->whereNotNull('submitted_at')
                ->where('submitted_at', '>=', $start)
                ->count();
            if ($count >= $settings[$key]) {
                throw ValidationException::withMessages(['limit' => 'Restaurant onboarding submission limit reached.']);
            }
        }
    }

    private function authorizeDriver(RestaurantOnboarding $onboarding, User $driver): void
    {
        if ((int) $onboarding->driver_id !== (int) $driver->id) {
            abort(404);
        }
    }

    private function safeDraftPayload(array $payload): array
    {
        foreach ([
            'driver_id',
            'onboarded_by_driver_id',
            'registration_source',
            'incentive_amount',
            'incentive_status',
            'payout_id',
            'approval_status',
            'status',
            'wallet_transaction_id',
        ] as $protectedField) {
            unset($payload[$protectedField]);
        }

        return collect($payload)
            ->map(fn ($value) => $this->serializableDraftValue($value))
            ->reject(fn ($value) => $value === null)
            ->all();
    }

    private function serializableDraftValue($value)
    {
        if ($value instanceof \Illuminate\Http\UploadedFile || $value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return null;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->serializableDraftValue($item))
                ->reject(fn ($item) => $item === null)
                ->all();
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function event(RestaurantOnboarding $onboarding, string $event, ?User $actor = null, ?array $old = null, ?array $new = null, ?string $notes = null): void
    {
        RestaurantOnboardingEvent::create([
            'restaurant_onboarding_id' => $onboarding->id,
            'actor_id' => $actor?->id,
            'actor_type' => $actor?->hasAnyRole(['admin', 'super_admin']) ? 'admin' : ($actor ? 'driver' : 'system'),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'notes' => $notes,
        ]);
    }

    private function nextApplicationNumber(): string
    {
        do {
            $number = 'ONB-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (RestaurantOnboarding::where('application_number', $number)->exists());

        return $number;
    }

    private function ownerOtpCacheKey(RestaurantOnboarding $onboarding, string $phone): string
    {
        return 'restaurant_onboarding_owner_otp:' . $onboarding->id . ':' . sha1($phone);
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }

    private function restaurantName(RestaurantOnboarding $onboarding): string
    {
        return $onboarding->restaurant?->name
            ?: $onboarding->partnerApplication?->business_name
            ?: data_get($onboarding->draft_payload, 'business_name', 'the restaurant');
    }

    private function statusLabel(string $status): string
    {
        return [
            RestaurantOnboarding::STATUS_DRAFT => 'Draft',
            RestaurantOnboarding::STATUS_SUBMITTED => 'Submitted',
            RestaurantOnboarding::STATUS_UNDER_REVIEW => 'Under Verification',
            RestaurantOnboarding::STATUS_CORRECTION_REQUIRED => 'Correction Required',
            RestaurantOnboarding::STATUS_RESUBMITTED => 'Resubmitted',
            RestaurantOnboarding::STATUS_APPROVED => 'Approved',
            RestaurantOnboarding::STATUS_ACTIVATED => 'Active',
            RestaurantOnboarding::STATUS_REJECTED => 'Rejected',
            RestaurantOnboarding::STATUS_CANCELLED => 'Cancelled',
        ][$status] ?? Str::headline($status);
    }
}
