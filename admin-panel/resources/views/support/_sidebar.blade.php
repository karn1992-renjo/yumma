@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'foodflow'));
    $appLogo = App\Models\AppSetting::getValue('app_logo');

    // Admin can grant this role permissions well beyond support tickets via
    // admin.roles.* -- this list mirrors admin.partials.sidebar's permission
    // mapping (see RolePermissionController::PERMISSION_GROUPS) so whatever
    // gets granted actually shows up as a link here too, not just at a URL
    // the user has to know to type in.
    $extraNav = array_values(array_filter([
        ['label' => 'Dashboard', 'icon' => 'chart-line', 'url' => route('admin.dashboard'), 'routes' => ['admin.dashboard'], 'perm' => ['view_dashboard']],
        ['label' => 'Live Orders', 'icon' => 'bolt', 'url' => route('admin.orders.live'), 'routes' => ['admin.orders.live'], 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
        ['label' => 'Orders', 'icon' => 'box', 'url' => route('admin.orders.index'), 'routes' => ['admin.orders.*'], 'perm' => ['view_orders', 'create_orders', 'update_order_status', 'cancel_orders', 'view_order_details', 'manage_orders']],
        ['label' => 'Restaurants', 'icon' => 'store', 'url' => route('admin.restaurants.index'), 'routes' => ['admin.restaurants.*'], 'perm' => ['view_restaurants', 'create_restaurants', 'edit_restaurants', 'delete_restaurants']],
        ['label' => 'Users', 'icon' => 'users', 'url' => route('admin.users.index'), 'routes' => ['admin.users.*'], 'perm' => ['view_users', 'create_users', 'edit_users', 'delete_users', 'block_users', 'manage_users']],
        ['label' => 'Drivers', 'icon' => 'truck-fast', 'url' => route('admin.drivers.index'), 'routes' => ['admin.drivers.*'], 'perm' => ['view_drivers', 'create_drivers', 'edit_drivers', 'delete_drivers', 'assign_drivers', 'manage_drivers']],
        ['label' => 'Driver Gigs', 'icon' => 'calendar-alt', 'url' => route('admin.gigs.index'), 'routes' => ['admin.gigs.*'], 'perm' => ['view_gigs', 'create_gigs', 'edit_gigs', 'delete_gigs', 'book_gigs', 'manage_gigs']],
        ['label' => 'Payouts', 'icon' => 'money-bill-wave', 'url' => route('admin.payouts.index'), 'routes' => ['admin.payouts.*', 'admin.wallets.*', 'admin.gift-cards.*'], 'perm' => ['view_payouts', 'create_payouts', 'process_payouts', 'approve_payouts', 'manage_payouts']],
        ['label' => 'Banners', 'icon' => 'image', 'url' => route('admin.banners.index'), 'routes' => ['admin.banners.*'], 'perm' => ['view_banners', 'create_banners', 'edit_banners', 'delete_banners', 'manage_banners']],
        ['label' => 'Promotions', 'icon' => 'tags', 'url' => route('admin.promotion-engine.index'), 'routes' => ['admin.promotion-engine.*'], 'perm' => ['view_promos', 'create_promos', 'edit_promos', 'delete_promos', 'manage_promos']],
        ['label' => 'Global Categories', 'icon' => 'layer-group', 'url' => route('admin.global-menu-categories.index'), 'routes' => ['admin.global-menu-categories.*'], 'perm' => ['view_global_categories', 'create_global_categories', 'edit_global_categories', 'delete_global_categories']],
        ['label' => 'Listed Menu', 'icon' => 'utensils', 'url' => route('admin.listed-menu.index'), 'routes' => ['admin.listed-menu.*'], 'perm' => ['view_listed_menu', 'create_listed_menu', 'edit_listed_menu', 'delete_listed_menu']],
        ['label' => 'Global Menu Items', 'icon' => 'list-check', 'url' => route('admin.master-menu-items.index'), 'routes' => ['admin.master-menu-items.*'], 'perm' => ['view_menu_items', 'create_menu_items', 'edit_menu_items', 'delete_menu_items', 'manage_menu']],
        ['label' => 'Partner Applications', 'icon' => 'handshake', 'url' => route('admin.partner-applications.index'), 'routes' => ['admin.partner-applications.*'], 'perm' => ['view_partner_applications', 'manage_partner_applications']],
        ['label' => 'Reports', 'icon' => 'chart-pie', 'url' => route('admin.analytics'), 'routes' => ['admin.analytics', 'admin.reports.*'], 'perm' => ['view_reports', 'export_reports', 'view_sales_report', 'view_earning_report']],
        ['label' => 'Branch Management', 'icon' => 'code-branch', 'url' => route('admin.branches.index'), 'routes' => ['admin.branches*'], 'perm' => ['manage_branches', 'manage_branch_restaurants', 'manage_branch_drivers', 'manage_branch_zones', 'manage_branch_staff']],
        ['label' => 'Settings', 'icon' => 'gear', 'url' => route('admin.settings.index'), 'routes' => ['admin.settings.*'], 'perm' => ['view_settings', 'update_settings', 'manage_settings', 'manage_app_branding', 'manage_payment_gateways']],
        app(\App\Services\Ai\AiSettingsService::class)->bool('ai_enabled')
            ? ['label' => 'AI Control Center', 'icon' => 'brain', 'url' => route('admin.ai.index'), 'routes' => ['admin.ai.*'], 'perm' => ['ai.view', 'ai.configure', 'ai.autonomy.change', 'ai.decisions.view', 'ai.actions.execute', 'ai.approvals.manage', 'ai.finance.view', 'ai.accounting.view', 'ai.cost.view', 'ai.kill_switch']]
            : null,
    ]));

    $extraNav = array_filter($extraNav, fn ($item) => auth()->user()->hasAnyPermission($item['perm']));
@endphp

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo-section">
        <div class="sidebar-logo-icon">
            @if($appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-headset"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>Support Desk</h2>
            <small>{{ $appName }}</small>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">
        <div class="sidebar-section-title">SUPPORT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('support.index') }}"
                   class="sidebar-nav-link {{ request()->routeIs('support.index') || request()->routeIs('support.show') ? 'active' : '' }}">
                    <i class="fas fa-headset"></i>
                    <span>Ticket Queue</span>
                </a>
            </li>
        </ul>

        @if($extraNav)
            <div class="sidebar-section-title">GRANTED ACCESS</div>
            <ul class="sidebar-nav">
                @foreach($extraNav as $item)
                    <li class="sidebar-nav-item">
                        <a href="{{ $item['url'] }}"
                           class="sidebar-nav-link {{ request()->routeIs($item['routes']) ? 'active' : '' }}">
                            <i class="fas fa-{{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

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
