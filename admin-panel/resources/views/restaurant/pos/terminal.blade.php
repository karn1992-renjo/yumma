@php
    $tabFilters = [
        ['key' => 'all', 'label' => 'All'],
        ['key' => 'pending', 'label' => 'New'],
        ['key' => 'confirmed', 'label' => 'Accepted'],
        ['key' => 'preparing', 'label' => 'Preparing'],
        ['key' => 'ready_for_pickup', 'label' => 'Ready'],
        ['key' => 'delivered', 'label' => 'Completed'],
        ['key' => 'cancelled', 'label' => 'Cancelled'],
    ];
    $reportsHref = auth()->user()->hasRestaurantPermission('view_reports')
        ? route('restaurant.analytics.index')
        : route('restaurant.dashboard');
    $isRestaurantOwner = auth()->user()->hasRole('restaurant_owner');
    $storeHref = $isRestaurantOwner ? route('restaurant.stores.index') : route('restaurant.dashboard');
    $printerHref = $isRestaurantOwner ? route('restaurant.printers.index') : route('restaurant.dashboard');
    $posBrandName = \App\Models\AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
    $brandingUrl = function (?string $path) {
        if (!$path) {
            return asset('favicon.ico');
        }

        return str_starts_with($path, 'branding/')
            ? route('media.branding', ['file' => basename($path)])
            : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    };
    $menuCategories = collect($menuItems)
        ->pluck('category')
        ->filter()
        ->unique()
        ->values();
    $statusCountMap = collect($statusFilters)->pluck('count', 'key');
    $mainMenu = [
        ['label' => 'Dashboard', 'icon' => 'th-large', 'filter' => 'all'],
        ['label' => 'Current Orders', 'icon' => 'clipboard-list', 'filter' => 'active'],
        ['label' => 'Recent Orders', 'icon' => 'history', 'filter' => 'recent'],
        ['label' => 'Walk-in Orders', 'icon' => 'cash-register', 'filter' => 'walk_in'],
        ['label' => 'Dine-In Tables', 'icon' => 'chair', 'filter' => 'dine_in'],
        ['label' => 'Reservations', 'icon' => 'calendar-check', 'filter' => 'scheduled'],
        ['label' => 'Kitchen Queue', 'icon' => 'utensils', 'filter' => 'preparing'],
        ['label' => 'Billing', 'icon' => 'file-invoice-dollar', 'filter' => 'billing'],
        ['label' => 'Customers', 'icon' => 'users', 'filter' => 'all'],
        ['label' => 'Reports', 'icon' => 'chart-bar', 'href' => $reportsHref],
        ['label' => 'Settings', 'icon' => 'cog', 'href' => $isRestaurantOwner ? route('restaurant.settings.index') : route('restaurant.dashboard')],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>POS Terminal - {{ $posBrandName }}</title>
<link rel="icon" href="{{ $appFavicon ? $brandingUrl($appFavicon) : asset('favicon.ico') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
    * {
        box-sizing: border-box;
    }

    html,
    body {
        width: 100%;
        height: 100%;
        margin: 0;
        overflow: hidden;
        background: #f4f7fb;
    }

    body.pos-terminal-active {
        overflow: hidden !important;
        background: #f4f7fb !important;
    }

    .pos-terminal-screen {
        --pos-side-width: 248px;
        position: fixed;
        inset: 0;
        z-index: 5000;
        width: 100%;
        max-width: 100%;
        height: 100dvh;
        min-height: 100dvh;
        display: grid;
        grid-template-columns: var(--pos-side-width) minmax(0, 1fr) 420px;
        background: linear-gradient(90deg, #08111f 0 var(--pos-side-width), #f4f7fb var(--pos-side-width) 100%);
        color: #111827;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        overflow: hidden;
    }

    .pos-terminal-screen.detail-collapsed {
        grid-template-columns: var(--pos-side-width) minmax(0, 1fr) 0;
    }

    .pos-terminal-screen.detail-collapsed .pos-detail {
        display: none;
    }

    .pos-terminal-screen:not(.detail-collapsed) .pos-topbar {
        gap: 8px;
        padding-inline: 10px;
    }

    .pos-terminal-screen:not(.detail-collapsed) .pos-topbar .time-pill {
        display: none;
    }

    .pos-terminal-screen:not(.detail-collapsed) .profile-pill {
        min-width: 126px;
        max-width: 138px;
    }

    .pos-terminal-screen:not(.detail-collapsed) .profile-avatar {
        display: none;
    }

    .pos-terminal-screen:not(.detail-collapsed) .restaurant-stack {
        flex-basis: 168px;
        min-width: 140px;
    }

    .pos-terminal-screen:not(.detail-collapsed) .top-pill {
        flex-basis: 122px;
    }

    .pos-terminal-screen:not(.detail-collapsed) .search-box {
        flex-basis: 210px;
        min-width: 140px;
    }

    .pos-terminal-screen:not(.detail-collapsed) .top-pill:nth-of-type(3) {
        display: none;
    }

    .pos-side {
        min-width: 0;
        min-height: 0;
        height: 100dvh;
        max-height: 100dvh;
        display: flex;
        flex-direction: column;
        padding: 14px 10px;
        color: #e5eefc;
        background: linear-gradient(180deg, #08111f 0%, #0d1b2f 52%, #08111f 100%);
        border-right: 1px solid rgba(148, 163, 184, .18);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, .65) rgba(15, 23, 42, .35);
    }

    .pos-side::-webkit-scrollbar {
        width: 8px;
    }

    .pos-side::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, .35);
    }

    .pos-side::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: rgba(148, 163, 184, .65);
    }

    .pos-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 8px 18px;
    }

    .pos-brand-icon {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        color: #fb6418;
        border-radius: 12px;
        background: rgba(251, 100, 24, .12);
        border: 1px solid rgba(251, 100, 24, .22);
        font-size: 20px;
    }

    .pos-brand-name {
        font-size: 21px;
        font-weight: 950;
        line-height: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pos-brand-name span {
        color: #fb6418;
    }

    .pos-nav-block {
        display: grid;
        gap: 5px;
        padding: 10px 0;
        border-top: 1px solid rgba(148, 163, 184, .16);
    }

    .pos-nav-title {
        color: #94a3b8;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: 7px 8px 4px;
    }

    .pos-nav-link,
    .pos-nav-button {
        width: 100%;
        min-height: 38px;
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr) auto;
        gap: 9px;
        align-items: center;
        padding: 8px 10px;
        border: 0;
        border-radius: 8px;
        color: #eef5ff;
        background: transparent;
        text-align: left;
        text-decoration: none;
        font-size: 13px;
        font-weight: 850;
    }

    .pos-nav-link i,
    .pos-nav-button i {
        font-size: 13px;
    }

    .pos-nav-link:hover,
    .pos-nav-button:hover,
    .pos-nav-button.active {
        color: #fff;
        background: linear-gradient(135deg, rgba(251, 100, 24, .95), rgba(163, 70, 24, .9));
    }

    .pos-nav-badge {
        min-width: 28px;
        padding: 3px 7px;
        border-radius: 999px;
        color: #fff;
        background: #fb6418;
        font-size: 10px;
        font-weight: 950;
        text-align: center;
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: var(--dot-color);
    }

    .pos-side-footer {
        display: grid;
        gap: 8px;
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid rgba(148, 163, 184, .16);
    }

    .shift-row {
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr) auto;
        gap: 9px;
        align-items: center;
        padding: 7px 8px;
        color: #dbeafe;
        font-size: 11px;
        font-weight: 800;
    }

    .shift-row strong {
        display: block;
        color: #fff;
        font-size: 12px;
        font-weight: 950;
    }

    .mini-state {
        border-radius: 999px;
        padding: 3px 7px;
        color: #22c55e;
        background: rgba(34, 197, 94, .12);
        font-size: 10px;
        font-weight: 950;
    }

    .pos-workspace {
        min-width: 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .pos-topbar {
        height: 70px;
        display: flex;
        gap: 10px;
        align-items: center;
        padding: 8px 14px;
        background: rgba(255, 255, 255, .96);
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .04);
        overflow: hidden;
    }

    .restaurant-stack {
        flex: 0 1 230px;
        min-width: 150px;
        overflow: hidden;
    }

    .restaurant-stack h1 {
        margin: 0;
        color: #111827;
        font-size: 17px;
        font-weight: 950;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .restaurant-stack button {
        border: 0;
        background: transparent;
        color: #475569;
        padding: 0;
        font-size: 12px;
        font-weight: 750;
    }

    .top-pill,
    .search-box,
    .profile-pill {
        min-width: 0;
        min-height: 42px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
    }

    .top-pill {
        flex: 0 1 150px;
    }

    .time-pill {
        flex-basis: 130px;
    }

    .top-pill i {
        color: #475569;
        font-size: 16px;
    }

    .top-pill strong,
    .profile-pill strong {
        display: block;
        color: #111827;
        font-size: 11px;
        font-weight: 950;
        line-height: 1.15;
        white-space: nowrap;
    }

    .top-pill span,
    .profile-pill span {
        display: block;
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .search-box {
        flex: 1 1 280px;
        min-width: 180px;
    }

    .search-box input {
        min-width: 0;
        flex: 1;
        border: 0;
        outline: 0;
        color: #0f172a;
        font-size: 13px;
        font-weight: 700;
    }

    .search-box input::placeholder {
        color: #64748b;
    }

    .search-box kbd {
        color: #475569;
        background: #f1f5f9;
        border: 0;
        font-size: 11px;
        font-weight: 900;
    }

    .icon-button {
        position: relative;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid;
        place-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        background: #fff;
    }

    .icon-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        display: grid;
        place-items: center;
        border-radius: 999px;
        color: #fff;
        background: #ef4444;
        font-size: 10px;
        font-weight: 950;
    }

    .online-pill {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 7px 11px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #047857;
        background: #fff;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    button.online-pill {
        cursor: pointer;
    }

    button.online-pill:hover {
        border-color: #86efac;
        box-shadow: 0 10px 22px rgba(34, 197, 94, .1);
    }

    .online-pill.offline {
        color: #991b1b;
    }

    .profile-pill {
        flex: 0 1 150px;
        min-width: 118px;
        max-width: 150px;
    }

    .profile-avatar {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(135deg, #111827, #fb6418);
        font-weight: 950;
    }

    .pos-content {
        min-height: 0;
        flex: 1;
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr);
        gap: 12px;
        padding: 14px 18px;
        overflow: hidden;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(132px, 1fr));
        gap: 10px;
    }

    .summary-card {
        min-height: 78px;
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: 11px;
        align-items: center;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 12px 24px rgba(15, 23, 42, .04);
    }

    .summary-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        color: var(--summary-color);
        background: color-mix(in srgb, var(--summary-color) 12%, white);
        font-size: 18px;
    }

    .summary-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 850;
    }

    .summary-value {
        color: #0f172a;
        font-size: 19px;
        font-weight: 950;
        line-height: 1.08;
        margin-top: 3px;
    }

    .summary-trend {
        color: #16a34a;
        font-size: 11px;
        font-weight: 850;
        margin-top: 3px;
    }

    .orders-panel {
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
    }

    .billing-panel {
        min-height: 0;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 12px;
        overflow: hidden;
    }

    .billing-panel.is-hidden,
    .orders-panel.is-hidden,
    .summary-grid.is-hidden {
        display: none !important;
    }

    .menu-browser,
    .bill-cart {
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
    }

    .menu-toolbar,
    .bill-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
    }

    .menu-search {
        min-width: 260px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 9px 11px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .menu-search input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        color: #0f172a;
        font-size: 13px;
        font-weight: 800;
    }

    .category-strip {
        display: flex;
        gap: 8px;
        padding: 10px 14px;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }

    .category-pill {
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 7px 11px;
        color: #475569;
        background: #fff;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .category-pill.active {
        color: #fff;
        border-color: #fb6418;
        background: #fb6418;
    }

    .menu-grid {
        min-height: 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
        align-content: start;
        padding: 12px;
        overflow: auto;
    }

    .menu-card {
        display: grid;
        grid-template-columns: 62px minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        min-height: 92px;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: inherit;
        background: #fff;
        text-align: left;
    }

    .menu-card:hover {
        border-color: #fb6418;
        box-shadow: 0 12px 24px rgba(251, 100, 24, .09);
    }

    .menu-thumb,
    .menu-thumb-placeholder {
        width: 62px;
        height: 62px;
        border-radius: 9px;
        background: #f1f5f9;
        object-fit: cover;
    }

    .menu-thumb-placeholder {
        display: grid;
        place-items: center;
        color: #fb6418;
        font-weight: 950;
    }

    .cart-body {
        min-height: 0;
        display: grid;
        align-content: start;
        gap: 9px;
        padding: 12px;
        overflow: auto;
    }

    .cart-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        background: #fff;
    }

    .qty-control {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 8px;
    }

    .qty-control button {
        width: 27px;
        height: 27px;
        border: 1px solid #dbe3ef;
        border-radius: 7px;
        color: #0f172a;
        background: #f8fafc;
        font-weight: 950;
    }

    .bill-foot {
        display: grid;
        gap: 10px;
        padding: 12px;
        border-top: 1px solid #e2e8f0;
    }

    .bill-input {
        width: 100%;
        min-height: 38px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 10px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 750;
    }

    .bill-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #475569;
        font-size: 13px;
        font-weight: 800;
    }

    .bill-total-row strong {
        color: #0f172a;
        font-size: 18px;
        font-weight: 950;
    }

    .order-tabs {
        display: flex;
        align-items: center;
        gap: 22px;
        padding: 0 16px;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }

    .tab-button {
        position: relative;
        border: 0;
        background: transparent;
        padding: 14px 0 12px;
        color: #334155;
        font-size: 12px;
        font-weight: 750;
        letter-spacing: 0;
        white-space: nowrap;
    }

    .tab-button.active {
        color: #fb6418;
    }

    .tab-button.active::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: #fb6418;
    }

    .orders-list {
        min-height: 0;
        display: grid;
        align-content: start;
        gap: 10px;
        padding: 12px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .order-card {
        position: relative;
        display: grid;
        grid-template-columns: minmax(150px, .95fr) minmax(150px, 1fr) minmax(170px, 1.15fr) minmax(92px, .52fr) minmax(84px, .45fr) minmax(118px, .6fr);
        gap: 12px;
        align-items: center;
        padding: 15px 16px 15px 20px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .035);
        cursor: pointer;
        min-width: 0;
    }

    .order-card > div {
        min-width: 0;
    }

    .order-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        border-radius: 10px 0 0 10px;
        background: var(--status-color);
    }

    .order-card.selected {
        border-color: #fb6418;
        box-shadow: 0 16px 34px rgba(251, 100, 24, .12);
    }

    .order-number {
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .order-time {
        color: #0f172a;
        font-size: 12px;
        font-weight: 750;
        line-height: 1.25;
        margin-top: 8px;
    }

    .order-elapsed {
        color: #64748b;
        font-size: 11px;
        font-weight: 650;
        line-height: 1.25;
        margin-top: 4px;
    }

    .status-label {
        display: inline-flex;
        border-radius: 6px;
        padding: 4px 8px;
        color: var(--status-color);
        background: color-mix(in srgb, var(--status-color) 12%, white);
        font-size: 10px;
        font-weight: 800;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .customer-name {
        color: #0f172a;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.25;
    }

    .customer-phone,
    .order-address,
    .order-muted {
        color: #64748b;
        font-size: 11px;
        font-weight: 650;
        line-height: 1.35;
        margin-top: 5px;
    }

    .type-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--type-color);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.25;
    }

    .payment-chip {
        display: inline-flex;
        border-radius: 6px;
        padding: 5px 8px;
        color: #047857;
        background: #dcfce7;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .payment-chip.cod,
    .payment-chip.pending {
        color: #b45309;
        background: #fef3c7;
    }

    .order-actions {
        display: grid;
        gap: 7px;
        min-width: 0;
    }

    .terminal-btn {
        min-height: 31px;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 1px solid #dbe3ef;
        border-radius: 6px;
        padding: 6px 9px;
        color: #1d4ed8;
        background: #f8fbff;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
    }

    .terminal-btn.accept {
        color: #fff;
        border-color: #fb6418;
        background: #fb6418;
    }

    .terminal-btn.reject {
        color: #dc2626;
        border-color: #fecaca;
        background: #fff5f5;
    }

    .terminal-btn.success {
        color: #047857;
        border-color: #bbf7d0;
        background: #ecfdf5;
    }

    .empty-orders {
        min-height: 280px;
        display: grid;
        place-items: center;
        color: #64748b;
        font-size: 14px;
        font-weight: 850;
        text-align: center;
    }

    .pos-detail {
        min-width: 0;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        border-left: 1px solid #e2e8f0;
    }

    .detail-head {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 14px;
        padding: 22px 18px 14px;
        border-bottom: 1px solid #e2e8f0;
    }

    .detail-title {
        color: #0f172a;
        font-size: 18px;
        font-weight: 950;
        margin: 0;
    }

    .detail-body {
        min-height: 0;
        flex: 1;
        display: grid;
        align-content: start;
        gap: 14px;
        padding: 14px 18px 18px;
        overflow: auto;
    }

    .detail-section {
        display: grid;
        gap: 10px;
        padding-bottom: 14px;
        border-bottom: 1px solid #e2e8f0;
    }

    .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 950;
    }

    .detail-row {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 12px;
        color: #475569;
        font-size: 12px;
        font-weight: 750;
    }

    .detail-row strong {
        color: #0f172a;
        font-weight: 950;
        text-align: right;
    }

    .detail-item {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
    }

    .detail-thumb,
    .detail-thumb-placeholder {
        width: 58px;
        height: 58px;
        border-radius: 10px;
        background: #f1f5f9;
        object-fit: cover;
    }

    .detail-thumb-placeholder {
        display: grid;
        place-items: center;
        color: #fb6418;
    }

    .timeline {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 6px;
    }

    .timeline-step {
        text-align: center;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 850;
    }

    .timeline-dot {
        width: 20px;
        height: 20px;
        display: grid;
        place-items: center;
        margin: 0 auto 5px;
        border-radius: 50%;
        color: #94a3b8;
        background: #f1f5f9;
    }

    .timeline-step.done {
        color: #047857;
    }

    .timeline-step.done .timeline-dot {
        color: #fff;
        background: #22c55e;
    }

    .new-order-toast {
        position: fixed;
        right: 444px;
        bottom: 20px;
        z-index: 5010;
        display: none;
        width: 330px;
        padding: 14px;
        border: 1px solid #fed7aa;
        border-radius: 14px;
        color: #0f172a;
        background: #fff7ed;
        box-shadow: 0 18px 42px rgba(15, 23, 42, .18);
    }

    .new-order-toast.show {
        display: block;
    }

    .pos-toast {
        position: fixed;
        left: 50%;
        bottom: 24px;
        z-index: 5020;
        transform: translateX(-50%);
        display: none;
        min-width: 260px;
        padding: 11px 14px;
        border-radius: 999px;
        color: #fff;
        background: #0f172a;
        box-shadow: 0 16px 34px rgba(15, 23, 42, .24);
        font-size: 13px;
        font-weight: 850;
        text-align: center;
    }

    .pos-toast.show {
        display: block;
    }

    @media (max-width: 1360px) {
        .pos-terminal-screen {
            --pos-side-width: 226px;
            grid-template-columns: var(--pos-side-width) minmax(0, 1fr) 380px;
        }

        .pos-terminal-screen.detail-collapsed {
            grid-template-columns: var(--pos-side-width) minmax(0, 1fr) 0;
        }

        .pos-topbar {
            gap: 8px;
            padding-inline: 10px;
        }

        .pos-topbar .time-pill {
            display: none;
        }

        .top-pill,
        .search-box,
        .profile-pill {
            min-height: 40px;
            padding: 6px 8px;
        }

        .restaurant-stack {
            flex-basis: 165px;
            min-width: 130px;
        }

        .top-pill {
            flex-basis: 128px;
        }

        .search-box {
            flex-basis: 220px;
            min-width: 150px;
        }

        .icon-button {
            width: 40px;
            height: 40px;
        }

        .online-pill {
            min-height: 40px;
            padding: 6px 9px;
        }

        .profile-pill {
            min-width: 118px;
            max-width: 132px;
        }

        .profile-avatar {
            display: none;
        }

        .summary-grid {
            grid-template-columns: repeat(3, minmax(150px, 1fr));
        }

        .billing-panel {
            grid-template-columns: minmax(0, 1fr) 330px;
        }

        .order-card {
            grid-template-columns: minmax(138px, .95fr) minmax(138px, 1fr) minmax(150px, 1.1fr) minmax(84px, .5fr) minmax(78px, .45fr) minmax(110px, .58fr);
            gap: 10px;
        }
    }
