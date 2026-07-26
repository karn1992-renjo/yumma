@extends('layouts.admin')

@section('title', 'Privacy & Legal Content')
@section('header', 'Privacy & Legal Content')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-shield-alt"></i> Public Policies</span>
            <h1>Privacy & Legal Content</h1>
            <p>Update the legal copy shown to customers, restaurants, drivers, and visitors across public policy pages.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Legal Content</h2>
                <p class="settings-card-subtitle">Keep these policies clear because they are surfaced in account, checkout, support, and public website flows.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.privacy">

                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Terms of Service</label>
                        <textarea name="legal_terms" class="form-control" rows="6">{{ $settings['legal_terms'] ?? 'Use of this platform is subject to account, order, payment, cancellation and support policies.' }}</textarea>
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Privacy Policy</label>
                        <textarea name="legal_privacy" class="form-control" rows="6">{{ $settings['legal_privacy'] ?? 'We process customer, restaurant, driver, location and order data to operate delivery and support workflows.' }}</textarea>
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Refund Policy</label>
                        <textarea name="legal_refund" class="form-control" rows="4">{{ $settings['legal_refund'] ?? 'Refund eligibility depends on payment status, restaurant acceptance, delivery progress and support review.' }}</textarea>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Legal Contact Email</label>
                        <input type="email" name="legal_contact_email" class="form-control" value="{{ $settings['legal_contact_email'] ?? ($settings['contact_email'] ?? 'support@foodflow.com') }}">
                        <small class="text-muted">Shown on public legal pages and help sections.</small>
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Privacy & Legal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
