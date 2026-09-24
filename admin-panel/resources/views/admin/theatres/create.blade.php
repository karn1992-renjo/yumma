@extends('layouts.admin')

@section('title', 'Create Theatre')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Create Theatre</h1>
        <p>Register a movie theatre for in-seat food ordering, with an owner login and geofence.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.theatres.store') }}" method="POST" enctype="multipart/form-data">
            @include('admin.theatres._form')
        </form>
    </div>
</div>
@endsection
