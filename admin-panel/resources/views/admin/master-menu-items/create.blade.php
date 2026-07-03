@extends('layouts.admin')

@section('title', 'Create Global Menu Item')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Global Menu Item</h1>
            <p>Add a reusable catalog item for restaurants.</p>
        </div>
        <a href="{{ route('admin.master-menu-items.index') }}" class="btn btn-light">Back</a>
    </div>
</div>

@include('admin.master-menu-items.partials.form', [
    'action' => route('admin.master-menu-items.store'),
    'method' => 'POST',
])
@endsection
