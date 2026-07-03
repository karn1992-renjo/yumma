@extends('layouts.admin')

@section('title', 'Create Gig Slot')

@section('content')
<div class="page-header">
    <h1>Create Global Gig Slot</h1>
    <p>Create an open delivery slot that drivers can discover and book from the driver app.</p>
</div>

<div class="table-card">
    <div class="card-header">
        <h5 class="mb-0">Gig Details</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.gigs.store') }}">
            @csrf
            @include('admin.gigs.partials.form', ['gig' => null])
        </form>
    </div>
</div>
@endsection
