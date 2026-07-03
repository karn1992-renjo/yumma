@php
    $meta = old() ? old() : (is_array($application->onboarding_meta ?? null) ? $application->onboarding_meta : []);
    if (!is_array($meta)) {
        $meta = [];
    }
    $bankDetails = old() ? old() : json_decode($application->bank_details ?? '[]', true);
    if (!is_array($bankDetails)) {
        $bankDetails = [];
    }
    $selectedType = old('partner_type', $application->partner_type ?: 'restaurant');
    $selectedAreaId = old('area_id', $application->area_id);
    $selectedArea = $deliveryAreas->firstWhere('id', (int) $selectedAreaId);
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Partner Type</label>
                        <select name="partner_type" id="partner_type" class="form-select" required>
                            <option value="restaurant" {{ $selectedType === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                            <option value="driver" {{ $selectedType === 'driver' ? 'selected' : '' }}>Driver</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" required>
                            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $application->status ?: 'pending') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div id="restaurant-fields" class="mt-4">
            <div class="table-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Restaurant Registration Workflow</h5>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Restaurant Name</label>
                            <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $application->business_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Restaurant Email</label>
                            <input type="email" name="business_email" class="form-control" value="{{ old('business_email', $application->business_email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Restaurant Phone</label>
                            <input type="text" name="business_phone" class="form-control" value="{{ old('business_phone', $application->business_phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Owner Name</label>
                            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $meta['owner_name'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $application->contact_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation</label>
                            <input type="text" name="contact_designation" class="form-control" value="{{ old('contact_designation', $application->contact_designation) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $application->contact_email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $application->contact_phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $application->city) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="pincode" class="form-control" value="{{ old('pincode', $application->pincode) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3">{{ old('address', $application->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Landmark</label>
                            <input type="text" name="landmark" class="form-control" value="{{ old('landmark', $meta['landmark'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cuisine</label>
                            <input type="text" name="cuisine" class="form-control" value="{{ old('cuisine', implode(', ', json_decode($application->cuisine ?? '[]', true) ?? [])) }}" placeholder="Fast Food, Biryani, Cafe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery Zone</label>
                            <select name="area_id" class="form-select d-none" data-auto-zone-id="restaurant">
                                <option value="">Not assigned</option>
                                @foreach ($deliveryAreas as $area)
                                    <option value="{{ $area->id }}" {{ (string) $selectedAreaId === (string) $area->id ? 'selected' : '' }}>
                                        {{ $area->name }}{{ $area->radius_km ? ' · ' . $area->radius_km . ' km' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-control bg-light d-flex align-items-center justify-content-between mt-2" style="min-height: 48px;">
                                <span data-auto-zone-name="restaurant">{{ $selectedArea?->name ?? 'Use current location to detect zone' }}</span>
                                <span class="badge bg-warning text-dark">Auto</span>
                            </div>
                            <div class="form-text">Auto-assigned from current coordinates for restaurant coverage and review mapping.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" class="form-control" value="{{ old('latitude', $application->latitude) }}" data-auto-zone-lat="restaurant">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" class="form-control" value="{{ old('longitude', $application->longitude) }}" data-auto-zone-lng="restaurant">
                        </div>
                        <div class="col-12">
                            <button
                                type="button"
                                class="btn btn-outline-dark btn-sm"
                                data-auto-zone-trigger="restaurant"
                            >
                                Use Current Location
                            </button>
                            <span class="small text-muted ms-2" data-auto-zone-status="restaurant">Zone will be fetched automatically from the current location.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Pure Veg</label>
                            <select name="is_pure_veg" class="form-select">
                                <option value="0" {{ !old('is_pure_veg', $application->is_pure_veg) ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('is_pure_veg', $application->is_pure_veg) ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">AI Verification</label>
                            <select name="ai_verification_enabled" class="form-select">
                                <option value="1" {{ old('ai_verification_enabled', $meta['ai_verification_enabled'] ?? 1) ? 'selected' : '' }}>Enabled</option>
                                <option value="0" {{ !old('ai_verification_enabled', $meta['ai_verification_enabled'] ?? 1) ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Timings, Delivery, Menu, Commission</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Opening Time</label>
                            <input type="text" name="opening_time" class="form-control" value="{{ old('opening_time', $meta['opening_time'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Closing Time</label>
                            <input type="text" name="closing_time" class="form-control" value="{{ old('closing_time', $meta['closing_time'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Second Shift Start</label>
                            <input type="text" name="secondary_opening_time" class="form-control" value="{{ old('secondary_opening_time', $meta['secondary_opening_time'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Second Shift End</label>
                            <input type="text" name="secondary_closing_time" class="form-control" value="{{ old('secondary_closing_time', $meta['secondary_closing_time'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Weekly Off</label>
                            <input type="text" name="weekly_off" class="form-control" value="{{ old('weekly_off', $meta['weekly_off'] ?? '') }}" placeholder="Mon, Tue">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Restaurant Categories</label>
                            <input type="text" name="restaurant_categories" class="form-control" value="{{ old('restaurant_categories', $meta['restaurant_categories'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Menu Summary</label>
                            <input type="text" name="menu_summary" class="form-control" value="{{ old('menu_summary', $meta['menu_summary'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimum Order Value</label>
                            <input type="number" step="0.01" name="minimum_order_value" class="form-control" value="{{ old('minimum_order_value', $meta['minimum_order_value'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Free Delivery Threshold</label>
                            <input type="number" step="0.01" name="free_delivery_threshold" class="form-control" value="{{ old('free_delivery_threshold', $meta['free_delivery_threshold'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Delivery Charges</label>
                            <input type="number" step="0.01" name="delivery_charges" class="form-control" value="{{ old('delivery_charges', $meta['delivery_charges'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Packaging Charge</label>
                            <input type="number" step="0.01" name="packaging_charge" class="form-control" value="{{ old('packaging_charge', $meta['packaging_charge'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">GST Percentage</label>
                            <input type="number" step="0.01" name="gst_percentage" class="form-control" value="{{ old('gst_percentage', $meta['gst_percentage'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Handling Fee</label>
                            <input type="number" step="0.01" name="handling_fee" class="form-control" value="{{ old('handling_fee', $meta['handling_fee'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Commission Preview</label>
                            <input type="text" name="commission_preview" class="form-control" value="{{ old('commission_preview', $meta['commission_preview'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payout Cycle</label>
                            <input type="text" name="payout_cycle" class="form-control" value="{{ old('payout_cycle', $meta['payout_cycle'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Photo Status</label>
                            <input type="text" name="photo_status" class="form-control" value="{{ old('photo_status', $meta['photo_status'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Document Status</label>
                            <input type="text" name="document_status" class="form-control" value="{{ old('document_status', $meta['document_status'] ?? '') }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Media & Compliance Uploads</h6>
                    <div class="row g-3">
                        @foreach ([
                            'logo_image' => 'Logo Image',
                            'banner_image' => 'Banner Image',
                            'cover_image' => 'Cover Image',
                            'interior_image' => 'Interior Photo',
                            'food_image' => 'Food Photo',
                            'kitchen_image' => 'Kitchen Photo',
                            'gst_certificate' => 'GST Certificate',
                            'fssai_license' => 'FSSAI License',
                            'bank_proof' => 'Bank Proof',
                            'shop_license' => 'Shop License',
                        ] as $field => $label)
                            <div class="col-md-6">
                                <label class="form-label">{{ $label }}</label>
                                <input type="file" name="{{ $field }}" class="form-control">
                                @if(!empty($meta[$field] ?? $application->{$field} ?? null))
                                    <div class="form-text">Current file available</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div id="driver-fields" class="mt-4">
            <div class="table-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Driver Registration Workflow</h5>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $application->full_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $application->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $application->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $application->city) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3">{{ old('address', $application->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery Zone</label>
                            <select name="area_id" class="form-select d-none" data-auto-zone-id="driver">
                                <option value="">Not assigned</option>
                                @foreach ($deliveryAreas as $area)
                                    <option value="{{ $area->id }}" {{ (string) $selectedAreaId === (string) $area->id ? 'selected' : '' }}>
                                        {{ $area->name }}{{ $area->radius_km ? ' · ' . $area->radius_km . ' km' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-control bg-light d-flex align-items-center justify-content-between mt-2" style="min-height: 48px;">
                                <span data-auto-zone-name="driver">{{ $selectedArea?->name ?? 'Use current location to detect zone' }}</span>
                                <span class="badge bg-warning text-dark">Auto</span>
                            </div>
                            <div class="form-text">Auto-assigned from current coordinates for dispatch geo-zone review.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vehicle Type</label>
                            <select name="vehicle_type" class="form-select">
                                @foreach (['bike' => 'Bike', 'scooter' => 'Scooter', 'ev_scooter' => 'EV Scooter', 'bicycle' => 'Bicycle', 'car' => 'Car'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('vehicle_type', $application->vehicle_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vehicle Number</label>
                            <input type="text" name="vehicle_number" class="form-control" value="{{ old('vehicle_number', $application->vehicle_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $application->license_number) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" class="form-control" value="{{ old('latitude', $application->latitude) }}" data-auto-zone-lat="driver">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" class="form-control" value="{{ old('longitude', $application->longitude) }}" data-auto-zone-lng="driver">
                        </div>
                        <div class="col-12">
                            <button
                                type="button"
                                class="btn btn-outline-dark btn-sm"
                                data-auto-zone-trigger="driver"
                            >
                                Use Current Location
                            </button>
                            <span class="small text-muted ms-2" data-auto-zone-status="driver">Zone will be fetched automatically from the current location.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $meta['date_of_birth'] ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <input type="text" name="gender" class="form-control" value="{{ old('gender', $meta['gender'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vehicle Model</label>
                            <input type="text" name="vehicle_model" class="form-control" value="{{ old('vehicle_model', $meta['vehicle_model'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fuel Type</label>
                            <input type="text" name="fuel_type" class="form-control" value="{{ old('fuel_type', $meta['fuel_type'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Landmark</label>
                            <input type="text" name="landmark" class="form-control" value="{{ old('landmark', $meta['landmark'] ?? '') }}">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Permissions, Banking & Documents</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Background Location</label>
                            <select name="background_location_enabled" class="form-select">
                                <option value="1" {{ old('background_location_enabled', $meta['background_location_enabled'] ?? 1) ? 'selected' : '' }}>Granted</option>
                                <option value="0" {{ !old('background_location_enabled', $meta['background_location_enabled'] ?? 1) ? 'selected' : '' }}>Not Granted</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Notifications</label>
                            <select name="notification_permission_enabled" class="form-select">
                                <option value="1" {{ old('notification_permission_enabled', $meta['notification_permission_enabled'] ?? 1) ? 'selected' : '' }}>Granted</option>
                                <option value="0" {{ !old('notification_permission_enabled', $meta['notification_permission_enabled'] ?? 1) ? 'selected' : '' }}>Not Granted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Holder</label>
                            <input type="text" name="bank_holder_name" class="form-control" value="{{ old('bank_holder_name', $bankDetails['holder_name'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $bankDetails['bank_name'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $bankDetails['account_number'] ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IFSC</label>
                            <input type="text" name="bank_ifsc" class="form-control" value="{{ old('bank_ifsc', $bankDetails['ifsc'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">UPI ID</label>
                            <input type="text" name="upi_id" class="form-control" value="{{ old('upi_id', $bankDetails['upi_id'] ?? '') }}">
                        </div>
                        @foreach ([
                            'profile_photo' => 'Profile Photo',
                            'vehicle_image' => 'Vehicle Image',
                            'license_document' => 'License Document',
                            'aadhar_card' => 'Aadhaar Card',
                            'pan_card' => 'PAN Card',
                            'vehicle_rc' => 'Vehicle RC',
                            'insurance_document' => 'Insurance Document',
                        ] as $field => $label)
                            <div class="col-md-6">
                                <label class="form-label">{{ $label }}</label>
                                <input type="file" name="{{ $field }}" class="form-control">
                                @if(!empty($application->{$field}))
                                    <div class="form-text">Current file available</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Workflow Notes</h5>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <label class="form-label">Admin Notes</label>
                    <textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                </div>
                <div class="small text-muted">
                    This admin workflow is fully wired to the same partner application model used by the driver and restaurant apps. Editing here updates the real review payload and approval mapping.
                </div>
            </div>
        </div>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger mt-4">
        <div class="fw-semibold mb-2">Please fix the following issues:</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@push('scripts')
@php
    $deliveryAreaPayload = $deliveryAreas->map(function ($area) {
        return [
            'id' => $area->id,
            'name' => $area->name,
            'description' => $area->description,
            'area_type' => $area->area_type,
            'latitude' => $area->latitude,
            'longitude' => $area->longitude,
            'radius_km' => $area->radius_km,
            'polygon_coordinates' => $area->polygon_coordinates,
        ];
    })->values();
@endphp
<script>
    const deliveryAreas = @json($deliveryAreaPayload);

    function togglePartnerSections() {
        const type = document.getElementById('partner_type').value;
        document.getElementById('restaurant-fields').style.display = type === 'restaurant' ? 'block' : 'none';
        document.getElementById('driver-fields').style.display = type === 'driver' ? 'block' : 'none';
    }

    function degreesToRadians(degrees) {
        return degrees * Math.PI / 180;
    }

    function distanceKm(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371;
        const dLat = degreesToRadians(lat2 - lat1);
        const dLon = degreesToRadians(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(degreesToRadians(lat1)) * Math.cos(degreesToRadians(lat2))
            * Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return earthRadius * c;
    }

    function pointInPolygon(polygon, latitude, longitude) {
        let intersections = 0;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            const current = polygon[i];
            const previous = polygon[j];
            if (
                (current.lat > latitude) !== (previous.lat > latitude) &&
                longitude < ((previous.lng - current.lng) * (latitude - current.lat)) / (previous.lat - current.lat) + current.lng
            ) {
                intersections++;
            }
        }
        return intersections % 2 === 1;
    }

    function areaFootprint(area) {
        if (area.area_type === 'polygon' && Array.isArray(area.polygon_coordinates) && area.polygon_coordinates.length >= 3) {
            let total = 0;
            for (let index = 0; index < area.polygon_coordinates.length; index++) {
                const current = area.polygon_coordinates[index];
                const next = area.polygon_coordinates[(index + 1) % area.polygon_coordinates.length];
                total += current.lat * next.lng;
                total -= next.lat * current.lng;
            }
            const polygonArea = Math.abs(total) / 2;
            return polygonArea > 0 ? polygonArea : Number.MAX_SAFE_INTEGER;
        }

        const radius = Number(area.radius_km);
        return radius > 0 ? radius : Number.MAX_SAFE_INTEGER;
    }

    function resolveArea(latitude, longitude) {
        const containingAreas = deliveryAreas
            .filter((area) => {
                if (area.area_type === 'polygon' && Array.isArray(area.polygon_coordinates) && area.polygon_coordinates.length >= 3) {
                    return pointInPolygon(area.polygon_coordinates, latitude, longitude);
                }

                if (area.latitude === null || area.longitude === null || area.radius_km === null) {
                    return false;
                }

                return distanceKm(latitude, longitude, Number(area.latitude), Number(area.longitude)) <= Number(area.radius_km);
            })
            .sort((left, right) => areaFootprint(left) - areaFootprint(right));

        if (containingAreas.length > 0) {
            return containingAreas[0];
        }

        return deliveryAreas
            .filter((area) => area.latitude !== null && area.longitude !== null)
            .sort((left, right) => {
                const leftDistance = distanceKm(latitude, longitude, Number(left.latitude), Number(left.longitude));
                const rightDistance = distanceKm(latitude, longitude, Number(right.latitude), Number(right.longitude));
                return leftDistance - rightDistance;
            })[0] ?? null;
    }

    function syncAutoZone(scope) {
        const latitudeField = document.querySelector(`[data-auto-zone-lat="${scope}"]`);
        const longitudeField = document.querySelector(`[data-auto-zone-lng="${scope}"]`);
        const zoneField = document.querySelector(`[data-auto-zone-id="${scope}"]`);
        const zoneName = document.querySelector(`[data-auto-zone-name="${scope}"]`);
        const status = document.querySelector(`[data-auto-zone-status="${scope}"]`);
        const latitude = Number(latitudeField?.value);
        const longitude = Number(longitudeField?.value);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            zoneField.value = '';
            zoneName.textContent = 'Use current location to detect zone';
            status.textContent = 'Zone will be fetched automatically from the current location.';
            return;
        }

        const area = resolveArea(latitude, longitude);
        if (!area) {
            zoneField.value = '';
            zoneName.textContent = 'No matching delivery zone';
            status.textContent = 'No active delivery zone matched these coordinates.';
            return;
        }

        zoneField.value = String(area.id);
        zoneName.textContent = area.name;
        status.textContent = `Auto-matched to ${area.name}.`;
    }

    function bindAutoZone(scope) {
        const latitudeField = document.querySelector(`[data-auto-zone-lat="${scope}"]`);
        const longitudeField = document.querySelector(`[data-auto-zone-lng="${scope}"]`);
        const trigger = document.querySelector(`[data-auto-zone-trigger="${scope}"]`);
        const status = document.querySelector(`[data-auto-zone-status="${scope}"]`);

        latitudeField?.addEventListener('input', () => syncAutoZone(scope));
        longitudeField?.addEventListener('input', () => syncAutoZone(scope));

        trigger?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                status.textContent = 'Browser location is not available on this device.';
                return;
            }

            status.textContent = 'Detecting current location...';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitudeField.value = position.coords.latitude.toFixed(6);
                    longitudeField.value = position.coords.longitude.toFixed(6);
                    syncAutoZone(scope);
                },
                () => {
                    status.textContent = 'Could not fetch current location. Check browser location permission.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                }
            );
        });

        syncAutoZone(scope);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const typeField = document.getElementById('partner_type');
        togglePartnerSections();
        typeField.addEventListener('change', togglePartnerSections);
        bindAutoZone('restaurant');
        bindAutoZone('driver');
    });
</script>
@endpush
