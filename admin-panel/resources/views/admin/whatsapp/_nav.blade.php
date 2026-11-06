@php
    $waNav = [
        ['admin.whatsapp.settings', 'fas fa-gear', 'Settings'],
        ['admin.whatsapp.inbox', 'fas fa-inbox', 'Inbox'],
        ['admin.whatsapp.broadcasts', 'fas fa-bullhorn', 'Broadcasts'],
        ['admin.whatsapp.reports', 'fas fa-chart-column', 'Reports'],
        ['admin.whatsapp.messages', 'fas fa-list', 'Message log'],
    ];
    $waWaiting = \App\Models\WhatsAppContact::where('bot_state', 'human')->count();
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($waNav as [$route, $icon, $label])
        <a href="{{ route($route) }}" class="btn btn-sm {{ request()->routeIs($route) ? 'btn-success' : 'btn-outline-secondary' }}">
            <i class="{{ $icon }} me-1"></i>{{ $label }}
            @if($route === 'admin.whatsapp.inbox' && $waWaiting > 0)<span class="badge bg-danger ms-1">{{ $waWaiting }}</span>@endif
        </a>
    @endforeach
</div>
