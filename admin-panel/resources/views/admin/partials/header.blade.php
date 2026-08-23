@php
    $currencySymbol = \App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = \App\Models\AppSetting::currencyDecimals();
    $appName = \App\Models\AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
    $user = auth()->user();
    $userAvatar = $user->profile_photo_url ?? null;
    $userInitials = substr($user->name, 0, 2);
    $userRole = $user->getRoleNames()->first() ?? 'Admin';
@endphp

<div class="top-header" id="topHeader">
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Enhanced Global Search -->
        <div class="header-search-wrapper" x-data="headerSearch()" x-init="init()" @click.away="closeDropdowns()" @keydown.escape.window="closeDropdowns()">
            <i class="fas fa-search search-icon"></i>
            <input
                type="text"
                id="headerSearchInput"
                x-model.debounce.300ms="query"
                @focus="openDropdown('search')"
                @input="handleSearchInput"
                placeholder="Search orders, restaurants, users, drivers..."
                autocomplete="off"
                spellcheck="false"
            />

            <!-- Search Results Dropdown -->
            <div
                x-show="showSearchDropdown"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 transform scale(0.95)"
                x-transition:enter-end="opacity-100 transform scale(100%)"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 transform scale(100%)"
                x-transition:leave-end="opacity-0 transform scale(0.95)"
                class="search-dropdown"
                id="searchDropdown"
            >
                <!-- Recent Searches -->
                <template x-if="query.length === 0 && recentSearches.length > 0">
                    <div class="search-section">
                        <div class="search-section-title">
                            <i class="fas fa-clock-rotate-left"></i> Recent
                        </div>
                        <div class="search-result-item" @click="searchFromRecent(item)" x-for="item in recentSearches" :key="item">
                            <i class="fas fa-search"></i>
                            <span x-text="item"></span>
                        </div>
                        <div class="search-clear-recent" @click="clearRecentSearches">
                            <i class="fas fa-trash-can"></i> Clear recent
                        </div>
                    </div>
                </template>

                <!-- Loading State -->
                <template x-if="query.length > 0 && isLoading">
                    <div class="search-loading">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span>Searching...</span>
                    </div>
                </template>

                <!-- Search Results -->
                <template x-if="query.length > 0 && !isLoading && results.length > 0">
                    <div class="search-section">
                        <template x-for="group in groupedResults" :key="group.type">
                            <div>
                                <div class="search-group-title" x-text="group.label + ' (' + group.items.length + ')'">
                                </div>
                                <div
                                    class="search-result-item"
                                    @click="navigateTo(result.url)"
                                    x-for="result in group.items"
                                    :key="result.id"
                                >
                                    <div class="search-result-icon" :class="`bg-${result.color}-100`">
                                        <i class="fas" :class="result.icon"></i>
                                    </div>
                                    <div class="search-result-content">
                                        <div class="search-result-title" x-text="result.title"></div>
                                        <div class="search-result-subtitle" x-text="result.subtitle"></div>
                                    </div>
                                    <div class="search-result-meta" x-text="formatCurrency(result.amount)"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- No Results -->
                <template x-if="query.length > 0 && !isLoading && results.length === 0">
                    <div class="search-no-results">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>No results found for "<strong x-text="query"></strong>"</span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Keyboard Shortcut Hint -->
        <div class="search-shortcut-hint" title="Press Ctrl/Cmd + K to search">
            <kbd>Ctrl</kbd> <span>+</span> <kbd>K</kbd>
        </div>
    </div>

    <div class="header-right">
        <!-- Quick Actions -->
        <div class="header-actions">
            <div x-data="{ open: false }" @click.away="open = false" @keydown.escape.window="open = false">
                <button
                    class="header-icon-btn"
                    @click="open = !open"
                    title="Quick actions"
                    aria-label="Quick actions"
                >
                    <i class="fas fa-bolt"></i>
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="quick-actions-dropdown"
                >
                    <div class="quick-actions-header">
                        <h4>Quick Actions</h4>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="{{ route('admin.pos.index') }}" class="quick-action-item" title="New Order (POS)">
                            <div class="quick-action-icon bg-primary-100">
                                <i class="fas fa-cash-register"></i>
                            </div>
                            <span>New Order</span>
                        </a>
                        <a href="{{ route('admin.restaurants.create') }}" class="quick-action-item" title="Add Restaurant">
                            <div class="quick-action-icon bg-success-100">
                                <i class="fas fa-store"></i>
                            </div>
                            <span>Add Restaurant</span>
                        </a>
                        <a href="{{ route('admin.users.create') }}" class="quick-action-item" title="Add User">
                            <div class="quick-action-icon bg-info-100">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <span>Add User</span>
                        </a>
                        <a href="{{ route('admin.drivers.create') }}" class="quick-action-item" title="Add Driver">
                            <div class="quick-action-icon bg-warning-100">
                                <i class="fas fa-motorcycle"></i>
                            </div>
                            <span>Add Driver</span>
                        </a>
                        <a href="{{ route('admin.orders.index') }}" class="quick-action-item" title="View Orders">
                            <div class="quick-action-icon bg-danger-100">
                                <i class="fas fa-box"></i>
                            </div>
                            <span>View Orders</span>
                        </a>
                        <a href="{{ route('admin.analytics') }}" class="quick-action-item" title="Analytics">
                            <div class="quick-action-icon bg-purple-100">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <span>Analytics</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Notification Center -->
            <div x-data="notificationCenter()" x-init="init()" @click.away="closeDropdown()" @keydown.escape.window="closeDropdown()">
                <button
                    class="header-icon-btn"
                    @click="open = !open; if(open) loadNotifications()"
                    title="Notifications"
                    aria-label="Notifications"
                >
                    <i class="fas fa-bell"></i>
                    <span
                        class="badge-notification"
                        x-show="unreadCount > 0"
                        x-text="unreadCount > 99 ? '99+' : unreadCount"
                    ></span>
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="notification-dropdown"
                    id="notificationDropdown"
                >
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <div class="notification-header-actions">
                            <a
                                href="#"
                                @click="markAllRead(); $event.preventDefault()"
                                x-show="unreadCount > 0"
                                title="Mark all as read"
                            >Mark all read</a>
                            <a href="{{ route('admin.support.index') }}" title="View all">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>

                    <div class="notification-list" x-show="notifications.length > 0">
                        <template x-for="notification in notifications" :key="notification.id">
                            <div
                                class="notification-item"
                                :class="{ 'unread': !notification.is_read }"
                                @click="markAsRead(notification.id); navigateTo(notification.url)"
                            >
                                <div class="notification-icon" :class="`bg-${notification.color}-100`">
                                    <i class="fas" :class="notification.icon"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title" x-text="notification.title"></div>
                                    <div class="notification-message" x-text="notification.message"></div>
                                    <div class="notification-time" x-text="notification.created_at"></div>
                                </div>
                                <div
                                    class="notification-unread-dot"
                                    x-show="!notification.is_read"
                                    title="Unread"
                                ></div>
                            </div>
                        </template>
                    </div>

                    <div
                        class="notification-empty"
                        x-show="notifications.length === 0"
                    >
                        <i class="fas fa-bell-slash"></i>
                        <span>No notifications yet</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="header-divider"></div>

        <!-- User Profile -->
        <div class="user-profile-wrapper" id="userProfileButton" @click="toggleUserDropdown()" x-data="{ open: false }">
            <div class="user-avatar-lg" id="userAvatar">
                @if($userAvatar)
                    <img src="{{ $userAvatar }}" alt="{{ $user->name }}" class="avatar-img">
                @else
                    {{ $userInitials }}
                @endif
            </div>
            <div class="user-info-text d-none d-sm-block">
                <span class="user-name">{{ $user->name }}</span>
                <span class="user-role">{{ ucfirst($userRole) }}</span>
            </div>
            <i class="fas fa-chevron-down user-dropdown-arrow"></i>
        </div>

        <div class="profile-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-user-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar-lg" style="width: 48px; height: 48px; font-size: 20px;">
                        @if($userAvatar)
                            <img src="{{ $userAvatar }}" alt="{{ $user->name }}" class="avatar-img">
                        @else
                            {{ $userInitials }}
                        @endif
                    </div>
                    <div>
                        <div class="fw-bold">{{ $user->name }}</div>
                        <div class="small text-muted">{{ $user->email }}</div>
                        <span class="badge badge-primary mt-1 d-inline-block" style="font-size: 10px;">{{ ucfirst($userRole) }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('profile.edit') }}" class="dropdown-menu-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="dropdown-menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="#" class="dropdown-menu-item" onclick="toggleDarkMode(); return false;" id="darkModeToggle">
                <i class="fas fa-moon"></i>
                <span>Dark Mode</span>
                <span class="dropdown-toggle-switch"></span>
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
    // Alpine.js component for header search
    function headerSearch() {
        return {
            query: '',
            showSearchDropdown: false,
            isLoading: false,
            results: [],
            recentSearches: [],
            searchTimeout: null,

            init() {
                this.recentSearches = JSON.parse(localStorage.getItem('admin_recent_searches') || '[]');
            },

            openDropdown(type) {
                this.showSearchDropdown = type === 'search';
            },

            closeDropdowns() {
                this.showSearchDropdown = false;
            },

            handleSearchInput() {
                if (this.query.length < 2) {
                    this.results = [];
                    this.isLoading = false;
                    return;
                }

                this.isLoading = true;

                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performSearch();
                }, 300);
            },

            async performSearch() {
                try {
                    const response = await fetch(`{{ route('admin.search') }}?q=${encodeURIComponent(this.query)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    this.results = data.results || [];
                } catch (error) {
                    console.error('Search failed:', error);
                    this.results = [];
                } finally {
                    this.isLoading = false;
                }
            },

            navigateTo(url) {
                if (url) {
                    window.location.href = url;
                }
            },

            searchFromRecent(item) {
                this.query = item;
                this.performSearch();
            },

            clearRecentSearches() {
                this.recentSearches = [];
                localStorage.removeItem('admin_recent_searches');
            },

            get groupedResults() {
                const groups = {};
                this.results.forEach(result => {
                    if (!groups[result.type]) {
                        groups[result.type] = {
                            type: result.type,
                            label: result.label || result.type,
                            items: []
                        };
                    }
                    groups[result.type].items.push(result);
                });
                return Object.values(groups);
            },

            formatCurrency(value) {
                if (!value) return '';
                const symbol = @json($currencySymbol);
                const decimals = @json($currencyDecimals);
                return symbol + Number(value).toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }
        }
    }

    // Alpine.js component for notification center
    function notificationCenter() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            isLoading: false,

            init() {
                this.loadUnreadCount();
                setInterval(() => this.loadUnreadCount(), 30000);
            },

            async loadUnreadCount() {
                try {
                    const response = await fetch(`{{ route('admin.notifications.stats') }}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    this.unreadCount = data.unread_notifications || 0;
                } catch (error) {
                    console.error('Failed to load notification stats:', error);
                }
            },

            async loadNotifications() {
                this.isLoading = true;
                try {
                    const response = await fetch(`{{ route('admin.notifications.recent') }}?limit=10`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                } catch (error) {
                    console.error('Failed to load notifications:', error);
                } finally {
                    this.isLoading = false;
                }
            },

            async markAsRead(id) {
                try {
                    const baseUrl = `{{ url('admin/notifications') }}/${id}/read`;
                    await fetch(baseUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                } catch (error) {
                    console.error('Failed to mark notification as read:', error);
                }
            },

            async markAllRead() {
                try {
                    await fetch(`{{ route('admin.notifications.read-all') }}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.is_read = true);
                } catch (error) {
                    console.error('Failed to mark all as read:', error);
                }
            },

            navigateTo(url) {
                if (url) {
                    window.location.href = url;
                }
            },

            closeDropdown() {
                this.open = false;
            }
        }
    }

    // Dark mode toggle
    function toggleDarkMode() {
        const body = document.body;
        const isDark = body.classList.contains('dark-mode');

        if (isDark) {
            body.classList.remove('dark-mode');
            localStorage.setItem('admin_theme', 'light');
        } else {
            body.classList.add('dark-mode');
            localStorage.setItem('admin_theme', 'dark');
        }
    }

    // Initialize dark mode from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('admin_theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }
    });

    // Keyboard shortcut: Ctrl/Cmd + K for search
    document.addEventListener('keydown', function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            const searchInput = document.getElementById('headerSearchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }

        // Ctrl/Cmd + Shift + N for new order (POS)
        if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'N') {
            event.preventDefault();
            window.location.href = '{{ route('admin.pos.index') }}';
        }
    });

    // Toggle user dropdown
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdownMenu');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    // Close dropdowns on click outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdownMenu');
        const userProfile = document.getElementById('userProfileButton');
        if (dropdown && userProfile && !userProfile.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>
