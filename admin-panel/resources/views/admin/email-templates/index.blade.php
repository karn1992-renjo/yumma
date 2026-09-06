@extends('layouts.admin')

@section('title', 'Email Templates')
@section('header', 'Email Templates')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Email Templates</h1>
            <p>HTML emails sent to customers. Edit the markup, add images and preview with sample data. Placeholders like <code>@{{order_number}}</code> are filled in at send time.</p>
        </div>
        <a href="{{ route('admin.settings.communication') }}" class="btn btn-outline-secondary">
            <i class="fas fa-gear me-2"></i>Mail (SMTP) Settings
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Subject</th>
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
                        <td class="text-muted small">{{ $template->title }}</td>
                        <td><span class="badge bg-{{ $template->is_active ? 'success' : 'secondary' }}">{{ $template->is_active ? 'Active' : 'Disabled' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.email-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">No email templates found. Run <code>php artisan migrate</code> to seed them.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
