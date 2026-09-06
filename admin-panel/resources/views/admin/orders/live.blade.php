@extends('layouts.admin')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
@endphp

@section('title', 'Live Orders')
@section('header', 'Live Orders')

@section('styles')
<style>
    .live-toolbar,.live-filters,.live-bulk,.live-column,.live-modal-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .live-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;margin-bottom:14px}
    .live-title{display:flex;align-items:center;gap:10px;font-weight:700;color:#111827}
    .live-dot{width:10px;height:10px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.14)}
    .live-muted{color:#6b7280;font-size:12px}
    .live-actions{display:flex;gap:8px;flex-wrap:wrap}
    .live-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:14px}
    .live-kpi{background:#fff;border:1px solid #e5e7eb;border-left:4px solid var(--kpi,#64748b);border-radius:8px;padding:12px 14px;min-height:74px}
    .live-kpi span{display:block;color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:.04em;white-space:normal}
    .live-kpi strong{display:block;font-size:25px;line-height:1.2;color:#111827}
    .live-filters{padding:14px;margin-bottom:12px}
    .live-grid{display:grid;grid-template-columns:minmax(240px,2fr) repeat(6,minmax(130px,1fr)) auto;gap:10px;align-items:end}
    .live-grid label{font-size:12px;color:#4b5563;font-weight:600;margin-bottom:4px}
    .live-bulk{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 12px;margin-bottom:12px}
    .live-board{display:flex;flex-direction:column;gap:14px;overflow:visible;padding-bottom:24px}
    .live-column{width:100%;min-height:0;display:flex;flex-direction:column;overflow:hidden}
    .live-column-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
    .live-column-title{display:flex;gap:8px;align-items:center;font-weight:800;color:#111827}
    .live-count{font-size:12px;background:#eef2f7;color:#374151;border-radius:999px;padding:3px 10px}
    .live-column-body{padding:10px;display:flex;flex-direction:column;gap:8px;background:#fff}
    .live-card{border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fff;display:grid;grid-template-columns:minmax(180px,.85fr) minmax(360px,2fr) minmax(260px,auto);gap:14px;align-items:center;transition:box-shadow .2s,border-color .2s,transform .2s}
    .live-card:hover{border-color:#cbd5e1;box-shadow:0 8px 22px rgba(15,23,42,.08)}
    .live-card.is-new{border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.16)}
    .live-card.is-changed{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.16)}
    .live-card-top{display:flex;justify-content:flex-start;align-items:center;gap:10px;margin:0;min-width:0}
    .live-card-title{display:flex;gap:8px;align-items:center;font-weight:800;color:#111827;min-width:0}
    .live-card-title span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .live-card-title input{width:16px;height:16px;flex:0 0 auto}
    .live-badge{font-size:11px;border-radius:999px;padding:3px 8px;background:#eef2ff;color:#3730a3;white-space:nowrap}
    .live-meta{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:6px 14px;color:#4b5563;font-size:12px;margin:0;min-width:0}
    .live-meta div{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .live-meta i{width:14px;color:#9ca3af}
    .live-money{font-weight:800;color:#111827}
    .live-card-actions{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap}
    .live-empty{padding:18px 12px;text-align:center;color:#9ca3af;font-size:13px;border:1px dashed #e5e7eb;border-radius:8px;background:#fbfdff}
    .live-overlay{position:fixed;inset:0;background:rgba(15,23,42,.24);display:none;align-items:center;justify-content:center;z-index:1060}
    .live-overlay.is-visible{display:flex}
    .live-modal-card{width:min(520px,calc(100vw - 28px));padding:18px}
    .live-modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .live-modal-title{font-weight:700;font-size:18px;color:#111827}
    .live-modal-close{border:0;background:transparent;font-size:24px;line-height:1;color:#6b7280}
    .live-driver-list{max-height:340px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px}
    .live-driver-row{display:flex;gap:10px;align-items:flex-start;padding:10px;border-bottom:1px solid #f3f4f6;cursor:pointer}
    .live-driver-row:last-child{border-bottom:0}
    .live-driver-row:hover{background:#f9fafb}
    .live-loading{position:fixed;right:18px;bottom:18px;background:#111827;color:#fff;border-radius:999px;padding:9px 14px;font-size:12px;display:none;z-index:1050}
    .live-loading.is-visible{display:block}
    @media (max-width:1200px){.live-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.live-card{grid-template-columns:1fr}.live-card-actions{justify-content:flex-start}.live-meta{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:640px){.live-toolbar,.live-bulk{align-items:flex-start;flex-direction:column}.live-grid{grid-template-columns:1fr}.live-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.live-meta{grid-template-columns:1fr}.live-card{gap:10px}.live-card-top{flex-wrap:wrap}.live-card-actions .btn{flex:1 1 auto}}
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="live-toolbar">
        <div>
            <div class="live-title"><span class="live-dot"></span><span>Live order list</span></div>
            <div class="live-muted">Auto-refreshes every 5 seconds. Last refresh: <span id="liveLastRefresh">-</span></div>
        </div>
        <div class="live-actions">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.orders.index', ['status' => 'action_required']) }}"><i class="fas fa-clock"></i> Queue</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.orders.index') }}"><i class="fas fa-list"></i> List</a>
            <button class="btn btn-primary btn-sm" type="button" id="liveRefreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <div class="live-kpis" id="liveKpis">
        @foreach(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready_for_pickup' => 'Ready', 'picked_up' => 'Picked up', 'on_the_way' => 'On the way', 'delivered_today' => 'Delivered today', 'failed_cancelled' => 'Failed/cancelled'] as $key => $label)
            <div class="live-kpi" data-kpi="{{ $key }}">
                <span>{{ $label }}</span>
                <strong>0</strong>
            </div>
        @endforeach
    </div>

    <form class="live-filters" id="liveFilterForm">
        <div class="live-grid">
            <div>
                <label for="liveSearch">Search</label>
                <input class="form-control form-control-sm" id="liveSearch" name="search" placeholder="Order, customer, phone, restaurant">
            </div>
            <div>
                <label for="liveStatusGroup">Status</label>
                <select class="form-control form-control-sm" id="liveStatusGroup" name="status_group">
                    <option value="active">Active</option>
                    @foreach($liveStatuses as $status => $meta)
                        <option value="{{ $status }}">{{ $meta['label'] }}</option>
                    @endforeach
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label for="liveRestaurant">Restaurant</label>
                <select class="form-control form-control-sm" id="liveRestaurant" name="restaurant_id">
                    <option value="">All</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}">{{ $restaurant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="liveBranch">Branch</label>
                <select class="form-control form-control-sm" id="liveBranch" name="branch_id">
                    <option value="">All</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="livePaymentStatus">Payment</label>
                <select class="form-control form-control-sm" id="livePaymentStatus" name="payment_status">
                    <option value="">All</option>
                    <option value="success">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div>
                <label for="liveOrderType">Type</label>
                <select class="form-control form-control-sm" id="liveOrderType" name="order_type">
                    <option value="">All</option>
                    <option value="delivery">Delivery</option>
                    <option value="takeaway">Takeaway</option>
                    <option value="dine_in">Dine in</option>
                </select>
            </div>
            <div>
                <label for="liveDateFrom">From</label>
                <input class="form-control form-control-sm" id="liveDateFrom" name="date_from" type="date" value="{{ now()->toDateString() }}">
            </div>
            <div>
                <button class="btn btn-outline-secondary btn-sm w-100" type="button" id="liveResetBtn"><i class="fas fa-times"></i> Reset</button>
            </div>
        </div>
    </form>

    <div class="live-bulk">
        <div><strong id="liveSelectedCount">0 selected</strong> <span class="live-muted">Use bulk updates for orders in compatible statuses.</span></div>
        <div class="live-actions">
            <select class="form-control form-control-sm" id="liveBulkStatus" style="width:180px">
                <option value="">Bulk action</option>
                <option value="confirmed">Confirm</option>
                <option value="preparing">Start preparing</option>
                <option value="ready_for_pickup">Mark ready</option>
            </select>
            <button class="btn btn-outline-primary btn-sm" type="button" id="liveBulkBtn"><i class="fas fa-check-double"></i> Apply</button>
        </div>
    </div>

    <div class="live-board" id="liveBoard"></div>
</div>

<div class="live-overlay" id="liveDriverModal" aria-hidden="true">
    <div class="live-modal-card">
        <div class="live-modal-head"><div class="live-modal-title">Assign driver</div><button class="live-modal-close" type="button" data-close-modal>&times;</button></div>
        <div class="live-driver-list" id="liveDriverList"></div>
    </div>
</div>

<div class="live-overlay" id="liveRefundModal" aria-hidden="true">
    <div class="live-modal-card">
        <div class="live-modal-head"><div class="live-modal-title">Refund order</div><button class="live-modal-close" type="button" data-close-modal>&times;</button></div>
        <form id="liveRefundForm">
            <input type="hidden" name="order_id">
            <div class="form-group"><label>Reason</label><textarea class="form-control" name="refund_reason" rows="3" required></textarea></div>
            <div class="form-group"><label>Amount</label><input class="form-control" name="refund_amount" type="number" min="0.01" step="0.01"></div>
            <button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-undo"></i> Process refund</button>
        </form>
    </div>
</div>

<div class="live-loading" id="liveLoading">Refreshing orders...</div>
@endsection

@section('scripts')
<script>
(() => {
    const routes = {
        data: @json(route('admin.orders.live-data')),
        bulk: @json(route('admin.orders.bulk-status'))
    };
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const currency = @json($currencySymbol);
    const state = {lastCheck: '', signatures: new Map(), selected: new Set(), orders: new Map(), timer: null};

    const board = document.getElementById('liveBoard');
    const filterForm = document.getElementById('liveFilterForm');
    const loading = document.getElementById('liveLoading');

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('liveRefreshBtn')?.addEventListener('click', () => loadLiveOrders(false));
        document.getElementById('liveResetBtn')?.addEventListener('click', () => {
            filterForm.reset();
            document.getElementById('liveDateFrom').value = @json(now()->toDateString());
            state.selected.clear();
            loadLiveOrders(false);
        });
        filterForm?.addEventListener('change', () => loadLiveOrders(false));
        filterForm?.addEventListener('submit', event => { event.preventDefault(); loadLiveOrders(false); });
        let searchTimer;
        document.getElementById('liveSearch')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadLiveOrders(false), 350);
        });
        document.getElementById('liveBulkBtn')?.addEventListener('click', bulkUpdateOrders);
        document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeModals));
        document.getElementById('liveRefundForm')?.addEventListener('submit', submitRefund);
        board?.addEventListener('click', handleBoardClick);
        loadLiveOrders(false);
        state.timer = setInterval(() => loadLiveOrders(true), 5000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadLiveOrders(true);
        });
    });

    async function loadLiveOrders(isPolling) {
        if (!board) return;
        const params = new URLSearchParams(new FormData(filterForm));
        if (isPolling && state.lastCheck) params.set('last_check', state.lastCheck);
        const url = `${routes.data}?${params.toString()}`;
        loading?.classList.add('is-visible');
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Could not load live orders.');
            renderKpis(data.counts || {});
            renderBoard(data.columns || []);
            state.lastCheck = data.server_time || state.lastCheck;
            document.getElementById('liveLastRefresh').textContent = new Date().toLocaleTimeString();
            window.dispatchEvent(new CustomEvent('admin-orders-live-refreshed', {detail: data.counts || {}}));
        } catch (error) {
            toast(error.message || 'Could not refresh live orders.', 'error');
        } finally {
            loading?.classList.remove('is-visible');
        }
    }

    function renderKpis(counts) {
        document.querySelectorAll('[data-kpi]').forEach(card => {
            card.querySelector('strong').textContent = counts[card.dataset.kpi] ?? 0;
        });
    }

    function renderBoard(columns) {
        const seen = new Set();
        board.innerHTML = columns.map(column => {
            const cards = (column.orders || []).map(order => {
                state.orders.set(String(order.id), order);
                seen.add(String(order.id));
                return renderOrderCard(order);
            }).join('');
            return `<section class="live-column" data-status="${escapeHtml(column.key)}">
                <div class="live-column-head">
                    <div class="live-column-title"><span style="width:9px;height:9px;border-radius:50%;background:${escapeHtml(column.color || '#64748b')}"></span>${escapeHtml(column.label)}</div>
                    <span class="live-count">${column.count || 0}</span>
                </div>
                <div class="live-column-body">${cards || '<div class="live-empty">No orders here</div>'}</div>
            </section>`;
        }).join('');
        for (const id of Array.from(state.selected)) {
            if (!seen.has(id)) state.selected.delete(id);
        }
        updateSelectedCount();
    }

    function renderOrderCard(order) {
        const signature = [order.status, order.payment_status, order.driver?.id || '', order.total, order.created_at_raw].join('|');
        const previous = state.signatures.get(String(order.id));
        const tone = !previous ? 'is-new' : (previous !== signature ? 'is-changed' : '');
        state.signatures.set(String(order.id), signature);
        const checked = state.selected.has(String(order.id)) ? 'checked' : '';
        return `<article class="live-card ${tone}" data-order-id="${order.id}">
            <div class="live-card-top">
                <div class="live-card-title"><input type="checkbox" data-select-order="${order.id}" ${checked}><span>#${escapeHtml(order.order_number || order.id)}</span></div>
                <span class="live-badge">${escapeHtml(order.order_type || 'delivery')}</span>
            </div>
            <div class="live-meta">
                <div><i class="fas fa-store"></i> ${escapeHtml(order.restaurant?.name || 'Restaurant')}</div>
                <div><i class="fas fa-user"></i> ${escapeHtml(order.customer?.name || 'Customer')} ${order.customer?.phone ? `- ${escapeHtml(order.customer.phone)}` : ''}</div>
                <div><i class="fas fa-receipt"></i> ${order.items_count || 0} item(s) - <span class="live-money">${formatMoney(order.total)}</span></div>
                <div><i class="fas fa-credit-card"></i> ${escapeHtml(order.payment_status || 'pending')} - ${escapeHtml(order.created_at || '')}</div>
                <div><i class="fas fa-motorcycle"></i> ${order.driver ? escapeHtml(order.driver.name) : 'No driver assigned'}</div>
            </div>
            <div class="live-card-actions">
                <a class="btn btn-outline-secondary btn-xs" href="${order.urls.show}"><i class="fas fa-eye"></i></a>
                <a class="btn btn-outline-secondary btn-xs" href="${order.urls.invoice}" target="_blank"><i class="fas fa-file-invoice"></i></a>
                ${nextStatusButton(order)}
                ${order.can_assign_driver ? `<button class="btn btn-outline-info btn-xs" type="button" data-action="driver" data-id="${order.id}"><i class="fas fa-motorcycle"></i></button>` : ''}
                ${order.can_refund ? `<button class="btn btn-outline-warning btn-xs" type="button" data-action="refund" data-id="${order.id}"><i class="fas fa-undo"></i></button>` : ''}
            </div>
        </article>`;
    }

    function nextStatusButton(order) {
        const next = {
            pending: ['confirmed', 'Confirm', 'check'],
            confirmed: ['preparing', 'Prepare', 'utensils'],
            preparing: ['ready_for_pickup', 'Ready', 'box'],
            ready_for_pickup: ['picked_up', 'Picked up', 'walking'],
            picked_up: ['on_the_way', 'On way', 'route'],
            on_the_way: ['delivered', 'Deliver', 'check-circle']
        }[order.status];
        if (!next) return '';
        return `<button class="btn btn-primary btn-xs" type="button" data-action="status" data-id="${order.id}" data-status="${next[0]}"><i class="fas fa-${next[2]}"></i> ${next[1]}</button>`;
    }

    function handleBoardClick(event) {
        const checkbox = event.target.closest('[data-select-order]');
        if (checkbox) {
            checkbox.checked ? state.selected.add(String(checkbox.dataset.selectOrder)) : state.selected.delete(String(checkbox.dataset.selectOrder));
            updateSelectedCount();
            return;
        }
        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;
        const id = actionButton.dataset.id;
        if (actionButton.dataset.action === 'status') updateOrderStatus(id, actionButton.dataset.status);
        if (actionButton.dataset.action === 'driver') openDriver(id);
        if (actionButton.dataset.action === 'refund') openRefund(id);
    }

    async function updateOrderStatus(id, status, extra = {}) {
        const order = state.orders.get(String(id));
        if (!order) return;
        const body = new URLSearchParams({_token: token, _method: 'PUT', status, ...extra});
        await post(order.urls.status, body, 'Order updated.');
    }
function openRefund(id) {
        const order = state.orders.get(String(id));
        const modal = document.getElementById('liveRefundModal');
        modal.querySelector('[name="order_id"]').value = id;
        modal.querySelector('[name="refund_reason"]').value = '';
        modal.querySelector('[name="refund_amount"]').value = order?.total || '';
        modal.classList.add('is-visible');
    }

    async function openDriver(id) {
        const order = state.orders.get(String(id));
        const modal = document.getElementById('liveDriverModal');
        const list = document.getElementById('liveDriverList');
        list.innerHTML = '<div class="live-empty">Loading drivers...</div>';
        modal.classList.add('is-visible');
        try {
            const response = await fetch(order.urls.available_drivers, {headers: {Accept: 'application/json'}});
            const data = await response.json();
            const drivers = data.drivers || data.data || [];
            list.innerHTML = drivers.length ? drivers.map(driver => `<label class="live-driver-row">
                <input type="radio" name="driver_id" value="${driver.id}">
                <span><strong>${escapeHtml(driver.name)}</strong><br><span class="live-muted">${escapeHtml(driver.phone || driver.email || '')}</span></span>
            </label>`).join('') + `<div class="p-2"><button class="btn btn-primary btn-sm" type="button" data-save-driver="${id}">Assign driver</button></div>` : '<div class="live-empty">No available drivers</div>';
            list.querySelector('[data-save-driver]')?.addEventListener('click', () => submitDriver(id));
        } catch (error) {
            list.innerHTML = '<div class="live-empty">Could not load drivers</div>';
        }
    }

    async function submitDriver(id) {
        const order = state.orders.get(String(id));
        const driverId = document.querySelector('#liveDriverList [name="driver_id"]:checked')?.value;
        if (!driverId) return toast('Select a driver first.', 'error');
        await post(order.urls.assign_driver, new URLSearchParams({_token: token, driver_id: driverId}), 'Driver assigned.');
        closeModals();
    }
function submitRefund(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const order = state.orders.get(String(form.order_id.value));
        post(order.urls.refund, new URLSearchParams({_token: token, refund_reason: form.refund_reason.value, refund_amount: form.refund_amount.value}), 'Refund processed.').then(closeModals);
    }

    async function bulkUpdateOrders() {
        const status = document.getElementById('liveBulkStatus').value;
        if (!status || !state.selected.size) return toast('Select orders and a bulk action first.', 'error');
        const body = new URLSearchParams({_token: token, status});
        state.selected.forEach(id => body.append('order_ids[]', id));
        await post(routes.bulk, body, 'Bulk update completed.');
        state.selected.clear();
        updateSelectedCount();
    }

    async function post(url, body, fallbackMessage) {
        try {
            const response = await fetch(url, {method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': token}, body});
            const data = await response.json();
            if (!response.ok || data.success === false) throw new Error(data.message || 'Request failed.');
            toast(data.message || fallbackMessage, 'success');
            await loadLiveOrders(false);
        } catch (error) {
            toast(error.message || 'Request failed.', 'error');
        }
    }

    function updateSelectedCount() {
        document.getElementById('liveSelectedCount').textContent = `${state.selected.size} selected`;
    }

    function closeModals() {
        document.querySelectorAll('.live-overlay').forEach(modal => modal.classList.remove('is-visible'));
    }

    function formatMoney(value) {
        const amount = Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        return `${currency}${amount}`;
    }

    function toast(message, type) {
        if (typeof showToastMessage === 'function') return showToastMessage(message, type);
        window.alert(message);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    }
})();
</script>
@endsection
