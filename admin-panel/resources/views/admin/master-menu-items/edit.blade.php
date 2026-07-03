@extends('layouts.admin')

@section('title', 'Edit Global Menu Item')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Global Menu Item</h1>
            <p>{{ $item->name }}</p>
        </div>
        <a href="{{ route('admin.master-menu-items.index') }}" class="btn btn-light">Back</a>
    </div>
</div>

@include('admin.master-menu-items.partials.form', [
    'action' => route('admin.master-menu-items.update', $item),
    'method' => 'PUT',
])
@endsection
