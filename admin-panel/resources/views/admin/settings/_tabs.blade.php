@php
    $settingsTabs = [
        ['route' => 'admin.settings.index', 'icon' => 'fas fa-sliders-h', 'label' => 'General'],
        ['route' => 'admin.settings.branding', 'icon' => 'fas fa-palette', 'label' => 'Branding'],
        ['route' => 'admin.settings.payment', 'icon' => 'fas fa-credit-card', 'label' => 'Payment'],
        ['route' => 'admin.settings.rewards', 'icon' => 'fas fa-gift', 'label' => 'Rewards'],
        ['route' => 'admin.settings.homepage', 'icon' => 'fas fa-home', 'label' => 'Homepage'],
        ['route' => 'admin.settings.privacy', 'icon' => 'fas fa-shield-alt', 'label' => 'Legal'],
        ['route' => 'admin.settings.driver_assignment', 'icon' => 'fas fa-route', 'label' => 'Drivers'],
        ['route' => 'admin.settings.communication', 'icon' => 'fas fa-envelope', 'label' => 'Communication'],
        ['route' => 'admin.settings.notifications', 'icon' => 'fas fa-bell', 'label' => 'Notifications'],
        ['route' => 'admin.settings.map', 'icon' => 'fas fa-map-marked-alt', 'label' => 'Map'],
        ['route' => 'admin.settings.cron', 'icon' => 'fas fa-clock', 'label' => 'Cron'],
    ];
@endphp

<nav class="settings-tabs" aria-label="Settings sections">
    @foreach($settingsTabs as $settingsTab)
        @continue(! \Illuminate\Support\Facades\Route::has($settingsTab['route']))

        <a
            href="{{ route($settingsTab['route']) }}"
            class="settings-tab {{ request()->routeIs($settingsTab['route']) ? 'active' : '' }}"
        >
            <i class="{{ $settingsTab['icon'] }}"></i>
            <span>{{ $settingsTab['label'] }}</span>
        </a>
    @endforeach
</nav>
