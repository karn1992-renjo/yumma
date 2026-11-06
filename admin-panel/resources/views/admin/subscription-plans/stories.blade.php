@extends('layouts.admin')

@php
    $editing = isset($plan) && $plan;
    $durationDays = (int) old('duration_days', $editing ? ($plan->validity_days ?: 30) : 30);
    $presets = [7 => '7 days', 15 => '15 days', 30 => '1 month', 90 => '3 months', 180 => '6 months', 365 => '1 year'];
    $isPreset = array_key_exists($durationDays, $presets);
    $currency = old('currency', $editing ? ($plan->currency ?: 'INR') : (\App\Models\AppSetting::getValue('currency_code', 'INR') ?: 'INR'));
@endphp

@section('title', $editing ? 'Edit Stories Plan' : 'New Stories Plan')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">{{ $editing ? 'Edit Stories Plan' : 'New Stories Plan' }}</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.subscription-plans.index') }}">Subscription Plans</a></li>
                    <li class="breadcrumb-item active">Stories plan</li>
                </ol>
            </div>
            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-light"><i class="fas fa-arrow-left me-2"></i> Back</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0 fw-bold">Stories plan (pay as you go)</h5></div>
                <div class="p-4">
                    <p class="text-muted small mb-4">A restaurant on commission billing buys this on its own, just to post Stories. One payment buys one period; nothing is charged again, and the restaurant pays for the next period or upgrades to a higher stories plan.</p>

                    <form action="{{ $editing ? route('admin.subscription-plans.stories.update', $plan) : route('admin.subscription-plans.stories.store') }}" method="POST" id="storiesPlanForm">
                        @csrf
                        @if($editing) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Plan name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $plan->name ?? '') }}" required placeholder="e.g., Stories Monthly">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">How long does one payment last? <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2 mb-2" id="durationPresets">
                                @foreach($presets as $days => $label)
                                    <button type="button" class="btn btn-sm {{ $durationDays === $days ? 'btn-primary' : 'btn-outline-secondary' }}" data-days="{{ $days }}">{{ $label }}</button>
                                @endforeach
                                <button type="button" class="btn btn-sm {{ $isPreset ? 'btn-outline-secondary' : 'btn-primary' }}" data-days="custom">Custom</button>
                            </div>
                            <div class="input-group" style="max-width:260px">
                                <input type="number" name="duration_days" id="durationDays" min="1" max="3650" class="form-control @error('duration_days') is-invalid @enderror" value="{{ $durationDays }}" required>
                                <span class="input-group-text">days</span>
                            </div>
                            @error('duration_days') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                                <input type="number" name="price" id="price" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plan->price ?? '') }}" required>
                                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Compare-at price <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="number" name="compare_at_price" id="compareAt" step="0.01" min="0" class="form-control @error('compare_at_price') is-invalid @enderror" value="{{ old('compare_at_price', $plan->compare_at_price ?? '') }}">
                                @error('compare_at_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">Shown struck through; must be higher than the price.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input type="text" name="currency" maxlength="3" class="form-control text-uppercase @error('currency') is-invalid @enderror" value="{{ $currency }}">
                                @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Stories allowed per day <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="number" name="stories_per_day" id="perDay" min="1" max="1000" class="form-control @error('stories_per_day') is-invalid @enderror" value="{{ old('stories_per_day', $plan->stories_per_day ?? '') }}" placeholder="Unlimited">
                                @error('stories_per_day') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">The daily limit for the restaurant. Leave empty for no limit.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Each story stays live for <span class="text-muted fw-normal">(hours)</span></label>
                                <input type="number" name="story_duration_hours" id="storyHours" min="1" max="720" class="form-control @error('story_duration_hours') is-invalid @enderror" value="{{ old('story_duration_hours', $plan->story_duration_hours ?? '') }}" placeholder="24 (default)">
                                @error('story_duration_hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">How long one story is visible to customers before it expires.</small>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="story_menu_link" id="menuLink" value="1" {{ old('story_menu_link', $editing ? $plan->story_menu_link : false) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="menuLink">Allow linking a menu item with a button (CTA)</label>
                            <div class="small text-muted">The restaurant can attach a dish to a story. Customers see it on the story with an "Order now" button that opens that dish.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea name="description" rows="3" maxlength="1000" class="form-control @error('description') is-invalid @enderror" placeholder="e.g., Post unlimited Stories and reach customers browsing the app.">{{ old('description', $plan->description ?? '') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Sort order</label>
                                <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
                                <small class="text-muted">Lower numbers are listed first.</small>
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $editing ? $plan->is_active : true) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="isActive">Active (restaurants can buy it)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $editing ? 'Save changes' : 'Create stories plan' }}</button>
                            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0 fw-bold">What restaurants will see</h5></div>
                <div class="p-4">
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong id="pvName">{{ old('name', $plan->name ?? 'Stories plan') ?: 'Stories plan' }}</strong>
                            <span class="text-nowrap"><span id="pvCompare" class="text-muted text-decoration-line-through me-1"></span><b id="pvPrice"></b></span>
                        </div>
                        <div class="text-muted small mt-1" id="pvDuration"></div>
                        <div class="small mt-2" id="pvDesc"></div>
                        <div class="mt-2"><span class="badge bg-warning text-dark">Stories included</span> <span class="badge bg-light text-dark border" id="pvPerDayLimit"></span> <span class="badge bg-light text-dark border" id="pvLive"></span> <span class="badge bg-success d-none" id="pvLink">Menu item + button</span></div>
                    </div>
                    <div class="small text-muted mt-3" id="pvPerDay"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('storiesPlanForm');
        var days = document.getElementById('durationDays');
        var price = document.getElementById('price');
        var compare = document.getElementById('compareAt');
        var name = form.querySelector('[name=name]');
        var desc = form.querySelector('[name=description]');
        var currency = form.querySelector('[name=currency]');
        var presets = document.querySelectorAll('#durationPresets button');

        function label(n) {
            n = parseInt(n, 10) || 0;
            if (n === 365) return '1 year';
            if (n > 0 && n % 30 === 0 && n <= 360) return (n / 30) + (n === 30 ? ' month' : ' months') + ' (' + n + ' days)';
            return n + (n === 1 ? ' day' : ' days');
        }
        function money(v) {
            var c = (currency.value || 'INR').toUpperCase();
            return c + ' ' + (parseFloat(v) || 0).toFixed(2);
        }
        function paint() {
            var d = parseInt(days.value, 10) || 0, p = parseFloat(price.value) || 0, cp = parseFloat(compare.value) || 0;
            document.getElementById('pvName').textContent = name.value || 'Stories plan';
            document.getElementById('pvPrice').textContent = money(p);
            document.getElementById('pvCompare').textContent = cp > p ? money(cp) : '';
            document.getElementById('pvDuration').textContent = 'One payment, valid for ' + label(d) + '. No auto-renewal.';
            document.getElementById('pvDesc').textContent = desc.value;
            document.getElementById('pvPerDay').textContent = d > 0 && p > 0 ? 'About ' + money(p / d) + ' per day.' : '';
            var pd = parseInt(document.getElementById('perDay').value, 10) || 0, hrs = parseInt(document.getElementById('storyHours').value, 10) || 24;
            document.getElementById('pvPerDayLimit').textContent = pd > 0 ? pd + (pd === 1 ? ' story' : ' stories') + ' / day' : 'Unlimited stories';
            document.getElementById('pvLive').textContent = 'Live for ' + hrs + 'h';
            document.getElementById('pvLink').classList.toggle('d-none', !document.getElementById('menuLink').checked);
            presets.forEach(function (b) {
                var on = b.dataset.days === String(d) || (b.dataset.days === 'custom' && !Array.prototype.some.call(presets, function (x) { return x.dataset.days === String(d); }));
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('btn-outline-secondary', !on);
            });
        }
        presets.forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.dataset.days === 'custom') { days.focus(); days.select(); return; }
                days.value = b.dataset.days;
                paint();
            });
        });
        [days, price, compare, name, desc, currency, document.getElementById('perDay'), document.getElementById('storyHours'), document.getElementById('menuLink')].forEach(function (el) { el.addEventListener('input', paint); });
        paint();
    })();
</script>
@endsection
