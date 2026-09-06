@php
    $gigTitle = old('title', $gig?->title);
    $gigDescription = old('description', $gig?->description);
    $gigAreaId = old('area_id', $gig?->area_id);
    $gigCapacity = old('capacity', $gig?->capacity ?? 1);
    $gigDate = old('date', $gig?->date?->format('Y-m-d'));
    $gigStart = old('start_time', optional($gig?->start_time)->format('H:i'));
    $gigEnd = old('end_time', optional($gig?->end_time)->format('H:i'));
    $gigStatus = old('status', $gig?->status ?? 'available');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label">Slot Title</label>
        <input type="text" name="title" class="form-control" value="{{ $gigTitle }}" placeholder="Dinner Peak Slot" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Delivery Area</label>
        <select name="area_id" id="gig-area" class="form-select" required>
            <option value="">Select area</option>
            @foreach($deliveryAreas as $area)
                <option value="{{ $area->id }}" @selected((string) $gigAreaId === (string) $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Describe the slot, expected demand, or area notes">{{ $gigDescription }}</textarea>
    </div>
    <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" id="gig-date" class="form-control" value="{{ $gigDate }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Start Time</label>
        <input type="time" name="start_time" id="gig-start-time" class="form-control" value="{{ $gigStart }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">End Time</label>
        <input type="time" name="end_time" class="form-control" value="{{ $gigEnd }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Driver Capacity</label>
        <input type="number" min="1" name="capacity" class="form-control" value="{{ $gigCapacity }}" required>
        @if($gig)
            <div class="form-text">{{ $gig->booked_count }} active booking(s)</div>
        @endif
    </div>

    @if($gig)
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                @foreach(['available', 'booked', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected($gigStatus === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">Base Pay</label>
        <input type="number" step="0.01" min="0" name="base_pay" class="form-control" value="{{ old('base_pay', $gig?->base_pay ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Order Incentive</label>
        <input type="number" step="0.01" min="0" name="order_incentive" class="form-control" value="{{ old('order_incentive', $gig?->order_incentive ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Login Incentive</label>
        <input type="number" step="0.01" min="0" name="login_incentive" class="form-control" value="{{ old('login_incentive', $gig?->login_incentive ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Minimum Login Minutes</label>
        <input type="number" min="0" name="min_login_minutes" class="form-control" value="{{ old('min_login_minutes', $gig?->min_login_minutes ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Minimum Orders Delivered</label>
        <input type="number" min="0" name="min_orders_required" class="form-control" value="{{ old('min_orders_required', $gig?->min_orders_required ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Max Cancellations Allowed</label>
        <input type="number" min="0" name="max_cancellations_allowed" class="form-control" value="{{ old('max_cancellations_allowed', $gig?->max_cancellations_allowed ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Auto Pricing</label>
        <select name="auto_pricing_enabled" class="form-select">
            <option value="1" @selected((string) old('auto_pricing_enabled', $gig?->auto_pricing_enabled ?? 1) === '1')>Enabled</option>
            <option value="0" @selected((string) old('auto_pricing_enabled', $gig?->auto_pricing_enabled ?? 1) === '0')>Disabled</option>
        </select>
    </div>
    <div class="col-12">
        <button type="button" id="gig-forecast-autofill" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-magic me-1"></i> Autofill from forecast
        </button>
        <span id="gig-forecast-status" class="form-text ms-2"></span>
    </div>
    <div class="col-md-4">
        <label class="form-label">Surge Multiplier</label>
        <input type="number" step="0.01" min="1" max="10" name="surge_multiplier" id="gig-surge-multiplier" class="form-control" value="{{ old('surge_multiplier', $gig?->surge_multiplier ?? 1) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Demand Score</label>
        <input type="number" step="0.01" min="0" max="100" name="demand_score" id="gig-demand-score" class="form-control" value="{{ old('demand_score', $gig?->demand_score ?? 0) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Forecasted Orders</label>
        <input type="number" min="0" name="forecasted_orders" id="gig-forecasted-orders" class="form-control" value="{{ old('forecasted_orders', $gig?->forecasted_orders ?? 0) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Recommended Capacity</label>
        <input type="number" min="1" name="recommended_capacity" id="gig-recommended-capacity" class="form-control" value="{{ old('recommended_capacity', $gig?->recommended_capacity) }}" placeholder="Optional">
    </div>
    <div class="col-12">
        <label class="form-label">Terms & Conditions</label>
        <textarea name="terms_conditions" class="form-control" rows="4" placeholder="Explain the payout condition, login requirement, order target, and cancellation rules">{{ old('terms_conditions', $gig?->terms_conditions) }}</textarea>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $gig ? 'Update Gig Slot' : 'Create Gig Slot' }}</button>
        <a href="{{ route('admin.gigs.index') }}" class="btn btn-light border">Cancel</a>
    </div>
</div>

<script>
(function () {
    var areaEl = document.getElementById('gig-area');
    var dateEl = document.getElementById('gig-date');
    var startEl = document.getElementById('gig-start-time');
    var demandEl = document.getElementById('gig-demand-score');
    var forecastedEl = document.getElementById('gig-forecasted-orders');
    var capacityEl = document.getElementById('gig-recommended-capacity');
    var surgeEl = document.getElementById('gig-surge-multiplier');
    var statusEl = document.getElementById('gig-forecast-status');
    var button = document.getElementById('gig-forecast-autofill');
    if (!button) return;

    function setStatus(text, isError) {
        statusEl.textContent = text;
        statusEl.className = 'form-text ms-2' + (isError ? ' text-danger' : ' text-success');
    }

    function autofill() {
        var areaId = areaEl.value;
        var date = dateEl.value;
        var start = startEl.value;

        if (!date || !start) {
            setStatus('Pick a date and start time first.', true);
            return;
        }

        var hour = parseInt(start.split(':')[0], 10);
        var params = new URLSearchParams({ date: date, hour: hour });
        if (areaId) params.set('area_id', areaId);

        setStatus('Looking up forecast…', false);

        fetch('{{ route('admin.gigs.forecast-lookup') }}?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) {
                    setStatus('Could not look up forecast.', true);
                    return;
                }
                if (!result.found) {
                    setStatus('No forecast computed yet for this slot — forecasts only cover today/tomorrow.', true);
                    return;
                }
                demandEl.value = result.data.demand_score;
                forecastedEl.value = result.data.forecasted_orders;
                capacityEl.value = result.data.recommended_capacity;
                surgeEl.value = result.data.surge_multiplier;
                setStatus('Filled from forecast — feel free to adjust.', false);
            })
            .catch(function () {
                setStatus('Could not look up forecast.', true);
            });
    }

    button.addEventListener('click', autofill);
})();
</script>
