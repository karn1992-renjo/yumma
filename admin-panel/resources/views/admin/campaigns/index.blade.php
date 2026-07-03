@extends('layouts.admin')

@section('title', 'Marketing Campaigns')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Marketing Campaigns</h1>
            <p class="text-muted mb-0">Manage your promotional campaigns</p>
        </div>
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary rounded-3">
            <i class="fas fa-plus me-2"></i> Create Campaign
        </a>
    </div>
</div>

<!-- Campaign Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-primary">{{ $campaigns->where('is_active', true)->where('end_date', '>=', now())->count() }}</div>
            <small class="text-muted">Active Campaigns</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-success">{{ number_format($campaigns->sum('impressions')) }}</div>
            <small class="text-muted">Total Impressions</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-warning">{{ number_format($campaigns->sum('clicks')) }}</div>
            <small class="text-muted">Total Clicks</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-info">
                @php
                    $totalImpressions = $campaigns->sum('impressions');
                    $ctr = $totalImpressions > 0 ? ($campaigns->sum('clicks') / $totalImpressions) * 100 : 0;
                @endphp
                {{ number_format($ctr, 2) }}%
            </div>
            <small class="text-muted">Click-Through Rate</small>
        </div>
    </div>
</div>

<!-- Campaigns Table -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">All Campaigns</h5>
        <span class="badge bg-primary rounded-3">{{ $campaigns->total() }} Campaigns</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Campaign</th>
                    <th>Type</th>
                    <th>Target</th>
                    <th>Period</th>
                    <th>Performance</th>
                    <th>Status</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($campaign->image_url)
                                <img src="{{ Storage::url($campaign->image_url) }}" class="rounded-3" width="40" height="40" style="object-fit: cover;">
                            @else
                                <div class="bg-light rounded-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            @endif
                            <div>
                                <div class="fw-semibold">{{ $campaign->name }}</div>
                                <small class="text-muted">ID: #{{ $campaign->id }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($campaign->type == 'banner')
                            <span class="badge bg-primary rounded-3">Banner</span>
                        @elseif($campaign->type == 'popup')
                            <span class="badge bg-warning rounded-3">Popup</span>
                        @elseif($campaign->type == 'email')
                            <span class="badge bg-info rounded-3">Email</span>
                        @else
                            <span class="badge bg-secondary rounded-3">Push</span>
                        @endif
                    </td>
                    <td>
                        <div>
                            @if($campaign->target_audience == 'all')
                                <span class="badge bg-success rounded-3">All Customers</span>
                            @elseif($campaign->target_audience == 'new_customer')
                                <span class="badge bg-info rounded-3">New Customers</span>
                            @else
                                <span class="badge bg-warning rounded-3">Returning Customers</span>
                            @endif
                        </div>
                        @if($campaign->target_location)
                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $campaign->target_location }}</small>
                        @endif
                    </td>
                    <td>
                        <small>{{ \Carbon\Carbon::parse($campaign->start_date)->format('d M') }} - {{ \Carbon\Carbon::parse($campaign->end_date)->format('d M Y') }}</small>
                        <br>
                        @if($campaign->end_date < now())
                            <span class="badge bg-secondary rounded-3">Expired</span>
                        @elseif($campaign->start_date > now())
                            <span class="badge bg-info rounded-3">Upcoming</span>
                        @elseif($campaign->is_active)
                            <span class="badge bg-success rounded-3">Live</span>
                        @else
                            <span class="badge bg-secondary rounded-3">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div>Impressions: <strong>{{ number_format($campaign->impressions) }}</strong></div>
                        <div>Clicks: <strong>{{ number_format($campaign->clicks) }}</strong></div>
                        @php $campaignCtr = $campaign->impressions > 0 ? ($campaign->clicks / $campaign->impressions) * 100 : 0; @endphp
                        <div class="text-muted">CTR: {{ number_format($campaignCtr, 2) }}%</div>
                    </td>
                    <td>
                        <form action="{{ route('admin.campaigns.update', $campaign->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="name" value="{{ $campaign->name }}">
                            <input type="hidden" name="type" value="{{ $campaign->type }}">
                            <input type="hidden" name="target_audience" value="{{ $campaign->target_audience }}">
                            <input type="hidden" name="start_date" value="{{ $campaign->start_date }}">
                            <input type="hidden" name="end_date" value="{{ $campaign->end_date }}">
                            <input type="hidden" name="is_active" value="{{ $campaign->is_active ? 0 : 1 }}">
                            <button type="submit" class="btn btn-sm rounded-3 {{ $campaign->is_active && $campaign->end_date >= now() ? 'btn-success' : 'btn-secondary' }}">
                                {{ $campaign->is_active && $campaign->end_date >= now() ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td>
                        <a href="{{ route('admin.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-outline-primary rounded-3">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.campaigns.destroy', $campaign->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" onclick="return confirm('Delete this campaign?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-megaphone fa-3x mb-3 d-block opacity-50"></i>
                            <h5>No Campaigns Found</h5>
                            <p class="mb-3">Create your first marketing campaign.</p>
                            <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary rounded-3">
                                <i class="fas fa-plus me-2"></i> Create Campaign
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $campaigns->links() }}
    </div>
</div>
@endsection
