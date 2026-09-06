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
            <button type="button" class="header-icon-btn" id="themeToggleBtn" title="Toggle dark / light mode" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i>
            </button>
            <a href="{{ route('admin.orders.index', ['status' => 'action_required']) }}" class="header-icon-btn" id="pendingOrdersBtn">
                <i class="fas fa-bell"></i>
                @php
                    $pendingOrdersCount = \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count();
                @endphp
                <span class="badge-notification {{ $pendingOrdersCount > 0 ? '' : 'd-none' }}" id="adminPendingOrdersBadge">{{ $pendingOrdersCount > 99 ? '99+' : $pendingOrdersCount }}</span>
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

<div id="adminOrderToastContainer" class="admin-order-toast-container"></div>

<style>
    .admin-order-toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        width: min(390px, calc(100vw - 40px));
        pointer-events: none;
    }

    .admin-order-toast {
        margin-bottom: 14px;
        overflow: hidden;
        border-left: 4px solid var(--primary);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 20px 42px rgba(15, 23, 42, .18);
        animation: slideInRight .3s ease;
        pointer-events: auto;
    }

    .admin-order-toast-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 15px;
        color: #fff;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .admin-order-toast-title {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 900;
    }

    .admin-order-toast-close {
        border: 0;
        background: transparent;
        color: #fff;
        font-size: 20px;
        line-height: 1;
        opacity: .75;
    }

    .admin-order-toast-close:hover {
        opacity: 1;
    }

    .admin-order-toast-body {
        padding: 15px;
    }

    .admin-order-toast-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .admin-order-toast-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 13px;
    }

    .admin-order-toast-actions .btn {
        border-radius: 10px;
        font-size: 12px;
        font-weight: 900;
    }

    @keyframes adminBadgePulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.18); }
        100% { transform: scale(1); }
    }

    .badge-pulse {
        animation: adminBadgePulse .5s ease;
    }
</style>

