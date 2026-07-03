<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DeliveryArea;
use App\Models\PartnerApplication;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\DeliveryAreaResolver;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly DeliveryAreaResolver $deliveryAreaResolver
    ) {
    }

    public function index(Request $request)
    {
        $query = PartnerApplication::query()
            ->with(['deliveryArea', 'reviewer'])
            ->orderByDesc('created_at');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('partner_type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('application_number', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('business_email', 'like', "%{$search}%")
                    ->orWhere('business_phone', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate(20);

        $stats = [
            'total' => PartnerApplication::count(),
            'pending' => PartnerApplication::where('status', 'pending')->count(),
            'approved' => PartnerApplication::where('status', 'approved')->count(),
            'rejected' => PartnerApplication::where('status', 'rejected')->count(),
            'restaurant' => PartnerApplication::where('partner_type', 'restaurant')->count(),
            'driver' => PartnerApplication::where('partner_type', 'driver')->count(),
        ];

        return view('admin.partner-applications.index', compact('applications', 'stats'));
    }

    public function create()
    {
        return view('admin.partner-applications.create', [
            'application' => new PartnerApplication([
                'partner_type' => 'restaurant',
                'status' => 'pending',
                'is_pure_veg' => false,
            ]),
            'deliveryAreas' => DeliveryArea::query()->orderBy('name')->get(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $partnerType = (string) $request->input('partner_type', 'restaurant');
        $validated = $request->validate($this->rules($partnerType));

        DB::beginTransaction();

        try {
            $documents = $this->storeDocuments($request, []);
            $application = PartnerApplication::create(
                $this->payload($request, $validated, $documents)
            );

            DB::commit();

            return redirect()
                ->route('admin.partner-applications.show', $application)
                ->with('success', 'Partner application created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to create application: ' . $e->getMessage());
        }
    }

    public function show(PartnerApplication $application)
    {
        $application->load(['deliveryArea', 'reviewer']);

        return view('admin.partner-applications.show', compact('application'));
    }

    public function edit(PartnerApplication $application)
    {
        return view('admin.partner-applications.edit', [
            'application' => $application,
            'deliveryAreas' => DeliveryArea::query()->orderBy('name')->get(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, PartnerApplication $application)
    {
        $partnerType = (string) $request->input('partner_type', $application->partner_type);
        $validated = $request->validate($this->rules($partnerType, $application));

        DB::beginTransaction();

        try {
            $documents = $this->storeDocuments($request, $application->toArray());
            $application->update($this->payload($request, $validated, $documents, $application));

            DB::commit();

            return redirect()
                ->route('admin.partner-applications.show', $application)
                ->with('success', 'Partner application updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Failed to update application: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, PartnerApplication $application)
    {
        if ($application->status !== 'pending') {
            return redirect()->back()->with('error', 'This application has already been processed.');
        }

        DB::beginTransaction();

        try {
            if ($application->partner_type === 'restaurant') {
                $this->approveRestaurantApplication($application);
            } else {
                $this->approveDriverApplication($application);
            }

            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
                'admin_notes' => $request->admin_notes,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.partner-applications.index')
                ->with('success', 'Application approved successfully. User account created.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to approve application: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, PartnerApplication $application)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($application->status !== 'pending') {
            return redirect()->back()->with('error', 'This application has already been processed.');
        }

        $application->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'admin_notes' => $request->rejection_reason,
        ]);

        Mail::send('emails.partner-rejected', [
            'application' => $application,
            'reason' => $request->rejection_reason,
        ], function ($mail) use ($application) {
            $email = $application->partner_type === 'restaurant'
                ? $application->contact_email
                : $application->email;
            $mail->to($email)->subject('Update on your FoodFlow Partner Application');
        });

        return redirect()
            ->route('admin.partner-applications.index')
            ->with('success', 'Application rejected successfully.');
    }

    public function destroy(PartnerApplication $application)
    {
        foreach ([
            'gst_certificate',
            'fssai_license',
            'license_document',
            'profile_photo',
            'vehicle_image',
            'aadhar_card',
            'pan_card',
            'vehicle_rc',
            'insurance_document',
        ] as $field) {
            if ($application->{$field}) {
                Storage::disk('public')->delete($application->{$field});
            }
        }

        $meta = is_array($application->onboarding_meta) ? $application->onboarding_meta : [];
        foreach (['logo_image', 'banner_image', 'cover_image', 'interior_image', 'food_image', 'kitchen_image', 'bank_proof', 'shop_license'] as $field) {
            if (!empty($meta[$field])) {
                Storage::disk('public')->delete($meta[$field]);
            }
        }

        $application->delete();

        return redirect()
            ->route('admin.partner-applications.index')
            ->with('success', 'Application deleted successfully.');
    }

    protected function rules(string $partnerType, ?PartnerApplication $application = null): array
    {
        $applicationId = $application?->id;

        $restaurantRules = [
            'partner_type' => ['required', 'in:restaurant'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email', Rule::unique('partner_applications', 'business_email')->ignore($applicationId)],
            'business_phone' => ['required', 'string', 'max:20', Rule::unique('partner_applications', 'business_phone')->ignore($applicationId)],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'cuisine' => ['nullable', 'string', 'max:500'],
            'is_pure_veg' => ['nullable', 'boolean'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_designation' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'area_id' => ['nullable', 'exists:delivery_areas,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
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
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'delivery_charges' => ['nullable', 'numeric', 'min:0'],
            'packaging_charge' => ['nullable', 'numeric', 'min:0'],
            'gst_percentage' => ['nullable', 'numeric', 'min:0'],
            'handling_fee' => ['nullable', 'numeric', 'min:0'],
            'commission_preview' => ['nullable', 'string', 'max:255'],
            'payout_cycle' => ['nullable', 'string', 'max:255'],
            'menu_summary' => ['nullable', 'string', 'max:2000'],
            'photo_status' => ['nullable', 'string', 'max:500'],
            'document_status' => ['nullable', 'string', 'max:500'],
            'ai_verification_enabled' => ['nullable', 'boolean'],
            'bank_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc' => ['nullable', 'string', 'max:100'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'gst_certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'fssai_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'logo_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'interior_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'food_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'kitchen_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bank_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'shop_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];

        $driverRules = [
            'partner_type' => ['required', 'in:driver'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('partner_applications', 'email')->ignore($applicationId)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('partner_applications', 'phone')->ignore($applicationId)],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'area_id' => ['nullable', 'exists:delivery_areas,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'vehicle_type' => ['required', 'in:bike,scooter,ev_scooter,bicycle,car'],
            'vehicle_number' => ['required', 'string', 'max:20'],
            'license_number' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'vehicle_model' => ['nullable', 'string', 'max:255'],
            'fuel_type' => ['nullable', 'string', 'max:50'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'background_location_enabled' => ['nullable', 'boolean'],
            'notification_permission_enabled' => ['nullable', 'boolean'],
            'bank_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc' => ['nullable', 'string', 'max:100'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'vehicle_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'license_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'aadhar_card' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'pan_card' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'vehicle_rc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'insurance_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];

        return $partnerType === 'driver' ? $driverRules : $restaurantRules;
    }

    protected function payload(Request $request, array $validated, array $documents, ?PartnerApplication $application = null): array
    {
        $partnerType = $validated['partner_type'];
        $generatedPassword = $application?->password ?: Hash::make(Str::password(24));
        $existingMeta = is_array($application?->onboarding_meta) ? $application->onboarding_meta : [];
        $resolvedArea = $this->resolveAreaFromCoordinates(
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        $base = [
            'application_number' => $application?->application_number ?: 'APP' . strtoupper(uniqid()),
            'partner_type' => $partnerType,
            'status' => $validated['status'] ?? ($application?->status ?? 'pending'),
            'city' => $validated['city'],
            'address' => $validated['address'],
            'area_id' => $resolvedArea->id,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'bank_details' => $this->bankDetailsPayload($request),
            'onboarding_meta' => $this->onboardingMetaPayload(
                $request,
                $documents,
                $existingMeta,
                $partnerType,
                $resolvedArea
            ),
            'password' => $generatedPassword,
            'admin_notes' => $validated['admin_notes'] ?? $application?->admin_notes,
        ];

        if ($partnerType === 'restaurant') {
            return array_merge($base, [
                'business_name' => $validated['business_name'],
                'business_email' => $validated['business_email'],
                'business_phone' => $this->normalizePhone($validated['business_phone']),
                'pincode' => $validated['pincode'] ?? null,
                'cuisine' => $this->encodeCuisine($validated['cuisine'] ?? ''),
                'is_pure_veg' => (bool) ($validated['is_pure_veg'] ?? false),
                'contact_name' => $validated['contact_name'],
                'contact_designation' => $validated['contact_designation'] ?? null,
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $this->normalizePhone($validated['contact_phone']),
                'gst_certificate' => $documents['gst_certificate'] ?? $application?->gst_certificate,
                'fssai_license' => $documents['fssai_license'] ?? $application?->fssai_license,
                'reviewed_at' => $validated['status'] === 'pending' ? null : ($application?->reviewed_at ?? now()),
                'reviewed_by' => $validated['status'] === 'pending' ? null : ($application?->reviewed_by ?? auth()->id()),
            ]);
        }

        return array_merge($base, [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $this->normalizePhone($validated['phone']),
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_number' => $validated['vehicle_number'],
            'license_number' => $validated['license_number'],
            'license_document' => $documents['license_document'] ?? $application?->license_document,
            'profile_photo' => $documents['profile_photo'] ?? $application?->profile_photo,
            'vehicle_image' => $documents['vehicle_image'] ?? $application?->vehicle_image,
            'aadhar_card' => $documents['aadhar_card'] ?? $application?->aadhar_card,
            'pan_card' => $documents['pan_card'] ?? $application?->pan_card,
            'vehicle_rc' => $documents['vehicle_rc'] ?? $application?->vehicle_rc,
            'insurance_document' => $documents['insurance_document'] ?? $application?->insurance_document,
            'reviewed_at' => $validated['status'] === 'pending' ? null : ($application?->reviewed_at ?? now()),
            'reviewed_by' => $validated['status'] === 'pending' ? null : ($application?->reviewed_by ?? auth()->id()),
        ]);
    }

    protected function storeDocuments(Request $request, array $existing = []): array
    {
        $documents = [];

        foreach ([
            'gst_certificate' => 'partner_documents/gst',
            'fssai_license' => 'partner_documents/fssai',
            'license_document' => 'partner_documents/licenses',
            'profile_photo' => 'partner_documents/profile_photos',
            'vehicle_image' => 'partner_documents/vehicle_images',
            'aadhar_card' => 'partner_documents/aadhar',
            'pan_card' => 'partner_documents/pan',
            'vehicle_rc' => 'partner_documents/vehicle_rc',
            'insurance_document' => 'partner_documents/insurance',
            'logo_image' => 'partner_documents/restaurant/logo',
            'banner_image' => 'partner_documents/restaurant/banner',
            'cover_image' => 'partner_documents/restaurant/cover',
            'interior_image' => 'partner_documents/restaurant/interior',
            'food_image' => 'partner_documents/restaurant/food',
            'kitchen_image' => 'partner_documents/restaurant/kitchen',
            'bank_proof' => 'partner_documents/restaurant/bank-proof',
            'shop_license' => 'partner_documents/restaurant/shop-license',
        ] as $field => $directory) {
            if ($request->hasFile($field)) {
                if (!empty($existing[$field])) {
                    Storage::disk('public')->delete($existing[$field]);
                }
                $documents[$field] = $request->file($field)->store($directory, 'public');
            }
        }

        return $documents;
    }

    protected function bankDetailsPayload(Request $request): ?string
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

    protected function onboardingMetaPayload(
        Request $request,
        array $documents,
        array $existingMeta,
        string $partnerType,
        DeliveryArea $resolvedArea
    ): array
    {
        $base = [
            'landmark' => $request->input('landmark'),
            'zone_name' => $resolvedArea->name,
        ];

        if ($partnerType === 'restaurant') {
            $restaurantMeta = array_filter([
                'owner_name' => $request->input('owner_name'),
                'restaurant_phone' => $request->input('restaurant_phone'),
                'opening_time' => $request->input('opening_time'),
                'closing_time' => $request->input('closing_time'),
                'secondary_opening_time' => $request->input('secondary_opening_time'),
                'secondary_closing_time' => $request->input('secondary_closing_time'),
                'weekly_off' => $request->input('weekly_off'),
                'restaurant_categories' => $request->input('restaurant_categories'),
                'minimum_order_value' => $request->input('minimum_order_value'),
                'free_delivery_threshold' => $request->input('free_delivery_threshold'),
                'delivery_charges' => $request->input('delivery_charges'),
                'packaging_charge' => $request->input('packaging_charge'),
                'gst_percentage' => $request->input('gst_percentage'),
                'handling_fee' => $request->input('handling_fee'),
                'commission_preview' => $request->input('commission_preview'),
                'payout_cycle' => $request->input('payout_cycle'),
                'menu_summary' => $request->input('menu_summary'),
                'photo_status' => $request->input('photo_status'),
                'document_status' => $request->input('document_status'),
                'ai_verification_enabled' => $request->boolean('ai_verification_enabled'),
                'logo_image' => $documents['logo_image'] ?? ($existingMeta['logo_image'] ?? null),
                'banner_image' => $documents['banner_image'] ?? ($existingMeta['banner_image'] ?? null),
                'cover_image' => $documents['cover_image'] ?? ($existingMeta['cover_image'] ?? null),
                'interior_image' => $documents['interior_image'] ?? ($existingMeta['interior_image'] ?? null),
                'food_image' => $documents['food_image'] ?? ($existingMeta['food_image'] ?? null),
                'kitchen_image' => $documents['kitchen_image'] ?? ($existingMeta['kitchen_image'] ?? null),
                'bank_proof' => $documents['bank_proof'] ?? ($existingMeta['bank_proof'] ?? null),
                'shop_license' => $documents['shop_license'] ?? ($existingMeta['shop_license'] ?? null),
            ], fn ($value) => $value !== null && $value !== '');

            return array_merge($existingMeta, $base, $restaurantMeta);
        }

        $driverMeta = array_filter([
            'date_of_birth' => $request->input('date_of_birth'),
            'gender' => $request->input('gender'),
            'vehicle_model' => $request->input('vehicle_model'),
            'fuel_type' => $request->input('fuel_type'),
            'background_location_enabled' => $request->boolean('background_location_enabled'),
            'notification_permission_enabled' => $request->boolean('notification_permission_enabled'),
        ], fn ($value) => $value !== null && $value !== '');

        return array_merge($existingMeta, $base, $driverMeta);
    }

    protected function approveRestaurantApplication(PartnerApplication $application): void
    {
        $bankDetails = json_decode($application->bank_details ?? '[]', true);
        $bankDetails = is_array($bankDetails) ? $bankDetails : [];
        $meta = is_array($application->onboarding_meta) ? $application->onboarding_meta : [];

        $owner = User::create([
            'name' => $application->contact_name,
            'email' => $application->contact_email,
            'phone' => $application->contact_phone,
            'password' => $application->password,
            'is_active' => true,
            'account_holder_name' => $bankDetails['holder_name'] ?? null,
            'bank_name' => $bankDetails['bank_name'] ?? null,
            'account_number' => $bankDetails['account_number'] ?? null,
            'ifsc_code' => $bankDetails['ifsc'] ?? null,
            'upi_id' => $bankDetails['upi_id'] ?? null,
        ]);
        $owner->assignRole('restaurant_owner');

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => $application->business_name,
            'email' => $application->business_email,
            'phone' => $application->business_phone,
            'address' => $application->address,
            'city' => $application->city,
            'state' => 'N/A',
            'pincode' => $application->pincode,
            'cuisine' => json_decode($application->cuisine ?? '[]', true) ?? [],
            'is_pure_veg' => (bool) $application->is_pure_veg,
            'latitude' => $application->latitude ?? 0,
            'longitude' => $application->longitude ?? 0,
            'is_open' => false,
            'is_verified' => true,
            'min_order_amount' => $meta['minimum_order_value'] ?? 199,
            'delivery_fee' => $meta['delivery_charges'] ?? 40,
            'delivery_time' => 30,
            'delivery_radius' => optional($application->deliveryArea)->radius_km ?? 5,
            'logo_image' => $meta['logo_image'] ?? null,
            'banner_image' => $meta['banner_image'] ?? null,
            'cover_image' => $meta['cover_image'] ?? null,
            'open_time' => $this->normalizeRestaurantTime($meta['opening_time'] ?? null),
            'close_time' => $this->normalizeRestaurantTime(
                $meta['secondary_closing_time'] ?? $meta['closing_time'] ?? null
            ),
            'weekly_timings' => $this->weeklyTimingsFromMeta($meta),
            'slug' => Str::slug($application->business_name) . '-' . uniqid(),
        ]);

        $owner->update(['current_restaurant_id' => $restaurant->id]);

        Mail::send('emails.partner-approved', [
            'application' => $application,
            'type' => 'restaurant',
            'email' => $application->contact_email,
            'password' => 'Use mobile OTP login in the app',
        ], function ($mail) use ($application) {
            $mail->to($application->contact_email)
                ->subject('Congratulations! Your Restaurant Application is Approved - FoodFlow');
        });
    }

    protected function approveDriverApplication(PartnerApplication $application): void
    {
        $bankDetails = json_decode($application->bank_details ?? '[]', true);
        $bankDetails = is_array($bankDetails) ? $bankDetails : [];
        $meta = is_array($application->onboarding_meta) ? $application->onboarding_meta : [];

        $driver = User::create([
            'name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'password' => $application->password,
            'is_active' => true,
            'vehicle_type' => $application->vehicle_type,
            'vehicle_number' => $application->vehicle_number,
            'license_number' => $application->license_number,
            'address' => $application->address,
            'delivery_area_id' => $application->area_id,
            'latitude' => $application->latitude,
            'longitude' => $application->longitude,
            'account_holder_name' => $bankDetails['holder_name'] ?? null,
            'bank_name' => $bankDetails['bank_name'] ?? null,
            'account_number' => $bankDetails['account_number'] ?? null,
            'ifsc_code' => $bankDetails['ifsc'] ?? null,
            'upi_id' => $bankDetails['upi_id'] ?? null,
        ]);
        $driver->assignRole('delivery_partner');

        Mail::send('emails.partner-approved', [
            'application' => $application,
            'type' => 'driver',
            'email' => $application->email,
            'password' => 'Use mobile OTP login in the app',
        ], function ($mail) use ($application) {
            $mail->to($application->email)
                ->subject('Congratulations! Your Delivery Partner Application is Approved - FoodFlow');
        });
    }

    protected function encodeCuisine(string $value): ?string
    {
        $items = collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return empty($items) ? null : json_encode($items);
    }

    protected function normalizePhone(?string $phone): string
    {
        return PhoneNumber::normalize(
            $phone,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
    }

    protected function normalizeRestaurantTime(?string $time): ?string
    {
        if (!filled($time)) {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function weeklyTimingsFromMeta(array $meta): array
    {
        $timings = Restaurant::getDefaultWeeklyTimings();
        $weeklyOff = collect(explode(',', (string) ($meta['weekly_off'] ?? '')))
            ->map(fn ($day) => strtolower(trim($day)))
            ->filter()
            ->values();

        $openTime = $this->normalizeRestaurantTime($meta['opening_time'] ?? null) ?? '09:00';
        $closeTime = $this->normalizeRestaurantTime(
            $meta['secondary_closing_time'] ?? $meta['closing_time'] ?? null
        ) ?? '22:00';
        $breakStart = $this->normalizeRestaurantTime($meta['closing_time'] ?? null);
        $breakEnd = $this->normalizeRestaurantTime($meta['secondary_opening_time'] ?? null);

        foreach ($timings as $day => $config) {
            $short = substr($day, 0, 3);
            $timings[$day]['is_open'] = !$weeklyOff->contains($short);
            $timings[$day]['open_time'] = $openTime;
            $timings[$day]['close_time'] = $closeTime;
            $timings[$day]['break_start'] = $breakStart;
            $timings[$day]['break_end'] = $breakEnd;
        }

        return $timings;
    }

    protected function resolveAreaFromCoordinates(float $latitude, float $longitude): DeliveryArea
    {
        $area = $this->deliveryAreaResolver->resolve($latitude, $longitude);

        if (! $area) {
            throw ValidationException::withMessages([
                'latitude' => 'No active delivery zone matched the current location.',
            ]);
        }

        return $area;
    }
}
