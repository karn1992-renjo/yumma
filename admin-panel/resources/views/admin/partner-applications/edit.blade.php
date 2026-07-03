@extends('layouts.admin')

@section('title', 'Edit Application #' . $application->application_number)
@section('header', 'Edit Partner Application')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Application #{{ $application->application_number }}</h1>
            <p>Update the real onboarding payload used for driver and restaurant approval.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.partner-applications.show', $application) }}" class="btn btn-outline-primary">
                <i class="fas fa-eye me-2"></i> View
            </a>
            <a href="{{ route('admin.partner-applications.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>
</div>

<form action="{{ route('admin.partner-applications.update', $application) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.partner-applications._form')

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.partner-applications.show', $application) }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Update Application</button>
    </div>
</form>
@endsection
