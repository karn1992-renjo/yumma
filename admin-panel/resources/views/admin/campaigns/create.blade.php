@extends('layouts.admin')

@section('title', 'Create Campaign')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="mt-4">Create Campaign</h1>
                <p class="text-muted mb-0">Set up a new promotional campaign for your customers.</p>
            </div>
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-secondary rounded-3">
                <i class="fas fa-arrow-left me-2"></i> Back to Campaigns
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Campaign Details</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.campaigns.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campaign Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="banner" {{ old('type') == 'banner' ? 'selected' : '' }}>Banner</option>
                                    <option value="popup" {{ old('type') == 'popup' ? 'selected' : '' }}>Popup</option>
                                    <option value="email" {{ old('type') == 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="push" {{ old('type') == 'push' ? 'selected' : '' }}>Push Notification</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Target Audience <span class="text-danger">*</span></label>
                                <select name="target_audience" class="form-select" required>
                                    <option value="all" {{ old('target_audience') == 'all' ? 'selected' : '' }}>All Customers</option>
                                    <option value="new_customer" {{ old('target_audience') == 'new_customer' ? 'selected' : '' }}>New Customers Only</option>
                                    <option value="returning_customer" {{ old('target_audience') == 'returning_customer' ? 'selected' : '' }}>Returning Customers Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required value="{{ old('start_date') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required value="{{ old('end_date') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Location (Optional)</label>
                            <input type="text" name="target_location" class="form-control" value="{{ old('target_location') }}">
                            <small class="text-muted">Leave empty to target all locations.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Image</label>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg">
                            <small class="text-muted">Upload an image for banner or popup campaigns.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Link URL</label>
                            <input type="url" name="link_url" class="form-control" value="{{ old('link_url') }}">
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ old('is_active') ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Activate Campaign</label>
                        </div>

                        <h6 class="fw-bold mb-3 mt-4">Discount Details (Optional)</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Discount Type</label>
                                <select name="discount_details[type]" class="form-select">
                                    <option value="" {{ old('discount_details.type') == '' ? 'selected' : '' }}>None</option>
                                    <option value="percentage" {{ old('discount_details.type') == 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="fixed" {{ old('discount_details.type') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount Value</label>
                                <input type="number" step="0.01" name="discount_details[value]" class="form-control" value="{{ old('discount_details.value') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minimum Order</label>
                                <input type="number" step="0.01" name="discount_details[min_order]" class="form-control" value="{{ old('discount_details.min_order') }}">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Create Campaign
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
