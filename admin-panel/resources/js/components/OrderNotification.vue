<template>
    <div class="order-notification-container">
        <!-- Notification Toast -->
        <transition-group name="notification" tag="div" class="notification-stack">
            <div v-for="notification in notifications" 
                 :key="notification.id" 
                 :class="['notification-toast', notification.type]"
                 @click="handleNotificationClick(notification)">
                <div class="notification-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="notification-icon">
                            <i :class="notification.type === 'new-order' ? 'fas fa-bell' : 'fas fa-sync-alt'"></i>
                        </div>
                        <strong class="notification-title">
                            {{ notification.type === 'new-order' ? 'New Order Received!' : 'Order Status Updated' }}
                        </strong>
                    </div>
                    <button class="btn-close btn-close-white" @click.stop="removeNotification(notification.id)"></button>
                </div>
                <div class="notification-body">
                    <div v-if="notification.type === 'new-order'" class="d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <div class="fw-bold">Order #{{ notification.orderId }}</div>
                            <div class="small">{{ notification.customerName }} • {{ notification.itemsCount }} items</div>
                            <div class="fw-bold text-primary mt-1">{{ formatCurrency(notification.total) }}</div>
                        </div>
                        <div class="notification-actions">
                            <button class="btn btn-accept btn-sm rounded-3" @click.stop="acceptOrder(notification.orderId)">
                                <i class="fas fa-check me-1"></i> Accept
                            </button>
                            <button class="btn btn-reject btn-sm rounded-3 mt-1" @click.stop="showRejectModal(notification.orderId)">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                        </div>
                    </div>
                    <div v-else>
                        <div class="mb-2">Order #{{ notification.orderId }} has been {{ notification.statusLabel }}</div>
                        <a :href="`/restaurant/orders/${notification.orderId}`" class="text-decoration-none small">
                            View Order <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </transition-group>

        <!-- Reject Order Modal -->
        <div class="modal fade" id="rejectOrderModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <h5 class="modal-title fw-bold">Reject Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Rejection</label>
                            <textarea v-model="rejectReason" class="form-control" rows="3" 
                                      placeholder="Please provide a reason for rejecting this order..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger rounded-3" @click="confirmReject" :disabled="!rejectReason">
                            <i class="fas fa-times-circle me-2"></i> Reject Order
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audio for new order -->
        <audio ref="newOrderSound" preload="auto">
            <source src="/sounds/new-order.mp3" type="audio/mpeg">
        </audio>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export default {
    name: 'OrderNotification',
    setup() {
        const notifications = ref([]);
        const echo = ref(null);
        const newOrderSound = ref(null);
        const rejectOrderId = ref(null);
        const rejectReason = ref('');
        let notificationId = 0;

        const formatCurrency = (price) => {
            if (typeof window.formatCurrency === 'function') {
                return window.formatCurrency(price);
            }

            const symbol = window.currencySymbol || '₹';
            const decimals = Number.isFinite(Number(window.currencyDecimals))
                ? Number(window.currencyDecimals)
                : 2;
            const amount = Number.parseFloat(price);

            return `${symbol}${Number.isFinite(amount) ? amount.toFixed(decimals) : (0).toFixed(decimals)}`;
        };

        const addNotification = (data, type) => {
            const id = ++notificationId;
            const notification = {
                id,
                type,
                orderId: data.id,
                total: data.total,
                customerName: data.customer_name,
                itemsCount: data.items_count,
                createdAt: data.created_at,
                statusLabel: data.status_label,
                timestamp: Date.now()
            };

            notifications.value.push(notification);

            // Play sound for new orders
            if (type === 'new-order' && newOrderSound.value) {
                newOrderSound.value.play().catch(e => console.log('Audio play failed:', e));
            }

            // Auto remove after 30 seconds
            setTimeout(() => {
                removeNotification(id);
            }, 30000);
        };

        const removeNotification = (id) => {
            const index = notifications.value.findIndex(n => n.id === id);
            if (index !== -1) {
                notifications.value.splice(index, 1);
            }
        };

        const handleNotificationClick = (notification) => {
            if (notification.type === 'new-order') {
                window.open(`/restaurant/orders/${notification.orderId}`, '_blank');
            } else {
                window.location.href = `/restaurant/orders/${notification.orderId}`;
            }
        };

        const acceptOrder = async (orderId) => {
            try {
                const response = await fetch(`/restaurant/orders/${orderId}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove notification
                    const notification = notifications.value.find(n => n.orderId === orderId);
                    if (notification) {
                        removeNotification(notification.id);
                    }
                    
                    // Show success toast (you can integrate with your preferred toast library)
                    showToast('Order accepted successfully!', 'success');
                    
                    // Reload orders table if on orders page
                    if (window.location.pathname.includes('/restaurant/orders')) {
                        location.reload();
                    }
                }
            } catch (error) {
                console.error('Error accepting order:', error);
                showToast('Failed to accept order. Please try again.', 'error');
            }
        };

        const showRejectModal = (orderId) => {
            rejectOrderId.value = orderId;
            rejectReason.value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectOrderModal'));
            modal.show();
        };

        const confirmReject = async () => {
            if (!rejectReason.value.trim()) return;
            
            try {
                const response = await fetch(`/restaurant/orders/${rejectOrderId.value}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ reason: rejectReason.value })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove notification
                    const notification = notifications.value.find(n => n.orderId === rejectOrderId.value);
                    if (notification) {
                        removeNotification(notification.id);
                    }
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('rejectOrderModal'));
                    modal.hide();
                    
                    showToast('Order rejected successfully!', 'warning');
                    
                    // Reload orders table if on orders page
                    if (window.location.pathname.includes('/restaurant/orders')) {
                        location.reload();
                    }
                }
            } catch (error) {
                console.error('Error rejecting order:', error);
                showToast('Failed to reject order. Please try again.', 'error');
            }
        };

        const showToast = (message, type) => {
            // Simple toast implementation - you can replace with a library like toastr
            const toast = document.createElement('div');
            toast.className = `custom-toast toast-${type}`;
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        };

        const initializeEcho = () => {
            const restaurantId = document.querySelector('meta[name="restaurant-id"]')?.content;
            
            if (!restaurantId) return;
            
            const runtimeBroadcastConfig = window.AppBroadcastConfig ?? {};
            const pusherScheme = runtimeBroadcastConfig.scheme ?? import.meta.env.VITE_PUSHER_SCHEME ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
            const pusherPort = runtimeBroadcastConfig.port ?? import.meta.env.VITE_PUSHER_PORT ?? import.meta.env.VITE_REVERB_PORT ?? (pusherScheme === 'https' ? 443 : 80);
            const pusherCluster = runtimeBroadcastConfig.cluster ?? import.meta.env.VITE_PUSHER_APP_CLUSTER ?? import.meta.env.VITE_REVERB_APP_CLUSTER;
            const pusherHost = runtimeBroadcastConfig.host ?? import.meta.env.VITE_PUSHER_HOST ?? import.meta.env.VITE_REVERB_HOST ?? (pusherCluster ? `ws-${pusherCluster}.pusher.com` : undefined);
            const pusherKey = runtimeBroadcastConfig.key ?? import.meta.env.VITE_PUSHER_APP_KEY ?? import.meta.env.VITE_REVERB_APP_KEY;

            if (!pusherKey) {
                console.warn('Pusher app key is missing. Order notifications were not initialized.');
                return;
            }

            echo.value = new Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
                wsHost: pusherHost,
                wssHost: pusherHost,
                wsPort: pusherPort,
                wssPort: pusherPort,
                forceTLS: pusherScheme === 'https',
                enabledTransports: pusherScheme === 'https' ? ['wss'] : ['ws'],
            });
            
            // Listen for new orders
            echo.value.private(`restaurant.${restaurantId}`)
                .listen('NewOrderEvent', (e) => {
                    addNotification(e, 'new-order');
                })
                .listen('OrderStatusUpdatedEvent', (e) => {
                    addNotification(e, 'status-update');
                });
        };

        onMounted(() => {
            initializeEcho();
            if (newOrderSound.value) {
                newOrderSound.value.volume = 0.5;
            }
        });

        onUnmounted(() => {
            if (echo.value) {
                echo.value.disconnect();
            }
        });

        return {
            notifications,
            newOrderSound,
            rejectReason,
            formatCurrency,
            removeNotification,
            handleNotificationClick,
            acceptOrder,
            showRejectModal,
            confirmReject
        };
    }
};
</script>

<style scoped>
.order-notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    width: 380px;
}

.notification-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notification-toast {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    animation: slideIn 0.3s ease;
}

.notification-toast:hover {
    transform: translateX(-5px);
    box-shadow: 0 15px 45px rgba(0, 0, 0, 0.2);
}

.notification-toast.new-order {
    border-left: 4px solid #ff6b6b;
}

.notification-toast.status-update {
    border-left: 4px solid #4ecdc4;
}

.notification-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-icon {
    width: 28px;
    height: 28px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.notification-title {
    font-size: 14px;
}

.notification-body {
    padding: 16px;
}

.notification-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.btn-accept {
    background: #10b981;
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
}

.btn-accept:hover {
    background: #059669;
    color: white;
}

.btn-reject {
    background: #ef4444;
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 12px;
}

.btn-reject:hover {
    background: #dc2626;
    color: white;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}

.notification-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.notification-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
