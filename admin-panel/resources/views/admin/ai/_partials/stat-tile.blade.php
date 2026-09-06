@php
    $tileColorMap = [
        'primary' => '#7c3aed', 'success' => '#22c55e', 'info' => '#3b82f6',
        'warning' => '#f59e0b', 'danger' => '#ef4444', 'secondary' => '#64748b',
    ];
    $tileAccent = $tileColorMap[$color ?? 'primary'] ?? '#7c3aed';
    $tileIcon = $icon ?? 'fa-chart-line';
@endphp
<div class="ai-kpi-card" style="--accent: {{ $tileAccent }}; --card-glow: {{ $tileAccent }}26;">
    <div class="ai-kpi-top">
        <div class="ai-kpi-icon"><i class="fas {{ $tileIcon }}"></i></div>
    </div>
    <div class="ai-kpi-label">{{ $label }}</div>
    <div class="ai-kpi-value">{{ $value }}</div>
    @isset($hint)
        <div class="ai-kpi-hint">{{ $hint }}</div>
    @endisset
</div>