<script>
    const adminOrderNotificationRoutes = {
        checkNew: @json(route('admin.orders.check-new')),
        counts: @json(route('admin.orders.notification-counts')),
        queue: @json(route('admin.orders.index', ['status' => 'action_required'])),
        statusBase: @json(url('/admin/orders')),
        favicon: @json(App\Models\AppSetting::getValue('app_favicon') ? \Illuminate\Support\Facades\Storage::disk('public')->url(App\Models\AppSetting::getValue('app_favicon')) : asset('favicon.ico')),
    };

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

    class AdminOrderNotificationManager {
        constructor(routes) {
            this.routes = routes;
            this.lastCheckTime = new Date();
            this.lastCheckTime.setMinutes(this.lastCheckTime.getMinutes() - 2);
            this.pollingFrequency = 5000;
            this.pollingInterval = null;
            this.audioContext = null;
            this.useWebAudio = true;
            this.toastContainer = document.getElementById('adminOrderToastContainer');
            this.notifiedOrderIds = new Set(JSON.parse(sessionStorage.getItem('adminNotifiedOrderIds') || '[]'));
            this.baseTitle = document.title.replace(/^\(\d+\)\s/, '');
        }

        init() {
            this.startPolling();
            setTimeout(() => this.checkNewOrders(), 1200);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.refreshCounts();
            });

            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        }

        startPolling() {
            this.pollingInterval = setInterval(() => this.checkNewOrders(), this.pollingFrequency);
        }

        async checkNewOrders() {
            try {
                const url = new URL(this.routes.checkNew, window.location.origin);
                url.searchParams.set('last_check', this.lastCheckTime.toISOString());

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) throw new Error('Order polling failed');

                const data = await response.json();
                if (data.success && Array.isArray(data.new_orders)) {
                    data.new_orders.forEach(order => {
                        const id = String(order.id);
                        if (this.notifiedOrderIds.has(id)) return;

                        this.notifiedOrderIds.add(id);
                        this.persistNotifiedOrders();
                        this.showOrderNotification(order);
                        this.playNotificationSound();
                    });
                }

                if (data.server_time) this.lastCheckTime = new Date(data.server_time);
                this.updatePendingBadge(Number(data.pending_count || 0));
            } catch (error) {
                console.debug('Admin order polling skipped', error);
            }
        }

        async refreshCounts() {
            try {
                const response = await fetch(this.routes.counts, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) return;
                const data = await response.json();
                this.updatePendingBadge(Number(data.pending_count || 0));
            } catch (error) {
                console.debug('Admin order count refresh skipped', error);
            }
        }

        playNotificationSound() {
            if (!this.useWebAudio) return;
            try {
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (this.audioContext.state === 'suspended') this.audioContext.resume();

                const oscillator = this.audioContext.createOscillator();
                const gainNode = this.audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(this.audioContext.destination);
                oscillator.frequency.value = 880;
                gainNode.gain.value = .28;
                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(.00001, this.audioContext.currentTime + .5);
                oscillator.stop(this.audioContext.currentTime + .5);
            } catch (error) {
                this.useWebAudio = false;
            }
        }

        showOrderNotification(order) {
            const orderLabel = order.order_number || order.id;
            const amount = this.formatCurrency(order.total);

            if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                new Notification('New order received', {
                    body: `#${orderLabel} from ${order.restaurant_name} - ${amount}`,
                    icon: this.routes.favicon,
                    tag: `admin-order-${order.id}`
                });
            }

            const toast = document.createElement('div');
            toast.className = 'admin-order-toast';
            toast.dataset.orderId = order.id;
            toast.innerHTML = `
                <div class="admin-order-toast-header">
                    <div class="admin-order-toast-title"><i class="fas fa-bell"></i> New Order Received</div>
                    <button class="admin-order-toast-close" type="button" aria-label="Dismiss">&times;</button>
                </div>
                <div class="admin-order-toast-body">
                    <div class="fw-bold text-dark">#${this.escapeHtml(orderLabel)}</div>
                    <div class="admin-order-toast-meta">${this.escapeHtml(order.restaurant_name)} - ${this.escapeHtml(order.customer_name || 'Guest')}</div>
                    <div class="admin-order-toast-meta">${Number(order.items_count || 0)} items${order.items_preview ? ` - ${this.escapeHtml(order.items_preview)}` : ''}</div>
                    <div class="fw-bold text-primary mt-2">${amount}</div>
                    <div class="admin-order-toast-actions">
                        <a class="btn btn-outline-primary btn-sm" href="${order.show_url}"><i class="fas fa-eye me-1"></i>View</a>
                        <a class="btn btn-outline-secondary btn-sm" href="${this.routes.queue}"><i class="fas fa-list me-1"></i>Queue</a>
                        <button class="btn btn-success btn-sm js-admin-confirm-order" type="button"><i class="fas fa-check me-1"></i>Confirm</button>
                    </div>
                </div>
            `;

            toast.querySelector('.admin-order-toast-close')?.addEventListener('click', () => toast.remove());
            toast.querySelector('.js-admin-confirm-order')?.addEventListener('click', () => this.confirmOrder(order.id, toast));
            this.toastContainer?.appendChild(toast);

            setTimeout(() => {
                if (!toast.parentNode) return;
                toast.classList.add('toast-slide-out');
                setTimeout(() => toast.remove(), 300);
            }, 25000);
        }

        async confirmOrder(orderId, toastElement) {
            const button = toastElement.querySelector('.js-admin-confirm-order');
            const original = button?.innerHTML;
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Confirming';
            }

            try {
                const response = await fetch(`${this.routes.statusBase}/${orderId}/status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ status: 'confirmed' })
                });
                const data = await response.json();

                if (data.success) {
                    toastElement.remove();
                    if (typeof showToastMessage === 'function') showToastMessage('Order confirmed successfully.', 'success');
                    this.refreshCounts();
                    if (window.location.pathname.includes('/admin/orders')) setTimeout(() => location.reload(), 500);
                    return;
                }

                throw new Error(data.message || 'Could not confirm order');
            } catch (error) {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = original;
                }
                if (typeof showToastMessage === 'function') showToastMessage(error.message || 'Failed to confirm order.', 'error');
            }
        }

        updatePendingBadge(count) {
            ['adminPendingOrdersBadge', 'adminOrderQueueSidebarBadge'].forEach(id => {
                const badge = document.getElementById(id);
                if (!badge) return;
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('d-none', count <= 0);
                if (count > 0) {
                    badge.classList.add('badge-pulse');
                    setTimeout(() => badge.classList.remove('badge-pulse'), 500);
                }
            });

            document.title = count > 0 ? `(${count}) ${this.baseTitle}` : this.baseTitle;
        }

        persistNotifiedOrders() {
            const latestIds = Array.from(this.notifiedOrderIds).slice(-80);
            this.notifiedOrderIds = new Set(latestIds);
            sessionStorage.setItem('adminNotifiedOrderIds', JSON.stringify(latestIds));
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        formatCurrency(value) {
            const amount = Number.parseFloat(value);
            const decimals = Number.isFinite(Number(window.currencyDecimals)) ? Number(window.currencyDecimals) : 2;
            const symbol = window.currencySymbol || 'Rs ';
            return `${symbol}${Number.isFinite(amount) ? amount.toFixed(decimals) : (0).toFixed(decimals)}`;
        }
    }

    refreshSupportInboxBadge();
    setInterval(refreshSupportInboxBadge, 15000);

    document.addEventListener('DOMContentLoaded', () => {
        window.adminOrderNotifications = new AdminOrderNotificationManager(adminOrderNotificationRoutes);
        window.adminOrderNotifications.init();
    });
</script>
<script>
    /* Dark / light mode toggle. Pre-paint init lives in the layout <head>. */
    (function () {
        var btn = document.getElementById('themeToggleBtn');
        if (!btn) return;
        var root = document.documentElement;

        function current() {
            return root.getAttribute('data-theme')
                || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        }
        function paintIcon() {
            var i = btn.querySelector('i');
            if (i) i.className = current() === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        paintIcon();

        btn.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('admin-theme', next); } catch (e) {}
            paintIcon();
        });
    })();
</script>
