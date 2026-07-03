{{-- resources/views/restaurant/partials/header.blade.php --}}
@php
    $user = auth()->user();
    $isOwner = $user->hasRole('restaurant_owner');
    $restaurants = $isOwner ? $user->restaurants : collect();
    $currentRestaurant = $user->activeRestaurant();
    $dashboardAllSelected = request()->routeIs('restaurant.dashboard') && request('scope') === 'all';
    $canMenu = $user->hasRestaurantPermission('view_menu_items') || $user->hasRestaurantPermission('manage_menu');
    $canReports = $user->hasRestaurantPermission('view_reports');
    
    // Count pending orders for notification badge
    $pendingOrders = 0;
    if ($currentRestaurant) {
        $pendingOrders = \App\Models\Order::where('restaurant_id', $currentRestaurant->id)
            ->where('status', 'pending')
            ->count();
    }
    
    // Count unread notifications
    $unreadNotifications = 0;
    if ($user && method_exists($user, 'unreadNotifications')) {
        $unreadNotifications = $user->unreadNotifications()->count();
    }
@endphp

<header class="top-header">
    <!-- Left Section -->
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Store Switcher Dropdown (if multiple restaurants) -->
        @if($isOwner && $restaurants->count() > 1)
        <div class="dropdown">
            <button class="btn btn-light border rounded-3 d-flex align-items-center gap-2" 
                    data-bs-toggle="dropdown"
                    style="background: #F8FAFC; padding: 8px 16px;">
                <i class="fas fa-store text-primary"></i>
                <span class="fw-semibold">{{ $dashboardAllSelected ? 'All Restaurants' : Str::limit($currentRestaurant->name ?? 'Select Store', 20) }}</span>
                <i class="fas fa-chevron-down text-muted ms-1" style="font-size: 12px;"></i>
            </button>
            <ul class="dropdown-menu" style="min-width: 250px;">
                @if(request()->routeIs('restaurant.dashboard'))
                <li>
                    <a href="{{ route('restaurant.dashboard', ['scope' => 'all']) }}" class="dropdown-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-layer-group me-2"></i>
                            All Restaurants
                            <br><small class="text-muted">Combined dashboard</small>
                        </div>
                        @if($dashboardAllSelected)
                            <i class="fas fa-check-circle text-success"></i>
                        @endif
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                @endif
                @foreach($restaurants as $restaurant)
                <li>
                    <form action="{{ route('restaurant.stores.switch') }}" method="POST" class="switch-store-form">
                        @csrf
                        <input type="hidden" name="restaurant_id" value="{{ $restaurant->id }}">
                        <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-store me-2"></i>
                                {{ $restaurant->name }}
                                <br><small class="text-muted">{{ $restaurant->city }}</small>
                            </div>
                            @if($restaurant->id == ($currentRestaurant->id ?? null))
                                <i class="fas fa-check-circle text-success"></i>
                            @endif
                        </button>
                    </form>
                </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="{{ route('restaurant.stores.index') }}" class="dropdown-item">
                        <i class="fas fa-plus-circle me-2"></i> Manage Stores
                    </a>
                </li>
            </ul>
        </div>
        @endif
        
        <div class="header-search-wrapper">
            <i class="fas fa-magnifying-glass search-icon"></i>
            <input type="text" 
                   placeholder="Search orders, menu items..." 
                   id="globalSearch"
                   autocomplete="off">
        </div>
    </div>
    
    <!-- Right Section -->
    <div class="header-right">
        <!-- Action Buttons -->
        <div class="header-actions">
            <!-- Search Button (Mobile only) -->
            <button class="header-icon-btn d-block d-lg-none" 
                    onclick="toggleMobileSearch()" 
                    title="Search"
                    aria-label="Search">
                <i class="fas fa-magnifying-glass"></i>
            </button>
            
            <!-- Quick Actions Dropdown -->
            @if($isOwner)
            <div class="dropdown">
                <button class="header-icon-btn" 
                        title="Quick Actions" 
                        data-bs-toggle="dropdown"
                        aria-label="Quick actions">
                    <i class="fas fa-plus"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2" 
                    style="min-width: 220px; border-radius: 12px; border: 1px solid var(--border);">
                    @if($canMenu)
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.menu.create') }}">
                            <i class="fas fa-utensils me-2 text-primary"></i> 
                            <span>Add Menu Item</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.categories.create') }}">
                            <i class="fas fa-folder-plus me-2 text-info"></i> 
                            <span>Add Category</span>
                        </a>
                    </li>
                    @endif
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.promos.create') }}">
                            <i class="fas fa-tag me-2 text-success"></i> 
                            <span>Create Promo Code</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.printers.create') }}">
                            <i class="fas fa-print me-2 text-secondary"></i> 
                            <span>Add Printer</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.support.create') }}">
                            <i class="fas fa-headset me-2 text-warning"></i> 
                            <span>Contact Support</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endif
            
            <!-- Notification Button -->
            <div class="dropdown">
                <button class="header-icon-btn" 
                        title="Notifications" 
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    @if($pendingOrders > 0 || $unreadNotifications > 0)
                        <span class="badge-notification">{{ ($pendingOrders + $unreadNotifications) > 99 ? '99+' : ($pendingOrders + $unreadNotifications) }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" 
                     style="width: 320px; border-radius: 14px; border: 1px solid var(--border);">
                    <div class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">Notifications</h6>
                            <a href="#" class="text-primary small text-decoration-none">Mark all as read</a>
                        </div>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        @if($pendingOrders > 0)
                            <a href="{{ route('restaurant.orders.index', ['status' => 'pending']) }}" 
                               class="dropdown-item py-3 border-bottom">
                                <div class="d-flex gap-3">
                                    <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width: 40px; height: 40px; color: var(--warning);">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small">New Orders Received</div>
                                        <small class="text-muted">
                                            You have {{ $pendingOrders }} pending order(s)
                                        </small>
                                        <br>
                                        <small class="text-muted">{{ now()->diffForHumans() }}</small>
                                    </div>
                                </div>
                            </a>
                        @endif
                        
                        @if($pendingOrders == 0 && $unreadNotifications == 0)
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-bell-slash fa-2x mb-2 d-block opacity-50"></i>
                                <small>No new notifications</small>
                            </div>
                        @endif
                    </div>
                    <div class="p-2 border-top text-center">
                        <a href="#" class="text-primary small text-decoration-none">View All Notifications</a>
                    </div>
                </div>
            </div>
            
            <!-- Messages Button -->
            <button class="header-icon-btn" title="Messages" aria-label="Messages">
                <i class="fas fa-comments"></i>
            </button>
        </div>
        
        <div class="header-divider"></div>
        
        <!-- Restaurant Status Toggle -->
        @if($currentRestaurant && $isOwner)
        <div class="status-toggle-wrapper">
            <div class="status-indicator {{ $currentRestaurant->is_open ? 'online' : 'offline' }}" 
                 id="statusIndicator"></div>
            <span class="status-text" id="statusLabel">
                {{ $currentRestaurant->is_open ? 'Online' : 'Offline' }}
            </span>
            <div class="form-check form-switch ms-2 mb-0">
                <input class="form-check-input" 
                       type="checkbox" 
                       {{ $currentRestaurant->is_open ? 'checked' : '' }}
                       onchange="toggleRestaurantStatus()"
                       id="restaurantStatusSwitch"
                       style="cursor: pointer;">
                <label class="form-check-label visually-hidden" for="restaurantStatusSwitch">
                    Toggle restaurant status
                </label>
            </div>
        </div>
        
        <div class="header-divider"></div>
        @endif
        
        <!-- User Profile Dropdown -->
        <div class="user-profile-wrapper" 
             id="userProfileButton" 
             onclick="toggleUserDropdown(event)"
             role="button"
             tabindex="0"
             aria-label="User menu">
            <div class="user-avatar-lg">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div class="user-info-text d-none d-md-flex">
                <span class="user-name">{{ Str::limit($user->name ?? 'User', 15) }}</span>
                <span class="user-role">{{ $isOwner ? 'Restaurant Owner' : 'Restaurant Staff' }}</span>
            </div>
            <i class="fas fa-chevron-down user-dropdown-arrow d-none d-md-block"></i>
            
            <!-- Dropdown Menu -->
            <div class="profile-dropdown-menu" id="userDropdownMenu">
                <div class="dropdown-user-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="user-avatar-lg" 
                             style="width: 48px; height: 48px; font-size: 20px;">
                            {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ $user->name ?? 'User' }}</div>
                            <div class="text-muted small">{{ $user->email ?? 'user@email.com' }}</div>
                        </div>
                    </div>
                </div>
                
                @if($isOwner)
                <a href="{{ route('restaurant.settings.index') }}" class="dropdown-menu-item">
                    <i class="fas fa-user-gear"></i>
                    <span>My Profile</span>
                </a>
                
                <a href="{{ route('restaurant.stores.index') }}" class="dropdown-menu-item">
                    <i class="fas fa-store"></i>
                    <span>Restaurant Settings</span>
                </a>
                @endif
                
                @if($canReports)
                <a href="{{ route('restaurant.analytics.index') }}" class="dropdown-menu-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Analytics</span>
                </a>
                @endif
                
                <div class="dropdown-divider"></div>
                
                <a href="{{ route('restaurant.support.index') }}" class="dropdown-menu-item">
                    <i class="fas fa-circle-question"></i>
                    <span>Help & Support</span>
                </a>
                
                <a href="{{ route('restaurant.support.faq') }}" class="dropdown-menu-item">
                    <i class="fas fa-book"></i>
                    <span>FAQs</span>
                </a>
                
                <div class="dropdown-divider"></div>
                
                <form method="POST" action="{{ route('logout') }}" id="logout-form-header">
                    @csrf
                    <button type="submit" class="dropdown-menu-item text-danger w-100 text-start bg-transparent border-0">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Search Overlay -->
