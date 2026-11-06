@extends('layouts.admin')

@section('title', 'App Open Video')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">App Open Video</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">App Open Video</li>
                </ol>
            </div>
            <a href="{{ route('admin.app-open-videos.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Upload Video</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <p class="text-muted">A video the <b>customer app</b> or <b>restaurant app</b> plays full-screen when it opens. People can skip it, and each person sees a given video only once. Upload a new file (or press "Show again") to play it for everyone again.</p>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Title</th><th>App</th><th>Status</th><th>Skip button</th><th>Window</th><th>Seen by</th><th>Preview</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @forelse($videos as $video)
                    <tr>
                        <td><strong>{{ $video->title }}</strong></td>
                        <td><span class="badge {{ $video->audience === 'customer' ? 'bg-info' : 'bg-dark' }}">{{ ucfirst($video->audience) }}</span></td>
                        <td>
                            @if($video->is_active)<span class="badge bg-success">Live</span>@else<span class="badge bg-secondary">Off</span>@endif
                        </td>
                        <td>{{ $video->skip_after_seconds > 0 ? 'after ' . $video->skip_after_seconds . 's' : 'immediately' }}</td>
                        <td class="small text-muted">
                            {{ $video->starts_at ? $video->starts_at->format('d M Y') : 'now' }} &rarr; {{ $video->ends_at ? $video->ends_at->format('d M Y') : 'no end' }}
                        </td>
                        <td>{{ $video->views_count }} {{ \Illuminate\Support\Str::plural('person', $video->views_count) }}</td>
                        <td><a href="{{ $video->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-play me-1"></i>Play</a></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <form method="POST" action="{{ route('admin.app-open-videos.toggle-status', $video) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ $video->is_active ? 'Switch off' : 'Make live' }}</button></form>
                                <form method="POST" action="{{ route('admin.app-open-videos.reset-views', $video) }}" onsubmit="return confirm('Play this video again for everyone?')">@csrf<button class="btn btn-sm btn-outline-warning">Show again</button></form>
                                <a href="{{ route('admin.app-open-videos.edit', $video) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.app-open-videos.destroy', $video) }}" onsubmit="return confirm('Delete this video?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No video yet. Upload one and people will see it when they next open the app.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
