@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'foodflow'));
    $appLogo = App\Models\AppSetting::getValue('app_logo');
    $theatreUser = auth()->user();
    $currentTheatre = $theatre ?? app(App\Services\TheatreManagementService::class)->theatreForUser($theatreUser);
    $theatreMembership = $currentTheatre
        ? \App\Models\TheatreUser::where('theatre_id', $currentTheatre->id)->where('user_id', $theatreUser->id)->where('is_active', true)->first()
        : null;
    $theatreCan = function (string $permission) use ($theatreUser, $theatreMembership) {
        try {
            if ($theatreUser->can($permission)) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to the membership check below
        }

        return $theatreMembership && in_array($permission, $theatreMembership->permissions ?? [], true);
    };

    $items = [
        ['route' => 'theatre.dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-chart-line', 'permission' => 'theatre.dashboard.view'],
        ['route' => 'theatre.restaurants', 'label' => 'Restaurants', 'icon' => 'fas fa-store', 'permission' => 'theatre.restaurants.view'],
        ['route' => 'theatre.screens', 'label' => 'Screens', 'icon' => 'fas fa-film', 'permission' => 'theatre.screens.view'],
        ['route' => 'theatre.shows', 'label' => 'Show Times', 'icon' => 'fas fa-clock', 'permission' => 'theatre.shows.view'],
        ['route' => 'theatre.runners', 'label' => 'Runners', 'icon' => 'fas fa-person-walking', 'permission' => 'theatre.runners.view'],
        ['route' => 'theatre.orders', 'label' => 'Orders', 'icon' => 'fas fa-box', 'permission' => 'theatre.orders.view'],
    ];
@endphp

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo-section">
        <div class="sidebar-logo-icon">
            @if($appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-film"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>{{ $currentTheatre?->name ?? 'Theatre' }}</h2>
            <small>{{ $currentTheatre?->code ?? $appName }}</small>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">
        <div class="sidebar-section-title">THEATRE</div>
        <ul class="sidebar-nav">
            @foreach($items as $item)
                @continue(! $theatreCan($item['permission']))
                <li class="sidebar-nav-item">
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-nav-link {{ request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*') ? 'active' : '' }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sidebar-section-title">ACCOUNT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="sidebar-nav-link w-100 border-0 bg-transparent text-start">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>
