@extends('layouts.admin')

@section('title', 'US Sales Tax')
@section('header', 'US Sales Tax')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-flag-usa"></i> Marketplace-facilitator sales tax</span>
            <h1>US Sales Tax</h1>
            <p>Under marketplace-facilitator law, the platform collects and remits sales tax on orders it facilitates — restaurants don't need their own registration for this. Set each restaurant's <strong>state</strong> in its own profile, and the matching rate below applies automatically. States left blank charge no sales tax.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-warning">
        <strong>Scope note:</strong> these are STATE base rates only. Many US cities/counties add their own
        district sales tax on top (e.g. a Los Angeles order is ~9.5%+, not California's 7.25% state rate alone).
        This platform does not yet look up address-level combined rates — enter your own known effective rate
        per state below if you need to account for local surtax in a specific market.
    </div>

    <div class="table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">State Rates</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="us-active" form="us-sales-tax-form" name="is_active" value="1" @checked($profile->is_active)>
                <label class="form-check-label" for="us-active">Active</label>
            </div>
        </div>
        <form id="us-sales-tax-form" method="POST" action="{{ route('admin.settings.us-sales-tax.update') }}">
            @csrf
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>State</th><th style="width:160px">Rate (%)</th><th class="text-muted small">Suggested state base rate</th></tr></thead>
                    <tbody>
                        @foreach($states as $code => $name)
                            @php
                                $suggested = $suggestedRates[$code] ?? 0;
                                $currentValue = old('rates.' . $code, $rates[$code] ?? '');
                            @endphp
                            <tr>
                                <td>{{ $name }} <span class="text-muted small">({{ $code }})</span></td>
                                <td>
                                    <input type="number" step="0.001" min="0" max="20" class="form-control form-control-sm"
                                           name="rates[{{ $code }}]" value="{{ $currentValue }}"
                                           placeholder="{{ $suggested }}">
                                </td>
                                <td class="text-muted small">
                                    {{ $suggested }}%
                                    @if($suggested == 0)
                                        &mdash; no state sales tax
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                <button type="submit" class="btn btn-primary">Save Rates</button>
                <a href="{{ route('admin.settings.country-tax') }}" class="btn btn-light border">Back to Country &amp; Tax</a>
            </div>
        </form>
    </div>
</div>
@endsection
