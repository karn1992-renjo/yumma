@extends('layouts.admin')

@section('title', 'Fleet — Gig Slots')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>{{ $branch->name }} Fleet</h1>
            <p>Create, edit, and monitor gig/shift slots for your own branch's drivers.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($capabilities['gigs_payouts'] ?? false)
                <a href="{{ route('branch.gigs.payout-approvals') }}" class="btn btn-outline-primary">
                    <i class="fas fa-hand-holding-dollar me-2"></i>Payout Approvals
                    @if(($stats['pending_payouts'] ?? 0) > 0)
                        <span class="badge bg-danger ms-1">{{ $stats['pending_payouts'] }}</span>
                    @endif
                </a>
            @endif
            @if($capabilities['gigs_create'] ?? false)
                <a href="{{ route('branch.gigs.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Slot</a>
            @endif
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach([
        'Total Today' => $stats['total_today'],
        'Active Today' => $stats['active_gigs'],
        'Completed Today' => $stats['completed_today'],
        'Pending Payouts' => $stats['pending_payouts'],
    ] as $label => $value)
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <p class="text-muted mb-1 small">{{ $label }}</p>
                <h3 class="mb-0 fw-bold">{{ number_format($value) }}</h3>
            </div>
        </div>
    @endforeach
</div>

<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="mb-0">Live Driver Map</h5>
            <div class="small text-muted">Your branch's own mapped drivers, live position — online/offline, updates automatically.</div>
        </div>
        <a href="{{ route('branch.fleet.dashboard') }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-up-right-and-down-left-from-center me-1"></i>Full Screen
        </a>
    </div>
    <div id="branchFleetMap" style="height:360px; border-radius:0 0 12px 12px;"></div>
    @if(empty($googleMapsApiKey))
        <div class="alert alert-info m-3 mb-3">Ask admin to add a Google Maps API key in map settings if the map does not render.</div>
    @endif
</div>

<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="mb-0">Gig Slots</h5>
            @if(!empty($selectedDate))
                <div class="small text-muted">Showing slots for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</div>
            @endif
        </div>
        <form method="GET" action="{{ route('branch.gigs.index') }}" class="d-flex align-items-center gap-2">
            <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate ?? '' }}">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            @if(!empty($selectedDate))
                <a href="{{ route('branch.gigs.index') }}" class="btn btn-sm btn-light border">Clear</a>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Area</th>
                    <th>Date & Time</th>
                    <th>Capacity</th>
                    <th>Pool</th>
                    <th>Estimated Earning</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    'Available Slots' => $availableGigs,
                    'Booked Slots' => $bookedGigs,
                    'Completed Slots' => $completedGigs,
                    'Cancelled Slots' => $cancelledGigs,
                ] as $groupLabel => $groupItems)
                    @if($groupItems->isNotEmpty())
                        <tr><td colspan="8" class="bg-light fw-semibold text-uppercase small">{{ $groupLabel }}</td></tr>
                    @endif
                    @foreach($groupItems as $gig)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    {{ $gig->title ?: 'Gig Slot #' . $gig->id }}
                                    @unless((int) $gig->branch_id === (int) $branch->id)
                                        <span class="badge bg-light text-muted border ms-1">Admin · zone-wide</span>
                                    @endunless
                                </div>
                                <div class="small text-muted">{{ $gig->description ?: 'No description added' }}</div>
                            </td>
                            <td>{{ $gig->area?->name ?? 'No area' }}</td>
                            <td><div>{{ $gig->date_short ?? $gig->date?->format('d M') }}</div><div class="small text-muted">{{ $gig->time_range }}</div></td>
                            <td><div class="fw-semibold">{{ $gig->booked_count }} / {{ $gig->capacity }} booked</div><div class="small text-muted">{{ $gig->available_seats }} open seat(s)</div></td>
                            <td><span class="badge bg-{{ $gig->open_to_pool ? 'info' : 'secondary' }}">{{ $gig->open_to_pool ? 'Open to all' : 'Branch only' }}</span></td>
                            <td>{{ App\Models\AppSetting::sanitizedCurrencySymbol() }}{{ number_format((float) $gig->estimated_earning, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td><span class="badge badge-{{ $gig->status === 'available' ? 'success' : ($gig->status === 'booked' ? 'primary' : ($gig->status === 'completed' ? 'info' : 'danger')) }}">{{ ucfirst($gig->status) }}</span></td>
                            <td class="text-end">
                                @if(($capabilities['gigs_edit'] ?? false) && (int) $gig->branch_id === (int) $branch->id)
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('branch.gigs.edit', $gig) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('branch.gigs.destroy', $gig) }}" method="POST" onsubmit="return confirm('Delete this gig slot?');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>
                                    </div>
                                @elseif(!$gig->branch_id)
                                    <span class="small text-muted">Managed by admin</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                @if($availableGigs->isEmpty() && $bookedGigs->isEmpty() && $completedGigs->isEmpty() && $cancelledGigs->isEmpty())
                    <tr><td colspan="8" class="text-center py-5 text-muted">No gig slots found. Create your first one to start scheduling your fleet.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
(function () {
    const markersUrl = @json(route('branch.fleet.markers'));
    let map = null;
    let mapMarkers = [];
    let infoWindows = [];

    function escapeText(value) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
    }

    function clearMarkers() {
        infoWindows.forEach((w) => w.close());
        mapMarkers.forEach((m) => m.setMap(null));
        infoWindows = [];
        mapMarkers = [];
    }

    function renderMarkers(drivers, fitBounds = false) {
        if (!map || typeof google === 'undefined') return;
        clearMarkers();
        const bounds = new google.maps.LatLngBounds();
        let visible = 0;

        drivers.forEach((driver) => {
            const lat = Number(driver.lat);
            const lng = Number(driver.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            const position = { lat, lng };
            const marker = new google.maps.Marker({
                position,
                map,
                title: driver.name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 9,
                    fillColor: driver.is_online ? '#10B981' : '#94A3B8',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                },
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="min-width:200px">
                        <strong>${escapeText(driver.name)}</strong><br>
                        <span>${escapeText(driver.phone)}</span><br>
                        <span>Status: ${driver.is_online ? 'Online' : 'Offline'}</span><br>
                        <span>Active orders: ${escapeText(driver.active_orders_count ?? 0)}</span>
                    </div>
                `,
            });

            marker.addListener('click', () => infoWindow.open({ anchor: marker, map }));
            mapMarkers.push(marker);
            infoWindows.push(infoWindow);
            bounds.extend(position);
            visible += 1;
        });

        if (fitBounds && visible > 1) {
            map.fitBounds(bounds);
        } else if (fitBounds && visible === 1) {
            map.setCenter(bounds.getCenter());
            map.setZoom(13);
        }
    }

    async function refreshMarkers(fitBounds = false) {
        try {
            const response = await fetch(markersUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (Array.isArray(payload.data)) {
                renderMarkers(payload.data, fitBounds);
            }
        } catch (error) {
            console.warn('Branch fleet marker refresh failed', error);
        }
    }

    function initMap() {
        const mapElement = document.getElementById('branchFleetMap');
        if (!mapElement || typeof google === 'undefined') return;

        map = new google.maps.Map(mapElement, {
            center: { lat: 20.5937, lng: 78.9629 },
            zoom: 5,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        refreshMarkers(true);
        window.setInterval(() => refreshMarkers(false), 10000);
    }

    document.addEventListener('DOMContentLoaded', initMap);
})();
</script>
@endsection
