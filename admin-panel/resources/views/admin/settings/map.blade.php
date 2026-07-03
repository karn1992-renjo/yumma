@extends('layouts.admin')

@section('title', 'Map & Location Settings')
@section('header', 'Map & Location Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Map & Location Settings</h1>
            <p>Set up the platform map API key and default delivery radius.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Map Settings</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Google Maps API Key</label>
                                <input type="text" name="google_maps_api_key" class="form-control" value="{{ $settings['google_maps_api_key'] ?? $settings['google_maps_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Default Delivery Radius (km)</label>
                                <input type="number" name="default_delivery_radius" class="form-control" step="0.5" min="0" value="{{ $settings['default_delivery_radius'] ?? 10 }}">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Map Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
