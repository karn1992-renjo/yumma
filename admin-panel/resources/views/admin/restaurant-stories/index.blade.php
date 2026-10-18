@extends('layouts.admin')

@section('title', 'Restaurant Stories')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">Restaurant Stories</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Restaurant Stories</li>
                </ol>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="table-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Live Stories</h5>
                <span class="badge bg-primary">Total: {{ $stories->total() }}</span>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Restaurant</th>
                            <th>Type</th>
                            <th>Caption</th>
                            <th>Posted</th>
                            <th>Expires</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stories as $story)
                        <tr>
                            <td>
                                <img src="{{ $story->thumbnailUrl() ?? $story->mediaUrl() }}" alt="" style="width:48px;height:64px;object-fit:cover;border-radius:8px;">
                            </td>
                            <td class="fw-semibold">{{ $story->restaurant->name ?? '—' }}</td>
                            <td>
                                @if($story->media_type === 'video')
                                    <span class="badge bg-info"><i class="fas fa-video me-1"></i> Video</span>
                                @else
                                    <span class="badge bg-secondary"><i class="fas fa-image me-1"></i> Image</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($story->caption, 40) ?: '—' }}</td>
                            <td>{{ $story->posted_at->format('d M, h:i A') }}</td>
                            <td>{{ $story->expires_at->format('d M, h:i A') }}</td>
                            <td>{{ $story->view_count }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger delete-story"
                                        data-id="{{ $story->id }}"
                                        data-restaurant="{{ $story->restaurant->name ?? 'this restaurant' }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-camera-retro fa-3x text-muted mb-3 d-block"></i>
                                <h5>No live stories right now</h5>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($stories->hasPages())
            <div class="card-footer">
                {{ $stories->links() }}
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Remove Story</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Remove this story from <strong id="deleteStoryRestaurant"></strong>?</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Remove Story</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.delete-story').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('deleteStoryRestaurant').textContent = this.dataset.restaurant;
            document.getElementById('deleteForm').action = `/admin/restaurant-stories/${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
</script>
@endsection
