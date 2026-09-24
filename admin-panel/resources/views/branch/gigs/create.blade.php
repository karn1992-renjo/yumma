@extends('layouts.admin')

@section('title', 'Create Gig Slot')

@section('content')
<div class="page-header">
    <h1>Create Fleet Gig Slot</h1>
    <p>Create a shift your own branch's drivers (or the general pool, if opted in) can book from the driver app.</p>
</div>

<div class="table-card">
    <div class="card-header">
        <h5 class="mb-0">Gig Details</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('branch.gigs.store') }}">
            @csrf
            @include('branch.gigs.partials.form', ['gig' => null])
        </form>
    </div>
</div>
@endsection
