@csrf
@if(isset($theatre))
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Theatre Name</label>
        <input name="name" class="form-control" value="{{ old('name', $theatre->name ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Theatre Code</label>
        <input name="code" class="form-control" value="{{ old('code', $theatre->code ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Logo</label>
        <input type="file" name="logo" class="form-control" accept="image/*">
    </div>

    <div class="col-md-4">
        <label class="form-label">Owner Name</label>
        <input name="owner_name" class="form-control" value="{{ old('owner_name', $theatre->owner_name ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Email</label>
        <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email', $theatre->owner_email ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Phone</label>
        <input name="owner_phone" class="form-control" value="{{ old('owner_phone', $theatre->owner_phone ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Password</label>
        <input type="password" name="owner_password" class="form-control" {{ isset($theatre) ? '' : 'required' }}>
        <div class="form-text">{{ isset($theatre) ? 'Leave blank to keep the current password.' : '' }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $theatre->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-4 pt-2">
            <input type="hidden" name="aisle_handoff_only" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="aisle_handoff_only" value="1" @checked(old('aisle_handoff_only', $theatre->aisle_handoff_only ?? false))>
            <label class="form-check-label">Aisle hand-off only</label>
        </div>
        <div class="form-text">Runners hand off at the row/aisle end instead of walking into a dark, playing auditorium.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Country</label>
        <input name="country" class="form-control" value="{{ old('country', $theatre->country ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">State</label>
        <input name="state" class="form-control" value="{{ old('state', $theatre->state ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">City</label>
        <input name="city" class="form-control" value="{{ old('city', $theatre->city ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Address</label>
        <input name="address" class="form-control" value="{{ old('address', $theatre->address ?? '') }}">
    </div>

    <div class="col-12"><hr></div>
    <div class="col-md-4">
        <label class="form-label">Latitude</label>
        <input type="number" step="0.0000001" name="latitude" class="form-control" value="{{ old('latitude', $theatre->latitude ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Longitude</label>
        <input type="number" step="0.0000001" name="longitude" class="form-control" value="{{ old('longitude', $theatre->longitude ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Geofence Radius (meters)</label>
        <input type="number" min="30" max="1000" name="geofence_radius_meters" class="form-control" value="{{ old('geofence_radius_meters', $theatre->geofence_radius_meters ?? 120) }}" required>
        <div class="form-text">How close a customer's GPS must be to this point before they can order to a seat here. GPS is often 20-50m+ off indoors -- keep this pragmatic, not tight.</div>
    </div>

    <div class="col-12"><hr></div>
    <div class="col-12">
        <label class="form-label fw-bold"><i class="fas fa-tags me-1 text-primary"></i>Theatre Amenities & Tags (BookMyShow Badges)</label>
        <p class="text-muted small mb-2">Select amenities displayed to customers in the app and search results.</p>
        @php
            $currentTags = old('tags', $theatre->tags ?? ['Dolby Atmos 7.1', '4K Laser Projection', 'Recliner Seats', 'M-Ticket', 'Food & Beverage']);
            if (!is_array($currentTags)) {
                $currentTags = is_string($currentTags) ? array_map('trim', explode(',', $currentTags)) : [];
            }
            $availableAmenities = [
                'M-Ticket' => 'fa-ticket-alt',
                'Food & Beverage' => 'fa-utensils',
                'Dolby Atmos 7.1' => 'fa-volume-up',
                '4K Laser Projection' => 'fa-video',
                'IMAX' => 'fa-film',
                'Recliner Seats' => 'fa-couch',
                'Wheelchair Accessible' => 'fa-wheelchair',
                'Parking Available' => 'fa-parking',
            ];
            $otherTags = array_diff($currentTags, array_keys($availableAmenities));
        @endphp
        <div class="row g-2 mb-3">
            @foreach($availableAmenities as $amenity => $icon)
                <div class="col-md-3 col-6">
                    <div class="form-check p-2 border rounded bg-light">
                        <input class="form-check-input ms-1 me-2" type="checkbox" name="tags[]" value="{{ $amenity }}" id="tag_{{ Str::slug($amenity) }}"
                            @checked(in_array($amenity, $currentTags))>
                        <label class="form-check-label fw-semibold" for="tag_{{ Str::slug($amenity) }}">
                            <i class="fas {{ $icon }} text-secondary me-1"></i>{{ $amenity }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>

        <label class="form-label small text-muted">Additional Custom Tags (comma-separated):</label>
        <input name="custom_tags" class="form-control" placeholder="e.g. Laser 3D, Dolby 7.1, Valet Parking" value="{{ old('custom_tags', implode(', ', $otherTags)) }}">
        <div class="form-text">These tags appear as BookMyShow pill badges on theatre cards and seat picker screens.</div>
    </div>

    <div class="col-12"><hr></div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $theatre->notes ?? '') }}</textarea>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
    </div>
@endif

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ isset($theatre) ? 'Update Theatre' : 'Create Theatre' }}</button>
    <a href="{{ route('admin.theatres.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
