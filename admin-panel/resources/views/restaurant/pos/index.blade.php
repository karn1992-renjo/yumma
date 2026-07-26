@extends('layouts.restaurant')

@section('title', 'POS')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $taxRate = (float) App\Models\TaxSetting::query()
        ->where('is_active', true)
        ->where(fn ($query) => $query->whereNull('calculation_type')->orWhere('calculation_type', 'percentage'))
        ->sum('rate');
    $productPayload = $products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku ?? '',
        'category_id' => $product->category_id,
        'category' => $product->category?->name ?: 'Uncategorised',
        'price' => (float) ($product->final_price ?? $product->getFinalPriceAttribute()),
        'track_inventory' => (bool) ($product->track_inventory ?? false),
        'stock_quantity' => (int) ($product->stock_quantity ?? 0),
        'image_url' => $product->image_url,
    ])->values();
@endphp

@section('content')
<style>
    .pos-terminal-root { position: relative; }
    .pos-shell { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 18px; align-items: start; }
    .pos-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .pos-search { min-width: 260px; max-width: 420px; }
    .pos-products { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; }
    .pos-product-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; min-height: 118px; display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 10px; align-items: center; }
    .pos-product-card:hover { border-color: #16a34a; box-shadow: 0 8px 20px rgba(22, 163, 74, .08); }
    .pos-product-card.is-disabled { opacity: .55; cursor: not-allowed; }
    .pos-product-image { width: 76px; height: 76px; border-radius: 8px; background: #f3f4f6; overflow: hidden; display: grid; place-items: center; color: #6b7280; font-weight: 900; font-size: 22px; grid-row: 1 / span 4; }
    .pos-product-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pos-product-image img + .pos-product-placeholder { display: none; }
    .pos-product-name { font-weight: 800; color: #111827; line-height: 1.25; }
    .pos-product-meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .pos-product-price { font-weight: 800; color: #16a34a; margin-top: 10px; }
    .pos-cart { position: sticky; top: 16px; }
    .pos-cart-items { display: grid; gap: 8px; max-height: 320px; overflow: auto; padding-right: 2px; }
    .pos-cart-row { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; display: grid; gap: 8px; }
    .pos-cart-row-top { display: flex; justify-content: space-between; gap: 10px; }
    .pos-cart-product { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .pos-cart-thumb { width: 42px; height: 42px; border-radius: 8px; background: #f3f4f6; overflow: hidden; display: grid; place-items: center; flex: 0 0 auto; color: #6b7280; font-weight: 900; }
    .pos-cart-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pos-cart-thumb img + span { display: none; }
    .pos-qty { display: inline-flex; align-items: center; gap: 7px; }
    .pos-qty button { width: 28px; height: 28px; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; font-weight: 800; }
    .pos-bill-row { display: flex; justify-content: space-between; padding: 5px 0; color: #4b5563; }
    .pos-bill-row strong { color: #111827; }
    .pos-total { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 10px; font-size: 18px; }
    .pos-split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .pos-empty { border: 1px dashed #d1d5db; border-radius: 8px; text-align: center; padding: 30px 14px; color: #6b7280; }
    .pos-category-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
    .pos-category-tabs button { border: 1px solid #d1d5db; background: #fff; border-radius: 999px; padding: 7px 12px; font-size: 13px; font-weight: 700; }
    .pos-category-tabs button.is-active { border-color: #16a34a; background: #ecfdf5; color: #166534; }
    .pos-receipt { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #111827; }
    .pos-receipt-line { display: flex; justify-content: space-between; gap: 12px; }
    .pos-fullscreen-bar { display: none; align-items: center; justify-content: space-between; gap: 14px; background: #111827; color: #fff; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
    .pos-fullscreen-bar h1 { font-size: 18px; font-weight: 900; margin: 0; }
    .pos-fullscreen-bar small { color: rgba(255,255,255,.7); }
    body.pos-terminal-fullscreen { overflow: hidden; background: #f8fafc; }
    body.pos-terminal-fullscreen .sidebar,
    body.pos-terminal-fullscreen .top-header,
    body.pos-terminal-fullscreen .sidebar-overlay,
    body.pos-terminal-fullscreen .direct-chat-widget,
    body.pos-terminal-fullscreen .direct-chat-fab,
    body.pos-terminal-fullscreen .direct-chat-panel,
    body.pos-terminal-fullscreen .direct-chat-bubble { display: none !important; }
    body.pos-terminal-fullscreen .main-content { margin: 0 !important; margin-top: 0 !important; min-height: 100vh !important; width: 100% !important; }
    body.pos-terminal-fullscreen .page-content { height: 100vh; overflow: hidden; padding: 12px !important; }
    body.pos-terminal-fullscreen .pos-terminal-root { height: calc(100vh - 24px); display: flex; flex-direction: column; }
    body.pos-terminal-fullscreen .pos-dashboard-chrome { display: none !important; }
    body.pos-terminal-fullscreen .pos-fullscreen-bar { display: flex; }
    body.pos-terminal-fullscreen #posForm { min-height: 0; flex: 1; display: flex; flex-direction: column; }
    body.pos-terminal-fullscreen .pos-shell { min-height: 0; flex: 1; grid-template-columns: minmax(0, 1fr) 410px; align-items: stretch; }
    body.pos-terminal-fullscreen .pos-shell > section,
    body.pos-terminal-fullscreen .pos-cart { min-height: 0; display: flex; flex-direction: column; }
    body.pos-terminal-fullscreen .pos-products { overflow: auto; padding-right: 4px; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); align-content: start; }
    body.pos-terminal-fullscreen .pos-cart { position: static; overflow: hidden; }
    body.pos-terminal-fullscreen .pos-cart > .card:first-child { min-height: 0; flex: 1; display: flex; flex-direction: column; }
    body.pos-terminal-fullscreen .pos-cart > .card:first-child .card-body { min-height: 0; overflow: auto; }
    body.pos-terminal-fullscreen .pos-cart > .card:last-child { display: none; }
    @media (max-width: 1180px) { .pos-shell { grid-template-columns: 1fr; } .pos-cart { position: static; } }
    @media print {
        body * { visibility: hidden; }
        #posReceipt, #posReceipt * { visibility: visible; }
        #posReceipt { position: absolute; inset: 0; width: 100%; }
        .modal-footer, .btn-close { display: none !important; }
    }
</style>

<div class="pos-terminal-root" id="posTerminalRoot">
    <div class="page-header pos-dashboard-chrome">
        <div>
            <h1>POS Terminal</h1>
            <p class="text-muted mb-0">Walk-in, takeaway, and counter billing for {{ $restaurant->name }}.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-primary" data-pos-action="new-bill">
                <kbd class="me-1">F1</kbd> New Bill
            </button>
            <button type="button" class="btn btn-outline-secondary" data-pos-action="hold-bill">
                <kbd class="me-1">F2</kbd> Hold Bill
            </button>
            <button type="button" class="btn btn-primary" id="posFullscreenToggle" aria-label="Full Screen" title="Full Screen">
                <i class="fas fa-expand me-2"></i> Full Screen Terminal
            </button>
            <a href="{{ route('restaurant.orders.index', ['search' => 'POS']) }}" class="btn btn-outline-secondary">
                <i class="fas fa-receipt me-2"></i> POS Orders
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4 pos-dashboard-chrome">
        <div class="col-md-4">
            <div class="stat-card p-3">
                <div class="text-muted fw-semibold">Today POS Revenue</div>
                <div class="h3 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($summary['today_revenue'], $currencyDecimals) }}</div>
                <small>{{ number_format($summary['today_orders']) }} POS orders today</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-3">
                <div class="text-muted fw-semibold">Total POS Orders</div>
                <div class="h3 fw-bold mb-0">{{ number_format($summary['total_orders']) }}</div>
                <small>All-time POS orders</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-3">
                <div class="text-muted fw-semibold">Store Status</div>
                <div class="h3 fw-bold mb-0">{{ $restaurant->is_open ? 'Open' : 'Closed' }}</div>
                <small>Counter billing mode</small>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('restaurant.pos.store') }}" id="posForm">
        @csrf
        <div id="posHiddenItems"></div>
        <div class="pos-fullscreen-bar">
            <div>
                <h1>POS Terminal</h1>
                <small>{{ $restaurant->name }} counter billing</small>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" class="btn btn-light btn-sm" data-pos-action="new-bill">
                    <kbd class="me-1">F1</kbd> New Bill
                </button>
                <button type="button" class="btn btn-light btn-sm" data-pos-action="hold-bill">
                    <kbd class="me-1">F2</kbd> Hold Bill
                </button>
                <button type="button" class="btn btn-light btn-sm" id="posFullscreenExit" aria-label="Exit Full Screen" title="Exit Full Screen">
                    <i class="fas fa-compress me-1"></i> Exit
                </button>
            </div>
        </div>

        <div class="pos-shell">
            <section>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="pos-toolbar">
                            <div class="input-group pos-search">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="search" class="form-control" id="posSearch" placeholder="Search name, SKU, barcode">
                            </div>
                            <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">
                                <i class="fas fa-xmark me-1"></i> Clear
                            </button>
                            <button type="button" class="btn btn-outline-danger ms-auto" id="clearCartBtn">
                                <i class="fas fa-trash me-1"></i> Clear Bill
                            </button>
                        </div>
                        <div class="pos-category-tabs mt-3" id="categoryTabs">
                            <button type="button" class="is-active" data-category="all">All</button>
                            @foreach($categories as $category)
                                <button type="button" data-category="{{ $category->id }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="pos-products" id="productGrid">
                    @foreach($products as $product)
                        @php
                            $tracksInventory = (bool) ($product->track_inventory ?? false);
                            $stockQuantity = (int) ($product->stock_quantity ?? 0);
                            $outOfStock = $tracksInventory && $stockQuantity <= 0;
                            $sku = $product->sku ?? '';
                            $finalPrice = (float) ($product->final_price ?? $product->getFinalPriceAttribute());
                        @endphp
                        <button
                            type="button"
                            class="pos-product-card text-start {{ $outOfStock ? 'is-disabled' : '' }}"
                            data-product-id="{{ $product->id }}"
                            data-category-id="{{ $product->category_id ?: 'none' }}"
                            data-search="{{ strtolower($product->name . ' ' . $sku . ' ' . $product->category?->name) }}"
                            @disabled($outOfStock)
                        >
                            <div class="pos-product-image">
                                @if($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.remove()">
                                @endif
                                <span class="pos-product-placeholder">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->name, 0, 1)) }}</span>
                            </div>
                            <div class="pos-product-name">{{ $product->name }}</div>
                            <div class="pos-product-meta">{{ $sku ?: 'No SKU' }} - {{ $product->category?->name ?: 'Uncategorised' }}</div>
                            @if($tracksInventory)
                                <div class="pos-product-meta {{ $outOfStock ? 'text-danger' : 'text-success' }}">
                                    {{ $stockQuantity }} in stock
                                </div>
                            @endif
                            <div class="pos-product-price">{{ $currencySymbol }}{{ number_format($finalPrice, $currencyDecimals) }}</div>
                        </button>
                    @endforeach
                </div>
            </section>

            <aside class="pos-cart">
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold">Current Bill</h5>
                        <span class="badge bg-success" id="cartCount">0 items</span>
                    </div>
                    <div class="card-body">
                        <div class="pos-empty" id="emptyCart">
                            <i class="fas fa-cash-register fa-2x mb-2"></i>
                            <div class="fw-bold">No items added</div>
                            <div class="small">Tap products or scan SKU to start billing.</div>
                        </div>
                        <div class="pos-cart-items d-none" id="cartItems"></div>

                        <hr>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" name="customer_name" id="customerNameInput" class="form-control" placeholder="Walk-in Customer">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Phone / GST reference</label>
                            <input type="text" name="customer_phone" id="customerPhoneInput" class="form-control" placeholder="Optional">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Bill Discount</label>
                            <input type="number" name="discount_amount" id="discountInput" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Payment</label>
                            <select name="payment_method" id="paymentMethod" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="wallet">Wallet</option>
                                <option value="split">Split Payment</option>
                            </select>
                        </div>
                        <div class="pos-split-grid d-none mb-2" id="splitFields">
                            <input type="number" name="paid_cash" class="form-control" min="0" step="0.01" placeholder="Cash">
                            <input type="number" name="paid_upi" class="form-control" min="0" step="0.01" placeholder="UPI">
                            <input type="number" name="paid_card" class="form-control" min="0" step="0.01" placeholder="Card">
                            <input type="number" name="paid_wallet" class="form-control" min="0" step="0.01" placeholder="Wallet">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bill Note</label>
                            <textarea name="notes" id="notesInput" class="form-control" rows="2" placeholder="Optional counter note"></textarea>
                        </div>

                        <div class="pos-bill-row"><span>Subtotal</span><strong id="subtotalText">{{ $currencySymbol }}0</strong></div>
                        <div class="pos-bill-row"><span>Discount</span><strong id="discountText">-{{ $currencySymbol }}0</strong></div>
                        <div class="pos-bill-row"><span>Estimated tax</span><strong id="taxText">{{ $currencySymbol }}0</strong></div>
                        <div class="pos-bill-row pos-total"><span>Total</span><strong id="totalText">{{ $currencySymbol }}0</strong></div>
                        <div class="small text-muted mt-2">Server recalculates tax and commission before saving.</div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary w-100" id="submitPosBtn" @disabled(! $restaurant->is_open)>
                            <i class="fas fa-print me-2"></i> Complete & Print Bill
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Recent POS Orders</h5>
                    </div>
                    <div class="card-body">
                        @forelse($recentOrders as $order)
                            <a href="{{ route('restaurant.pos.index', ['receipt' => $order->id]) }}" class="d-flex justify-content-between border-bottom py-2 text-decoration-none text-dark">
                                <div>
                                    <div class="fw-bold">#{{ $order->order_number }}</div>
                                    <div class="small text-muted">{{ strtoupper($order->payment_method) }} - {{ $order->created_at?->format('d M, h:i A') }}</div>
                                </div>
                                <div class="fw-bold">{{ $currencySymbol }}{{ number_format((float) $order->total, $currencyDecimals) }}</div>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">No POS orders yet.</div>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

@if($receiptOrder)
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt #{{ $receiptOrder->order_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="posReceipt">
                    <div class="pos-receipt">
                        <div class="text-center fw-bold">{{ $restaurant->name }}</div>
                        <div class="text-center small">{{ $restaurant->address }}</div>
                        <hr>
                        <div class="small">Bill: #{{ $receiptOrder->order_number }}</div>
                        <div class="small">Date: {{ $receiptOrder->created_at?->format('d M Y, h:i A') }}</div>
                        <div class="small">Payment: {{ strtoupper($receiptOrder->payment_method) }}</div>
                        <hr>
                        @foreach($receiptOrder->orderItems as $item)
                            <div>
                                <div>{{ $item->menuItem?->name ?? 'Item' }}</div>
                                <div class="pos-receipt-line small">
                                    <span>{{ $item->quantity }} x {{ $currencySymbol }}{{ number_format((float) $item->unit_price, $currencyDecimals) }}</span>
                                    <span>{{ $currencySymbol }}{{ number_format((float) $item->total_price, $currencyDecimals) }}</span>
                                </div>
                            </div>
                        @endforeach
                        <hr>
                        <div class="pos-receipt-line"><span>Subtotal</span><span>{{ $currencySymbol }}{{ number_format((float) $receiptOrder->subtotal, $currencyDecimals) }}</span></div>
                        <div class="pos-receipt-line"><span>Discount</span><span>-{{ $currencySymbol }}{{ number_format((float) $receiptOrder->discount, $currencyDecimals) }}</span></div>
                        <div class="pos-receipt-line"><span>Tax</span><span>{{ $currencySymbol }}{{ number_format((float) $receiptOrder->tax, $currencyDecimals) }}</span></div>
                        <div class="pos-receipt-line fw-bold"><span>Total</span><span>{{ $currencySymbol }}{{ number_format((float) $receiptOrder->total, $currencyDecimals) }}</span></div>
                        <hr>
                        <div class="text-center small">Thank you.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('restaurant.orders.show', $receiptOrder->id) }}" class="btn btn-outline-secondary">Open Order</a>
                    <form method="POST" action="{{ route('restaurant.printers.invoice', $receiptOrder->id) }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-print me-1"></i> Print to Selected Printer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const products = @json($productPayload);
    const productMap = new Map(products.map((product) => [String(product.id), product]));
    const cart = new Map();
    const currency = @json($currencySymbol);
    const decimals = Number(@json($currencyDecimals));
    const taxRate = Number(@json($taxRate));
    const heldBillKey = `restaurant-pos-held-bill-${@json($restaurant->id)}`;

    const productGrid = document.getElementById('productGrid');
    const cartItems = document.getElementById('cartItems');
    const emptyCart = document.getElementById('emptyCart');
    const hiddenItems = document.getElementById('posHiddenItems');
    const cartCount = document.getElementById('cartCount');
    const discountInput = document.getElementById('discountInput');
    const paymentMethod = document.getElementById('paymentMethod');
    const splitFields = document.getElementById('splitFields');
    const submitBtn = document.getElementById('submitPosBtn');
    const terminalRoot = document.getElementById('posTerminalRoot');
    const fullscreenToggle = document.getElementById('posFullscreenToggle');
    const fullscreenExit = document.getElementById('posFullscreenExit');
    const searchInput = document.getElementById('posSearch');
    const customerNameInput = document.getElementById('customerNameInput');
    const customerPhoneInput = document.getElementById('customerPhoneInput');
    const notesInput = document.getElementById('notesInput');
    const newBillButtons = document.querySelectorAll('[data-pos-action="new-bill"]');
    const holdBillButtons = document.querySelectorAll('[data-pos-action="hold-bill"]');

    function money(amount) {
        return currency + Number(amount || 0).toFixed(decimals);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function productInitial(product) {
        return escapeHtml(String(product?.name || '?').trim().charAt(0).toUpperCase() || '?');
    }

    function productThumb(product) {
        const imageUrl = String(product?.image_url || '');
        if (!imageUrl) {
            return `<span class="pos-cart-thumb"><span>${productInitial(product)}</span></span>`;
        }

        return `<span class="pos-cart-thumb"><img src="${escapeHtml(imageUrl)}" alt="" onerror="this.remove()"><span>${productInitial(product)}</span></span>`;
    }

    function syncFullscreenControls(enabled) {
        document.body.classList.toggle('pos-terminal-fullscreen', enabled);
        fullscreenToggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        if (fullscreenToggle) {
            fullscreenToggle.title = enabled ? 'Exit Full Screen' : 'Full Screen';
            fullscreenToggle.setAttribute('aria-label', fullscreenToggle.title);
            const icon = fullscreenToggle.querySelector('i');
            icon?.classList.toggle('fa-expand', !enabled);
            icon?.classList.toggle('fa-compress', enabled);
        }
    }

    function setTerminalFullscreen(enabled) {
        syncFullscreenControls(enabled);
        if (enabled && terminalRoot?.requestFullscreen && !document.fullscreenElement) {
            terminalRoot.requestFullscreen().catch(() => syncFullscreenControls(true));
        }
        if (!enabled && document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(() => syncFullscreenControls(false));
        }
    }

    function currentBillSnapshot() {
        return {
            items: Array.from(cart.values()).map((item) => ({ id: item.product.id, quantity: item.quantity })),
            customer_name: customerNameInput?.value || '',
            customer_phone: customerPhoneInput?.value || '',
            discount_amount: discountInput?.value || '0',
            payment_method: paymentMethod?.value || 'cash',
            paid_cash: document.querySelector('[name="paid_cash"]')?.value || '',
            paid_card: document.querySelector('[name="paid_card"]')?.value || '',
            paid_upi: document.querySelector('[name="paid_upi"]')?.value || '',
            paid_wallet: document.querySelector('[name="paid_wallet"]')?.value || '',
            notes: notesInput?.value || '',
            held_at: new Date().toISOString(),
        };
    }

    function readHeldBill() {
        try {
            const payload = localStorage.getItem(heldBillKey);
            return payload ? JSON.parse(payload) : null;
        } catch (error) {
            return null;
        }
    }

    function applyHeldBill(snapshot) {
        cart.clear();
        (snapshot?.items || []).forEach((item) => {
            const product = productMap.get(String(item.id));
            const quantity = Math.max(0, Number(item.quantity || 0));
            if (!product || quantity <= 0) return;
            const safeQuantity = product.track_inventory ? Math.min(quantity, Number(product.stock_quantity || 0)) : quantity;
            if (safeQuantity > 0) {
                cart.set(String(product.id), { product, quantity: safeQuantity });
            }
        });
        if (customerNameInput) customerNameInput.value = snapshot?.customer_name || '';
        if (customerPhoneInput) customerPhoneInput.value = snapshot?.customer_phone || '';
        if (discountInput) discountInput.value = snapshot?.discount_amount || '0';
        if (paymentMethod) paymentMethod.value = snapshot?.payment_method || 'cash';
        const paidCashInput = document.querySelector('[name="paid_cash"]'); if (paidCashInput) paidCashInput.value = snapshot?.paid_cash || '';
        const paidCardInput = document.querySelector('[name="paid_card"]'); if (paidCardInput) paidCardInput.value = snapshot?.paid_card || '';
        const paidUpiInput = document.querySelector('[name="paid_upi"]'); if (paidUpiInput) paidUpiInput.value = snapshot?.paid_upi || '';
        const paidWalletInput = document.querySelector('[name="paid_wallet"]'); if (paidWalletInput) paidWalletInput.value = snapshot?.paid_wallet || '';
        if (notesInput) notesInput.value = snapshot?.notes || '';
        splitFields.classList.toggle('d-none', paymentMethod.value !== 'split');
        renderCart();
    }

    function resetBillForm() {
        if (customerNameInput) customerNameInput.value = '';
        if (customerPhoneInput) customerPhoneInput.value = '';
        if (discountInput) discountInput.value = '0';
        if (paymentMethod) paymentMethod.value = 'cash';
        splitFields.querySelectorAll('input').forEach((input) => input.value = '');
        splitFields.classList.add('d-none');
        if (notesInput) notesInput.value = '';
    }

    function clearBill(resetForm = false) {
        cart.clear();
        if (resetForm) resetBillForm();
        renderCart();
        searchInput?.focus();
    }

    function newBill() {
        if (cart.size > 0 && !confirm('Start a new bill and clear the current items?')) {
            return;
        }
        clearBill(true);
    }

    function holdBill() {
        if (cart.size === 0) {
            const heldBill = readHeldBill();
            if (heldBill && confirm('Restore the held bill?')) {
                applyHeldBill(heldBill);
                localStorage.removeItem(heldBillKey);
                return;
            }
            alert('Add at least one item before holding a bill.');
            return;
        }

        if (readHeldBill() && !confirm('Replace the currently held bill?')) {
            return;
        }

        localStorage.setItem(heldBillKey, JSON.stringify(currentBillSnapshot()));
        clearBill(true);
        alert('Bill held. Press F2 again with an empty bill to restore it.');
    }

    function addProduct(id) {
        const product = productMap.get(String(id));
        if (!product) return;
        const current = cart.get(String(id)) || { product, quantity: 0 };
        const nextQuantity = current.quantity + 1;
        if (product.track_inventory && nextQuantity > product.stock_quantity) {
            alert(product.name + ' has only ' + product.stock_quantity + ' in stock.');
            return;
        }
        cart.set(String(id), { product, quantity: nextQuantity });
        renderCart();
    }

    function setQuantity(id, quantity) {
        const current = cart.get(String(id));
        if (!current) return;
        const nextQuantity = Math.max(0, Number(quantity || 0));
        if (current.product.track_inventory && nextQuantity > current.product.stock_quantity) {
            alert(current.product.name + ' has only ' + current.product.stock_quantity + ' in stock.');
            return;
        }
        if (nextQuantity <= 0) {
            cart.delete(String(id));
        } else {
            cart.set(String(id), { product: current.product, quantity: nextQuantity });
        }
        renderCart();
    }

    function totals() {
        const subtotal = Array.from(cart.values()).reduce((sum, item) => sum + item.product.price * item.quantity, 0);
        const discount = Math.min(Number(discountInput.value || 0), subtotal);
        const taxable = Math.max(0, subtotal - discount);
        const tax = taxable * (taxRate / 100);
        const total = taxable + tax;
        return { subtotal, discount, tax, total };
    }

    function renderCart() {
        const rows = Array.from(cart.values());
        emptyCart.classList.toggle('d-none', rows.length > 0);
        cartItems.classList.toggle('d-none', rows.length === 0);
        cartItems.innerHTML = '';
        hiddenItems.innerHTML = '';

        rows.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'pos-cart-row';
            row.innerHTML = `
                <div class="pos-cart-row-top">
                    <div class="pos-cart-product">
                        ${productThumb(item.product)}
                        <div class="min-w-0">
                            <div class="fw-bold">${escapeHtml(item.product.name)}</div>
                            <div class="small text-muted">${money(item.product.price)} each</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove="${item.product.id}">Remove</button>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="pos-qty">
                        <button type="button" data-dec="${item.product.id}">-</button>
                        <strong>${item.quantity}</strong>
                        <button type="button" data-inc="${item.product.id}">+</button>
                    </div>
                    <strong>${money(item.product.price * item.quantity)}</strong>
                </div>
            `;
            cartItems.appendChild(row);

            hiddenItems.insertAdjacentHTML('beforeend', `
                <input type="hidden" name="items[${index}][id]" value="${item.product.id}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
            `);
        });

        const count = rows.reduce((sum, item) => sum + item.quantity, 0);
        const bill = totals();
        cartCount.textContent = count + (count === 1 ? ' item' : ' items');
        document.getElementById('subtotalText').textContent = money(bill.subtotal);
        document.getElementById('discountText').textContent = '-' + money(bill.discount);
        document.getElementById('taxText').textContent = money(bill.tax);
        document.getElementById('totalText').textContent = money(bill.total);
        submitBtn.disabled = count === 0 || @json(! $restaurant->is_open);
    }

    productGrid.addEventListener('click', function (event) {
        const product = event.target.closest('[data-product-id]');
        if (product && !product.disabled) addProduct(product.dataset.productId);
    });

    cartItems.addEventListener('click', function (event) {
        const inc = event.target.closest('[data-inc]');
        const dec = event.target.closest('[data-dec]');
        const remove = event.target.closest('[data-remove]');
        if (inc) setQuantity(inc.dataset.inc, (cart.get(String(inc.dataset.inc))?.quantity || 0) + 1);
        if (dec) setQuantity(dec.dataset.dec, (cart.get(String(dec.dataset.dec))?.quantity || 0) - 1);
        if (remove) setQuantity(remove.dataset.remove, 0);
    });

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('.pos-product-card').forEach((card) => {
            card.style.display = card.dataset.search.includes(query) ? '' : 'none';
        });
    });

    document.getElementById('clearSearchBtn').addEventListener('click', function () {
        searchInput.value = '';
        document.querySelectorAll('.pos-product-card').forEach((card) => card.style.display = '');
    });

    document.getElementById('categoryTabs').addEventListener('click', function (event) {
        const button = event.target.closest('[data-category]');
        if (!button) return;
        this.querySelectorAll('button').forEach((node) => node.classList.toggle('is-active', node === button));
        const category = button.dataset.category;
        document.querySelectorAll('.pos-product-card').forEach((card) => {
            card.style.display = category === 'all' || card.dataset.categoryId === category ? '' : 'none';
        });
    });

    document.getElementById('clearCartBtn').addEventListener('click', function () {
        clearBill(false);
    });

    newBillButtons.forEach((button) => button.addEventListener('click', newBill));
    holdBillButtons.forEach((button) => button.addEventListener('click', holdBill));
    fullscreenToggle?.addEventListener('click', () => setTerminalFullscreen(!document.body.classList.contains('pos-terminal-fullscreen')));
    fullscreenExit?.addEventListener('click', () => setTerminalFullscreen(false));

    document.addEventListener('keydown', function (event) {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.key === 'F1') {
            event.preventDefault();
            newBill();
        }
        if (event.key === 'F2') {
            event.preventDefault();
            holdBill();
        }
    });

    document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement) {
            syncFullscreenControls(false);
        }
    });

    discountInput.addEventListener('input', renderCart);
    paymentMethod.addEventListener('change', function () {
        splitFields.classList.toggle('d-none', this.value !== 'split');
    });

    document.getElementById('posForm').addEventListener('submit', function (event) {
        if (cart.size === 0) {
            event.preventDefault();
            alert('Add at least one item to complete a POS bill.');
            return;
        }

        if (paymentMethod.value === 'split') {
            const bill = totals();
            const splitTotal = Array.from(splitFields.querySelectorAll('input')).reduce((sum, input) => sum + Number(input.value || 0), 0);
            if (Math.abs(splitTotal - bill.total) > 0.05) {
                event.preventDefault();
                alert('Split payment total must match ' + money(bill.total) + '.');
            }
        }
    });

    @if($receiptOrder)
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        receiptModal.show();
    @endif

    renderCart();
});
</script>
@endsection
