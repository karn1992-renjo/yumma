@extends('layouts.admin')

@section('title', 'Seat Map — ' . $screen->name)

@section('styles')
<style>
    .smb-shell { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .smb-shell { grid-template-columns: 1fr; } }
    .smb-canvas-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; overflow: hidden; }
    .smb-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid rgba(15,23,42,.07); }
    .smb-toolbar .spacer { flex: 1 1 auto; }
    #seatSvgWrap { background: #0f172a; }
    #seatSvg { width: 100%; height: 560px; display: block; cursor: default; }
    .smb-seat { cursor: pointer; }
    .smb-seat.gap { cursor: pointer; opacity: .18; }
    .smb-handle { cursor: grab; }
    .smb-handle:active { cursor: grabbing; }
    .smb-row-card { border: 1px solid rgba(15,23,42,.08); border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; background: #f8fafc; }
    .smb-row-card .row-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .smb-row-card input[type="text"], .smb-row-card input[type="number"], .smb-row-card select {
        font-size: 12.5px; padding: 3px 6px; border-radius: 6px; border: 1px solid rgba(15,23,42,.15);
    }
    .smb-sidebar-card { background: #fff; border: 1px solid rgba(15,23,42,.08); border-radius: 16px; padding: 14px; margin-bottom: 16px; }
    .smb-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    #seatInfoBox { min-height: 22px; }
</style>
@endsection

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>{{ $screen->name }} — Seat Map</h1>
        <p>{{ $theatre->name }}. A seat here is only ever a delivery-location label — nothing about availability or booking is tracked.</p>
    </div>
    <a href="{{ route('theatre.screens') }}" class="btn btn-light"><i class="fas fa-arrow-left me-2"></i>All Screens</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form id="seatMapForm" action="{{ route('theatre.screens.seat-map.update', $screen) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="seat_map_config" id="seatMapConfigInput">

    <div class="smb-shell">
        <div class="smb-canvas-card">
            <div class="smb-toolbar">
                <button type="button" class="btn btn-sm btn-primary" id="addRowBtn"><i class="fas fa-plus me-1"></i>Add Row</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="previewToggleBtn"><i class="fas fa-eye me-1"></i>Preview as Customer</button>
                <span class="spacer"></span>
                <span id="seatInfoBox" class="text-white small"></span>
            </div>
            <div id="seatSvgWrap">
                <svg id="seatSvg" viewBox="0 0 800 560" preserveAspectRatio="xMidYMid meet"></svg>
            </div>
            <div class="p-3 border-top">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Seat Map</button>
                <span class="text-muted small ms-2">Drag the two end handles to move a row; drag the middle handle to curve it. Click a seat to mark it as an aisle gap.</span>
            </div>
        </div>

        <div>
            <div class="smb-sidebar-card">
                <h6 class="fw-bold mb-2">Sections</h6>
                <div id="sectionLegend" class="small mb-2"></div>
                <div class="d-flex gap-2">
                    <select id="bulkSectionSelect" class="form-select form-select-sm"></select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="applySectionBtn">Apply to checked rows</button>
                </div>
                <div class="form-text">Check rows below, pick a section, then apply. Sections are for display only.</div>
            </div>
            <div class="smb-sidebar-card">
                <h6 class="fw-bold mb-2">Rows</h6>
                <div id="rowList"></div>
                <div class="text-muted small" id="noRowsHint">No rows yet — click "Add Row".</div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var $ = function (id) { return document.getElementById(id); };

    var svg = $('seatSvg');
    var rowListEl = $('rowList');
    var noRowsHint = $('noRowsHint');
    var sectionLegendEl = $('sectionLegend');
    var bulkSectionSelect = $('bulkSectionSelect');
    var seatInfoBox = $('seatInfoBox');
    var hiddenInput = $('seatMapConfigInput');
    var previewBtn = $('previewToggleBtn');

    var DEFAULT_SECTIONS = {
        standard: { label: 'Standard', color: '#64748b' },
        executive: { label: 'Executive', color: '#3b82f6' },
        recliner: { label: 'Recliner', color: '#f97316' },
    };

    var existing = @json($screen->seat_map_config ?? null);
    var state = {
        rows: (existing && Array.isArray(existing.rows)) ? existing.rows : [],
        sections: (existing && existing.sections) ? existing.sections : DEFAULT_SECTIONS,
        previewMode: false,
    };
    var nextRowSeq = state.rows.length + 1;

    function uid() {
        return 'r' + Math.random().toString(36).slice(2, 9);
    }

    function toSvgPoint(evt) {
        var pt = svg.createSVGPoint();
        var touch = evt.touches && evt.touches.length ? evt.touches[0] : evt;
        pt.x = touch.clientX;
        pt.y = touch.clientY;
        return pt.matrixTransform(svg.getScreenCTM().inverse());
    }

    // -- Bezier seat placement -------------------------------------------
    function controlPoint(row) {
        var mx = (row.start.x + row.end.x) / 2;
        var my = (row.start.y + row.end.y) / 2;
        var dx = row.end.x - row.start.x;
        var dy = row.end.y - row.start.y;
        var len = Math.sqrt(dx * dx + dy * dy) || 1;
        var nx = -dy / len;
        var ny = dx / len;
        var offset = row.curve_offset || 0;
        return { x: mx + nx * offset, y: my + ny * offset };
    }

    function bezierPoint(row, t) {
        var p0 = row.start, p2 = row.end, p1 = controlPoint(row);
        var mt = 1 - t;
        return {
            x: mt * mt * p0.x + 2 * mt * t * p1.x + t * t * p2.x,
            y: mt * mt * p0.y + 2 * mt * t * p1.y + t * t * p2.y,
        };
    }

    function seatPositions(row) {
        var n = Math.max(1, row.seat_count | 0);
        var pts = [];
        for (var i = 0; i < n; i++) {
            var t = n === 1 ? 0.5 : (i + 0.5) / n;
            pts.push(bezierPoint(row, t));
        }
        return pts;
    }

    function seatLabelFor(row, index) {
        var gaps = row.gaps || [];
        if (gaps.indexOf(index) !== -1) return null;
        var seatNumber = 0;
        for (var i = 0; i <= index; i++) {
            if (gaps.indexOf(i) === -1) seatNumber++;
        }
        return row.label + seatNumber;
    }

    // -- Rendering ----------------------------------------------------------
    var dragging = null; // { rowId, point: 'start'|'end'|'curve' }

    function el(tag, attrs) {
        var node = document.createElementNS(SVG_NS, tag);
        for (var k in attrs) node.setAttribute(k, attrs[k]);
        return node;
    }

    function render() {
        while (svg.firstChild) svg.removeChild(svg.firstChild);

        state.rows.forEach(function (row) {
            var section = state.sections[row.section] || DEFAULT_SECTIONS.standard;
            var color = section.color || '#64748b';
            var cp = controlPoint(row);

            // guide curve
            svg.appendChild(el('path', {
                d: 'M ' + row.start.x + ' ' + row.start.y + ' Q ' + cp.x + ' ' + cp.y + ' ' + row.end.x + ' ' + row.end.y,
                stroke: 'rgba(255,255,255,.15)', fill: 'none', 'stroke-width': 2,
            }));

            // row label near start
            var labelPos = bezierPoint(row, 0.02);
            var labelText = el('text', { x: labelPos.x - 22, y: labelPos.y + 4, fill: '#94a3b8', 'font-size': 12, 'font-weight': 700 });
            labelText.textContent = row.label;
            svg.appendChild(labelText);

            // seats
            var positions = seatPositions(row);
            positions.forEach(function (pos, idx) {
                var isGap = (row.gaps || []).indexOf(idx) !== -1;
                var seat = el('circle', {
                    cx: pos.x, cy: pos.y, r: 8,
                    fill: isGap ? 'none' : color,
                    stroke: isGap ? 'rgba(255,255,255,.25)' : 'rgba(255,255,255,.4)',
                    'stroke-width': 1,
                    class: 'smb-seat' + (isGap ? ' gap' : ''),
                });
                seat.addEventListener('click', function (evt) {
                    evt.stopPropagation();
                    if (state.previewMode) {
                        if (isGap) return;
                        seatInfoBox.textContent = 'Selected seat: ' + seatLabelFor(row, idx);
                        return;
                    }
                    var i = (row.gaps || []).indexOf(idx);
                    row.gaps = row.gaps || [];
                    if (i === -1) row.gaps.push(idx); else row.gaps.splice(i, 1);
                    syncAndRender();
                });
                svg.appendChild(seat);
            });

            if (!state.previewMode) {
                // drag handles: start, end, curve midpoint
                [['start', row.start], ['end', row.end], ['curve', cp]].forEach(function (pair) {
                    var handle = el('circle', {
                        cx: pair[1].x, cy: pair[1].y, r: 6,
                        fill: pair[0] === 'curve' ? '#f97316' : '#22c55e',
                        stroke: '#fff', 'stroke-width': 1.5,
                        class: 'smb-handle',
                    });
                    handle.addEventListener('mousedown', function (evt) { dragging = { rowId: row.id, point: pair[0] }; evt.preventDefault(); });
                    handle.addEventListener('touchstart', function (evt) { dragging = { rowId: row.id, point: pair[0] }; evt.preventDefault(); }, { passive: false });
                    svg.appendChild(handle);
                });
            }
        });
    }

    svg.addEventListener('mousemove', onDragMove);
    svg.addEventListener('touchmove', onDragMove, { passive: false });
    window.addEventListener('mouseup', function () { dragging = null; syncHiddenField(); });
    window.addEventListener('touchend', function () { dragging = null; syncHiddenField(); });

    function onDragMove(evt) {
        if (!dragging) return;
        evt.preventDefault();
        var row = state.rows.find(function (r) { return r.id === dragging.rowId; });
        if (!row) return;
        var pt = toSvgPoint(evt);

        if (dragging.point === 'start') {
            row.start = { x: pt.x, y: pt.y };
        } else if (dragging.point === 'end') {
            row.end = { x: pt.x, y: pt.y };
        } else {
            // Recompute curve_offset from the dragged point's distance from the straight midpoint,
            // signed by which side of the row line it's on.
            var mx = (row.start.x + row.end.x) / 2;
            var my = (row.start.y + row.end.y) / 2;
            var dx = row.end.x - row.start.x;
            var dy = row.end.y - row.start.y;
            var len = Math.sqrt(dx * dx + dy * dy) || 1;
            var nx = -dy / len, ny = dx / len;
            var vx = pt.x - mx, vy = pt.y - my;
            row.curve_offset = vx * nx + vy * ny;
        }
        render();
    }

    // -- Sidebar row list -----------------------------------------------
    function renderRowList() {
        rowListEl.innerHTML = '';
        noRowsHint.style.display = state.rows.length ? 'none' : 'block';

        state.rows.forEach(function (row) {
            var card = document.createElement('div');
            card.className = 'smb-row-card';

            var top = document.createElement('div');
            top.className = 'row-top';

            var check = document.createElement('input');
            check.type = 'checkbox';
            check.dataset.rowId = row.id;
            check.className = 'row-select-check';

            var labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.value = row.label;
            labelInput.style.width = '48px';
            labelInput.addEventListener('input', function () { row.label = labelInput.value || row.label; syncAndRender(); });

            var seatCountInput = document.createElement('input');
            seatCountInput.type = 'number';
            seatCountInput.min = 1;
            seatCountInput.max = 60;
            seatCountInput.value = row.seat_count;
            seatCountInput.style.width = '58px';
            seatCountInput.addEventListener('input', function () {
                var n = parseInt(seatCountInput.value, 10);
                row.seat_count = (n >= 1 && n <= 60) ? n : row.seat_count;
                row.gaps = (row.gaps || []).filter(function (g) { return g < row.seat_count; });
                syncAndRender();
            });

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-sm btn-outline-danger ms-auto';
            delBtn.innerHTML = '<i class="fas fa-trash"></i>';
            delBtn.addEventListener('click', function () {
                state.rows = state.rows.filter(function (r) { return r.id !== row.id; });
                syncAndRender();
            });

            top.appendChild(check);
            top.appendChild(labelInput);
            top.appendChild(seatCountInput);
            top.appendChild(delBtn);
            card.appendChild(top);

            var sectionSelect = document.createElement('select');
            sectionSelect.className = 'form-select form-select-sm';
            Object.keys(state.sections).forEach(function (key) {
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = state.sections[key].label;
                if (row.section === key) opt.selected = true;
                sectionSelect.appendChild(opt);
            });
            sectionSelect.addEventListener('change', function () { row.section = sectionSelect.value; syncAndRender(); });
            card.appendChild(sectionSelect);

            rowListEl.appendChild(card);
        });
    }

    function renderSectionLegend() {
        sectionLegendEl.innerHTML = '';
        bulkSectionSelect.innerHTML = '';
        Object.keys(state.sections).forEach(function (key) {
            var s = state.sections[key];
            var row = document.createElement('div');
            row.innerHTML = '<span class="smb-legend-dot" style="background:' + s.color + '"></span>' + s.label;
            sectionLegendEl.appendChild(row);

            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = s.label;
            bulkSectionSelect.appendChild(opt);
        });
    }

    $('applySectionBtn').addEventListener('click', function () {
        var chosen = bulkSectionSelect.value;
        document.querySelectorAll('.row-select-check:checked').forEach(function (chk) {
            var row = state.rows.find(function (r) { return r.id === chk.dataset.rowId; });
            if (row) row.section = chosen;
        });
        syncAndRender();
    });

    $('addRowBtn').addEventListener('click', function () {
        var y = 60 + state.rows.length * 42;
        state.rows.push({
            id: uid(),
            label: String.fromCharCode(65 + ((nextRowSeq - 1) % 26)),
            seat_count: 10,
            start: { x: 80, y: y },
            end: { x: 720, y: y },
            curve_offset: 0,
            gaps: [],
            section: 'standard',
        });
        nextRowSeq++;
        syncAndRender();
    });

    previewBtn.addEventListener('click', function () {
        state.previewMode = !state.previewMode;
        previewBtn.classList.toggle('btn-outline-secondary', !state.previewMode);
        previewBtn.classList.toggle('btn-secondary', state.previewMode);
        previewBtn.innerHTML = state.previewMode
            ? '<i class="fas fa-pen me-1"></i>Back to Editing'
            : '<i class="fas fa-eye me-1"></i>Preview as Customer';
        seatInfoBox.textContent = state.previewMode ? 'Click a seat to see its label, just like a customer would.' : '';
        render();
    });

    function syncHiddenField() {
        hiddenInput.value = JSON.stringify({ rows: state.rows, sections: state.sections });
    }

    function syncAndRender() {
        renderRowList();
        renderSectionLegend();
        render();
        syncHiddenField();
    }

    $('seatMapForm').addEventListener('submit', syncHiddenField);

    syncAndRender();
})();
</script>
@endsection
