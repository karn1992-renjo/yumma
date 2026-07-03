@extends('layouts.admin')

@section('title', 'Edit Gig Slot')

@section('content')
<div class="page-header">
    <h1>Edit Gig Slot</h1>
    <p>Update slot timing, incentives, and booking conditions for drivers.</p>
</div>

<div class="table-card">
    <div class="card-header">
        <h5 class="mb-0">Gig Slot Details</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.gigs.update', $gig) }}">
            @csrf
            @method('PUT')
            @include('admin.gigs.partials.form', ['gig' => $gig])
        </form>
    </div>
</div>
@endsection
