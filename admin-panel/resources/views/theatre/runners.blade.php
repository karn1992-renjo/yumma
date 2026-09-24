@extends('layouts.admin')

@section('title', 'Theatre Runners')

@section('content')
<div class="page-header">
    <h1>Runner Roster</h1>
    <p>Runners walk food from a mapped stall to a customer's seat at {{ $theatre->name }} — no road delivery, no GPS routing.</p>
</div>

<div class="row g-4">
    @if($capabilities['runners_manage'] ?? false)
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Add a Runner</h5></div>
                <div class="card-body">
                    <form action="{{ route('theatre.runners.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Name</label>
                            <input name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phone</label>
                            <input name="phone" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger mb-0 py-2">{{ $errors->first() }}</div></div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary w-100">Add Runner</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <div class="{{ ($capabilities['runners_manage'] ?? false) ? 'col-lg-8' : 'col-12' }}">
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Runner</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse($roster as $entry)
                            <tr>
                                <td>{{ $entry->runner?->name }}</td>
                                <td>{{ $entry->runner?->phone }}<div class="small text-muted">{{ $entry->runner?->email }}</div></td>
                                <td><span class="badge bg-{{ $entry->approval_status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($entry->approval_status) }}</span></td>
                                <td class="text-end">
                                    @if(($capabilities['runners_manage'] ?? false) && $entry->runner)
                                        <form action="{{ route('theatre.runners.remove', $entry->runner) }}" method="POST" onsubmit="return confirm('Remove this runner from the roster?');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-5 text-muted">No runners added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
