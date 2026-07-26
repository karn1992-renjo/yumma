/**
 * Laravel Application Entry Point
 * Bootstraps the application and initializes real-time features
 */

import './bootstrap';

/**
 * Real-time Order Manager - Pure JavaScript
 * Handles live order notifications, accepting/rejecting orders
 */

class RealTimeOrderManager {
    constructor() {
        this.pollingInterval = null;
        this.lastCheckTime = null;
        this.pendingOrders = new Map();
        this.pollingFrequency = 5000; // 5 seconds
        this.audioContext = null;
        this.audioAllowed = false;
        this.toastContainer = null;
        this.currentOrderForReject = null;
        this.init();
    }
    
    init() {
        // Create toast container
        this.createToastContainer();
        
        // Initialize last check time (get orders from last 5 minutes)
        this.lastCheckTime = new Date();
        this.lastCheckTime.setMinutes(this.lastCheckTime.getMinutes() - 5);
        
        // Initialize audio
        this.initAudio();
        
        // Start polling for new orders
        this.startPolling();
        
        // Start refreshing order counts
        this.startCountRefresh();
        
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refreshCounts();
            }
        });
        
        // Request notification permission and enable audio on first user interaction
        const enableAudio = async () => {
            this.audioAllowed = true;
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            this.initAudioContext();
            if (this.audioContext) {
                try {
                    if (this.audioContext.state === 'suspended') {
                        await this.audioContext.resume();
                    }
                    const unlockOsc = this.audioContext.createOscillator();
                    const unlockGain = this.audioContext.createGain();
                    unlockGain.gain.value = 0;
                    unlockOsc.connect(unlockGain);
                    unlockGain.connect(this.audioContext.destination);
                    unlockOsc.start();
                    unlockOsc.stop(this.audioContext.currentTime + 0.01);
                } catch (e) {
                    console.log('Audio unlock failed:', e);
                }
            }
        };
        document.addEventListener('pointerdown', enableAudio, { once: true });
        document.addEventListener('keydown', enableAudio, { once: true });
        
        // Initialize reject modal handler
        this.initRejectModal();
        
        console.log('Real-time order manager initialized');
    }
    
    createToastContainer() {
        let container = document.getElementById('orderToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'orderToastContainer';
            container.className = 'order-toast-container';
            document.body.appendChild(container);
        }
        this.toastContainer = container;
    }
    
    initAudio() {
        // Use Web Audio API for notification sounds
        this.useWebAudio = true;
        this.audioAllowed = false;
    }
    
    initAudioContext() {
        if (!this.audioAllowed || this.audioContext) return;
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch(e) {
            this.useWebAudio = false;
            console.log('Web Audio API not supported');
        }
    }
    
    async playNotificationSound() {
        if (!this.useWebAudio || !this.audioAllowed) return;
        
        if (!this.audioContext) {
            this.initAudioContext();
        }
        
        if (!this.audioContext) return;
        
        if (this.audioContext.state === 'suspended') {
            console.log('AudioContext suspended, skipping sound until next interaction');
            return;
        }
        
        try {
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.value = 880; // A5 note
            gainNode.gain.value = 0.3;
            
            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.00001, this.audioContext.currentTime + 0.5);
            oscillator.stop(this.audioContext.currentTime + 0.5);
        } catch(e) {
            console.log('Could not play sound:', e);
        }
    }
    
    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.checkNewOrders();
        }, this.pollingFrequency);
    }
    
    startCountRefresh() {
        // Refresh order counts every 10 seconds
        setInterval(() => {
            this.refreshCounts();
        }, 10000);
    }
    
    async checkNewOrders() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) return;
            
            const response = await fetch(`/restaurant/orders/check-new?last_check=${encodeURIComponent(this.lastCheckTime.toISOString())}`, {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success && data.new_orders && data.new_orders.length > 0) {
                data.new_orders.forEach(order => {
                    if (!this.pendingOrders.has(order.id)) {
                        this.pendingOrders.set(order.id, order);
                        this.showOrderNotification(order);
                        this.playNotificationSound();
                    }
                });
            }
            
            if (data.server_time) {
                this.lastCheckTime = new Date(data.server_time);
            }
            
            this.updatePendingBadge(data.pending_count || 0);
            
        } catch (error) {
            console.error('Error checking orders:', error);
        }
    }
    
    async refreshCounts() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) return;
            
            const response = await fetch('/restaurant/orders/counts', {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const counts = await response.json();
            this.updateDashboardStats(counts);
            
        } catch (error) {
            console.error('Error refreshing counts:', error);
        }
    }

    formatCurrency(value) {
        if (typeof window.formatCurrency === 'function') {
            return window.formatCurrency(value);
        }

        const symbol = window.currencySymbol || '₹';
        const decimals = Number.isFinite(Number(window.currencyDecimals))
            ? Number(window.currencyDecimals)
            : 2;
        const amount = Number.parseFloat(value);

        return `${symbol}${Number.isFinite(amount) ? amount.toFixed(decimals) : (0).toFixed(decimals)}`;
    }
    
    showOrderNotification(order) {
        // Show browser notification if page is hidden and permission granted
        if (document.hidden && Notification.permission === 'granted') {
            new Notification('New Order Received!', {
                body: `Order #${order.id} from ${order.customer_name} - ${this.formatCurrency(order.total)}`,
                icon: '/favicon.ico',
                tag: `order-${order.id}`,
                silent: false
            });
        }
        
        // Create toast notification element
        const toast = document.createElement('div');
        toast.className = 'order-toast';
        toast.dataset.orderId = order.id;
        toast.innerHTML = `
            <div class="order-toast-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="order-toast-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <strong class="order-toast-title">New Order Received!</strong>
                </div>
                <button class="order-toast-close" onclick="this.closest('.order-toast').remove()">&times;</button>
            </div>
            <div class="order-toast-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-6">Order #${order.id}</div>
                        <div class="small text-muted">${this.escapeHtml(order.customer_name)} • ${order.items_count} items</div>
                        ${order.items_preview ? `<div class="small text-muted mt-1">${this.escapeHtml(order.items_preview)}</div>` : ''}
                        <div class="fw-bold text-primary mt-2">${this.formatCurrency(order.total)}</div>
                    </div>
                    <div class="order-toast-actions">
                        <button class="btn-accept-order" data-order-id="${order.id}">
                            <i class="fas fa-check me-1"></i> Accept
                        </button>
                        <button class="btn-reject-order" data-order-id="${order.id}">
                            <i class="fas fa-times me-1"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add event listeners to buttons
        const acceptBtn = toast.querySelector('.btn-accept-order');
        const rejectBtn = toast.querySelector('.btn-reject-order');
        
        acceptBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.acceptOrder(order.id, toast);
        });
        
        rejectBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showRejectModal(order.id, toast);
        });
        
        // Add toast to container
        if (this.toastContainer) {
            this.toastContainer.appendChild(toast);
        } else {
            document.body.appendChild(toast);
        }
        
        // Auto remove after 25 seconds
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.classList.add('toast-slide-out');
                setTimeout(() => toast.remove(), 300);
            }
        }, 25000);
    }
    
    async acceptOrder(orderId, toastElement) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            
            const response = await fetch(`/restaurant/orders/${orderId}/accept`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove the toast notification
                if (toastElement) toastElement.remove();
                this.pendingOrders.delete(orderId);
                this.showToastMessage('Order accepted successfully!', 'success');
                
                // Refresh current page if on orders page
                if (window.location.pathname.includes('/restaurant/orders')) {
                    setTimeout(() => location.reload(), 500);
                } else {
                    this.refreshCounts();
                }
            } else {
                this.showToastMessage(data.message || 'Failed to accept order', 'error');
            }
        } catch (error) {
            console.error('Error accepting order:', error);
            this.showToastMessage('Failed to accept order. Please try again.', 'error');
        }
    }
    
    showRejectModal(orderId, toastElement) {
        this.currentOrderForReject = { orderId, toastElement };
        const modalElement = document.getElementById('rejectOrderModal');
        if (!modalElement) {
            console.error('Reject modal not found');
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        const reasonTextarea = document.getElementById('rejectReason');
        if (reasonTextarea) reasonTextarea.value = '';
        modal.show();
    }
    
    initRejectModal() {
        const confirmBtn = document.getElementById('confirmRejectBtn');
        if (!confirmBtn) return;
        
        // Remove existing listeners by cloning
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', async () => {
            if (!this.currentOrderForReject) return;
            
            const reason = document.getElementById('rejectReason')?.value.trim();
            if (!reason) {
                this.showToastMessage('Please provide a reason for rejection', 'warning');
                return;
            }
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                
                const response = await fetch(`/restaurant/orders/${this.currentOrderForReject.orderId}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ reason: reason })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove the toast notification
                    if (this.currentOrderForReject.toastElement) {
                        this.currentOrderForReject.toastElement.remove();
                    }
                    this.pendingOrders.delete(this.currentOrderForReject.orderId);
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('rejectOrderModal'));
                    if (modal) modal.hide();
                    
                    this.showToastMessage('Order rejected successfully!', 'warning');
                    
                    // Refresh current page if on orders page
                    if (window.location.pathname.includes('/restaurant/orders')) {
                        setTimeout(() => location.reload(), 500);
                    } else {
                        this.refreshCounts();
                    }
                } else {
                    this.showToastMessage(data.message || 'Failed to reject order', 'error');
                }
            } catch (error) {
                console.error('Error rejecting order:', error);
                this.showToastMessage('Failed to reject order. Please try again.', 'error');
            } finally {
                this.currentOrderForReject = null;
            }
        });
    }
    
    updatePendingBadge(count) {
        const badge = document.getElementById('pendingOrdersBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
                badge.classList.add('badge-pulse');
                setTimeout(() => badge.classList.remove('badge-pulse'), 500);
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Update browser tab title
        if (count > 0) {
            const currentTitle = document.title.replace(/^\(\d+\)\s/, '');
            document.title = `(${count}) ${currentTitle}`;
        } else {
            document.title = document.title.replace(/^\(\d+\)\s/, '');
        }
    }
    
    updateDashboardStats(counts) {
        // Update pending orders count in stats cards
        const pendingCard = document.querySelector('.stat-card .pending-count');
        if (pendingCard && counts.pending !== undefined) {
            pendingCard.textContent = counts.pending;
        }
        
        // Update today's orders count
        const todayOrdersCard = document.querySelector('.stat-card .today-orders');
        if (todayOrdersCard && counts.total_today !== undefined) {
            todayOrdersCard.textContent = counts.total_today;
        }
        
        // Update revenue today
        const revenueCard = document.querySelector('.stat-card .revenue-today');
        if (revenueCard && counts.revenue_today !== undefined) {
            revenueCard.textContent = this.formatCurrency(counts.revenue_today);
        }
        
        // Update confirmed orders count
        const confirmedCard = document.querySelector('.stat-card .confirmed-count');
        if (confirmedCard && counts.confirmed !== undefined) {
            confirmedCard.textContent = counts.confirmed;
        }
        
        // Update preparing orders count
        const preparingCard = document.querySelector('.stat-card .preparing-count');
        if (preparingCard && counts.preparing !== undefined) {
            preparingCard.textContent = counts.preparing;
        }
    }
    
    showToastMessage(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `custom-toast-message toast-${type}`;
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        
        toast.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <i class="fas ${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('toast-slide-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    }
}

function initPosTerminalShortcuts() {
    const actionConfig = {
        newBill: {
            key: 'F1',
            label: 'New Bill',
            selectors: [
                '[data-pos-action="new-bill"]',
                '[data-action="new-bill"]',
                '[data-shortcut="F1"]',
                '#newBillBtn',
                '#new-bill-btn',
                '.js-new-bill',
                '.new-bill-btn'
            ],
            methods: [
                ['POS', 'newBill'],
                ['posTerminal', 'newBill'],
                ['newBill'],
                ['createNewBill']
            ],
            textMatchers: ['new bill', 'newbill']
        },
        holdBill: {
            key: 'F2',
            label: 'Hold Bill',
            selectors: [
                '[data-pos-action="hold-bill"]',
                '[data-action="hold-bill"]',
                '[data-shortcut="F2"]',
                '#holdBillBtn',
                '#hold-bill-btn',
                '.js-hold-bill',
                '.hold-bill-btn'
            ],
            methods: [
                ['POS', 'holdBill'],
                ['posTerminal', 'holdBill'],
                ['holdBill'],
                ['holdCurrentBill']
            ],
            textMatchers: ['hold bill', 'holdbill']
        },
        fullscreen: {
            key: null,
            label: 'Full Screen',
            selectors: [
                '[data-pos-action="toggle-fullscreen"]',
                '[data-action="toggle-fullscreen"]',
                '#posFullscreenBtn',
                '#fullscreenBtn',
                '#fullScreenBtn',
                '#toggleFullscreenBtn',
                '.js-toggle-fullscreen',
                '.pos-fullscreen-btn',
                '.fullscreen-btn',
                '.full-screen-btn',
                '[aria-label*="Full Screen" i]',
                '[aria-label*="Fullscreen" i]',
                '[title*="Full Screen" i]',
                '[title*="Fullscreen" i]'
            ],
            methods: [],
            textMatchers: ['full screen', 'fullscreen']
        }
    };

    const actionElements = 'button, a, [role="button"], input[type="button"], input[type="submit"]';

    function isDisabled(element) {
        return Boolean(
            element.disabled ||
            element.getAttribute('aria-disabled') === 'true' ||
            element.classList.contains('disabled')
        );
    }

    function normalizedText(element) {
        const value = element.value ||
            element.textContent ||
            element.getAttribute('aria-label') ||
            element.getAttribute('title') ||
            '';
        return value.replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function findActionElement(config) {
        for (const selector of config.selectors) {
            const element = document.querySelector(selector);
            if (element && !isDisabled(element)) {
                return element;
            }
        }

        const candidates = Array.from(document.querySelectorAll(actionElements));
        return candidates.find((element) => {
            if (isDisabled(element)) return false;
            const text = normalizedText(element);
            return config.textMatchers.some((match) => text.includes(match));
        }) || null;
    }

    function getWindowMethod(path) {
        if (path.length === 1) {
            return typeof window[path[0]] === 'function' ? window[path[0]].bind(window) : null;
        }

        const target = window[path[0]];
        if (!target || typeof target[path[1]] !== 'function') {
            return null;
        }

        return target[path[1]].bind(target);
    }

    function runAction(config) {
        if (config === actionConfig.fullscreen) {
            window.togglePosFullscreen();
            return true;
        }

        const element = findActionElement(config);
        if (element) {
            element.click();
            return true;
        }

        for (const path of config.methods) {
            const method = getWindowMethod(path);
            if (method) {
                method();
                return true;
            }
        }

        return false;
    }

    function annotateAction(config) {
        const element = findActionElement(config);
        if (!element) return;

        if (config.key) {
            element.setAttribute('aria-keyshortcuts', config.key);
        }
        if (!element.title) {
            element.title = config.key
                ? `${config.label} (${config.key})`
                : config.label;
        }
    }

    Object.values(actionConfig).forEach(annotateAction);

    function fullscreenElement() {
        return document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement ||
            null;
    }

    async function requestFullscreen(element) {
        if (element.requestFullscreen) return element.requestFullscreen();
        if (element.webkitRequestFullscreen) return element.webkitRequestFullscreen();
        if (element.mozRequestFullScreen) return element.mozRequestFullScreen();
        if (element.msRequestFullscreen) return element.msRequestFullscreen();
        throw new Error('Fullscreen API is not available in this browser.');
    }

    async function exitFullscreen() {
        if (document.exitFullscreen) return document.exitFullscreen();
        if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
        if (document.mozCancelFullScreen) return document.mozCancelFullScreen();
        if (document.msExitFullscreen) return document.msExitFullscreen();
        return null;
    }

    function fullscreenTarget() {
        return document.querySelector('[data-pos-fullscreen-target]') ||
            document.querySelector('#posTerminal') ||
            document.querySelector('.pos-terminal') ||
            document.documentElement;
    }

    function setFullscreenButtonState() {
        const active = Boolean(fullscreenElement());
        document.body.classList.toggle('pos-terminal-fullscreen', active);

        const element = findActionElement(actionConfig.fullscreen);
        if (!element) return;

        element.setAttribute('aria-pressed', active ? 'true' : 'false');
        element.title = active ? 'Exit Full Screen' : 'Full Screen';
        element.classList.toggle('is-active', active);

        const icon = element.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-expand', !active);
            icon.classList.toggle('fa-compress', active);
        }
    }

    window.togglePosFullscreen = async function togglePosFullscreen() {
        try {
            if (fullscreenElement()) {
                await exitFullscreen();
            } else {
                await requestFullscreen(fullscreenTarget());
            }
        } catch (error) {
            console.warn('Unable to toggle POS fullscreen mode:', error);
        } finally {
            setFullscreenButtonState();
        }
    };

    const fullscreenButton = findActionElement(actionConfig.fullscreen);
    if (fullscreenButton) {
        fullscreenButton.addEventListener('click', (event) => {
            event.preventDefault();
            window.togglePosFullscreen();
        });
    }

    document.addEventListener('fullscreenchange', setFullscreenButtonState);
    document.addEventListener('webkitfullscreenchange', setFullscreenButtonState);
    document.addEventListener('mozfullscreenchange', setFullscreenButtonState);
    document.addEventListener('MSFullscreenChange', setFullscreenButtonState);
    setFullscreenButtonState();

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const config = Object.values(actionConfig).find((item) => item.key && item.key === event.key);
        if (!config) return;

        if (runAction(config)) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
}

// Initialize the order manager when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    initPosTerminalShortcuts();

    // Only initialize if we're on a restaurant page (has restaurant-id meta)
    const restaurantId = document.querySelector('meta[name="restaurant-id"]')?.content;
    if (restaurantId) {
        window.orderManager = new RealTimeOrderManager();
        console.log('Real-time order manager started for restaurant:', restaurantId);
    }
});

// Export for module usage if needed
export { RealTimeOrderManager };
