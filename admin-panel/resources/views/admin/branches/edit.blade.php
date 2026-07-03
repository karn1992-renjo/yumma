@extends('layouts.admin')

@section('title', 'Edit Branch')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Edit Branch</h1>
        <p>{{ $branch->name }} commission, territory ownership, finance, and compliance details.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.branches.update', $branch) }}" method="POST" enctype="multipart/form-data">
            @include('admin.branches._form', ['branch' => $branch])
        </form>
    </div>
</div>
@endsection
