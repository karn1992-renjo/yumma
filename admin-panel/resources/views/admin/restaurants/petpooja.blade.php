@extends('layouts.admin')

@section('title', 'Petpooja Integration')
@section('header', 'Petpooja Integration')

@php
    $maskedKey = $config && $config->app_key_encrypted ? '••••' . substr($config->app_key_encrypted, -4) : null;
    $maskedSecret = $config && $config->app_secret_encrypted ? '••••' . substr($config->app_secret_encrypted, -4) : null;
    $maskedToken = $config && $config->access_token_encrypted ? '••••' . substr($config->access_token_encrypted, -4) : null;
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Petpooja Integration — {{ $restaurant->name }}</h1>
            <p>Configure this restaurant's Petpooja POS credentials. The menu is managed by Petpooja once connected — edits here don't change the menu directly.</p>
        </div>
        <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>Back to Restaurant
        </a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <strong>Credentials</strong>
    </div>
    <div class="p-4">
        <form method="POST" action="{{ route('admin.restaurants.petpooja.store', $restaurant) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Petpooja Restaurant ID (restID)</label>
                    <input type="text" name="rest_id" class="form-control" value="{{ old('rest_id', $config?->rest_id) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Environment</label>
                    <select name="environment" class="form-select">
                        <option value="sandbox" @selected(old('environment', $config?->environment ?? 'sandbox') === 'sandbox')>Sandbox</option>
                        <option value="production" @selected(old('environment', $config?->environment) === 'production')>Production</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">App Key</label>
                    <input type="text" name="app_key" class="form-control" placeholder="{{ $maskedKey ?: 'Not set' }}">
                    <small class="text-muted">Leave blank to keep the existing value.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">App Secret</label>
                    <input type="text" name="app_secret" class="form-control" placeholder="{{ $maskedSecret ?: 'Not set' }}">
                    <small class="text-muted">Leave blank to keep the existing value.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Access Token</label>
                    <input type="text" name="access_token" class="form-control" placeholder="{{ $maskedToken ?: 'Not set' }}">
                    <small class="text-muted">Leave blank to keep the existing value.</small>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $config?->is_active))>
                        <label class="form-check-label" for="is_active">Active — relay orders &amp; sync with Petpooja</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_menu_managed_by_pos" value="1" class="form-check-input" id="is_menu_managed_by_pos" @checked(old('is_menu_managed_by_pos', $config?->is_menu_managed_by_pos ?? true))>
                        <label class="form-check-label" for="is_menu_managed_by_pos">Menu is managed by Petpooja (blocks manual edits in our admin/restaurant panel)</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save me-2"></i>Save Configuration
            </button>
        </form>
    </div>
</div>

@if($config)
    <div class="table-card mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <strong>Menu Sync</strong>
            <span class="badge badge-{{ $config->last_menu_sync_status === 'applied' ? 'success' : ($config->last_menu_sync_status === 'failed' ? 'danger' : 'secondary') }}">
                {{ $config->last_menu_sync_status ? ucfirst($config->last_menu_sync_status) : 'Never synced' }}
            </span>
        </div>
        <div class="p-4">
            <p class="text-muted mb-3">
                Last synced: {{ $config->last_menu_synced_at?->format('d M Y h:i A') ?? 'Never' }}
                @if($config->last_menu_sync_error)
                    <br><span class="text-danger">{{ $config->last_menu_sync_error }}</span>
                @endif
            </p>
            <form method="POST" action="{{ route('admin.restaurants.petpooja.sync-menu', $restaurant) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary" @disabled(! $config->hasCredentials())>
                    <i class="fas fa-rotate me-2"></i>Sync Menu Now
                </button>
            </form>
            <a href="{{ route('admin.restaurants.petpooja.syncs', $restaurant) }}" class="btn btn-outline-secondary">
                <i class="fas fa-clock-rotate-left me-2"></i>Sync History
            </a>
        </div>
    </div>
@endif
@endsection
