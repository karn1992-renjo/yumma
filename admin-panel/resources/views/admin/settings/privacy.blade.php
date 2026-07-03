@extends('layouts.admin')

@section('title', 'Privacy & Legal Content')
@section('header', 'Privacy & Legal Content')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Privacy & Legal Content</h1>
            <p>Update the platform's legal content displayed to users.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Privacy & Legal Content</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.privacy">

                    <div class="row gy-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Terms of Service</label>
                            <textarea name="legal_terms" class="form-control" rows="6">{{ $settings['legal_terms'] ?? 'Use of this platform is subject to account, order, payment, cancellation and support policies.' }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Privacy Policy</label>
                            <textarea name="legal_privacy" class="form-control" rows="6">{{ $settings['legal_privacy'] ?? 'We process customer, restaurant, driver, location and order data to operate delivery and support workflows.' }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Refund Policy</label>
                            <textarea name="legal_refund" class="form-control" rows="4">{{ $settings['legal_refund'] ?? 'Refund eligibility depends on payment status, restaurant acceptance, delivery progress and support review.' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Legal Contact Email</label>
                            <input type="email" name="legal_contact_email" class="form-control" value="{{ $settings['legal_contact_email'] ?? ($settings['contact_email'] ?? 'support@foodflow.com') }}">
                            <small class="text-muted">Shown on public legal pages and help sections.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Privacy & Legal</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
