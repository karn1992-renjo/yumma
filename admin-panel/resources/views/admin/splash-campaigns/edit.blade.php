@extends('layouts.admin')

@section('title', 'Edit Splash Campaign')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">Edit Splash Campaign</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.splash-campaigns.index') }}">Splash Campaigns</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
            <a href="{{ route('admin.splash-campaigns.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <div class="table-card">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Campaign Details</h5>
        </div>
        <div class="p-4">
            <form action="{{ route('admin.splash-campaigns.update', $campaign) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.splash-campaigns._form')
            </form>
        </div>
    </div>
</div>
@endsection
