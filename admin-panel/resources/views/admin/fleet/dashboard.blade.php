@extends('layouts.admin')

@section('title', 'Fleet Dashboard')

@section('styles')
@if(empty($googleMapsApiKey))
@endif
<style>
    .fleet-map {
        height: 420px;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid var(--border);
        background: linear-gradient(135deg, #EFF6FF, #F8FAFC);
    }

    .fleet-filter-card,
    .fleet-panel {
        background: #fff;
        border-radius: 18px;
        border: 1px solid var(--border);
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.05);
    }

    .driver-row {
        border-bottom: 1px solid #eef2f7;
    }

    .driver-row:last-child {
        border-bottom: 0;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1>Fleet Dashboard</h1>
    <p>Track live delivery partners on map, filter by driver or area, and monitor gig readiness.</p>
</div>

<div class="fleet-filter-card p-4 mb-4">
    <form method="GET" action="{{ route('admin.fleet.dashboard') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Driver Search</label>
                <input type="text" name="driver" class="form-control" value="{{ request('driver') }}" placeholder="Name or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label">Area</label>
                <select name="area_id" class="form-select">
                    <option value="">All Areas</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" @selected((string) request('area_id') === (string) $area->id)>{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Online + Offline</option>
                    <option value="online" @selected(request('status') === 'online')>Online only</option>
                    <option value="offline" @selected(request('status') === 'offline')>Offline only</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="{{ route('admin.fleet.dashboard') }}" class="btn btn-light border">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Visible Drivers</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total_drivers']) }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Online Drivers</p>
            <h3 class="mb-0 fw-bold text-success">{{ number_format($stats['online_drivers']) }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Booked Gigs Today</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['booked_gigs_today']) }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Active Deliveries</p>
            <h3 class="mb-0 fw-bold text-primary">{{ number_format($stats['active_deliveries']) }}</h3>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="fleet-panel p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2 pt-2">
                <div>
                    <h5 class="mb-1 fw-bold">Live Driver Map</h5>
                    <div class="text-muted small">Admin can locate drivers by current position and area.</div>
                </div>
                <div class="small text-muted">{{ count($driverMarkers) }} drivers with mappable location</div>
            </div>
            <div id="fleetMap" class="fleet-map"></div>
            @if(empty($googleMapsApiKey))
                <div class="alert alert-info mt-3 mb-0">
                    Showing driver locations with Google Maps. Add a Google Maps API key in admin map settings if the map is unavailable.
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="fleet-panel">
            <div class="p-4 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold">Driver Roster</h5>
                        <div class="text-muted small">Searchable live operations list</div>
                    </div>
                    <a href="{{ route('admin.gigs.index') }}" class="btn btn-sm btn-primary">Manage Gigs</a>
                </div>
            </div>
            <div style="max-height: 520px; overflow-y: auto;">
                @forelse($drivers as $driver)
                    <div class="driver-row p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold">{{ $driver->name }}</div>
                                <div class="text-muted small">{{ $driver->phone }}</div>
                                <div class="text-muted small mt-1">{{ $driver->deliveryArea?->name ?? 'Unassigned area' }}</div>
                            </div>
                            <span class="badge {{ $onlineDriverIds->contains($driver->id) ? 'badge-success' : 'badge-secondary' }}">
                                {{ $onlineDriverIds->contains($driver->id) ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        <div class="row g-2 mt-3 small">
                            <div class="col-6">
                                <div class="text-muted">Logged In</div>
                                <div class="fw-semibold">
                                    @if($onlineDriverIds->contains($driver->id) && !empty($driverOnlineDurations[$driver->id]['duration']))
                                        {{ $driverOnlineDurations[$driver->id]['duration'] }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Booked Gigs</div>
                                <div class="fw-semibold">{{ $driver->booked_gigs_count }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Active Orders</div>
                                <div class="fw-semibold">{{ $driver->active_orders_count }}/{{ $driver->effective_max_active_orders }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Since</div>
                                <div class="fw-semibold">
                                    @if($onlineDriverIds->contains($driver->id) && !empty($driverOnlineDurations[$driver->id]['started_at']))
                                        {{ \Carbon\Carbon::parse($driverOnlineDurations[$driver->id]['started_at'])->format('h:i A') }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted">No drivers found for the selected filters.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="fleet-panel mt-4">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1 fw-bold">Today's Gig Slots</h5>
            <div class="text-muted small">Open, booked, and active slot coverage for the day</div>
        </div>
        <a href="{{ route('admin.gigs.index') }}" class="btn btn-outline-primary btn-sm">Open Gigs</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Gig Slot</th>
                    <th>Area</th>
                    <th>Time</th>
                    <th>Driver</th>
                    <th>Incentive</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($todayGigs as $gig)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $gig->title ?: 'Gig Slot #' . $gig->id }}</div>
                            <div class="small text-muted">{{ $gig->min_login_minutes }} min login, {{ $gig->min_orders_required }} orders, max {{ $gig->max_cancellations_allowed }} cancellations</div>
                        </td>
                        <td>{{ $gig->area?->name ?? 'No area' }}</td>
                        <td>{{ optional($gig->start_time)->format('h:i A') }} - {{ optional($gig->end_time)->format('h:i A') }}</td>
                        <td>{{ $gig->driver?->name ?? 'Open for booking' }}</td>
                        <td>{{ $gig->estimated_earning }}</td>
                        <td><span class="badge badge-{{ $gig->status === 'available' ? 'success' : ($gig->status === 'booked' ? 'primary' : ($gig->status === 'completed' ? 'info' : 'danger')) }}">{{ ucfirst($gig->status) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No gig slots created for today.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
    const fleetDrivers = @json($driverMarkers);

    function initFleetMap() {
        const mapElement = document.getElementById('fleetMap');
        if (!mapElement || typeof google === 'undefined') {
            return;
        }

        const defaultCenter = fleetDrivers.length
            ? {
                lat: Number(fleetDrivers[0].lat),
                lng: Number(fleetDrivers[0].lng),
            }
            : { lat: 20.5937, lng: 78.9629 };

        const map = new google.maps.Map(mapElement, {
            center: defaultCenter,
            zoom: fleetDrivers.length ? 11 : 5,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        const bounds = new google.maps.LatLngBounds();

        fleetDrivers.forEach((driver) => {
            const position = {
                lat: Number(driver.lat),
                lng: Number(driver.lng),
            };

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
                    <div style="min-width:220px;">
                        <strong>${driver.name}</strong><br>
                        <span>${driver.phone ?? ''}</span><br>
                        <span>${driver.area ?? 'Unassigned area'}</span><br>
                        <span>Status: ${driver.is_online ? 'Online' : 'Offline'}</span><br>
                        <span>Active orders: ${driver.active_orders_count}</span>
                    </div>
                `,
            });

            marker.addListener('click', () => infoWindow.open({ anchor: marker, map }));
            bounds.extend(position);
        });

        if (fleetDrivers.length > 1) {
            map.fitBounds(bounds);
        }
    }
    document.addEventListener('DOMContentLoaded', initFleetMap);
</script>
@endsection



