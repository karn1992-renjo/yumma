@extends('layouts.admin')

@section('title', 'Delivery Radius Settings')
@section('header', 'Delivery Radius Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Delivery Radius Settings</h1>
            <p>Configure the default delivery radius for restaurants and service area lookups.</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-primary">Back to System Settings</a>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Default Radius</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Delivery Radius (km)</label>
                        <input type="number" name="default_delivery_radius" class="form-control" step="0.5" value="{{ $settings['default_delivery_radius'] ?? 10 }}" min="0">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Radius</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
