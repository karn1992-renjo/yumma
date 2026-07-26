@extends('layouts.admin')

@section('title', 'Fleet Dashboard')

@section('styles')
<style>
    .fleet-shell { display: flex; flex-direction: column; gap: 18px; }
    .fleet-head,
    .fleet-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }
    .fleet-head { padding: 18px; }
    .fleet-title h1 { margin: 0; color: #0f172a; font-size: 1.55rem; font-weight: 850; letter-spacing: 0; }
    .fleet-title p { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
    .fleet-stat-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
    .fleet-stat {
        min-height: 108px;
        padding: 16px;
        border: 1px solid var(--border);
        border-radius: 18px;
        background: #fff;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }
    .fleet-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--stat-color);
        background: color-mix(in srgb, var(--stat-color) 13%, #fff);
        flex: 0 0 auto;
    }
    .fleet-stat-label { color: #64748b; font-size: .73rem; font-weight: 850; text-transform: uppercase; letter-spacing: .05em; }
    .fleet-stat-value { color: #0f172a; font-size: 1.45rem; font-weight: 900; line-height: 1.1; }
    .fleet-filter { padding: 16px; }
    .fleet-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(340px, .75fr); gap: 18px; }
    .fleet-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .fleet-card-head h2 { margin: 0; color: #0f172a; font-size: 1.02rem; font-weight: 850; }
    .fleet-card-head p { margin: 3px 0 0; color: #64748b; font-size: .82rem; font-weight: 650; }
    .fleet-map {
        height: 420px;
        margin: 16px;
        border-radius: 16px;
        border: 1px solid #dbeafe;
        background: linear-gradient(135deg, #eff6ff, #f8fafc);
        overflow: hidden;
    }
    .driver-list { max-height: 530px; overflow-y: auto; }
    .driver-item {
        padding: 15px 18px;
        border-bottom: 1px solid #edf2f7;
    }
    .driver-item:last-child { border-bottom: 0; }
    .driver-name { color: #0f172a; font-weight: 850; }
    .driver-muted { color: #64748b; font-size: .82rem; font-weight: 650; }
    .fleet-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .74rem;
        font-weight: 850;
        white-space: nowrap;
    }
    .fleet-pill.success { background: #dcfce7; color: #047857; }
    .fleet-pill.warning { background: #fef3c7; color: #92400e; }
    .fleet-pill.danger { background: #fee2e2; color: #b91c1c; }
    .fleet-pill.info { background: #dbeafe; color: #1d4ed8; }
    .fleet-table { margin: 0; }
    .fleet-table thead th {
        padding: 13px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-size: .73rem;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: .05em;
        white-space: nowrap;
    }
    .fleet-table tbody td { padding: 15px 18px; vertical-align: middle; border-color: #edf2f7; }

    @media (max-width: 1300px) {
        .fleet-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .fleet-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
        .fleet-title h1 { font-size: 1.25rem; }
        .fleet-stat-grid { grid-template-columns: 1fr; }
        .fleet-card-head { align-items: flex-start; flex-direction: column; }
        .fleet-map { height: 330px; margin: 12px; }
        .fleet-table thead { display: none; }
        .fleet-table, .fleet-table tbody, .fleet-table tr, .fleet-table td { display: block; width: 100%; }
        .fleet-table tbody tr {
            margin: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }
        .fleet-table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
        }
        .fleet-table tbody td::before {
            content: attr(data-label);
            flex: 0 0 90px;
            color: #64748b;
            font-size: .72rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .fleet-table tbody td:last-child { border-bottom: 0; }
    }
</style>
@endsection

@section('content')
<div class="fleet-shell">
    <section class="fleet-head">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="fleet-title">
                <h1>Fleet Dashboard</h1>
                <p>Track live drivers, delivery load, gig capacity, and mappable locations in one operations view.</p>
            </div>
            <a href="{{ route('admin.gigs.index') }}" class="btn btn-primary fw-bold">
                <i class="fas fa-calendar-plus me-2"></i>Manage Gigs
            </a>
        </div>
    </section>

    <section class="fleet-card fleet-filter">
        <form method="GET" action="{{ route('admin.fleet.dashboard') }}" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-bold">Driver</label>
                <input type="search" name="driver" class="form-control" value="{{ request('driver') }}" placeholder="Search name or phone">
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold">Area</label>
                <select name="area_id" class="form-select">
                    <option value="">All Areas</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) request('area_id') === (string) $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">Online and offline</option>
                    <option value="online" @selected(request('status') === 'online')>Online only</option>
                    <option value="offline" @selected(request('status') === 'offline')>Offline only</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-2"></i>Filter</button>
                <a href="{{ route('admin.fleet.dashboard') }}" class="btn btn-light border">Reset</a>
            </div>
        </form>
    </section>

    <section class="fleet-stat-grid">
        <div class="fleet-stat" style="--stat-color:#2563eb;">
            <div><div class="fleet-stat-label">Visible Drivers</div><div class="fleet-stat-value">{{ number_format($stats['total_drivers'] ?? 0) }}</div></div>
            <div class="fleet-stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="fleet-stat" style="--stat-color:#10b981;">
            <div><div class="fleet-stat-label">Online</div><div class="fleet-stat-value">{{ number_format($stats['online_drivers'] ?? 0) }}</div></div>
            <div class="fleet-stat-icon"><i class="fas fa-signal"></i></div>
        </div>
        <div class="fleet-stat" style="--stat-color:#7c3aed;">
            <div><div class="fleet-stat-label">Booked Gigs</div><div class="fleet-stat-value">{{ number_format($stats['booked_gigs_today'] ?? 0) }}</div></div>
            <div class="fleet-stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="fleet-stat" style="--stat-color:#f97316;">
            <div><div class="fleet-stat-label">Open Gig Seats</div><div class="fleet-stat-value">{{ number_format($stats['available_gigs_today'] ?? 0) }}</div></div>
            <div class="fleet-stat-icon"><i class="fas fa-chair"></i></div>
        </div>
        <div class="fleet-stat" style="--stat-color:#ef4444;">
            <div><div class="fleet-stat-label">Active Deliveries</div><div class="fleet-stat-value">{{ number_format($stats['active_deliveries'] ?? 0) }}</div></div>
            <div class="fleet-stat-icon"><i class="fas fa-route"></i></div>
        </div>
    </section>

    <section class="fleet-grid">
        <div class="fleet-card">
            <div class="fleet-card-head">
                <div>
                    <h2>Live Driver Map</h2>
                    <p>{{ count($driverMarkers) }} drivers have usable coordinates.</p>
                </div>
                <span class="fleet-pill info"><i class="fas fa-location-dot"></i>Live positions</span>
            </div>
            <div id="fleetMap" class="fleet-map"></div>
            @if(empty($googleMapsApiKey))
                <div class="alert alert-info mx-3 mb-3">Add a Google Maps API key in map settings if the map does not render.</div>
            @endif
        </div>

        <div class="fleet-card">
            <div class="fleet-card-head">
                <div>
                    <h2>Driver Roster</h2>
                    <p>Capacity, online state, and assigned load.</p>
                </div>
            </div>
            <div class="driver-list">
                @forelse($drivers as $driver)
                    @php
                        $isOnline = $onlineDriverIds->contains($driver->id);
                        $duration = $driverOnlineDurations[$driver->id]['duration'] ?? null;
                        $startedAt = $driverOnlineDurations[$driver->id]['started_at'] ?? null;
                    @endphp
                    <div class="driver-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="driver-name">{{ $driver->name }}</div>
                                <div class="driver-muted">{{ $driver->phone ?: 'No phone' }}</div>
                                <div class="driver-muted">{{ $driver->deliveryArea?->name ?? 'Unassigned area' }}</div>
                            </div>
                            <span class="fleet-pill {{ $isOnline ? 'success' : '' }}">
                                <i class="fas fa-circle"></i>{{ $isOnline ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        <div class="row g-2 mt-3">
                            <div class="col-6"><div class="driver-muted">Active Orders</div><strong>{{ $driver->active_orders_count }}/{{ $driver->effective_max_active_orders }}</strong></div>
                            <div class="col-6"><div class="driver-muted">Booked Gigs</div><strong>{{ $driver->booked_gigs_count }}</strong></div>
                            <div class="col-6"><div class="driver-muted">Online For</div><strong>{{ $isOnline && $duration ? $duration : '-' }}</strong></div>
                            <div class="col-6"><div class="driver-muted">Since</div><strong>{{ $isOnline && $startedAt ? \Carbon\Carbon::parse($startedAt)->format('h:i A') : '-' }}</strong></div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted">No drivers found for the selected filters.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="fleet-card">
        <div class="fleet-card-head">
            <div>
                <h2>Today's Gig Slots</h2>
                <p>Slot coverage, capacity, incentives, and assigned drivers.</p>
            </div>
            <a href="{{ route('admin.gigs.index') }}" class="btn btn-sm btn-outline-primary">Open Gigs</a>
        </div>
        <div class="table-responsive">
            <table class="table fleet-table">
                <thead>
                    <tr>
                        <th>Gig Slot</th>
                        <th>Area</th>
                        <th>Time</th>
                        <th>Capacity</th>
                        <th>Incentive</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($todayGigs as $gig)
                        @php
                            $bookedCount = $gig->active_bookings_count ?? $gig->booked_count ?? 0;
                            $driverNames = $gig->bookings
                                ->whereIn('status', ['booked', 'completed'])
                                ->map(fn ($booking) => $booking->driver?->name)
                                ->filter()
                                ->values();
                            $statusClass = match ($gig->status) {
                                'available' => 'success',
                                'booked' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td data-label="Gig">
                                <div>
                                    <div class="driver-name">{{ $gig->title ?: 'Gig Slot #' . $gig->id }}</div>
                                    <div class="driver-muted">{{ $gig->min_login_minutes }} min login, {{ $gig->min_orders_required }} orders required</div>
                                </div>
                            </td>
                            <td data-label="Area">{{ $gig->area?->name ?? 'No area' }}</td>
                            <td data-label="Time">{{ optional($gig->start_time)->format('h:i A') }} - {{ optional($gig->end_time)->format('h:i A') }}</td>
                            <td data-label="Capacity">
                                <div>
                                    <strong>{{ $bookedCount }} / {{ $gig->capacity }}</strong>
                                    <div class="driver-muted">{{ $driverNames->isNotEmpty() ? $driverNames->join(', ') : 'Open for booking' }}</div>
                                </div>
                            </td>
                            <td data-label="Incentive"><strong>{{ $gig->estimated_earning }}</strong></td>
                            <td data-label="Status"><span class="fleet-pill {{ $statusClass }}">{{ ucfirst($gig->status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">No gig slots created for today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
let fleetDrivers = @json($driverMarkers);
const fleetMarkersUrl = @json(route('admin.fleet.markers'));
let fleetMap = null;
let fleetMapMarkers = [];
let fleetInfoWindows = [];

function escapeFleetText(value) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
}

function clearFleetMarkers() {
    fleetInfoWindows.forEach((infoWindow) => infoWindow.close());
    fleetMapMarkers.forEach((marker) => marker.setMap(null));
    fleetInfoWindows = [];
    fleetMapMarkers = [];
}

function renderFleetMarkers(drivers, fitBounds = false) {
    if (!fleetMap || typeof google === 'undefined') {
        return;
    }

    clearFleetMarkers();
    const bounds = new google.maps.LatLngBounds();
    let visibleDrivers = 0;

    drivers.forEach((driver) => {
        const lat = Number(driver.lat);
        const lng = Number(driver.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        const position = { lat, lng };
        const marker = new google.maps.Marker({
            position,
            map: fleetMap,
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
                <div style="min-width:220px">
                    <strong>${escapeFleetText(driver.name)}</strong><br>
                    <span>${escapeFleetText(driver.phone)}</span><br>
                    <span>${escapeFleetText(driver.area || 'Unassigned area')}</span><br>
                    <span>Status: ${driver.is_online ? 'Online' : 'Offline'}</span><br>
                    <span>Active orders: ${escapeFleetText(driver.active_orders_count ?? 0)}</span><br>
                    <span>Updated: ${escapeFleetText(driver.updated_at || 'recently')}</span>
                </div>
            `,
        });

        marker.addListener('click', () => infoWindow.open({ anchor: marker, map: fleetMap }));
        fleetMapMarkers.push(marker);
        fleetInfoWindows.push(infoWindow);
        bounds.extend(position);
        visibleDrivers += 1;
    });

    if (fitBounds && visibleDrivers > 1) {
        fleetMap.fitBounds(bounds);
    } else if (fitBounds && visibleDrivers === 1) {
        fleetMap.setCenter(bounds.getCenter());
        fleetMap.setZoom(13);
    }
}

async function refreshFleetMarkers() {
    try {
        const url = new URL(fleetMarkersUrl, window.location.origin);
        new URLSearchParams(window.location.search).forEach((value, key) => {
            url.searchParams.set(key, value);
        });

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        if (Array.isArray(payload.data)) {
            fleetDrivers = payload.data;
            renderFleetMarkers(fleetDrivers);
        }
    } catch (error) {
        console.warn('Fleet marker refresh failed', error);
    }
}

function initFleetMap() {
    const mapElement = document.getElementById('fleetMap');
    if (!mapElement || typeof google === 'undefined') {
        return;
    }

    const defaultCenter = fleetDrivers.length
        ? { lat: Number(fleetDrivers[0].lat), lng: Number(fleetDrivers[0].lng) }
        : { lat: 20.5937, lng: 78.9629 };

    fleetMap = new google.maps.Map(mapElement, {
        center: defaultCenter,
        zoom: fleetDrivers.length ? 11 : 5,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });

    renderFleetMarkers(fleetDrivers, true);
    window.setInterval(refreshFleetMarkers, 10000);
}

document.addEventListener('DOMContentLoaded', initFleetMap);
</script>
@endsection

