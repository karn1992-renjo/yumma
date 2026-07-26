<div class="billing-panel is-hidden" id="billingPanel">
    <section class="menu-browser">
        <div class="menu-toolbar">
            <div>
                <div class="panel-title">Walk-in POS Billing</div>
                <div class="order-muted">Search menu, add items, and generate a counter bill.</div>
            </div>
            <label class="menu-search">
                <i class="fas fa-search text-muted"></i>
                <input type="search" id="menuSearch" placeholder="Search food, category, price">
            </label>
        </div>

        <div class="category-strip" id="menuCategories">
            <button type="button" class="category-pill active" data-menu-category="all">All</button>
            @foreach($menuCategories as $category)
                <button type="button" class="category-pill" data-menu-category="{{ $category }}">{{ $category }}</button>
            @endforeach
        </div>

        <div class="menu-grid" id="menuGrid"></div>
    </section>

    <form method="POST" action="{{ route('restaurant.pos.store') }}" class="bill-cart" id="terminalPosForm">
        @csrf
        <input type="hidden" name="redirect_to" value="terminal">
        <div id="terminalCartHidden"></div>

        <div class="bill-head">
            <div>
                <div class="panel-title">Current Bill</div>
                <div class="order-muted" id="terminalCartCount">0 items</div>
            </div>
            <button type="button" class="terminal-btn reject" id="clearTerminalCart">
                <i class="fas fa-trash"></i>Clear
            </button>
        </div>

        <div class="cart-body" id="terminalCartItems">
            <div class="empty-orders">
                <div>
                    <i class="fas fa-cash-register fa-2x mb-3"></i><br>
                    Add menu items to start a POS bill.
                </div>
            </div>
        </div>

        <div class="bill-foot">
            <input type="text" name="customer_name" class="bill-input" placeholder="Customer name (optional)">
            <input type="text" name="customer_phone" class="bill-input" placeholder="Phone / GST reference (optional)">
            <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 8px;">
                <input type="number" name="discount_amount" id="terminalDiscount" class="bill-input" min="0" step="0.01" value="0" placeholder="Discount">
                <select name="payment_method" id="terminalPaymentMethod" class="bill-input" required>
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="wallet">Wallet</option>
                </select>
            </div>
            <textarea name="notes" class="bill-input" rows="2" placeholder="Kitchen or billing notes"></textarea>

            <div class="bill-total-row">
                <span>Subtotal</span>
                <span id="terminalSubtotal">{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</span>
            </div>
            <div class="bill-total-row">
                <span>Discount</span>
                <span id="terminalDiscountLabel">-{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</span>
            </div>
            <div class="bill-total-row">
                <span>Grand Total</span>
                <strong id="terminalGrandTotal">{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</strong>
            </div>

            <button type="submit" class="terminal-btn accept" style="min-height: 42px;">
                <i class="fas fa-file-invoice"></i>Generate Invoice
            </button>
        </div>
    </form>
</div>
