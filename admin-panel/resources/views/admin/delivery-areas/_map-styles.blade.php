{{-- Delivery-area form styles (loaded into <head> via @section('styles')). --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    .da-page { max-width: 1200px; }
    .da-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
    .da-title { font-size:1.4rem; font-weight:700; margin:0; }
    .da-sub { margin:.15rem 0 0; color:var(--m3-text-muted,#6c757d); }
    .da-back { white-space:nowrap; }

    .da-grid { display:grid; grid-template-columns: minmax(320px, 420px) 1fr; gap:1.25rem; align-items:start; }
    @media (max-width: 1100px) { .da-grid { grid-template-columns: 1fr; } }

    .da-card {
        background: var(--m3-surface-2, #fff);
        border: 1px solid var(--m3-outline, #e5e7eb);
        border-radius: var(--m3-r-lg, 18px);
        box-shadow: var(--m3-elev-1, 0 1px 2px rgba(0,0,0,.06));
        overflow: hidden;
    }
    .da-card-head {
        display:flex; align-items:center; gap:.6rem;
        padding: .9rem 1.15rem; font-weight:700;
        border-bottom: 1px solid var(--m3-outline, #eee);
        color: var(--m3-text-strong, #111);
    }
    .da-card-head i { color: var(--primary, #ff5a1f); }
    .da-card-body { padding: 1.15rem; }
    .da-card-foot { padding: 1rem 1.15rem; border-top:1px solid var(--m3-outline,#eee); display:flex; gap:.6rem; }
    .da-coverage { position: sticky; top: 1rem; }
    @media (max-width: 1100px) { .da-coverage { position: static; } }

    .da-inset { border:1px solid var(--m3-outline,#e5e7eb); border-radius:14px; padding:.9rem; background:var(--m3-tint-input, #f8fafc); }
    .da-mini-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--m3-text-muted,#6c757d); font-weight:700; margin-bottom:.15rem; display:block; }

    /* segmented control */
    .da-seg { display:flex; gap:.35rem; background:var(--m3-tint-input,#f1f3f5); padding:.3rem; border-radius:999px; }
    .da-seg-btn {
        flex:1; border:0; background:transparent; padding:.55rem .8rem; border-radius:999px;
        font-weight:600; color:var(--m3-text-muted,#555); cursor:pointer; transition:all .15s ease;
    }
    .da-seg-btn.is-active {
        background: var(--m3-surface-2, #fff); color: var(--primary, #ff5a1f);
        box-shadow: 0 1px 4px rgba(0,0,0,.12);
    }
    .da-seg-btn:disabled { opacity:.45; cursor:not-allowed; }
    .da-seg-btn i { margin-right:.35rem; }

    .da-search .input-group-text { background:var(--m3-tint-input,#f8fafc); border-color:var(--m3-outline,#e5e7eb); }

    .da-map-wrap { position:relative; border-radius:14px; overflow:hidden; border:1px solid var(--m3-outline,#e5e7eb); }
    #areaMap { height: 460px; width:100%; background:#eef1f4; z-index:0; }
    #areaMap.is-drawing { cursor: crosshair; }
    .da-map-toolbar {
        position:absolute; top:10px; left:10px; z-index:500;
        display:flex; gap:.4rem; background:rgba(255,255,255,.94);
        padding:.35rem; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.12);
    }
    .da-map-hint {
        position:absolute; left:10px; right:10px; bottom:10px; z-index:500;
        background:rgba(17,17,17,.8); color:#fff; font-size:.82rem;
        padding:.5rem .75rem; border-radius:10px; pointer-events:none;
        opacity:0; transform:translateY(6px); transition:all .2s ease;
    }
    .da-map-hint.show { opacity:1; transform:none; }
    .da-map-fallback { padding:1.25rem; }

    .da-chip {
        font-size:.78rem; font-weight:700; color:var(--primary,#ff5a1f);
        background: color-mix(in srgb, var(--primary,#ff5a1f) 12%, transparent);
        padding:.15rem .6rem; border-radius:999px;
    }
    .da-points { max-height:170px; overflow-y:auto; border:1px solid var(--m3-outline,#e5e7eb); border-radius:12px; padding:.5rem; background:var(--m3-tint-input,#f8fafc); }
    .da-point-row { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.35rem .5rem; border-radius:8px; font-size:.8rem; }
    .da-point-row + .da-point-row { margin-top:.2rem; }
    .da-point-row:hover { background: color-mix(in srgb, var(--primary,#ff5a1f) 8%, transparent); }
    .da-point-row .idx { font-weight:700; color:var(--primary,#ff5a1f); min-width:1.4rem; }
    .da-point-row .rm { border:0; background:transparent; color:#dc3545; cursor:pointer; font-size:1rem; line-height:1; }

    /* leaflet markers (divIcon, no external images) */
    .da-vtx { display:flex; align-items:center; justify-content:center;
        width:22px; height:22px; border-radius:50%; background:var(--primary,#ff5a1f);
        color:#fff; font-size:11px; font-weight:700; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.4); }
    .da-ctr { width:16px; height:16px; border-radius:50%; background:var(--primary,#ff5a1f); border:3px solid #fff; box-shadow:0 1px 5px rgba(0,0,0,.45); }
    .da-handle { width:14px; height:14px; border-radius:50%; background:#fff; border:3px solid var(--primary,#ff5a1f); box-shadow:0 1px 4px rgba(0,0,0,.35); }

    .pac-container { z-index: 20000 !important; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,.18); border:0; margin-top:4px; }
</style>
