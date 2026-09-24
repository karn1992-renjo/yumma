@extends('layouts.admin')

@section('title', 'Show Times')

@section('content')
{{--
    swaad-only: the day's screenings. Scheduling only — swaad never sells
    tickets, so there is no seat inventory here. A show exists to answer
    two operational questions: when is the interval (so food can be timed
    to arrive with the lights) and when does ordering close.
--}}
<div class="page-header">
    <h1>Show Times</h1>
    <p>Load each day's screenings so customers can choose <strong>“bring it now”</strong> or <strong>“bring it at the interval”</strong>. A screen with no show loaded still takes deliver-now orders.</p>
</div>

<div class="row g-4">
    @if($capabilities['shows_manage'] ?? false)
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Add a Show</h5></div>
                <div class="card-body">
                    <form action="{{ route('theatre.shows.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Screen</label>
                            <select name="screen_id" class="form-select" required>
                                <option value="">Choose a screen…</option>
                                @foreach($screens as $screen)
                                    <option value="{{ $screen->id }}" @selected(old('screen_id') == $screen->id)>{{ $screen->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Movie</label>
                            <input name="movie_title" class="form-control" value="{{ old('movie_title') }}" placeholder="e.g. Mahavatar Narsimha" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Language <span class="text-muted">(optional)</span></label>
                            <input name="language" class="form-control" value="{{ old('language') }}" placeholder="e.g. Hindi">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Starts</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Interval <span class="text-muted">(optional)</span></label>
                            <input type="datetime-local" name="interval_at" class="form-control" value="{{ old('interval_at') }}">
                            <div class="form-text">Leave blank for a screening that runs straight through — it simply won't offer interval delivery.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ends</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}" required>
                            <div class="form-text">Ordering closes {{ \App\Models\TheatreShow::ORDER_CUTOFF_MINUTES }} minutes before this.</div>
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger mb-0 py-2">{{ $errors->first() }}</div></div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary w-100">Add Show</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="{{ ($capabilities['shows_manage'] ?? false) ? 'col-lg-8' : 'col-12' }}">
        <div class="table-card">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between p-3 border-bottom">
                <h5 class="mb-0">{{ $date->isToday() ? 'Today' : $date->format('D, d M Y') }}</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ $date->toDateString() }}">
                    <button class="btn btn-sm btn-outline-secondary">Show</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Screen</th>
                            <th>Movie</th>
                            <th>Starts</th>
                            <th>Interval</th>
                            <th>Ends</th>
                            <th>Status</th>
                            @if($capabilities['shows_manage'] ?? false)<th class="text-end">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shows as $show)
                            <tr>
                                <td>{{ $show->screen?->name }}</td>
                                <td>
                                    {{ $show->movie_title }}
                                    @if($show->language)<div class="text-muted small">{{ $show->language }}</div>@endif
                                </td>
                                <td>{{ $show->starts_at->format('h:i A') }}</td>
                                <td>
                                    @if($show->interval_at)
                                        {{ $show->interval_at->format('h:i A') }}
                                    @else
                                        <span class="text-muted small">No interval</span>
                                    @endif
                                </td>
                                <td>{{ $show->ends_at->format('h:i A') }}</td>
                                <td>
                                    @if(! $show->is_active)
                                        <span class="badge bg-secondary">Disabled</span>
                                    @elseif($show->isAcceptingOrders())
                                        <span class="badge bg-success">Taking orders</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Closed</span>
                                    @endif
                                </td>
                                @if($capabilities['shows_manage'] ?? false)
                                    <td class="text-end">
                                        <form action="{{ route('theatre.shows.toggle', $show) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">{{ $show->is_active ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                        <form action="{{ route('theatre.shows.destroy', $show) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Remove this show?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($capabilities['shows_manage'] ?? false) ? 7 : 6 }}" class="text-center py-5 text-muted">
                                    No shows loaded for this day.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
