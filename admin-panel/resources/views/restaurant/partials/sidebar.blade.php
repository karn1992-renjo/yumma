{{-- resources/views/restaurant/partials/sidebar.blade.php --}}
@php
    $user = auth()->user();
    $restaurants = $user->hasRole('restaurant_owner') ? $user->restaurants : collect();
    $currentRestaurant = $user->activeRestaurant();
    $currentRoute = Route::currentRouteName();
    $isOwner = $user->hasRole('restaurant_owner');
    $canOrders = $user->hasRestaurantPermission('view_orders') || $user->hasRestaurantPermission('manage_orders');
    $canMenu = $user->hasRestaurantPermission('view_menu_items') || $user->hasRestaurantPermission('manage_menu');
    $canReports = $user->hasRestaurantPermission('view_reports');
    
    // Count pending orders for badge
    $pendingCount = 0;
    if ($currentRestaurant) {
        $pendingCount = \App\Models\Order::where('restaurant_id', $currentRestaurant->id)
            ->where('status', 'pending')
            ->count();
    }
    
    // Count open support tickets
    $openTicketsCount = 0;
    if ($currentRestaurant && class_exists('\App\Models\SupportTicket')) {
        $openTicketsCount = \App\Models\SupportTicket::where('restaurant_id', $currentRestaurant->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
    }
@endphp

<aside class="sidebar" id="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo-section">
        <div class="sidebar-logo-icon">
            <i class="fas fa-utensils"></i>
        </div>
        <div class="sidebar-logo-text">
            <h2>Food<span>Flow</span></h2>
            <small>Restaurant Panel</small>
        </div>
    </div>
    
    <!-- Navigation -->
    <div class="sidebar-nav-wrapper">
        <div class="sidebar-section-title">MAIN MENU</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.dashboard') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            @if($canOrders)
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.orders.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Orders</span>
                    @if($pendingCount > 0)
                        <span class="sidebar-badge">{{ $pendingCount > 99 ? '99+' : $pendingCount }}</span>
                    @endif
                </a>
            </li>
            @endif

            @if($canMenu)
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.menu.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.menu.*') ? 'active' : '' }}">
                    <i class="fas fa-utensils"></i>
                    <span>Menu Items</span>
                </a>
            </li>
            
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.categories.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.categories.*') ? 'active' : '' }}">
                    <i class="fas fa-list-alt"></i>
                    <span>Categories</span>
                </a>
            </li>
            @endif
        </ul>
        
        @if($isOwner)
        <div class="sidebar-section-title">MANAGEMENT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.stores.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.stores.*') ? 'active' : '' }}">
                    <i class="fas fa-store"></i>
                    <span>My Stores</span>
                    @if($restaurants->count() > 1)
                        <span class="sidebar-badge" style="background: #10b981;">{{ $restaurants->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.printers.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.printers.*') ? 'active' : '' }}">
                    <i class="fas fa-print"></i>
                    <span>Printers</span>
                </a>
            </li>
        </ul>
        @endif
        
        @if($isOwner)
        <div class="sidebar-section-title">MARKETING</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.promos.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.promos.*') ? 'active' : '' }}">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Promo Codes</span>
                </a>
            </li>
        </ul>
        @endif
        
        @if($canReports)
        <div class="sidebar-section-title">INSIGHTS</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.analytics.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.analytics.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics & Reports</span>
                </a>
            </li>
        </ul>
        @endif
        
        @if($isOwner)
        <div class="sidebar-section-title">SETTINGS</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.wallet.index') }}"
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.wallet.*') ? 'active' : '' }}">
                    <i class="fas fa-wallet"></i>
                    <span>Wallet</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.settings.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.settings.index') ? 'active' : '' }}">
                    <i class="fas fa-cog"></i>
                    <span>General Settings</span>
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.settings.timing') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.settings.timing') ? 'active' : '' }}">
                    <i class="fas fa-clock"></i>
                    <span>Timing Settings</span>
                </a>
            </li>
        </ul>
        @endif

        @if($isOwner)
        <div class="sidebar-section-title">TEAM</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.staff.index') }}"
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.staff.*') ? 'active' : '' }}">
                    <i class="fas fa-user-group"></i>
                    <span>Staff Management</span>
                </a>
            </li>
        </ul>
        @endif
        
        <div class="sidebar-section-title">SUPPORT</div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.support.index') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.support.*') ? 'active' : '' }}">
                    <i class="fas fa-headset"></i>
                    <span>Help & Support</span>
                    @if($openTicketsCount > 0)
                        <span class="sidebar-badge" style="background: #f59e0b;">{{ $openTicketsCount }}</span>
                    @endif
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="{{ route('restaurant.support.faq') }}" 
                   class="sidebar-nav-link {{ request()->routeIs('restaurant.support.faq') ? 'active' : '' }}">
                    <i class="fas fa-question-circle"></i>
                    <span>FAQs</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-restaurant-card">
            <div class="sidebar-restaurant-avatar">
                {{ strtoupper(substr($currentRestaurant->name ?? 'R', 0, 1)) }}
            </div>
            <div class="sidebar-restaurant-info">
                <div class="restaurant-name" title="{{ $currentRestaurant->name ?? 'My Restaurant' }}">
                    {{ Str::limit($currentRestaurant->name ?? 'My Restaurant', 20) }}
                </div>
                <div class="restaurant-status">
                    @if($currentRestaurant && $currentRestaurant->is_open)
                        <i class="fas fa-circle text-success" style="font-size: 8px;"></i>
                        <span>Open for Orders</span>
                    @else
                        <i class="fas fa-circle text-secondary" style="font-size: 8px;"></i>
                        <span>Currently Closed</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</aside>

<style>
    /* Additional sidebar styles */
    .sidebar-footer {
        border-top: 1px solid var(--border);
        padding: 24px 20px 26px;
        margin-top: auto;
    }
    
    .sidebar-restaurant-card {
        background: #F8FAFC;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar-restaurant-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
    }
    
    .sidebar-restaurant-info {
        flex: 1;
        min-width: 0;
    }
    
    .restaurant-name {
        font-weight: 600;
        font-size: 13px;
        color: #1E293B;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .restaurant-status {
        font-size: 11px;
        color: #64748B;
        display: flex;
        align-items: center;
        gap: 4px;
    }
</style>

<script>
    // Sidebar toggle for mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
    }
    
    // Close sidebar when clicking overlay
    document.addEventListener('click', function(event) {
        const overlay = document.getElementById('sidebarOverlay');
        const sidebar = document.getElementById('sidebar');
        if (overlay && overlay.classList.contains('show') && event.target === overlay) {
            toggleSidebar();
        }
    });
</script>
