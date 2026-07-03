<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Services\DeliveryAreaResolver;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly DeliveryAreaResolver $deliveryAreaResolver
    ) {
    }

    public function submit(Request $request)
    {
        $partnerType = $request->input('partner_type');

        $validator = Validator::make(
            $request->all(),
            $this->validationRules($partnerType)
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($partnerType === 'driver') {
            $normalizedPhone = $this->normalizePhone($request->input('phone'));
            if (
                PartnerApplication::query()->where('phone', $normalizedPhone)->exists() ||
                User::query()->where('phone', $normalizedPhone)->exists()
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account or pending application already exists with this mobile number.',
                ], 422);
            }
            if (! $this->hasValidPhoneVerificationToken(
                $normalizedPhone,
                (string) $request->input('verified_phone_token'),
                'signup'
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mobile OTP verification is required before submitting the driver application.',
                ], 422);
            }
            $request->merge(['phone' => $normalizedPhone]);
        }

        DB::beginTransaction();

        try {
            $resolvedArea = $this->resolveAreaFromRequest($request);
            $applicationNumber = 'APP' . strtoupper(uniqid());
            $documents = $this->storeDocuments($request);
            $bankDetails = $this->bankDetailsPayload($request);
            $meta = $this->onboardingMetaPayload($request, $resolvedArea);
            $generatedPassword = Str::password(24);

            $application = PartnerApplication::create(array_merge(
                [
                    'application_number' => $applicationNumber,
                    'partner_type' => $partnerType,
                    'status' => 'pending',
                    'city' => $request->input('city'),
                    'address' => $request->input('address'),
                    'bank_details' => !empty($bankDetails) ? json_encode($bankDetails) : null,
                    'onboarding_meta' => !empty($meta) ? $meta : null,
                    'password' => Hash::make((string) $request->input('password', $generatedPassword)),
                ],
                $this->typeSpecificPayload($request, $documents, $resolvedArea)
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully. It is now pending admin approval.',
                'data' => [
                    'application_id' => $application->id,
                    'application_number' => $application->application_number,
                    'partner_type' => $application->partner_type,
                    'status' => $application->status,
                    'status_message' => $this->statusMessage($application),
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner application API submission failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application.',
            ], 500);
        }
    }

    public function status(string $applicationNumber)
    {
        $application = PartnerApplication::where('application_number', $applicationNumber)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'application_number' => $application->application_number,
                'partner_type' => $application->partner_type,
                'status' => $application->status,
                'status_message' => $this->statusMessage($application),
                'admin_notes' => $application->admin_notes,
                'reviewed_at' => optional($application->reviewed_at)?->toIso8601String(),
                'created_at' => optional($application->created_at)?->toIso8601String(),
                'full_name' => $application->full_name,
                'business_name' => $application->business_name,
                'email' => $application->email ?: $application->business_email,
                'phone' => $application->phone ?: $application->business_phone,
                'bank_details' => $application->bank_details ? json_decode($application->bank_details, true) : null,
                'onboarding_meta' => $application->onboarding_meta,
                'delivery_area' => $application->deliveryArea ? [
                    'id' => $application->deliveryArea->id,
                    'name' => $application->deliveryArea->name,
                ] : null,
            ],
        ]);
    }

    private function validationRules(?string $partnerType): array
    {
        if ($partnerType === 'restaurant') {
            return [
                'partner_type' => 'required|in:restaurant',
                'business_name' => 'required|string|max:255',
                'business_email' => 'required|email|unique:partner_applications,business_email|unique:restaurants,email|unique:users,email',
                'business_phone' => 'required|string|max:20|unique:partner_applications,business_phone',
                'city' => 'required|string|max:120',
                'address' => 'required|string|max:1000',
                'pincode' => 'nullable|string|max:10',
                'area_id' => 'nullable|exists:delivery_areas,id',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'cuisine' => 'nullable|string|max:255',
                'is_pure_veg' => 'nullable|boolean',
                'contact_name' => 'required|string|max:255',
                'contact_designation' => 'nullable|string|max:100',
                'contact_email' => 'required|email',
                'contact_phone' => 'required|string|max:20',
                    'gst_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'fssai_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'terms' => 'accepted',
            ];
        }

        return [
            'partner_type' => 'required|in:driver',
            'verified_phone_token' => 'required|string|min:20|max:255',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:partner_applications,email|unique:users,email',
            'phone' => 'required|string|max:20|unique:partner_applications,phone|unique:users,phone',
            'city' => 'required|string|max:120',
            'address' => 'required|string|max:1000',
            'vehicle_type' => 'required|in:bike,scooter,ev_scooter,bicycle,car',
            'vehicle_number' => 'required|string|max:20',
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'nullable|date',
            'gender' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'vehicle_model' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|string|max:50',
            'area_id' => 'nullable|exists:delivery_areas,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'vehicle_image' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'aadhar_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pan_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'vehicle_rc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'insurance_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'terms' => 'accepted',
        ];
    }

    private function storeDocuments(Request $request): array
    {
        return [
            'gst_certificate' => $request->hasFile('gst_certificate')
                ? $request->file('gst_certificate')->store('partner_documents/gst', 'public')
                : null,
            'fssai_license' => $request->hasFile('fssai_license')
                ? $request->file('fssai_license')->store('partner_documents/fssai', 'public')
                : null,
            'license_document' => $request->hasFile('license_document')
                ? $request->file('license_document')->store('partner_documents/licenses', 'public')
                : null,
            'profile_photo' => $request->hasFile('profile_photo')
                ? $request->file('profile_photo')->store('partner_documents/profile_photos', 'public')
                : null,
            'vehicle_image' => $request->hasFile('vehicle_image')
                ? $request->file('vehicle_image')->store('partner_documents/vehicle_images', 'public')
                : null,
            'aadhar_card' => $request->hasFile('aadhar_card')
                ? $request->file('aadhar_card')->store('partner_documents/aadhar', 'public')
                : null,
            'pan_card' => $request->hasFile('pan_card')
                ? $request->file('pan_card')->store('partner_documents/pan', 'public')
                : null,
            'vehicle_rc' => $request->hasFile('vehicle_rc')
                ? $request->file('vehicle_rc')->store('partner_documents/vehicle_rc', 'public')
                : null,
            'insurance_document' => $request->hasFile('insurance_document')
                ? $request->file('insurance_document')->store('partner_documents/insurance', 'public')
                : null,
        ];
    }

    private function bankDetailsPayload(Request $request): array
    {
        $bankDetails = [];
        foreach ([
            'bank_holder_name' => 'holder_name',
            'bank_account_number' => 'account_number',
            'bank_ifsc' => 'ifsc',
            'bank_name' => 'bank_name',
            'upi_id' => 'upi_id',
            'bank_details' => 'additional_info',
        ] as $input => $target) {
            $value = $request->input($input);
            if ($value !== null && $value !== '') {
                $bankDetails[$target] = $value;
            }
        }

        return $bankDetails;
    }

    private function onboardingMetaPayload(Request $request, ?DeliveryArea $area): array
    {
        return array_filter([
            'date_of_birth' => $request->input('date_of_birth'),
            'gender' => $request->input('gender'),
            'vehicle_model' => $request->input('vehicle_model'),
            'fuel_type' => $request->input('fuel_type'),
            'landmark' => $request->input('landmark'),
            'owner_name' => $request->input('owner_name'),
            'restaurant_phone' => $request->input('restaurant_phone'),
            'weekly_off' => $request->input('weekly_off'),
            'opening_time' => $request->input('opening_time'),
            'closing_time' => $request->input('closing_time'),
            'secondary_opening_time' => $request->input('secondary_opening_time'),
            'secondary_closing_time' => $request->input('secondary_closing_time'),
            'restaurant_categories' => $request->input('restaurant_categories'),
            'photo_status' => $request->input('photo_status'),
            'document_status' => $request->input('document_status'),
            'minimum_order_value' => $request->input('minimum_order_value'),
            'free_delivery_threshold' => $request->input('free_delivery_threshold'),
            'delivery_charges' => $request->input('delivery_charges'),
            'packaging_charge' => $request->input('packaging_charge'),
            'gst_percentage' => $request->input('gst_percentage'),
            'handling_fee' => $request->input('handling_fee'),
            'commission_preview' => $request->input('commission_preview'),
            'payout_cycle' => $request->input('payout_cycle'),
            'menu_summary' => $request->input('menu_summary'),
            'ai_verification_enabled' => $request->boolean('ai_verification_enabled'),
            'background_location_enabled' => $request->boolean('background_location_enabled'),
            'notification_permission_enabled' => $request->boolean('notification_permission_enabled'),
            'zone_name' => $area?->name,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function typeSpecificPayload(Request $request, array $documents, ?DeliveryArea $resolvedArea): array
    {
        if ($request->input('partner_type') === 'restaurant') {
            $cuisine = trim((string) $request->input('cuisine', ''));
            return [
                'business_name' => $request->input('business_name'),
                'business_email' => $request->input('business_email'),
                'business_phone' => $request->input('business_phone'),
                'pincode' => $request->input('pincode'),
                'cuisine' => $cuisine !== '' ? json_encode([$cuisine]) : null,
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'area_id' => $resolvedArea?->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'contact_name' => $request->input('contact_name'),
                'contact_designation' => $request->input('contact_designation'),
                'contact_email' => $request->input('contact_email'),
                'contact_phone' => $request->input('contact_phone'),
                'gst_certificate' => $documents['gst_certificate'],
                'fssai_license' => $documents['fssai_license'],
            ];
        }

        return [
            'full_name' => $request->input('full_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'vehicle_type' => $request->input('vehicle_type'),
            'vehicle_number' => $request->input('vehicle_number'),
            'license_number' => $request->input('license_number'),
            'license_document' => $documents['license_document'],
            'profile_photo' => $documents['profile_photo'],
            'vehicle_image' => $documents['vehicle_image'],
            'aadhar_card' => $documents['aadhar_card'],
            'pan_card' => $documents['pan_card'],
            'vehicle_rc' => $documents['vehicle_rc'],
            'insurance_document' => $documents['insurance_document'],
            'area_id' => $resolvedArea?->id,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ];
    }

    private function resolveAreaFromRequest(Request $request): ?DeliveryArea
    {
        $area = $this->deliveryAreaResolver->resolve(
            $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            $request->input('longitude') !== null ? (float) $request->input('longitude') : null
        );

        if (! $area) {
            throw ValidationException::withMessages([
                'latitude' => 'No active delivery zone matched the current location.',
            ]);
        }

        $request->merge(['area_id' => $area->id]);

        return $area;
    }

    private function statusMessage(PartnerApplication $application): string
    {
        return match ($application->status) {
            'approved' => 'Application approved. You can now sign in to your account.',
            'rejected' => 'Application rejected. Please review admin notes and reapply if needed.',
            default => 'Application submitted and pending admin review.',
        };
    }

    private function defaultMobileCountryCode(): string
    {
        return PhoneNumber::normalizeCountryCode(
            AppSetting::getValue('default_mobile_country_code', '+91')
        ) ?: '+91';
    }

    private function normalizePhone(?string $phone): string
    {
        return PhoneNumber::normalize($phone, $this->defaultMobileCountryCode());
    }

    private function hasValidPhoneVerificationToken(string $phone, string $token, string $flow): bool
    {
        $cacheKey = 'verified_phone:' . $flow . ':' . sha1($token);
        $cachedPhone = Cache::get($cacheKey);

        if ($cachedPhone !== $phone) {
            return false;
        }

        Cache::forget($cacheKey);
        return true;
    }
}
