@extends('layouts.admin')

@section('title', 'Add Subscription Plan')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">Add Subscription Plan</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.subscription-plans.index') }}">Subscription Plans</a></li>
                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
            <a href="{{ route('admin.subscription-plans.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Plan Details</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.subscription-plans.store') }}" method="POST">
                        @csrf
                        @include('admin.subscription-plans._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
