@extends('layouts.admin')

@section('title', 'Create Partner Application')
@section('header', 'Create Partner Application')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Partner Application</h1>
            <p>Create a real driver or restaurant application directly from the admin panel.</p>
        </div>
        <a href="{{ route('admin.partner-applications.index') }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>
</div>

<form action="{{ route('admin.partner-applications.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.partner-applications._form')

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.partner-applications.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Create Application</button>
    </div>
</form>
@endsection
