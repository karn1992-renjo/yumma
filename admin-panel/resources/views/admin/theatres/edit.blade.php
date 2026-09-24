@extends('layouts.admin')

@section('title', 'Edit Theatre')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Edit {{ $theatre->name }}</h1>
        <p>Update theatre details, geofence, and hand-off policy.</p>
    </div>
    <form action="{{ route('admin.theatres.resync-owner-permissions', $theatre) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-rotate me-1"></i> Reset owner permissions to default
        </button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.theatres.update', $theatre) }}" method="POST" enctype="multipart/form-data">
            @include('admin.theatres._form')
        </form>
    </div>
</div>
@endsection
