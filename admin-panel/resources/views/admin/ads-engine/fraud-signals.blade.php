@extends('layouts.admin')

@section('title', 'Ad Fraud Signals')
@section('header', 'Ad Fraud Signals')

@section('content')
@php
    $status = $filters['status'] ?? '';
    $severity = $filters['severity'] ?? '';
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Ad Fraud Signals</h1>
            <p>Automated risk flags on billed clicks -- IP click bursts and abnormal click-through rates.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ads-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Campaigns
            </a>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['open' => 'Open', 'reviewed' => 'Reviewed', 'dismissed' => 'Dismissed'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Severity</label>
            <select name="severity" class="form-select">
                <option value="">All severities</option>
                @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                    <option value="{{ $value }}" @selected($severity === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.ads-engine.fraud-signals') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Restaurant</th>
                    <th>Signal</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signals as $signal)
                    <tr>
                        <td>{{ $signal->campaign?->name ?: 'Campaign #'.$signal->restaurant_ad_campaign_id }}</td>
                        <td>{{ $signal->campaign?->restaurant?->name ?: '-' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $signal->signal_type)) }}</td>
                        <td>
                            <span class="badge bg-{{ $signal->severity === 'high' ? 'danger' : ($signal->severity === 'medium' ? 'warning' : 'secondary') }}">{{ ucfirst($signal->severity) }} ({{ $signal->score }})</span>
                        </td>
                        <td><span class="badge bg-{{ $signal->status === 'open' ? 'warning' : ($signal->status === 'reviewed' ? 'success' : 'secondary') }}">{{ ucfirst($signal->status) }}</span></td>
                        <td class="text-end">
                            @if($signal->status === 'open')
                                <div class="d-flex justify-content-end gap-2">
                                    <form action="{{ route('admin.ads-engine.fraud-signals.update', $signal) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="reviewed">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Mark Reviewed</button>
                                    </form>
                                    <form action="{{ route('admin.ads-engine.fraud-signals.update', $signal) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="dismissed">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">No fraud signals found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $signals->links() }}
    </div>
</div>
@endsection
