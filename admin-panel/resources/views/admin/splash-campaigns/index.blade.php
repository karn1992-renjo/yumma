@extends('layouts.admin')

@section('title', 'Splash Campaigns')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">Splash Campaigns</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Splash Campaigns</li>
                </ol>
            </div>
            <a href="{{ route('admin.splash-campaigns.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add Campaign
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Campaigns</h6>
                        <h3 class="mb-0">{{ $stats['total'] }}</h3>
                    </div>
                    <div class="icon primary"><i class="fas fa-bolt-lightning"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Currently Eligible</h6>
                        <h3 class="mb-0 text-success">{{ $stats['active'] }}</h3>
                    </div>
                    <div class="icon success"><i class="fas fa-check-circle"></i></div>
                </div>
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
                <h5 class="mb-0 fw-bold">Campaign List</h5>
                <span class="badge bg-primary">Total: {{ $campaigns->count() }}</span>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Artwork</th>
                            <th>Name</th>
                            <th>Platform</th>
                            <th>Window</th>
                            <th>Duration</th>
                            <th>On Tap</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $campaign)
                        <tr>
                            <td>
                                <img src="{{ \App\Services\MediaStorage::url($campaign->image_path) }}" alt=""
                                     style="width:40px;height:56px;object-fit:cover;border-radius:6px;">
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $campaign->title }}</div>
                                @if($campaign->headline)
                                    <div class="text-muted small">{{ $campaign->headline }}</div>
                                @endif
                            </td>
                            <td>{{ ucfirst($campaign->platform) }}</td>
                            <td class="small">
                                {{ $campaign->start_at?->format('d M, H:i') ?? 'Immediately' }}
                                &rarr;
                                {{ $campaign->end_at?->format('d M, H:i') ?? 'No expiry' }}
                            </td>
                            <td>{{ $campaign->display_duration_seconds }}s</td>
                            <td>{{ \Illuminate\Support\Str::headline($campaign->tap_action) }}</td>
                            <td>
                                @if($campaign->isCurrentlyEligible())
                                    <span class="badge bg-success">Live</span>
                                @elseif($campaign->is_active)
                                    <span class="badge bg-warning text-dark">Scheduled/Expired</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary toggle-status" data-id="{{ $campaign->id }}">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <a href="{{ route('admin.splash-campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger delete-campaign" data-id="{{ $campaign->id }}" data-name="{{ $campaign->title }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-bolt-lightning fa-3x text-muted mb-3 d-block"></i>
                                <h5>No Campaigns Yet</h5>
                                <p class="text-muted">Click the button above to publish your first promotional splash campaign.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Delete Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete campaign <strong id="deleteCampaignName"></strong>?</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.delete-campaign').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            document.getElementById('deleteCampaignName').textContent = name;
            document.getElementById('deleteForm').action = `/admin/splash-campaigns/${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });

    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`/admin/splash-campaigns/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then(res => res.json())
                .then(() => window.location.reload())
                .catch(() => alert('Could not update status. Please try again.'));
        });
    });
</script>
@endsection
