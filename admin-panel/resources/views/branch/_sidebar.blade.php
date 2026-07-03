@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'foodflow'));
    $appLogo = App\Models\AppSetting::getValue('app_logo');
    $branchUser = auth()->user();
    $currentBranch = $branch ?? app(App\Services\BranchManagementService::class)->branchForUser($branchUser);
    $branchMembership = $currentBranch
        ? \App\Models\BranchUser::where('branch_id', $currentBranch->id)->where('user_id', $branchUser->id)->where('is_active', true)->first()
        : null;
    $branchCan = function (array $permissions) use ($branchUser, $branchMembership) {
        if (empty($permissions)) {
            return true;
        }

        foreach ($permissions as $permission) {
            try {
                if ($branchUser->can($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $branchMembership && count(array_intersect($permissions, $branchMembership->permissions ?? [])) > 0;
    };

    $items = [
        ['route' => 'branch.dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-chart-line', 'permissions' => []],
        ['route' => 'branch.orders', 'label' => 'Orders', 'icon' => 'fas fa-box', 'permissions' => ['branch.orders.view', 'view_orders', 'manage_orders']],
        ['route' => 'branch.restaurants', 'label' => 'Restaurants', 'icon' => 'fas fa-store', 'permissions' => ['branch.restaurants.view', 'manage_restaurants']],
        ['route' => 'branch.drivers', 'label' => 'Drivers', 'icon' => 'fas fa-truck', 'permissions' => ['branch.drivers.view', 'manage_drivers']],
        ['route' => 'branch.zones', 'label' => 'Territories', 'icon' => 'fas fa-map-location-dot', 'permissions' => ['branch.zones.view', 'manage_zones']],
        ['route' => 'branch.wallet', 'label' => 'Wallet', 'icon' => 'fas fa-wallet', 'permissions' => ['branch.wallet.view', 'view_wallet', 'view_earnings']],
        ['route' => 'branch.settlements', 'label' => 'Settlements', 'icon' => 'fas fa-file-invoice-dollar', 'permissions' => ['branch.settlements.view', 'submit_settlement_requests', 'view_wallet']],
        ['route' => 'branch.reports', 'label' => 'Reports', 'icon' => 'fas fa-chart-pie', 'permissions' => ['branch.reports.view', 'view_reports']],
        ['route' => 'branch.settings', 'label' => 'Settings', 'icon' => 'fas fa-gear', 'permissions' => ['branch.settings.view', 'manage_staff']],
        ['route' => 'branch.tickets', 'label' => 'Support', 'icon' => 'fas fa-headset', 'permissions' => ['branch.tickets.view', 'manage_support_tickets', 'view_assigned_tasks']],
    ];
@endphp

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo-section">
        <div class="sidebar-logo-icon">
            @if($appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-code-branch"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>{{ $currentBranch?->name ?? 'Branch' }}</h2>
            <small>{{ $currentBranch?->code ?? $appName }}</small>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">
        <div class="sidebar-section-title">BRANCH</div>
        <ul class="sidebar-nav">
            @foreach($items as $item)
                @continue(! $branchCan($item['permissions']))
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
