@extends('layouts.admin')

@section('title', 'Notification Templates')
@section('header', 'Notification Templates')

@section('content')
@php
    $channelLabels = ['push' => 'Push + In-App', 'sms' => 'SMS', 'database' => 'In-App Only'];
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Notification Templates</h1>
            <p>Editable copy for order-status push/in-app alerts and SMS messages. Placeholders like <code>@{{order_number}}</code> are substituted at send time.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.push-notifications.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-paper-plane me-2"></i>Push Broadcasts
            </a>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Channel</label>
            <select name="channel" class="form-select">
                <option value="">All channels</option>
                @foreach($channelLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['channel'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Group</label>
            <select name="group" class="form-select">
                <option value="">All groups</option>
                @foreach($groups as $group)
                    <option value="{{ $group }}" @selected(($filters['group'] ?? '') === $group)>{{ ucwords(str_replace('_', ' ', $group)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.notification-templates.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Channel</th>
                    <th>Preview</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $template->label }}</div>
                            <div class="text-muted small">{{ $template->key }}</div>
                        </td>
                        <td><span class="badge bg-{{ $template->channel === 'sms' ? 'info' : 'primary' }}">{{ $channelLabels[$template->channel] ?? ucfirst($template->channel) }}</span></td>
                        <td class="text-muted small">
                            @if($template->title)
                                <div class="fw-semibold">{{ $template->title }}</div>
                            @endif
                            <div>{{ \Illuminate\Support\Str::limit($template->body, 90) }}</div>
                        </td>
                        <td><span class="badge bg-{{ $template->is_active ? 'success' : 'secondary' }}">{{ $template->is_active ? 'Active' : 'Disabled' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.notification-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">No notification templates found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
