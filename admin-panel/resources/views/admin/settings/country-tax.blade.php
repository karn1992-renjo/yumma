@extends('layouts.admin')

@section('title', 'Country & Tax')
@section('header', 'Country & Tax')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-globe"></i> Operate correctly in every market</span>
            <h1>Country &amp; Tax</h1>
            <p>Pick the platform's default operating country and activate the countries you actually do business in. Each active country drives its own tax calculation, invoice format, and compliance rules automatically — no restaurant, branch, or order needs to be touched by hand.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- ============ PLATFORM DEFAULT ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Platform Default Country</h2>
                <p class="settings-card-subtitle">Used for any restaurant/branch that hasn't been assigned a country of its own. Every existing restaurant today has no country set, so changing this changes their tax rules — leave as India unless you're sure.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form method="POST" action="{{ route('admin.settings.country-tax.platform-default') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Default country</label>
                    <select name="platform_country_code" class="form-select">
                        @foreach($countryDefaults as $code => $info)
                            <option value="{{ $code }}" @selected($platformDefault === $code)>{{ $info['name'] }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Save default</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ ACTIVE COUNTRIES ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Countries</h2>
                <p class="settings-card-subtitle">A country with its own dedicated compliance module (full filings, invoice format, registration validation) links to its own settings page. Any other activated country uses a simple, correctly-labeled single tax rate until it gets its own module.</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCountryModal">
                <i class="fas fa-plus me-1"></i> Add Country
            </button>
        </div>
        <div class="settings-card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Country</th><th>Currency</th><th>Tax</th><th>Module</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse($profiles as $profile)
                            @php
                                $isDedicated = in_array($profile->country_code, $dedicatedCodes, true);
                                $customRoute = $customSettingsRoutes[$profile->country_code] ?? null;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $profile->country_name }} <span class="text-muted small">({{ $profile->country_code }})</span></td>
                                <td>{{ $profile->currency_code }}</td>
                                <td>{{ $profile->tax_name }}@if(! $customRoute) &middot; {{ rtrim(rtrim(number_format((float) data_get($profile->config, 'rate', 0), 2), '0'), '.') }}%@endif</td>
                                <td>
                                    @if($isDedicated)
                                        <span class="badge bg-primary">Full compliance module</span>
                                    @else
                                        <span class="badge bg-secondary">Generic tax</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $profile->is_active ? 'success' : 'secondary' }}">{{ $profile->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        @if($customRoute)
                                            <a href="{{ route($customRoute) }}" class="btn btn-sm btn-outline-primary">Manage full setup</a>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCountry{{ $profile->id }}">Edit</button>
                                        @endif
                                        <form action="{{ route('admin.settings.country-tax.profiles.toggle', $profile) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $profile->is_active ? 'danger' : 'success' }}">{{ $profile->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No countries configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ============ ADD COUNTRY MODAL ============ --}}
<div class="modal fade" id="addCountryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.settings.country-tax.profiles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Country</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('admin.settings._country-tax-form', ['prefix' => 'add', 'profile' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Country</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($profiles as $profile)
    @if(empty($customSettingsRoutes[$profile->country_code]))
        <div class="modal fade" id="editCountry{{ $profile->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.settings.country-tax.profiles.update', $profile) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $profile->country_name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @include('admin.settings._country-tax-form', ['prefix' => 'edit' . $profile->id, 'profile' => $profile])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<script>
    // Dynamic form: picking a country auto-fills currency/tax name/suggested
    // rate from the server-provided defaults table — admin can still edit
    // every field afterwards.
    window.countryTaxDefaults = @json($countryDefaults);
</script>
@endsection
