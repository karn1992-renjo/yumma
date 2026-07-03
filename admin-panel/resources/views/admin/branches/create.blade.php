@extends('layouts.admin')

@section('title', 'Create Branch')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Create Branch</h1>
        <p>Register a franchise branch with owner login, wallet, commission sharing, and settlement rules.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.branches.store') }}" method="POST" enctype="multipart/form-data">
            @include('admin.branches._form')
        </form>
    </div>
</div>
@endsection
