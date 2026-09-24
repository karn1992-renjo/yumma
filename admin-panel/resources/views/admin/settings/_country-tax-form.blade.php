@php
    $countryCode = old($prefix . '_country_code', $profile?->country_code);
    $countryName = old($prefix . '_country_name', $profile?->country_name);
    $currencyCode = old($prefix . '_currency_code', $profile?->currency_code);
    $taxName = old($prefix . '_tax_name', $profile?->tax_name);
    $rate = old($prefix . '_rate', data_get($profile?->config, 'rate'));
    $includesFees = old($prefix . '_fees', data_get($profile?->config, 'taxable_base_includes_fees', false));
    $isActive = old($prefix . '_active', $profile?->is_active ?? true);
    $formId = 'country-tax-form-' . $prefix;
@endphp

<div id="{{ $formId }}" data-country-tax-form>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Country</label>
            @if($profile)
                <input type="text" class="form-control" value="{{ $profile->country_name }} ({{ $profile->country_code }})" disabled>
                <input type="hidden" name="country_code" value="{{ $profile->country_code }}">
                <input type="hidden" name="country_name" value="{{ $profile->country_name }}">
            @else
                <select name="country_code" class="form-select" data-country-select>
                    <option value="">Select a country</option>
                    @foreach($countryDefaults as $code => $info)
                        <option value="{{ $code }}" data-name="{{ $info['name'] }}" data-currency="{{ $info['currency'] }}" data-tax-name="{{ $info['tax_name'] }}" data-rate="{{ $info['rate'] }}" @selected($countryCode === $code)>{{ $info['name'] }} ({{ $code }})</option>
                    @endforeach
                    <option value="__custom" @selected($countryCode && ! isset($countryDefaults[$countryCode]))>Other (type manually)</option>
                </select>
                <input type="text" name="country_name" class="form-control mt-2" data-country-name placeholder="Country name" value="{{ $countryName }}">
            @endif
        </div>
        <div class="col-md-3">
            <label class="form-label">Currency</label>
            <input type="text" name="currency_code" class="form-control" data-currency-code maxlength="3" placeholder="e.g. GBP" value="{{ $currencyCode }}" @disabled($profile)>
        </div>
        <div class="col-md-3">
            <label class="form-label">Tax name</label>
            <input type="text" name="tax_name" class="form-control" data-tax-name placeholder="e.g. VAT" value="{{ $taxName }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Rate (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" data-rate value="{{ $rate }}">
            <div class="form-text">Applied flat on the item subtotal (and fees, if checked below).</div>
        </div>
        <div class="col-md-8 d-flex align-items-end gap-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="taxable_base_includes_fees" id="{{ $formId }}-fees" value="1" @checked($includesFees)>
                <label class="form-check-label" for="{{ $formId }}-fees">Also tax delivery/platform fees</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="{{ $formId }}-active" value="1" @checked($isActive)>
                <label class="form-check-label" for="{{ $formId }}-active">Active</label>
            </div>
        </div>
    </div>
</div>

@once
<script>
document.addEventListener('change', function (e) {
    const select = e.target.closest('[data-country-select]');
    if (! select) return;
    const wrap = select.closest('[data-country-tax-form]');
    const opt = select.selectedOptions[0];
    if (! opt || opt.value === '__custom' || opt.value === '') return;

    const nameInput = wrap.querySelector('[data-country-name]');
    const currencyInput = wrap.querySelector('[data-currency-code]');
    const taxNameInput = wrap.querySelector('[data-tax-name]');
    const rateInput = wrap.querySelector('[data-rate]');

    if (nameInput) nameInput.value = opt.dataset.name || '';
    if (currencyInput) currencyInput.value = opt.dataset.currency || '';
    if (taxNameInput) taxNameInput.value = opt.dataset.taxName || '';
    if (rateInput) rateInput.value = opt.dataset.rate || '';
});
</script>
@endonce