<div class="mobile-search-overlay" id="mobileSearchOverlay" style="display: none;">
    <div class="d-flex align-items-center gap-3 p-3 bg-white shadow-sm">
        <button class="btn btn-link text-dark p-0" onclick="toggleMobileSearch()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <div class="flex-fill">
            <input type="text" 
                   class="form-control border-0 bg-light" 
                   placeholder="Search orders, menu items..."
                   id="mobileSearchInput"
                   autofocus>
        </div>
    </div>
    <div class="p-3" id="mobileSearchResults">
        <div class="text-center text-muted py-5">
            <i class="fas fa-search fa-3x mb-3 d-block opacity-50"></i>
            <small>Type to search...</small>
        </div>
    </div>
</div>

<script>
    // Global search functionality
    document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `{{ route("restaurant.orders.index") }}?search=${encodeURIComponent(query)}`;
            }
        }
    });
    
    // Mobile search toggle
    function toggleMobileSearch() {
        const overlay = document.getElementById('mobileSearchOverlay');
        if (overlay.style.display === 'none' || !overlay.style.display) {
            overlay.style.display = 'block';
            setTimeout(() => document.getElementById('mobileSearchInput')?.focus(), 100);
        } else {
            overlay.style.display = 'none';
        }
    }
    
    // Mobile search functionality
    document.getElementById('mobileSearchInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `{{ route("restaurant.orders.index") }}?search=${encodeURIComponent(query)}`;
                toggleMobileSearch();
            }
        }
    });
    
    // Store switch forms
    document.querySelectorAll('.switch-store-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const button = this.querySelector('button[type="submit"]');
            const originalHtml = button?.innerHTML;
            if (button) button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    restaurant_id: this.querySelector('[name="restaurant_id"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (@json(request()->routeIs('restaurant.dashboard') && request('scope') === 'all')) {
                        window.location.href = @json(route('restaurant.dashboard'));
                    } else {
                        location.reload();
                    }
                } else if (button) {
                    button.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (button) button.innerHTML = originalHtml;
            });
        });
    });
    
    // Restaurant status toggle
    function toggleRestaurantStatus() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const indicator = document.getElementById('statusIndicator');
        const label = document.getElementById('statusLabel');
        const switchInput = document.getElementById('restaurantStatusSwitch');
        
        fetch('{{ route("restaurant.toggle-status") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.is_open) {
                    indicator.className = 'status-indicator online';
                    label.textContent = 'Online';
                    showToastMessage('Restaurant is now Online', 'success');
                } else {
                    indicator.className = 'status-indicator offline';
                    label.textContent = 'Offline';
                    showToastMessage('Restaurant is now Offline', 'warning');
                }
            } else if (switchInput) {
                switchInput.checked = !switchInput.checked;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (switchInput) switchInput.checked = !switchInput.checked;
        });
    }
    
    // User dropdown toggle
    function toggleUserDropdown(event) {
        event?.stopPropagation();
        const dropdown = document.getElementById('userDropdownMenu');
        dropdown.classList.toggle('show');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdownMenu');
        const userProfile = document.getElementById('userProfileButton');
        if (dropdown && userProfile && !userProfile.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
    
    // Toast message helper
    function showToastMessage(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `custom-toast-message toast-${type}`;
        toast.innerHTML = `<div class="d-flex align-items-center gap-2"><i class="fas ${getToastIcon(type)}"></i><span>${message}</span></div>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function getToastIcon(type) {
        switch(type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-exclamation-circle';
            case 'warning': return 'fa-exclamation-triangle';
            default: return 'fa-info-circle';
        }
    }
</script>

<style>
    .mobile-search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #fff;
        z-index: 2000;
    }
    
    .custom-toast-message {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 12px;
        color: white;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .toast-success { background: #10b981; }
    .toast-error { background: #ef4444; }
    .toast-warning { background: #f59e0b; }
    .toast-info { background: #3b82f6; }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .header-search-wrapper {
            display: none;
        }
        .status-toggle-wrapper .status-text {
            display: none;
        }
    }
    
    @media (max-width: 480px) {
        .header-actions .header-icon-btn:nth-child(2),
        .header-actions .header-icon-btn:nth-child(3) {
            display: none;
        }
        .status-toggle-wrapper {
            padding: 4px 10px;
        }
    }
</style>
