@extends('layouts.admin')

@section('title', 'Edit Campaign')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">Edit Campaign</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}">Campaigns</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Campaign Information</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.campaigns.update', $campaign->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $campaign->name) }}">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Campaign Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="banner" {{ $campaign->type == 'banner' ? 'selected' : '' }}>Banner</option>
                                    <option value="popup" {{ $campaign->type == 'popup' ? 'selected' : '' }}>Popup</option>
                                    <option value="email" {{ $campaign->type == 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="push" {{ $campaign->type == 'push' ? 'selected' : '' }}>Push Notification</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Target Audience <span class="text-danger">*</span></label>
                                <select name="target_audience" class="form-select" required>
                                    <option value="all" {{ $campaign->target_audience == 'all' ? 'selected' : '' }}>All Customers</option>
                                    <option value="new_customer" {{ $campaign->target_audience == 'new_customer' ? 'selected' : '' }}>New Customers Only</option>
                                    <option value="returning_customer" {{ $campaign->target_audience == 'returning_customer' ? 'selected' : '' }}>Returning Customers Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required value="{{ old('start_date', $campaign->start_date->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required value="{{ old('end_date', $campaign->end_date->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Location (Optional)</label>
                            <input type="text" name="target_location" class="form-control" value="{{ old('target_location', $campaign->target_location) }}">
                            <small class="text-muted">Leave empty to target all locations</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Campaign Image</label>
                            @if($campaign->image_url)
                                <div class="mb-2">
                                    <img src="{{ Storage::url($campaign->image_url) }}" class="rounded" height="80">
                                    <br>
                                    <small class="text-muted">Current image</small>
                                </div>
                            @endif
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg">
                            <small class="text-muted">Upload new image to replace current</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Link URL</label>
                            <input type="url" name="link_url" class="form-control" value="{{ old('link_url', $campaign->link_url) }}">
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ $campaign->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">Campaign Active</label>
                        </div>

                        <h6 class="fw-bold mb-3 mt-4">Discount Details (Optional)</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Discount Type</label>
                                <select name="discount_details[type]" class="form-select">
                                    <option value="">None</option>
                                    <option value="percentage" {{ ($campaign->discount_details['type'] ?? '') == 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="fixed" {{ ($campaign->discount_details['type'] ?? '') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount Value</label>
                                <input type="number" step="0.01" name="discount_details[value]" class="form-control" value="{{ $campaign->discount_details['value'] ?? '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Min Order Amount</label>
                                <input type="number" step="0.01" name="discount_details[min_order]" class="form-control" value="{{ $campaign->discount_details['min_order'] ?? '' }}">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Update Campaign
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Campaign Stats</h5>
                </div>
                <div class="p-4">
                    <div class="mb-3 text-center">
                        <div class="bg-light rounded p-3">
                            <div class="display-4 fw-bold text-primary">{{ number_format($campaign->impressions) }}</div>
                            <div class="text-muted">Total Impressions</div>
                        </div>
                    </div>
                    <div class="mb-3 text-center">
                        <div class="bg-light rounded p-3">
                            <div class="display-4 fw-bold text-success">{{ number_format($campaign->clicks) }}</div>
                            <div class="text-muted">Total Clicks</div>
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="bg-light rounded p-3">
                            @php
                                $ctr = $campaign->impressions > 0 ? ($campaign->clicks / $campaign->impressions) * 100 : 0;
                            @endphp
                            <div class="display h3 fw-bold text-warning">{{ number_format($ctr, 2) }}%</div>
                            <div class="text-muted">Click-Through Rate (CTR)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
