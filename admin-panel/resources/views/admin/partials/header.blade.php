<div class="top-header">
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="header-search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="headerSearchInput" placeholder="Search orders, restaurants, users..." onkeyup="handleHeaderSearch(event)">
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-actions">
            <a href="{{ route('admin.orders.index') }}?status=pending" class="header-icon-btn" id="pendingOrdersBtn">
                <i class="fas fa-bell"></i>
                @php
                    $pendingOrdersCount = \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count();
                @endphp
                @if($pendingOrdersCount > 0)
                    <span class="badge-notification">{{ $pendingOrdersCount > 99 ? '99+' : $pendingOrdersCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.support.index') }}" class="header-icon-btn" id="supportInboxBtn" title="Live chat inbox">
                <i class="fas fa-comments"></i>
                <span class="badge-notification d-none" id="supportInboxBadge">0</span>
            </a>
        </div>
        
        <div class="header-divider"></div>
        
        <div class="user-profile-wrapper" id="userProfileButton" onclick="toggleUserDropdown()">
            <div class="user-avatar-lg">
                {{ substr(auth()->user()->name, 0, 2) }}
            </div>
            <div class="user-info-text d-none d-sm-block">
                <span class="user-name">{{ auth()->user()->name }}</span>
                <span class="user-role">Super Admin</span>
            </div>
            <i class="fas fa-chevron-down user-dropdown-arrow"></i>
        </div>
        
        <div class="profile-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-user-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar-lg" style="width: 48px; height: 48px; font-size: 20px;">
                        {{ substr(auth()->user()->name, 0, 2) }}
                    </div>
                    <div>
                        <div class="fw-bold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted">{{ auth()->user()->email }}</div>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.settings.index') }}" class="dropdown-menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}" id="logoutForm">
                @csrf
                <button type="submit" class="dropdown-menu-item w-100 text-start text-danger" style="background: none; border: none;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    async function refreshSupportInboxBadge() {
        try {
            const response = await fetch(`{{ route('admin.support.notification-summary') }}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) return;
            const data = await response.json();
            const badge = document.getElementById('supportInboxBadge');
            if (!badge) return;
            const count = Number(data.count || 0);
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('d-none', count <= 0);
        } catch (error) {
            console.debug('Support inbox polling skipped', error);
        }
    }

    function handleHeaderSearch(event) {
        if (event.key === 'Enter') {
            const searchTerm = event.target.value.trim();
            if (searchTerm) {
                // Determine current page and redirect with search
                const currentPath = window.location.pathname;
                if (currentPath.includes('/admin/orders')) {
                    window.location.href = `{{ route('admin.orders.index') }}?search=${encodeURIComponent(searchTerm)}`;
                } else if (currentPath.includes('/admin/restaurants')) {
                    window.location.href = `{{ route('admin.restaurants.index') }}?search=${encodeURIComponent(searchTerm)}`;
                } else if (currentPath.includes('/admin/users')) {
                    window.location.href = `{{ route('admin.users.index') }}?search=${encodeURIComponent(searchTerm)}`;
                } else if (currentPath.includes('/admin/drivers')) {
                    window.location.href = `{{ route('admin.drivers.index') }}?search=${encodeURIComponent(searchTerm)}`;
                } else {
                    window.location.href = `{{ route('admin.orders.index') }}?search=${encodeURIComponent(searchTerm)}`;
                }
            }
        }
    }

    refreshSupportInboxBadge();
    setInterval(refreshSupportInboxBadge, 15000);
</script>
