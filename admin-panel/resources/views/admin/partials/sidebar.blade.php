@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
    $appLogo = App\Models\AppSetting::getValue('app_logo');
    $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
    $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text'], true) ? $headerBrandingType : 'text';
    $appLogoUrl = $appLogo && str_starts_with($appLogo, 'branding/')
        ? route('media.branding', ['file' => basename($appLogo)])
        : ($appLogo ? \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) : null);

    $isActive = function ($patterns) {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $pendingOrdersCount = \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count();
    $pendingPartnerApplications = \App\Models\PartnerApplication::where('status', 'pending')->count();
    $pendingRestaurantApprovals = \App\Models\RestaurantLocationChangeRequest::where('status', 'pending')->count();
    $pendingDiningBookings = \App\Models\DiningBooking::where('status', 'pending')->count();
    $openTicketsCount = \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

    $badge = fn ($count) => $count > 99 ? '99+' : $count;

    $sections = [
        [
            'label' => 'Main',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'chart-line', 'url' => route('admin.dashboard'), 'active' => ['admin.dashboard']],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                [
                    'label' => 'Branch Management',
                    'icon' => 'code-branch',
                    'active' => ['admin.branches*'],
                    'children' => [
                        ['label' => 'All Branches', 'url' => route('admin.branches.index'), 'active' => ['admin.branches.index']],
                        ['label' => 'Create Branch', 'url' => route('admin.branches.create'), 'active' => ['admin.branches.create']],
                        ['label' => 'Branch Users', 'url' => route('admin.branches.users'), 'active' => ['admin.branches.users']],
                        ['label' => 'Branch Wallets', 'url' => route('admin.branches.wallets'), 'active' => ['admin.branches.wallets']],
                        ['label' => 'Settlements', 'url' => route('admin.branches.settlements'), 'active' => ['admin.branches.settlements']],
                        ['label' => 'Payouts', 'url' => route('admin.branches.payouts'), 'active' => ['admin.branches.payouts']],
                        ['label' => 'Reports', 'url' => route('admin.branches.reports'), 'active' => ['admin.branches.reports']],
                        ['label' => 'Territories', 'url' => route('admin.branches.zones'), 'active' => ['admin.branches.zones']],
                        ['label' => 'Audit Logs', 'url' => route('admin.branches.audit-logs'), 'active' => ['admin.branches.audit-logs']],
                    ],
                ],
                ['label' => 'Restaurants', 'icon' => 'store', 'url' => route('admin.restaurants.index'), 'active' => ['admin.restaurants.*']],
                ['label' => 'Delivery Areas', 'icon' => 'map-location-dot', 'url' => route('admin.delivery-areas.index'), 'active' => ['admin.delivery-areas.*']],
                ['label' => 'Orders', 'icon' => 'box', 'url' => route('admin.orders.index'), 'active' => ['admin.orders.*'], 'badge' => $pendingOrdersCount ? $badge($pendingOrdersCount) : null],
                ['label' => 'POS Dashboard', 'icon' => 'cash-register', 'url' => route('admin.pos.index'), 'active' => ['admin.pos.*']],
                ['label' => 'Users', 'icon' => 'users', 'url' => route('admin.users.index'), 'active' => ['admin.users.*']],
                ['label' => 'Drivers', 'icon' => 'truck-fast', 'url' => route('admin.drivers.index'), 'active' => ['admin.drivers.*']],
                ['label' => 'Fleet Dashboard', 'icon' => 'route', 'url' => route('admin.fleet.dashboard'), 'active' => ['admin.fleet.*']],
                ['label' => 'Partner Applications', 'icon' => 'handshake', 'url' => route('admin.partner-applications.index'), 'active' => ['admin.partner-applications.*'], 'badge' => $pendingPartnerApplications ? $badge($pendingPartnerApplications) : null],
            ],
        ],
        [
            'label' => 'Catalog',
            'items' => [
                ['label' => 'Banners', 'icon' => 'image', 'url' => route('admin.banners.index'), 'active' => ['admin.banners.*']],
                ['label' => 'Cuisines', 'icon' => 'egg', 'url' => route('admin.cuisines.index'), 'active' => ['admin.cuisines.*']],
                ['label' => 'Global Categories', 'icon' => 'layer-group', 'url' => route('admin.global-menu-categories.index'), 'active' => ['admin.global-menu-categories.*']],
                ['label' => 'Listed Menu', 'icon' => 'utensils', 'url' => route('admin.listed-menu.index'), 'active' => ['admin.listed-menu.*']],
                ['label' => 'Global Menu Items', 'icon' => 'list-check', 'url' => url('/admin/master-menu-items'), 'active' => ['admin.master-menu-items.index', 'admin.master-menu-items.edit']],
                ['label' => 'Add Global Item', 'icon' => 'plus-circle', 'url' => url('/admin/master-menu-items/create'), 'active' => ['admin.master-menu-items.create']],
            ],
        ],
        [
            'label' => 'Finance',
            'items' => [
                ['label' => 'Payouts', 'icon' => 'money-bill-wave', 'url' => route('admin.payouts.index'), 'active' => ['admin.payouts.index']],
                ['label' => 'Restaurant Approvals', 'icon' => 'clipboard-check', 'url' => route('admin.restaurant-approvals.index'), 'active' => ['admin.restaurant-approvals.*'], 'badge' => $pendingRestaurantApprovals ? $badge($pendingRestaurantApprovals) : null],
                ['label' => 'Wallets', 'icon' => 'wallet', 'url' => route('admin.wallets.index'), 'active' => ['admin.wallets.*']],
                ['label' => 'Gift Cards', 'icon' => 'gift', 'url' => route('admin.gift-cards.index'), 'active' => ['admin.gift-cards.*']],
                ['label' => 'Refunds', 'icon' => 'rotate-left', 'url' => route('admin.refunds.index'), 'active' => ['admin.refunds.*']],
                ['label' => 'Commissions', 'icon' => 'percent', 'url' => route('admin.commissions'), 'active' => ['admin.commissions*']],
                ['label' => 'Payout History', 'icon' => 'clock-rotate-left', 'url' => route('admin.payouts.history'), 'active' => ['admin.payouts.history*']],
                ['label' => 'Analytics', 'icon' => 'chart-pie', 'url' => route('admin.analytics'), 'active' => ['admin.analytics', 'admin.reports.*']],
            ],
        ],
        [
            'label' => 'Engagement',
            'items' => [
                [
                    'label' => 'Promotion Engine',
                    'icon' => 'tags',
                    'active' => ['admin.promotion-engine.*'],
                    'children' => [
                        ['label' => 'Promotions', 'url' => route('admin.promotion-engine.index'), 'active' => ['admin.promotion-engine.index', 'admin.promotion-engine.create', 'admin.promotion-engine.edit']],
                        ['label' => 'Coupon Library', 'url' => route('admin.promotion-engine.coupons'), 'active' => ['admin.promotion-engine.coupons']],
                        ['label' => 'Analytics', 'url' => route('admin.promotion-engine.analytics'), 'active' => ['admin.promotion-engine.analytics']],
                        ['label' => 'Engine Logs', 'url' => route('admin.promotion-engine.logs'), 'active' => ['admin.promotion-engine.logs']],
                    ],
                ],
                ['label' => 'Legacy Promo Codes', 'icon' => 'ticket-alt', 'url' => route('admin.promos.index'), 'active' => ['admin.promos.*']],
                ['label' => 'Campaigns', 'icon' => 'bullhorn', 'url' => route('admin.campaigns.index'), 'active' => ['admin.campaigns*']],
                ['label' => 'Push Notifications', 'icon' => 'paper-plane', 'url' => route('admin.push-notifications.index'), 'active' => ['admin.push-notifications*']],
                ['label' => 'Dining Bookings', 'icon' => 'utensils', 'url' => route('admin.dining-bookings.index'), 'active' => ['admin.dining-bookings.*'], 'badge' => $pendingDiningBookings ? $badge($pendingDiningBookings) : null],
                ['label' => 'Celebration Types', 'icon' => 'champagne-glasses', 'url' => route('admin.celebration-types.index'), 'active' => ['admin.celebration-types*']],
                ['label' => 'Support Tickets', 'icon' => 'headset', 'url' => route('admin.support.index'), 'active' => ['admin.support.*'], 'badge' => $openTicketsCount ? $badge($openTicketsCount) : null],
            ],
        ],
        [
            'label' => 'Restaurant Controls',
            'items' => [
                ['label' => 'Driver Gigs', 'icon' => 'calendar-alt', 'url' => route('admin.gigs.index'), 'active' => ['admin.gigs.*']],
                ['label' => 'Offline Reasons', 'icon' => 'clock', 'url' => route('admin.offline-reasons.index'), 'active' => ['admin.offline-reasons*']],
                ['label' => 'Cancellation Limits', 'icon' => 'ban', 'url' => route('admin.cancellation-limits'), 'active' => ['admin.cancellation-limits*']],
                ['label' => 'Refund Policies', 'icon' => 'hand-holding-dollar', 'url' => route('admin.refund-policies.index'), 'active' => ['admin.refund-policies.*']],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                [
                    'label' => 'Settings',
                    'icon' => 'gear',
                    'id' => 'settings-menu',
                    'active' => ['admin.settings.*', 'admin.home-sections.*', 'admin.delivery-charges*', 'admin.taxes*', 'admin.payout-settings.*'],
                    'children' => [
                        ['label' => 'General Settings', 'url' => route('admin.settings.index'), 'active' => ['admin.settings.index']],
                        ['label' => 'Homepage Content', 'url' => route('admin.settings.homepage'), 'active' => ['admin.settings.homepage']],
                        ['label' => 'Home Sections', 'url' => route('admin.home-sections.index'), 'active' => ['admin.home-sections.*']],
                        ['label' => 'Privacy & Legal', 'url' => route('admin.settings.privacy'), 'active' => ['admin.settings.privacy']],
                        ['label' => 'Driver Assignment', 'url' => route('admin.settings.driver_assignment'), 'active' => ['admin.settings.driver_assignment']],
                        ['label' => 'Communication', 'url' => route('admin.settings.communication'), 'active' => ['admin.settings.communication']],
                        ['label' => 'Notifications', 'url' => route('admin.settings.notifications'), 'active' => ['admin.settings.notifications']],
                        ['label' => 'Delivery Charges', 'url' => route('admin.delivery-charges'), 'active' => ['admin.delivery-charges*']],
                        ['label' => 'Taxes & Charges', 'url' => route('admin.taxes'), 'active' => ['admin.taxes*']],
                        ['label' => 'Branding', 'url' => route('admin.settings.branding'), 'active' => ['admin.settings.branding']],
                        ['label' => 'Payments', 'url' => route('admin.settings.payment'), 'active' => ['admin.settings.payment']],
                        ['label' => 'Payout Gateway', 'url' => route('admin.payout-settings.edit'), 'active' => ['admin.payout-settings.*']],
                        ['label' => 'Map Settings', 'url' => route('admin.settings.map'), 'active' => ['admin.settings.map']],
                        ['label' => 'Storage Settings', 'url' => route('admin.settings.index') . '#media-storage-settings', 'active' => [], 'attrs' => 'data-storage-settings-link'],
                        ['label' => 'Cron Jobs', 'url' => route('admin.settings.cron'), 'active' => ['admin.settings.cron']],
                    ],
                ],
            ],
        ],
    ];