</style>
</head>

<body class="pos-terminal-active">
<div class="pos-terminal-screen" id="posTerminal">
    <aside class="pos-side">
        <div class="pos-brand">
            <div class="pos-brand-icon"><i class="fas fa-utensils"></i></div>
            <div class="pos-brand-name">{{ $posBrandName }}<span> POS</span></div>
        </div>

        <div class="pos-nav-block">
            @foreach($mainMenu as $item)
                @if(isset($item['href']))
                    <a href="{{ $item['href'] }}" class="pos-nav-link">
                        <i class="fas fa-{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <button type="button" class="pos-nav-button {{ $loop->first ? 'active' : '' }}" data-side-filter="{{ $item['filter'] }}">
                        <i class="fas fa-{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if($item['filter'] === 'active')
                            <span class="pos-nav-badge">{{ collect($statusFilters)->whereIn('key', ['pending', 'confirmed', 'preparing', 'ready_for_pickup'])->sum('count') }}</span>
                        @elseif($item['filter'] === 'preparing' && ($statusCountMap['preparing'] ?? 0) > 0)
                            <span class="pos-nav-badge">{{ $statusCountMap['preparing'] }}</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>

        <div class="pos-nav-block">
            <div class="pos-nav-title">Order Status</div>
            @foreach($statusFilters as $filter)
                <button type="button" class="pos-nav-button" data-side-filter="{{ $filter['key'] }}">
                    <span class="status-dot" style="--dot-color: {{ $filter['color'] }};"></span>
                    <span>{{ $filter['label'] }}</span>
                    <span class="pos-nav-badge">{{ $filter['count'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="pos-side-footer">
            <div class="shift-row">
                <i class="fas fa-calendar-day"></i>
                <span><strong>Current Shift</strong>{{ now()->format('A') === 'AM' ? 'Morning Shift' : 'Evening Shift' }}</span>
                <span class="mini-state">Open</span>
            </div>
            <div class="shift-row">
                <i class="fas fa-cash-register"></i>
                <span><strong>Cash Drawer</strong>{{ $terminalMeta['cash_drawer'] }}</span>
            </div>
            <div class="shift-row">
                <i class="fas fa-print"></i>
                <span><strong>Printer Status</strong>{{ $terminalMeta['printer_name'] ?: 'Not configured' }}</span>
                <span class="mini-state">{{ $terminalMeta['printer_online'] ? 'Online' : 'Setup' }}</span>
            </div>
            <div class="shift-row">
                <i class="fas fa-rotate"></i>
                <span><strong>Sync Status</strong>Synced {{ $terminalMeta['sync_label'] }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="pos-nav-button">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="pos-workspace">
        <header class="pos-topbar">
            <div class="restaurant-stack">
                <h1>{{ $terminalMeta['restaurant_name'] }}</h1>
                <a href="{{ $storeHref }}" style="color:#475569;text-decoration:none;font-size:12px;font-weight:750;">
                    {{ $terminalMeta['location'] ?: $terminalMeta['branch_name'] }} <i class="fas fa-chevron-down ms-1"></i>
                </a>
            </div>
            <div class="top-pill">
                <i class="fas fa-store"></i>
                <div><span>Branch</span><strong>{{ $terminalMeta['branch_name'] }}</strong></div>
            </div>
            <div class="top-pill">
                <i class="fas fa-calendar"></i>
                <div><strong>{{ now()->format('d M Y') }}</strong><span>{{ now()->format('l') }}</span></div>
            </div>
            <div class="top-pill time-pill">
                <i class="fas fa-clock"></i>
                <div><strong id="terminalClock">{{ now()->format('h:i A') }}</strong><span>{{ config('app.timezone') }}</span></div>
            </div>
            <label class="search-box">
                <i class="fas fa-search text-muted"></i>
                <input type="search" id="terminalSearch" placeholder="Search by Order ID, Customer, Phone...">
                <kbd>Ctrl + K</kbd>
            </label>
            <button type="button"
                    class="online-pill {{ $terminalMeta['is_open'] ? '' : 'offline' }}"
                    id="terminalStatusToggle"
                    data-toggle-url="{{ route('restaurant.toggle-status') }}"
                    data-online="{{ $terminalMeta['is_open'] ? '1' : '0' }}"
                    title="{{ $terminalMeta['is_open'] ? 'Go offline' : 'Go online' }}">
                <i class="fas fa-circle"></i><span>{{ $terminalMeta['is_open'] ? 'Online' : 'Offline' }}</span>
            </button>
            <a href="{{ route('restaurant.dashboard') }}" class="icon-button" title="Back to main dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
            <button type="button" class="icon-button" title="New POS bill" data-open-billing>
                <i class="fas fa-plus"></i>
            </button>
            <a href="{{ $printerHref }}" class="icon-button" title="Printer setup">
                <i class="fas fa-print"></i>
            </a>
            <button type="button" class="icon-button" title="Refresh order activity" id="refreshTerminalBtn">
                <i class="fas fa-bell"></i>
                @if(($statusCountMap['pending'] ?? 0) > 0)
                    <span class="icon-badge">{{ $statusCountMap['pending'] }}</span>
                @endif
            </button>
            <div class="profile-pill">
                <div class="profile-avatar">{{ strtoupper(substr($terminalMeta['cashier_name'], 0, 1)) }}</div>
                <div class="min-w-0">
                    <strong class="text-truncate">{{ $terminalMeta['cashier_name'] }}</strong>
                    <span>{{ $terminalMeta['cashier_role'] }}</span>
                </div>
            </div>
        </header>

        <section class="pos-content">
            <div class="summary-grid">
                @foreach($summaryCards as $card)
                    @php
                        $color = ['blue' => '#2563eb', 'green' => '#22c55e', 'orange' => '#fb6418', 'red' => '#ef4444'][$card['tone']] ?? '#2563eb';
                    @endphp
                    <div class="summary-card">
                        <div class="summary-icon" style="--summary-color: {{ $color }};">
                            <i class="fas fa-{{ $card['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="summary-label">{{ $card['label'] }}</div>
                            <div class="summary-value">{{ $card['value'] }}</div>
                            <div class="summary-trend">{{ $card['trend'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="orders-panel">
                <div class="order-tabs">
                    @foreach($tabFilters as $tab)
                        <button type="button" class="tab-button {{ $loop->first ? 'active' : '' }}" data-tab-filter="{{ $tab['key'] }}">
                            {{ $tab['label'] }} ({{ $statusCountMap[$tab['key']] ?? ($tab['key'] === 'all' ? $statusCountMap['all'] ?? 0 : 0) }})
                        </button>
                    @endforeach
                </div>
                <div class="orders-list" id="ordersList"></div>
            </div>

            @include('restaurant.pos.partials.terminal-menu')
        </section>
    </main>

    <aside class="pos-detail">
        <div class="detail-head">
            <div>
                <h2 class="detail-title" id="detailOrderNumber">Select an order</h2>
                <div class="order-muted" id="detailOrderMeta">Live order information appears here.</div>
            </div>
            <button type="button" class="icon-button" id="detailCloseBtn" title="Clear selection">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="detail-body" id="detailBody"></div>
    </aside>

    <div class="new-order-toast" id="newOrderToast">
        <div class="fw-bold"><i class="fas fa-bell me-2 text-danger"></i>New order activity</div>
        <div class="small mt-1">Order counts changed. Refresh the terminal to load the latest order cards.</div>
        <button type="button" class="terminal-btn accept mt-3" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh Orders
        </button>
    </div>

    <div class="pos-toast" id="posToast"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('pos-terminal-active');

    let orders = @json($orders);
    const menuItems = @json($menuItems);
    const canPrint = @json($canPrint);
    const currencySymbol = @json($currencySymbol);
    const currencyDecimals = Number(@json($currencyDecimals));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urls = {
        ordersBase: @json(url('/restaurant/orders')),
        kotBase: @json(url('/restaurant/printers/kot')),
        invoiceBase: @json(url('/restaurant/printers/invoice')),
        counts: @json(route('restaurant.orders.counts')),
        terminalData: @json(route('restaurant.pos.terminal.data')),
        detailBase: @json(url('/restaurant/orders')),
        posBill: @json(route('restaurant.pos.index')),
    };
    const statusColors = {
        pending: '#ef4444',
        confirmed: '#2563eb',
        preparing: '#fb6418',
        ready_for_pickup: '#22c55e',
        picked_up: '#8b5cf6',
        on_the_way: '#60a5fa',
        delivered: '#64748b',
        cancelled: '#ef4444',
        refunded: '#64748b',
    };
    const typeColors = {
        delivery: '#ef4444',
        pickup: '#6d28d9',
        dine_in: '#059669',
        walk_in: '#fb6418',
    };
    const activeStatuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'];

    let activeFilter = 'all';
    let activeMenuCategory = 'all';
    let selectedOrderId = orders.length ? orders[0].id : null;
    let detailVisible = Boolean(selectedOrderId);
    let lastPendingCount = Number(@json($statusCountMap['pending'] ?? 0));
    const cart = new Map();

    function setDetailCollapsed(collapsed) {
        const terminal = document.getElementById('posTerminal');
        if (terminal) {
            terminal.classList.toggle('detail-collapsed', collapsed);
        }
    }

    function showDetailPanel() {
        detailVisible = true;
        setDetailCollapsed(false);
    }

    function hideDetailPanel() {
        detailVisible = false;
        selectedOrderId = null;
        renderDetail(null);
        setDetailCollapsed(true);
        document.querySelectorAll('.order-card').forEach(card => card.classList.remove('selected'));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function formatMoney(value) {
        return currencySymbol + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: currencyDecimals,
            maximumFractionDigits: currencyDecimals,
        });
    }

    function isBillingMode() {
        return activeFilter === 'billing' || activeFilter === 'walk_in';
    }

    function renderWorkspace() {
        const billingPanel = document.getElementById('billingPanel');
        const ordersPanel = document.querySelector('.orders-panel');
        const summaryGrid = document.querySelector('.summary-grid');

        if (isBillingMode()) {
            billingPanel.classList.remove('is-hidden');
            ordersPanel.classList.add('is-hidden');
            summaryGrid.classList.add('is-hidden');
            renderMenu();
            renderCart();
            hideDetailPanel();
            return;
        }

        billingPanel.classList.add('is-hidden');
        ordersPanel.classList.remove('is-hidden');
        summaryGrid.classList.remove('is-hidden');
        renderOrders();
    }

    function orderMatchesFilter(order) {
        if (activeFilter === 'all') return true;
        if (activeFilter === 'active') return activeStatuses.includes(order.status);
        if (activeFilter === 'recent') return ['delivered', 'cancelled', 'refunded'].includes(order.status);
        if (activeFilter === 'scheduled') return Boolean(order.scheduled_time);
        if (['walk_in', 'dine_in', 'pickup', 'delivery'].includes(activeFilter)) return order.type === activeFilter;
        return order.status === activeFilter;
    }

    function filteredOrders() {
        const search = document.getElementById('terminalSearch').value.trim().toLowerCase();

        return orders.filter(function (order) {
            const haystack = [order.number, order.customer_name, order.customer_phone, order.total, order.status_label]
                .join(' ')
                .toLowerCase();

            return orderMatchesFilter(order) && (!search || haystack.includes(search));
        });
    }

    function actionButton(action, orderId) {
        const configs = {
            accept: ['accept', 'check', 'Accept'],
            reject: ['reject', 'times', 'Reject'],
            view: ['', 'eye', 'View'],
            print_kot: ['', 'print', 'Print KOT'],
            print_invoice: ['', 'file-invoice', 'Print Invoice'],
            preparing: ['', 'utensils', 'Mark Preparing'],
            ready: ['success', 'box-open', 'Mark Ready'],
        };
        const config = configs[action] || ['', 'ellipsis', 'More'];

        return `<button type="button" class="terminal-btn ${config[0]}" data-order-action="${action}" data-order-id="${orderId}">
            <i class="fas fa-${config[1]}"></i>${config[2]}
        </button>`;
    }

    function renderOrders() {
        const list = document.getElementById('ordersList');
        const rows = filteredOrders();

        if (!rows.length) {
            list.innerHTML = '<div class="empty-orders"><div><i class="fas fa-clipboard-list fa-2x mb-3"></i><br>No orders match this terminal view.</div></div>';
            hideDetailPanel();
            return;
        }

        if (detailVisible && !rows.some(order => order.id === selectedOrderId)) {
            selectedOrderId = rows[0].id;
        }

        list.innerHTML = rows.map(function (order) {
            const actions = (order.actions || ['view']).slice(0, 3).map(action => actionButton(action, order.id)).join('');

            return `<article class="order-card ${detailVisible && order.id === selectedOrderId ? 'selected' : ''}" data-order-id="${order.id}" style="--status-color: ${statusColors[order.status] || '#64748b'}; --type-color: ${typeColors[order.type] || '#fb6418'};">
                <div>
                    <div class="order-number">#${escapeHtml(order.number)}</div>
                    <div class="order-time">${escapeHtml(order.created_time)}</div>
                    <div class="order-elapsed">${escapeHtml(order.elapsed)}</div>
                </div>
                <div>
                    <span class="status-label">${escapeHtml(order.status_label)}</span>
                    <div class="customer-name mt-2">${escapeHtml(order.customer_name)}</div>
                    <div class="customer-phone">${escapeHtml(order.customer_phone || 'No phone')}</div>
                </div>
                <div>
                    <div class="type-chip"><i class="fas fa-${escapeHtml(order.type_icon)}"></i>${escapeHtml(order.type_label)}</div>
                    <div class="order-address text-truncate">${escapeHtml(order.table_label || order.address || 'Counter pickup')}</div>
                    <div class="order-muted">${escapeHtml(order.priority)} priority</div>
                </div>
                <div>
                    <div class="customer-name">${escapeHtml(order.items_count)} Items</div>
                    <div class="order-time">${escapeHtml(order.total)}</div>
                </div>
                <div>
                    <span class="payment-chip ${escapeHtml(order.payment_status_key)}">${escapeHtml(order.payment_status)}</span>
                    <div class="order-muted">${escapeHtml(order.payment_method)}</div>
                </div>
                <div class="order-actions">${actions}</div>
            </article>`;
        }).join('');

        if (detailVisible) {
            showDetailPanel();
            renderDetail(orders.find(order => order.id === selectedOrderId));
        } else {
            setDetailCollapsed(true);
        }
    }

    function renderMenu() {
        const grid = document.getElementById('menuGrid');
        const searchInput = document.getElementById('menuSearch');
        const search = (searchInput?.value || '').trim().toLowerCase();
        const rows = menuItems.filter(function (item) {
            const categoryMatch = activeMenuCategory === 'all' || item.category === activeMenuCategory;
            const haystack = [item.name, item.category, item.price_label, item.diet].join(' ').toLowerCase();

            return categoryMatch && (!search || haystack.includes(search));
        });

        if (!rows.length) {
            grid.innerHTML = '<div class="empty-orders"><div><i class="fas fa-utensils fa-2x mb-3"></i><br>No menu items found.</div></div>';
            return;
        }

        grid.innerHTML = rows.map(function (item) {
            const image = item.image
                ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="menu-thumb">`
                : `<div class="menu-thumb-placeholder">${escapeHtml(String(item.name || 'I').slice(0, 1).toUpperCase())}</div>`;

            return `<button type="button" class="menu-card" data-menu-id="${item.id}">
                ${image}
                <div class="min-w-0">
                    <div class="customer-name text-truncate">${escapeHtml(item.name)}</div>
                    <div class="order-muted text-truncate">${escapeHtml(item.category)} · ${escapeHtml(item.diet || '')}</div>
                    <div class="order-time">${escapeHtml(item.price_label)}</div>
                </div>
            </button>`;
        }).join('');
    }

    function renderCart() {
        const container = document.getElementById('terminalCartItems');
        const hidden = document.getElementById('terminalCartHidden');
        const countLabel = document.getElementById('terminalCartCount');
        const discountInput = document.getElementById('terminalDiscount');
        const discount = Math.max(0, Number(discountInput?.value || 0));
        const rows = Array.from(cart.values());
        const itemCount = rows.reduce((sum, item) => sum + item.quantity, 0);
        const subtotal = rows.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const grandTotal = Math.max(0, subtotal - discount);

        countLabel.textContent = itemCount + (itemCount === 1 ? ' item' : ' items');
        document.getElementById('terminalSubtotal').textContent = formatMoney(subtotal);
        document.getElementById('terminalDiscountLabel').textContent = '-' + formatMoney(Math.min(discount, subtotal));
        document.getElementById('terminalGrandTotal').textContent = formatMoney(grandTotal);

        hidden.innerHTML = rows.map(function (item, index) {
            return `<input type="hidden" name="items[${index}][id]" value="${item.id}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`;
        }).join('');

        if (!rows.length) {
            container.innerHTML = '<div class="empty-orders"><div><i class="fas fa-cash-register fa-2x mb-3"></i><br>Add menu items to start a POS bill.</div></div>';
            return;
        }

        container.innerHTML = rows.map(function (item) {
            return `<div class="cart-row">
                <div class="min-w-0">
                    <div class="customer-name text-truncate">${escapeHtml(item.name)}</div>
                    <div class="order-muted">${escapeHtml(item.price_label)} each</div>
                    <div class="qty-control">
                        <button type="button" data-cart-dec="${item.id}">-</button>
                        <strong>${item.quantity}</strong>
                        <button type="button" data-cart-inc="${item.id}">+</button>
                    </div>
                </div>
                <div class="row-amount">${formatMoney(item.price * item.quantity)}</div>
            </div>`;
        }).join('');
    }

    function addMenuItem(itemId) {
        const item = menuItems.find(row => Number(row.id) === Number(itemId));

        if (!item) {
            return;
        }

        const existing = cart.get(Number(item.id));
        cart.set(Number(item.id), {
            id: Number(item.id),
            name: item.name,
            price: Number(item.price || 0),
            price_label: item.price_label,
            quantity: existing ? existing.quantity + 1 : 1,
        });
        renderCart();
    }

    function renderDetail(order) {
        const body = document.getElementById('detailBody');
        const title = document.getElementById('detailOrderNumber');
        const meta = document.getElementById('detailOrderMeta');

        if (!order) {
            title.textContent = 'Select an order';
            meta.textContent = 'Live order information appears here.';
            body.innerHTML = '<div class="empty-orders"><div><i class="fas fa-receipt fa-2x mb-3"></i><br>No order selected.</div></div>';
            return;
        }

        title.textContent = 'Order #' + order.number;
        meta.textContent = order.type_label + ' · ' + order.created_time + ' · ' + order.payment_status;

        const itemsHtml = (order.items || []).map(function (item) {
            const image = item.image
                ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="detail-thumb">`
                : '<div class="detail-thumb-placeholder"><i class="fas fa-utensils"></i></div>';
            const options = [item.variant, item.addons, item.notes].filter(Boolean).map(escapeHtml).join(' · ');

            return `<div class="detail-item">
                ${image}
                <div class="min-w-0">
                    <div class="customer-name text-truncate">${escapeHtml(item.name)}</div>
                    <div class="order-muted">Qty: ${escapeHtml(item.qty)} ${options ? ' · ' + options : ''}</div>
                </div>
                <strong>${escapeHtml(item.total_label)}</strong>
            </div>`;
        }).join('') || '<div class="order-muted">No items found.</div>';

        const timelineHtml = (order.timeline || []).slice(0, 5).map(function (step) {
            return `<div class="timeline-step ${step.done ? 'done' : ''}">
                <div class="timeline-dot"><i class="fas fa-check"></i></div>
                <div>${escapeHtml(step.label)}</div>
                <div>${escapeHtml(step.time || '-')}</div>
            </div>`;
        }).join('');

        body.innerHTML = `
            <section class="detail-section">
                <div class="section-title">Customer Details ${order.map_url ? `<a href="${escapeHtml(order.map_url)}" target="_blank" class="terminal-btn"><i class="fas fa-map-marker-alt"></i>Map</a>` : ''}</div>
                <div class="detail-row"><span>Name</span><strong>${escapeHtml(order.customer_name)}</strong></div>
                <div class="detail-row"><span>Phone</span><strong>${escapeHtml(order.customer_phone || 'N/A')}</strong></div>
                <div class="detail-row"><span>Email</span><strong>${escapeHtml(order.customer_email || 'N/A')}</strong></div>
                <div class="detail-row"><span>Address</span><strong>${escapeHtml(order.address || 'Counter pickup')}</strong></div>
            </section>
            <section class="detail-section">
                <div class="section-title">Order Timeline</div>
                <div class="timeline">${timelineHtml}</div>
            </section>
            <section class="detail-section">
                <div class="section-title">Order Items (${escapeHtml(order.items_count)}) <a href="${urls.detailBase}/${order.id}" class="panel-link">Edit Items</a></div>
                ${itemsHtml}
            </section>
            <section class="detail-section">
                <div class="section-title">Bill Summary</div>
                <div class="detail-row"><span>Subtotal</span><strong>${escapeHtml(order.subtotal)}</strong></div>
                <div class="detail-row"><span>Delivery Charge</span><strong>${escapeHtml(order.delivery_fee)}</strong></div>
                <div class="detail-row"><span>Packaging Charge</span><strong>${escapeHtml(order.packaging_fee)}</strong></div>
                <div class="detail-row"><span>Discount</span><strong>-${escapeHtml(order.discount)}</strong></div>
                <div class="detail-row"><span>GST / Tax</span><strong>${escapeHtml(order.tax)}</strong></div>
                <div class="detail-row"><span class="customer-name">Grand Total</span><strong class="customer-name">${escapeHtml(order.total)}</strong></div>
            </section>`;
    }

    async function runOrderAction(action, orderId) {
        if (action === 'view') {
            window.location.href = `${urls.detailBase}/${orderId}`;
            return;
        }

        if (action === 'call') {
            const order = orders.find(item => item.id === Number(orderId));
            if (order?.customer_phone) window.location.href = `tel:${order.customer_phone}`;
            return;
        }

        if (action === 'whatsapp') {
            const order = orders.find(item => item.id === Number(orderId));
            if (order?.customer_phone) window.open(`https://wa.me/${String(order.customer_phone).replace(/\D/g, '')}`, '_blank');
            return;
        }

        if (action === 'print_kot' || action === 'print_invoice') {
            if (!canPrint) {
                showToast('Printer action requires restaurant owner access.');
                return;
            }
            submitPost(`${action === 'print_kot' ? urls.kotBase : urls.invoiceBase}/${orderId}`);
            return;
        }

        if (action === 'ready') {
            await updateOrderStatus(orderId, 'ready_for_pickup');
            return;
        }

        if (action === 'preparing') {
            await updateOrderStatus(orderId, 'preparing');
            return;
        }

        if (action === 'accept') {
            await postJson(`${urls.ordersBase}/${orderId}/accept`, {});
            return;
        }

        if (action === 'reject') {
            const reason = window.prompt('Reason for rejecting this order?');
            if (!reason) return;
            await postJson(`${urls.ordersBase}/${orderId}/reject`, { reason });
        }
    }

    async function updateOrderStatus(orderId, status) {
        await postJson(`${urls.ordersBase}/${orderId}/update-status`, { status });
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await response.json();

        if (!response.ok || data.success === false) {
            showToast(data.message || 'Action failed.');
            return;
        }

        showToast(data.message || 'Order updated.');
        window.setTimeout(() => window.location.reload(), 650);
    }

    function submitPost(url) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.innerHTML = `<input type="hidden" name="_token" value="${csrf}">`;
        document.body.appendChild(form);
        form.submit();
    }

    function showToast(message) {
        const toast = document.getElementById('posToast');
        toast.textContent = message;
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 2600);
    }

    document.getElementById('ordersList').addEventListener('click', function (event) {
        const actionButtonEl = event.target.closest('[data-order-action]');
        if (actionButtonEl) {
            event.stopPropagation();
            runOrderAction(actionButtonEl.dataset.orderAction, actionButtonEl.dataset.orderId);
            return;
        }

        const card = event.target.closest('.order-card');
        if (card) {
            selectedOrderId = Number(card.dataset.orderId);
            showDetailPanel();
            renderWorkspace();
        }
    });

    document.getElementById('detailBody').addEventListener('click', function (event) {
        const actionButtonEl = event.target.closest('[data-order-action]');
        if (actionButtonEl) {
            runOrderAction(actionButtonEl.dataset.orderAction, actionButtonEl.dataset.orderId);
        }
    });

    document.querySelectorAll('[data-tab-filter], [data-side-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = this.dataset.tabFilter || this.dataset.sideFilter || 'all';
            document.querySelectorAll('[data-tab-filter], [data-side-filter]').forEach(item => item.classList.remove('active'));
            document.querySelectorAll(`[data-tab-filter="${activeFilter}"], [data-side-filter="${activeFilter}"]`).forEach(item => item.classList.add('active'));
            renderWorkspace();
        });
    });

    document.querySelectorAll('[data-open-billing]').forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = 'billing';
            document.querySelectorAll('[data-tab-filter], [data-side-filter]').forEach(item => item.classList.remove('active'));
            document.querySelectorAll('[data-side-filter="billing"]').forEach(item => item.classList.add('active'));
            renderWorkspace();
        });
    });

    document.getElementById('terminalSearch').addEventListener('input', renderWorkspace);
    document.getElementById('menuSearch')?.addEventListener('input', renderMenu);

    document.getElementById('menuCategories')?.addEventListener('click', function (event) {
        const pill = event.target.closest('[data-menu-category]');
        if (!pill) return;
        activeMenuCategory = pill.dataset.menuCategory || 'all';
        document.querySelectorAll('[data-menu-category]').forEach(item => item.classList.remove('active'));
        pill.classList.add('active');
        renderMenu();
    });

    document.getElementById('menuGrid')?.addEventListener('click', function (event) {
        const card = event.target.closest('[data-menu-id]');
        if (card) {
            addMenuItem(card.dataset.menuId);
        }
    });

    document.getElementById('terminalCartItems')?.addEventListener('click', function (event) {
        const inc = event.target.closest('[data-cart-inc]');
        const dec = event.target.closest('[data-cart-dec]');
        const id = Number(inc?.dataset.cartInc || dec?.dataset.cartDec || 0);

        if (!id || !cart.has(id)) return;

        const item = cart.get(id);
        item.quantity += inc ? 1 : -1;

        if (item.quantity <= 0) {
            cart.delete(id);
        } else {
            cart.set(id, item);
        }

        renderCart();
    });

    document.getElementById('terminalDiscount')?.addEventListener('input', renderCart);
    document.getElementById('clearTerminalCart')?.addEventListener('click', function () {
        cart.clear();
        renderCart();
    });
    document.getElementById('terminalPosForm')?.addEventListener('submit', function (event) {
        if (cart.size === 0) {
            event.preventDefault();
            showToast('Add at least one menu item to generate a POS bill.');
        }
    });

    document.getElementById('detailCloseBtn').addEventListener('click', function () {
        hideDetailPanel();
    });

    document.getElementById('refreshTerminalBtn')?.addEventListener('click', function () {
        fetchTerminalData(false);
        showToast('Refreshing terminal orders.');
    });

    document.getElementById('terminalStatusToggle')?.addEventListener('click', async function () {
        const button = this;
        const label = button.querySelector('span');
        button.disabled = true;

        try {
            const response = await fetch(button.dataset.toggleUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();

            if (!response.ok || data.success === false) {
                showToast(data.message || 'Could not update restaurant status.');
                return;
            }

            const isOnline = Boolean(data.is_open);
            button.dataset.online = isOnline ? '1' : '0';
            button.classList.toggle('offline', !isOnline);
            button.title = isOnline ? 'Go offline' : 'Go online';
            if (label) {
                label.textContent = isOnline ? 'Online' : 'Offline';
            }
            showToast(isOnline ? 'Restaurant is online.' : 'Restaurant is offline.');
        } catch (error) {
            showToast('Could not update restaurant status.');
        } finally {
            button.disabled = false;
        }
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.getElementById('terminalSearch').focus();
        }
    });

    window.setInterval(function () {
        document.getElementById('terminalClock').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }, 15000);

    async function fetchTerminalData(showPopup) {
        try {
            const response = await fetch(urls.terminalData, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            const counts = payload.counts || {};
            const pending = Number(counts.pending || 0);
            if (showPopup && pending > lastPendingCount) {
                document.getElementById('newOrderToast').classList.add('show');
            }
            if (Array.isArray(payload.orders)) {
                orders = payload.orders;
                if (!isBillingMode()) {
                    renderWorkspace();
                }
            }
            lastPendingCount = pending;
        } catch (error) {
            // Keep the terminal usable if polling is unavailable.
        }
    }

    window.setInterval(function () {
        fetchTerminalData(true);
    }, 30000);

    renderWorkspace();
    fetchTerminalData(false);
});
</script>
</body>
</html>
