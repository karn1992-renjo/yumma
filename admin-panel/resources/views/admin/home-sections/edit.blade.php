@extends('layouts.admin')

@section('title', 'Edit Home Section')
@section('header', 'Edit Home Section')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Home Section</h1>
            <p>Update the configuration and scheduling for this homepage section.</p>
        </div>
        <a href="{{ route('admin.home-sections.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.home-sections.update', $homeSection) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.home-sections._form')
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
</form>
@endsection
