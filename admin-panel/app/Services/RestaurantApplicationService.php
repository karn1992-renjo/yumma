<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Rules\UniqueUserContactForRole;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RestaurantApplicationService
{
    public function __construct(
        private readonly DeliveryAreaResolver $deliveryAreaResolver,
        private readonly CashfreeVerificationService $verification
    ) {
    }

    public function publicRules(): array
    {
        return [
            'partner_type' => 'required|in:restaurant',
            'business_name' => 'required|string|max:255',
            'business_email' => ['required', 'email', 'unique:partner_applications,business_email', 'unique:restaurants,email', UniqueUserContactForRole::email('restaurant_owner')],
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
            'gstin_number' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}\d{4}[A-Z]{1}$/i'],
            'fssai_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'terms' => 'accepted',
        ];
    }

    public function driverSubmissionRules(?PartnerApplication $application = null): array
    {
        $applicationId = $application?->id;

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email', Rule::unique('partner_applications', 'business_email')->ignore($applicationId), 'unique:restaurants,email'],
            'business_phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'area_id' => ['nullable', 'exists:delivery_areas,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'cuisine' => ['nullable', 'string', 'max:500'],
            'is_pure_veg' => ['nullable', 'boolean'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_designation' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'restaurant_phone' => ['nullable', 'string', 'max:20'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'opening_time' => ['nullable', 'string', 'max:40'],
            'closing_time' => ['nullable', 'string', 'max:40'],
            'secondary_opening_time' => ['nullable', 'string', 'max:40'],
            'secondary_closing_time' => ['nullable', 'string', 'max:40'],
            'weekly_off' => ['nullable', 'string', 'max:255'],
            'restaurant_categories' => ['nullable', 'string', 'max:1000'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_charges' => ['nullable', 'numeric', 'min:0'],
            'packaging_charge' => ['nullable', 'numeric', 'min:0'],
            'gst_percentage' => ['nullable', 'numeric', 'min:0'],
            'handling_fee' => ['nullable', 'numeric', 'min:0'],
            'menu_summary' => ['nullable', 'string', 'max:2000'],
            'gst_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'gstin_number' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'gst_number' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}\d{4}[A-Z]{1}$/i'],
            'fssai_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'logo_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'menu_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bank_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'shop_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc' => ['nullable', 'string', 'max:100'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'driver_latitude' => ['nullable', 'numeric'],
            'driver_longitude' => ['nullable', 'numeric'],
            'declaration' => ['accepted'],
            'terms' => ['accepted'],
        ];
    }

    public function createFromRequest(Request $request, array $extra = []): PartnerApplication
    {
        $resolvedArea = $this->resolveAreaFromRequest($request);
        $documents = $this->storeDocuments($request, []);
        $application = PartnerApplication::create(array_merge(
            [
                'application_number' => $extra['application_number'] ?? 'APP' . strtoupper(uniqid()),
                'partner_type' => 'restaurant',
                'status' => 'pending',
                'city' => $request->input('city'),
                'address' => $request->input('address'),
                'bank_details' => $this->bankDetailsPayload($request),
                'onboarding_meta' => $this->onboardingMetaPayload($request, $documents, [], $resolvedArea, $extra),
                'password' => $extra['password'] ?? Hash::make((string) $request->input('password', Str::password(24))),
            ],
            $this->restaurantPayload($request, $documents, $resolvedArea)
        ));

        $this->runDocumentVerification($application);

        return $application;
    }

    public function updateFromRequest(Request $request, PartnerApplication $application, array $extra = []): PartnerApplication
    {
        $resolvedArea = $this->resolveAreaFromRequest($request);
        $existingMeta = is_array($application->onboarding_meta) ? $application->onboarding_meta : [];
        $documents = $this->storeDocuments($request, array_merge($application->toArray(), $existingMeta));

        $application->update(array_merge(
            [
                'partner_type' => 'restaurant',
                'status' => 'pending',
                'city' => $request->input('city'),
                'address' => $request->input('address'),
                'bank_details' => $this->bankDetailsPayload($request),
                'onboarding_meta' => $this->onboardingMetaPayload($request, $documents, $existingMeta, $resolvedArea, $extra),
                'admin_notes' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ],
            $this->restaurantPayload($request, $documents, $resolvedArea, $application)
        ));

        $this->runDocumentVerification($application->fresh());

        return $application->fresh();
    }

    public function validOwnerOtp(string $phone, string $token): bool
    {
        $cacheKey = 'verified_phone:restaurant_onboarding_owner:' . sha1($token);
        $cachedPhone = Cache::get($cacheKey);
        if ($cachedPhone !== $this->normalizePhone($phone)) {
            return false;
        }

        Cache::forget($cacheKey);
        return true;
    }

    public function normalizePhone(?string $phone): string
    {
        return PhoneNumber::normalize($phone, AppSetting::getValue('default_mobile_country_code', '+91'));
    }

    private function restaurantPayload(Request $request, array $documents, ?DeliveryArea $resolvedArea, ?PartnerApplication $application = null): array
    {
        $cuisine = trim((string) $request->input('cuisine', ''));

        return [
            'business_name' => $request->input('business_name'),
            'business_email' => $request->input('business_email'),
            'business_phone' => $this->normalizePhone($request->input('business_phone')),
            'pincode' => $request->input('pincode'),
            'cuisine' => $cuisine !== '' ? json_encode(array_values(array_filter(array_map('trim', explode(',', $cuisine))))) : null,
            'is_pure_veg' => $request->boolean('is_pure_veg'),
            'area_id' => $resolvedArea?->id,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'contact_name' => $request->input('contact_name'),
            'contact_designation' => $request->input('contact_designation'),
            'contact_email' => $request->input('contact_email'),
            'contact_phone' => $this->normalizePhone($request->input('contact_phone')),
            'gst_certificate' => $documents['gst_certificate'] ?? $application?->gst_certificate,
            'gstin_number' => $this->upperOrExisting($request->input('gstin_number', $request->input('gst_number')), $application?->gstin_number),
            'pan_number' => $this->upperOrExisting($request->input('pan_number'), $application?->pan_number),
            'fssai_license' => $documents['fssai_license'] ?? $application?->fssai_license,
        ];
    }

    private function onboardingMetaPayload(Request $request, array $documents, array $existingMeta, ?DeliveryArea $area, array $extra): array
    {
        return array_merge($existingMeta, array_filter([
            'source' => $extra['source'] ?? null,
            'restaurant_onboarding_id' => $extra['restaurant_onboarding_id'] ?? null,
            'onboarded_by_driver_id' => $extra['onboarded_by_driver_id'] ?? null,
            'landmark' => $request->input('landmark'),
            'owner_name' => $request->input('owner_name'),
            'restaurant_phone' => $request->input('restaurant_phone'),
            'weekly_off' => $request->input('weekly_off'),
            'opening_time' => $request->input('opening_time'),
            'closing_time' => $request->input('closing_time'),
            'secondary_opening_time' => $request->input('secondary_opening_time'),
            'secondary_closing_time' => $request->input('secondary_closing_time'),
            'restaurant_categories' => $request->input('restaurant_categories'),
            'minimum_order_value' => $request->input('minimum_order_value', $request->input('minimum_order_amount')),
            'delivery_charges' => $request->input('delivery_charges'),
            'packaging_charge' => $request->input('packaging_charge'),
            'gst_percentage' => $request->input('gst_percentage'),
            'handling_fee' => $request->input('handling_fee'),
            'menu_summary' => $request->input('menu_summary'),
            'logo_image' => $documents['logo_image'] ?? $documents['logo'] ?? null,
            'banner_image' => $documents['banner_image'] ?? $documents['cover_image'] ?? null,
            'menu_photo' => $documents['menu_photo'] ?? null,
            'cover_image' => $documents['cover_image'] ?? null,
            'bank_proof' => $documents['bank_proof'] ?? null,
            'shop_license' => $documents['shop_license'] ?? null,
            'zone_name' => $area?->name,
            'service_area_status' => $area ? 'serviceable' : 'not_serviceable',
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    private function storeDocuments(Request $request, array $existing = []): array
    {
        $documents = [];
        foreach ([
            'gst_certificate' => 'partner_documents/gst',
            'fssai_license' => 'partner_documents/fssai',
            'logo_image' => 'partner_documents/restaurant/logo',
            'logo' => 'partner_documents/restaurant/logo',
            'banner_image' => 'partner_documents/restaurant/banner',
            'cover_image' => 'partner_documents/restaurant/cover',
            'menu_photo' => 'partner_documents/restaurant/menu',
            'bank_proof' => 'partner_documents/restaurant/bank-proof',
            'shop_license' => 'partner_documents/restaurant/shop-license',
            'pan_card' => 'partner_documents/pan',
        ] as $field => $directory) {
            if ($request->hasFile($field)) {
                if (! empty($existing[$field])) {
                    Storage::disk('public')->delete($existing[$field]);
                }
                $documents[$field] = $request->file($field)->store($directory, 'public');
            }
        }

        return $documents;
    }

    private function bankDetailsPayload(Request $request): ?string
    {
        $bankDetails = array_filter([
            'holder_name' => $request->input('bank_holder_name'),
            'bank_name' => $request->input('bank_name'),
            'account_number' => $request->input('bank_account_number'),
            'ifsc' => $request->input('bank_ifsc'),
            'upi_id' => $request->input('upi_id'),
        ], fn ($value) => filled($value));

        return empty($bankDetails) ? null : json_encode($bankDetails);
    }

    private function resolveAreaFromRequest(Request $request): ?DeliveryArea
    {
        $area = $this->deliveryAreaResolver->resolve(
            $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            $request->input('longitude') !== null ? (float) $request->input('longitude') : null
        );

        if ($area) {
            $request->merge(['area_id' => $area->id]);
        }

        return $area;
    }

    private function runDocumentVerification(PartnerApplication $application): void
    {
        $results = $this->verification->verifyPartnerApplication($application);
        if (! empty($results)) {
            $application->update([
                'document_verification' => array_replace((array) $application->document_verification, $results),
            ]);
        }
    }

    private function upperOrExisting(?string $value, ?string $existing): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? strtoupper($value) : $existing;
    }
}