@endphp

<aside class="sidebar admin-sidebar" id="sidebar">
    <div class="sidebar-logo-section admin-sidebar-brand">
        <div class="sidebar-logo-icon">
            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogoUrl)
                <img src="{{ $appLogoUrl }}" alt="{{ $appName }}" class="sidebar-logo-image">
            @else
                <i class="fas fa-shield-halved"></i>
            @endif
        </div>
        <div class="sidebar-logo-text">
            <h2>{{ $appName }} <span>Admin</span></h2>
            <small>Control Panel</small>
        </div>
    </div>

    <nav class="sidebar-nav-wrapper admin-sidebar-scroll" aria-label="Admin navigation">
        @foreach($sections as $section)
            <div class="sidebar-section-title">{{ $section['label'] }}</div>
            <ul class="sidebar-nav">
                @foreach($section['items'] as $item)
                    @php
                        $hasChildren = !empty($item['children']);
                        $active = $isActive($item['active'] ?? []);
                        $itemId = $item['id'] ?? \Illuminate\Support\Str::slug($item['label']) . '-menu';
                    @endphp

                    <li class="sidebar-nav-item {{ $hasChildren ? 'sidebar-parent' : '' }}" id="{{ $hasChildren ? $itemId : '' }}">
                        @if($hasChildren)
                            <a href="#" onclick="toggleSettingsSubmenu(event)" class="sidebar-nav-link sidebar-parent-link {{ $active ? 'active open' : '' }}">
                                <i class="fas fa-{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                <i class="fas fa-chevron-down sidebar-submenu-toggle"></i>
                            </a>
                            <ul class="sidebar-submenu {{ $active ? 'open' : '' }}">
                                @foreach($item['children'] as $child)
                                    @php $childActive = $isActive($child['active'] ?? []); @endphp
                                    <li class="sidebar-nav-item">
                                        <a href="{{ $child['url'] }}" {!! $child['attrs'] ?? '' !!} class="sidebar-nav-link {{ $childActive ? 'active' : '' }}">
                                            <i class="fas fa-circle"></i>
                                            <span>{{ $child['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item['url'] }}" class="sidebar-nav-link {{ $active ? 'active' : '' }}">
                                <i class="fas fa-{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                @if(!empty($item['badge']))
                                    <span class="sidebar-badge">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>
</aside>

<style>
    .admin-sidebar {
        background:
            radial-gradient(circle at 10% 0%, color-mix(in srgb, var(--primary) 24%, transparent), transparent 30%),
            linear-gradient(180deg, #0b1220 0%, #111827 56%, #172033 100%) !important;
    }

    .admin-sidebar .admin-sidebar-brand {
        padding: 18px 18px !important;
        min-height: var(--topbar-height);
    }

    .admin-sidebar .sidebar-logo-icon {
        width: 46px !important;
        height: 46px !important;
        border-radius: 16px !important;
    }

    .admin-sidebar .sidebar-logo-text {
        min-width: 0;
    }

    .admin-sidebar .sidebar-logo-text h2 {
        max-width: 185px;
        color: #f8fafc !important;
        font-size: 18px !important;
        font-weight: 950 !important;
        line-height: 1.08 !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-sidebar .sidebar-logo-text h2 span {
        color: var(--primary-light) !important;
    }

    .admin-sidebar .sidebar-logo-text small {
        color: #94a3b8 !important;
        font-size: 10px !important;
        font-weight: 800 !important;
    }

    .admin-sidebar .admin-sidebar-scroll {
        padding: 12px 10px 18px !important;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, .45) transparent;
    }

    .admin-sidebar .admin-sidebar-scroll::-webkit-scrollbar {
        width: 7px;
    }

    .admin-sidebar .admin-sidebar-scroll::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, .45);
        border-radius: 999px;
    }

    .admin-sidebar .sidebar-section-title {
        margin-top: 10px !important;
        padding: 8px 12px 5px !important;
        color: #94a3b8 !important;
        font-size: 10px !important;
        font-weight: 950 !important;
        letter-spacing: .14em !important;
    }

    .admin-sidebar .sidebar-nav-item {
        margin-bottom: 3px !important;
    }

    .admin-sidebar .sidebar-nav-link {
        min-height: 40px !important;
        padding: 9px 12px !important;
        border-radius: 13px !important;
        color: rgba(226, 232, 240, .9) !important;
        font-size: 13px !important;
        font-weight: 850 !important;
        gap: 10px !important;
        white-space: nowrap;
    }

    .admin-sidebar .sidebar-nav-link span:not(.sidebar-badge) {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-sidebar .sidebar-nav-link i:first-child {
        width: 22px !important;
        height: 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
    }

    .admin-sidebar .sidebar-nav-link:hover {
        background: rgba(255, 255, 255, .08) !important;
        color: #fff !important;
        transform: translateX(2px);
    }

    .admin-sidebar .sidebar-nav-link.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        color: #fff !important;
        box-shadow: 0 12px 24px color-mix(in srgb, var(--primary) 22%, transparent) !important;
    }

    .admin-sidebar .sidebar-parent-link {
        justify-content: flex-start !important;
    }

    .admin-sidebar .sidebar-submenu-toggle {
        margin-left: auto !important;
        font-size: 10px !important;
        transition: transform .2s ease;
    }

    .admin-sidebar .sidebar-parent-link.open .sidebar-submenu-toggle {
        transform: rotate(180deg);
    }

    .admin-sidebar .sidebar-submenu {
        display: block !important;
        max-height: 0;
        margin: 4px 0 5px 24px !important;
        padding-left: 8px !important;
        overflow: hidden;
        border-left: 1px solid rgba(255, 255, 255, .13);
        transition: max-height .25s ease;
    }

    .admin-sidebar .sidebar-submenu.open {
        max-height: 900px;
    }

    .admin-sidebar .sidebar-submenu .sidebar-nav-link {
        min-height: 34px !important;
        padding: 7px 10px !important;
        border-radius: 10px !important;
        color: rgba(203, 213, 225, .82) !important;
        font-size: 12px !important;
    }

    .admin-sidebar .sidebar-submenu .sidebar-nav-link i:first-child {
        width: 10px !important;
        height: 10px !important;
        font-size: 5px !important;
        color: rgba(148, 163, 184, .85) !important;
        background: transparent !important;
    }

    .admin-sidebar .sidebar-badge {
        margin-left: auto;
        min-width: 24px;
        padding: 2px 7px;
        border-radius: 999px;
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #fb7185) !important;
        font-size: 10px;
        font-weight: 950;
        text-align: center;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNav = document.querySelector('.admin-sidebar-scroll');
        const storageLink = document.querySelector('[data-storage-settings-link]');
        const scrollKey = 'adminSidebarScrollTop';

        if (sidebarNav) {
            const savedScroll = Number(sessionStorage.getItem(scrollKey));
            if (Number.isFinite(savedScroll)) {
                sidebarNav.scrollTop = savedScroll;
            }

            sidebarNav.addEventListener('scroll', function() {
                sessionStorage.setItem(scrollKey, String(sidebarNav.scrollTop));
            }, { passive: true });

            sidebarNav.querySelectorAll('a[href]:not([href="#"])').forEach(function(link) {
                link.addEventListener('click', function() {
                    sessionStorage.setItem(scrollKey, String(sidebarNav.scrollTop));
                });
            });
        }

        function syncStorageSettingsLink() {
            if (window.location.hash !== '#media-storage-settings' || !storageLink) return;

            document.querySelectorAll('#settings-menu .sidebar-nav-link.active').forEach(function(link) {
                link.classList.remove('active');
            });
            storageLink.classList.add('active');
        }

        syncStorageSettingsLink();
        window.addEventListener('hashchange', syncStorageSettingsLink);
    });
</script>
