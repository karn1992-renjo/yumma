@extends('layouts.admin')

@section('title', 'Create Home Section')
@section('header', 'Create Home Section')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Home Section</h1>
            <p>Add a new curated section to the public homepage.</p>
        </div>
        <a href="{{ route('admin.home-sections.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.home-sections.store') }}" enctype="multipart/form-data">
    @csrf
    @include('admin.home-sections._form')
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Create Section</button>
    </div>
</form>
@endsection
