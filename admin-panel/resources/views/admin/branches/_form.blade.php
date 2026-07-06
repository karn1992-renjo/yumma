@csrf
@if(isset($branch))
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Branch Name</label>
        <input name="name" class="form-control" value="{{ old('name', $branch->name ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Branch Code</label>
        <input name="code" class="form-control" value="{{ old('code', $branch->code ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Branch Logo</label>
        <input type="file" name="logo" class="form-control" accept="image/*">
    </div>

    <div class="col-md-4">
        <label class="form-label">Owner Name</label>
        <input name="owner_name" class="form-control" value="{{ old('owner_name', $branch->owner_name ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Email</label>
        <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email', $branch->owner_email ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Phone</label>
        <input name="owner_phone" class="form-control" value="{{ old('owner_phone', $branch->owner_phone ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Owner Password</label>
        <input type="password" name="owner_password" class="form-control" {{ isset($branch) ? '' : 'required' }}>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $branch->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Settlement Cycle</label>
        <select name="settlement_cycle" class="form-select" required>
            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly'] as $value => $label)
                <option value="{{ $value }}" @selected(old('settlement_cycle', $branch->settlement_cycle ?? 'weekly') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Country</label>
        <input name="country" class="form-control" value="{{ old('country', $branch->country ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">State</label>
        <input name="state" class="form-control" value="{{ old('state', $branch->state ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">City</label>
        <input name="city" class="form-control" value="{{ old('city', $branch->city ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Address</label>
        <input name="address" class="form-control" value="{{ old('address', $branch->address ?? '') }}">
    </div>

    <div class="col-12">
        <label class="form-label">Delivery Zones</label>
        @php
            $selectedDeliveryAreaIds = collect(old('delivery_area_ids', $selectedDeliveryAreaIds ?? []))->map(fn ($id) => (int) $id)->all();
        @endphp
        <select name="delivery_area_ids[]" class="form-select" multiple size="8" {{ isset($branch) ? '' : 'required' }}>
            @foreach($deliveryAreas ?? [] as $deliveryArea)
                @php
                    $assignment = ($assignedDeliveryAreas ?? collect())->get($deliveryArea->id);
                    $assignedToCurrentBranch = isset($branch) && $assignment && (int) $assignment->branch_id === (int) $branch->id;
                @endphp
                <option value="{{ $deliveryArea->id }}" @selected(in_array($deliveryArea->id, $selectedDeliveryAreaIds, true))>
                    {{ $deliveryArea->name }}
                    - {{ ucfirst($deliveryArea->area_type) }}
                    @if($deliveryArea->area_type === 'circle')
                        ({{ $deliveryArea->radius_km }} km)
                    @endif
                    @if($assignment)
                        - {{ $assignedToCurrentBranch ? 'assigned to this branch' : 'assigned to ' . ($assignment->branch?->name ?? 'another branch') }}
                    @endif
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ isset($branch) ? 'Selected zones will become this branch territory. Unselected current zones will be removed from this branch.' : 'Select one or more delivery zones for this branch.' }}</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">GST Number</label>
        <input name="gst_number" class="form-control" value="{{ old('gst_number', $branch->gst_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">PAN Number</label>
        <input name="pan_number" class="form-control" value="{{ old('pan_number', $branch->pan_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Trade License</label>
        <input name="trade_license" class="form-control" value="{{ old('trade_license', $branch->trade_license ?? '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Branch Share %</label>
        <input type="number" step="0.01" min="0" max="100" name="branch_share_percent" class="form-control" value="{{ old('branch_share_percent', $branch->branch_share_percent ?? 70) }}" required>
        <div class="form-text">Share of the restaurant earning commission credited to this branch.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Admin Share</label>
        <input class="form-control" value="{{ number_format(100 - (float) old('branch_share_percent', $branch->branch_share_percent ?? 70), 2) }}%" disabled>
        <div class="form-text">Calculated automatically as the remainder.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Account Holder</label>
        <input name="bank_details[account_holder_name]" class="form-control" value="{{ old('bank_details.account_holder_name', $branch->bank_details['account_holder_name'] ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Bank Name</label>
        <input name="bank_details[bank_name]" class="form-control" value="{{ old('bank_details.bank_name', $branch->bank_details['bank_name'] ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Account Number</label>
        <input name="bank_details[account_number]" class="form-control" value="{{ old('bank_details.account_number', $branch->bank_details['account_number'] ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">IFSC / UPI</label>
        <input name="bank_details[ifsc_code]" class="form-control" value="{{ old('bank_details.ifsc_code', $branch->bank_details['ifsc_code'] ?? '') }}">
    </div>

    @unless(isset($branch))
        <div class="col-12"><hr></div>
        <div class="col-md-4">
            <label class="form-label">Optional Manager Name</label>
            <input name="manager_name" class="form-control" value="{{ old('manager_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Optional Manager Email</label>
            <input type="email" name="manager_email" class="form-control" value="{{ old('manager_email') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Optional Manager Phone</label>
            <input name="manager_phone" class="form-control" value="{{ old('manager_phone') }}">
        </div>
    @endunless

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $branch->notes ?? '') }}</textarea>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger mt-3">
        {{ $errors->first() }}
    </div>
@endif

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ isset($branch) ? 'Update Branch' : 'Create Branch' }}</button>
    <a href="{{ route('admin.branches.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
