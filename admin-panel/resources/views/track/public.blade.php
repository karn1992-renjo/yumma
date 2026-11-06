<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Track order #{{ $order->order_number }} · {{ $appName }}</title>
    <style>
        :root { --brand: {{ $brand }}; --bg: #f6f7fb; --card: #fff; --ink: #1c2434; --muted: #6b7280; --line: #e5e7eb; --ok: #16a34a; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f1420; --card: #181f2e; --ink: #eef1f7; --muted: #9aa3b5; --line: #2a3347; } }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--ink); display: flex; flex-direction: column; }
        header { padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top)); background: var(--card); border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        header b { font-size: 15px; } header span { color: var(--muted); font-size: 12px; }
        #map { flex: 1; min-height: 260px; background: #e9edf3; }
        .nomap { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; color: var(--muted); padding: 24px; }
        .panel { background: var(--card); border-top: 1px solid var(--line); padding: 14px 16px; padding-bottom: max(14px, env(safe-area-inset-bottom)); border-radius: 18px 18px 0 0; margin-top: -14px; position: relative; box-shadow: 0 -6px 20px rgba(0,0,0,.08); }
        .status { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
        .status h1 { font-size: 20px; margin: 0; }
        .eta { font-weight: 700; color: var(--brand); white-space: nowrap; }
        .sub { color: var(--muted); font-size: 13px; margin: 4px 0 0; }
        .steps { display: flex; margin: 14px 0 4px; }
        .step { flex: 1; text-align: center; font-size: 10.5px; color: var(--muted); position: relative; }
        .step i { display: block; width: 14px; height: 14px; margin: 0 auto 5px; border-radius: 50%; background: var(--line); position: relative; z-index: 1; }
        .step::before { content: ''; position: absolute; top: 6px; left: -50%; width: 100%; height: 2px; background: var(--line); }
        .step:first-child::before { display: none; }
        .step.done { color: var(--ink); } .step.done i, .step.done::before { background: var(--ok); }
        .step.now i { box-shadow: 0 0 0 5px rgba(22,163,74,.2); }
        .rider { display: flex; align-items: center; gap: 10px; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); font-size: 14px; }
        .rider .dot { width: 34px; height: 34px; border-radius: 50%; background: var(--brand); color: #fff; display: grid; place-items: center; font-size: 17px; }
        .stale { color: #b45309; font-size: 12px; }
        .banner { padding: 10px 16px; font-size: 14px; font-weight: 600; text-align: center; }
        .banner.cancel { background: #fee2e2; color: #991b1b; } .banner.ok { background: #dcfce7; color: #166534; }
        footer { text-align: center; font-size: 11px; color: var(--muted); padding: 6px 0 10px; }
    </style>
</head>
<body>
<header><div><b>{{ $appName }}</b><br><span>Order #{{ $order->order_number }} · {{ $order->restaurant->name ?? '' }}</span></div><span id="updated">Live</span></header>
<div id="banner" class="banner" style="display:none"></div>
@if($mapsKey)
    <div id="map"></div>
@else
    <div class="nomap" id="nomap">Live map is not configured. Your status and rider updates still appear below.</div>
@endif

<section class="panel">
    <div class="status"><h1 id="label">{{ ucwords(str_replace('_', ' ', $order->status)) }}</h1><div class="eta" id="eta"></div></div>
    <p class="sub" id="sub">Updating live…</p>
    <div class="steps" id="steps">
        @foreach($steps as $i => $s)<div class="step" data-i="{{ $i }}"><i></i>{{ $s['label'] }}</div>@endforeach
    </div>
    <div class="rider" id="rider" style="display:none"><div class="dot">🛵</div><div><b id="riderName"></b> is bringing your order<div class="stale" id="stale"></div></div></div>
</section>
<footer>Link expires automatically · {{ $appName }}</footer>

<script>
const DATA_URL = @json($dataUrl);
const HAS_MAP = @json((bool) $mapsKey);
let map, markers = {}, fitted = false, timer;

function icon(emoji, color) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44"><circle cx="22" cy="22" r="19" fill="${color}" stroke="#fff" stroke-width="3"/><text x="22" y="29" font-size="20" text-anchor="middle">${emoji}</text></svg>`;
    return { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize: new google.maps.Size(40, 40), anchor: new google.maps.Point(20, 20) };
}
function put(key, pos, emoji, color, title) {
    if (!pos || pos.lat == null) { if (markers[key]) { markers[key].setMap(null); delete markers[key]; } return; }
    const p = { lat: pos.lat, lng: pos.lng };
    if (!markers[key]) markers[key] = new google.maps.Marker({ map, position: p, icon: icon(emoji, color), title });
    else markers[key].setPosition(p);
}
window.initMap = function () {
    map = new google.maps.Map(document.getElementById('map'), { center: { lat: 20.59, lng: 78.96 }, zoom: 5, disableDefaultUI: true, zoomControl: true });
    poll();
};
function paint(d) {
    document.getElementById('label').textContent = d.label;
    document.getElementById('eta').textContent = d.eta_min ? 'about ' + d.eta_min + ' min' : '';
    document.querySelectorAll('.step').forEach(el => {
        const i = +el.dataset.i;
        el.classList.toggle('done', i <= d.step && !d.cancelled);
        el.classList.toggle('now', i === d.step && !d.delivered && !d.cancelled);
    });
    const banner = document.getElementById('banner');
    banner.style.display = d.cancelled || d.delivered ? '' : 'none';
    banner.className = 'banner ' + (d.cancelled ? 'cancel' : 'ok');
    banner.textContent = d.cancelled ? 'This order was cancelled.' : 'Delivered. Enjoy your meal! 🎉';
    document.getElementById('sub').textContent = d.cancelled ? '' : d.delivered ? '' :
        d.rider ? 'Your rider is on the way.' : (d.step >= 2 ? 'The restaurant is preparing your food.' : 'Waiting for the restaurant to accept.');
    const r = document.getElementById('rider');
    r.style.display = d.rider ? '' : 'none';
    if (d.rider) {
        document.getElementById('riderName').textContent = d.rider.name || 'Your rider';
        document.getElementById('stale').textContent = d.rider.stale ? 'Last location was a few minutes ago' : '';
    }
    document.getElementById('updated').textContent = 'Updated ' + new Date(d.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    if (HAS_MAP && map) {
        put('rest', d.restaurant, '🍴', '#f59e0b', d.restaurant.name);
        put('drop', d.drop, '🏠', '#2563eb', 'Delivery address');
        put('rider', d.rider, '🛵', '#16a34a', 'Rider');
        const pts = Object.values(markers).map(m => m.getPosition());
        if (pts.length && (!fitted || d.rider)) {
            const b = new google.maps.LatLngBounds(); pts.forEach(p => b.extend(p));
            map.fitBounds(b, 60); if (pts.length === 1) map.setZoom(15); fitted = true;
        }
    }
    if (d.delivered || d.cancelled) clearInterval(timer);
}
async function poll() {
    try {
        const res = await fetch(DATA_URL, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(res.status);
        paint(await res.json());
    } catch (e) { document.getElementById('updated').textContent = 'Reconnecting…'; }
}
if (!HAS_MAP) poll();
timer = setInterval(poll, 8000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
</script>
@if($mapsKey)
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&callback=initMap"></script>
@endif
</body>
</html>
