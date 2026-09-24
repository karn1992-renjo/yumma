<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b0f19">
    <meta name="robots" content="noindex, nofollow">
    <title>Order to your seat — {{ $theatre->name }}</title>
    {{--
        swaad-only: the page a customer lands on after scanning the QR on
        their armrest. Deliberately one self-contained file with no build
        step and no framework: it loads on cinema wifi, in the dark, on
        whatever phone the customer already has, and the whole point of it
        is that nothing has to be installed first.

        Dark by default (a bright white page in a dark auditorium annoys
        the row behind), large tap targets, and no sound anywhere.

        It browses through this app's own signature-gated endpoints, but
        every rupee goes through the SAME /api/theatre/checkout engine the
        Flutter app uses -- server-side re-pricing, multi-stall fan-out and
        payment verification included. There is no second checkout here.
    --}}
    <style>
        :root {
            --bg: #0b0f19;
            --surface: #151b2b;
            --surface-2: #1d2436;
            --line: #2a3347;
            --text: #e8ecf5;
            --muted: #94a3b8;
            --accent: #f97316;
            --accent-ink: #0b0f19;
            --green: #22c55e;
            --radius: 14px;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.45;
            padding-bottom: 96px;
        }

        button, input, select { font: inherit; color: inherit; }

        .wrap { max-width: 640px; margin: 0 auto; padding: 0 16px; }

        header.seat-bar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(11, 15, 25, .96);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(8px);
        }

        .seat-bar .wrap { padding-top: 12px; padding-bottom: 12px; display: flex; align-items: center; gap: 12px; }
        .seat-bar .venue { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
        .seat-bar .seat { font-size: 17px; font-weight: 800; }
        .seat-bar .seat span { color: var(--accent); }
        .back-btn {
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 10px;
            width: 38px; height: 38px;
            flex: 0 0 38px;
            cursor: pointer;
            font-size: 18px;
        }

        h2.section { font-size: 15px; margin: 22px 0 10px; letter-spacing: .02em; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 14px;
            margin-bottom: 10px;
            display: flex;
            gap: 12px;
            align-items: center;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .card[disabled] { opacity: .45; cursor: not-allowed; }
        .card .logo {
            width: 46px; height: 46px; flex: 0 0 46px;
            border-radius: 12px;
            background: var(--surface-2);
            display: grid; place-items: center;
            font-weight: 800; color: var(--muted);
            overflow: hidden;
        }
        .card .logo img { width: 100%; height: 100%; object-fit: cover; }
        .card .name { font-weight: 700; }

        .chip {
            display: inline-block;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid var(--line);
            color: var(--muted);
            margin-right: 6px;
        }
        .chip.closed { color: #fca5a5; border-color: #7f1d1d; }

        .item {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--line);
            align-items: flex-start;
        }
        .item:last-child { border-bottom: 0; }
        .item .info { flex: 1 1 auto; min-width: 0; }
        .item .nm { font-weight: 650; }
        .item .desc { font-size: 13px; color: var(--muted); }
        .item .price { margin-top: 4px; font-weight: 700; }
        .veg-dot, .nonveg-dot {
            display: inline-block; width: 11px; height: 11px;
            border: 1.5px solid var(--green); border-radius: 3px;
            position: relative; margin-right: 6px; vertical-align: middle;
        }
        .nonveg-dot { border-color: #ef4444; }
        .veg-dot::after, .nonveg-dot::after {
            content: ''; position: absolute; inset: 2px;
            border-radius: 50%; background: var(--green);
        }
        .nonveg-dot::after { background: #ef4444; }

        .add-btn {
            flex: 0 0 auto;
            background: var(--surface-2);
            border: 1px solid var(--accent);
            color: var(--accent);
            font-weight: 800;
            border-radius: 10px;
            padding: 9px 18px;
            cursor: pointer;
            min-height: 42px;
        }

        .stepper { display: flex; align-items: center; gap: 0; border: 1px solid var(--accent); border-radius: 10px; overflow: hidden; }
        .stepper button {
            background: var(--surface-2); border: 0; color: var(--accent);
            font-weight: 800; width: 38px; height: 42px; cursor: pointer;
        }
        .stepper .qty { min-width: 30px; text-align: center; font-weight: 800; color: var(--accent); }

        .cat-title {
            font-size: 12px; text-transform: uppercase; letter-spacing: .08em;
            color: var(--muted); margin: 20px 0 4px;
        }

        .bottom-bar {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            background: var(--surface);
            border-top: 1px solid var(--line);
            padding: 12px 0 calc(12px + env(safe-area-inset-bottom));
            z-index: 30;
        }
        .bottom-bar .wrap { display: flex; align-items: center; gap: 12px; }
        .btn-primary {
            flex: 1 1 auto;
            background: var(--accent);
            color: var(--accent-ink);
            border: 0;
            border-radius: 12px;
            padding: 15px 20px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            min-height: 52px;
        }
        .btn-primary[disabled] { opacity: .5; cursor: not-allowed; }
        .btn-ghost {
            background: transparent; border: 1px solid var(--line);
            color: var(--text); border-radius: 12px; padding: 14px 18px;
            font-weight: 700; cursor: pointer; width: 100%;
        }

        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; }
        .field input {
            width: 100%;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px;
            font-size: 16px; /* 16px keeps iOS from zooming the page on focus */
        }

        .pay-option {
            display: flex; align-items: center; gap: 12px;
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 12px; padding: 14px; margin-bottom: 8px; cursor: pointer;
        }
        .pay-option.selected { border-color: var(--accent); }
        .pay-option input { accent-color: var(--accent); width: 18px; height: 18px; }

        .line { display: flex; justify-content: space-between; padding: 5px 0; }
        .line.total { font-weight: 800; font-size: 17px; border-top: 1px solid var(--line); margin-top: 8px; padding-top: 12px; }

        .notice {
            background: var(--surface-2); border: 1px solid var(--line);
            border-radius: 12px; padding: 12px 14px; font-size: 13px; color: var(--muted);
            margin: 12px 0;
        }
        .error { color: #fca5a5; font-size: 13px; margin: 8px 0; }

        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--muted); display: inline-block; margin-right: 8px; }
        .status-dot.live { background: var(--green); }

        .spinner {
            width: 22px; height: 22px; border-radius: 50%;
            border: 2.5px solid var(--line); border-top-color: var(--accent);
            animation: spin .7s linear infinite; margin: 32px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        [hidden] { display: none !important; }

        @media (prefers-reduced-motion: reduce) {
            .spinner { animation-duration: 2s; }
        }
    </style>
</head>
<body>
    <header class="seat-bar">
        <div class="wrap">
            <button class="back-btn" id="backBtn" hidden aria-label="Go back">&larr;</button>
            <div>
                <div class="venue">{{ $theatre->name }}</div>
                <div class="seat">{{ $screen->name }} · Seat <span>{{ $seatLabel }}</span></div>
            </div>
        </div>
    </header>

    <main class="wrap">
        {{-- Stall list --}}
        <section id="viewStalls">
            <h2 class="section">What would you like?</h2>
            <p class="muted small" style="margin-top:-6px">Delivered to your seat. Order from as many counters as you like — you pay once.</p>
            <div id="stallList"></div>
        </section>

        {{-- One stall's menu --}}
        <section id="viewMenu" hidden>
            <h2 class="section" id="menuTitle"></h2>
            <div id="menuBody"></div>
        </section>

        {{-- Cart + checkout --}}
        <section id="viewCart" hidden>
            <h2 class="section">Your order</h2>
            <div id="cartBody"></div>

            <div class="notice" id="handoffNotice" hidden>
                Our runner will meet you at the end of your row rather than walk into the auditorium.
            </div>

            <h2 class="section">Pay</h2>
            <div id="payOptions"></div>
            <p class="error" id="cartError" hidden></p>
        </section>

        {{-- Phone + OTP --}}
        <section id="viewAuth" hidden>
            <h2 class="section">Where do we send updates?</h2>
            <p class="muted small" style="margin-top:-6px">Your number is only used for this order and the runner's call.</p>

            <div id="authPhoneStep">
                <div class="field">
                    <label for="phoneInput">Mobile number</label>
                    <input id="phoneInput" type="tel" inputmode="tel" autocomplete="tel" placeholder="10-digit mobile number">
                </div>
            </div>

            <div id="authOtpStep" hidden>
                <div class="field" id="nameField" hidden>
                    <label for="nameInput">Your name</label>
                    <input id="nameInput" type="text" autocomplete="given-name" placeholder="So the runner can find you">
                </div>
                <div class="field">
                    <label for="otpInput">Enter the OTP</label>
                    <input id="otpInput" type="tel" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code">
                </div>
                <button class="btn-ghost" id="changeNumberBtn" type="button">Use a different number</button>
            </div>

            <p class="error" id="authError" hidden></p>
        </section>

        {{-- Placed --}}
        <section id="viewStatus" hidden>
            <h2 class="section">Order placed</h2>
            <div id="statusBody"></div>
            <div class="notice">
                Keep this page open to follow your order. The runner will bring it to
                <strong>{{ $screen->name }}, seat {{ $seatLabel }}</strong>.
            </div>
        </section>

        <div class="spinner" id="spinner" hidden></div>
    </main>

    <div class="bottom-bar" id="bottomBar" hidden>
        <div class="wrap">
            <div id="barSummary" class="small"></div>
            <button class="btn-primary" id="barAction"></button>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var CTX = @json($context);
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;
        var MONEY = CTX.currency_symbol || '₹';
        // Scoped per seat: two people in the same row on the same phone
        // (it happens -- one phone gets passed along the row) must not
        // inherit each other's cart or login.
        var STORE_KEY = 'swaad.seat.' + CTX.theatre_id + '.' + CTX.screen_id + '.' + CTX.seat_label;

        var el = function (id) { return document.getElementById(id); };
        var money = function (amount) { return MONEY + Number(amount || 0).toFixed(2); };

        var state = {
            view: 'stalls',
            stalls: @json($stalls),
            menus: {},
            activeStall: null,
            cart: {},          // restaurantId -> { name, items: { lineKey -> line } }
            token: null,
            customerName: null,
            paymentMethod: null,
            otpFlow: null,
            phone: null,
            orderGroupId: null,
            busy: false,
        };

        restore();

        /* ---------------------------------------------------------------
           Persistence: a cart must survive the browser being backgrounded
           mid-film, an accidental back-swipe, or the OTP SMS pulling the
           customer into their messages app and back.
        ---------------------------------------------------------------- */
        function persist() {
            try {
                sessionStorage.setItem(STORE_KEY, JSON.stringify({
                    cart: state.cart,
                    token: state.token,
                    customerName: state.customerName,
                    orderGroupId: state.orderGroupId,
                }));
            } catch (e) { /* private mode: the page still works, just forgetfully */ }
        }

        function restore() {
            try {
                var saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}');
                state.cart = saved.cart || {};
                state.token = saved.token || null;
                state.customerName = saved.customerName || null;
                state.orderGroupId = saved.orderGroupId || null;
            } catch (e) { /* ignore */ }
        }

        /* ---------------------------------------------------------------
           Networking
        ---------------------------------------------------------------- */
        function get(path) {
            return fetch(CTX.base_url + path, { headers: { 'Accept': 'application/json' } })
                .then(readJson);
        }

        function postLocal(path, body) {
            return fetch(CTX.base_url + path, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(body),
            }).then(readJson);
        }

        function postApi(path, body) {
            return fetch('/api' + path, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + state.token,
                },
                body: JSON.stringify(body),
            }).then(readJson);
        }

        function getApi(path) {
            return fetch('/api' + path, {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + state.token },
            }).then(readJson);
        }

        function readJson(response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    var message = body.message
                        || (body.errors && Object.values(body.errors)[0] && Object.values(body.errors)[0][0])
                        || 'Something went wrong. Please try again.';
                    var error = new Error(message);
                    error.body = body;
                    error.status = response.status;
                    throw error;
                }
                return body;
            });
        }

        /* ---------------------------------------------------------------
           Cart
        ---------------------------------------------------------------- */
        function lineKey(item, variant, addOns) {
            return [item.id, variant ? variant.name : '', (addOns || []).map(function (a) { return a.name; }).sort().join('+')].join('|');
        }

        function addToCart(stall, item, variant, addOns) {
            var key = lineKey(item, variant, addOns);
            var bucket = state.cart[stall.id] || { name: stall.name, items: {} };
            var unit = Number(item.price)
                + (variant ? Number(variant.price) : 0)
                + (addOns || []).reduce(function (sum, a) { return sum + Number(a.price); }, 0);

            if (bucket.items[key]) {
                bucket.items[key].quantity += 1;
            } else {
                bucket.items[key] = {
                    id: item.id,
                    name: item.name,
                    unit: unit,
                    quantity: 1,
                    variant: variant || null,
                    addOns: addOns || [],
                };
            }

            state.cart[stall.id] = bucket;
            persist();
            render();
        }

        function changeQty(stallId, key, delta) {
            var bucket = state.cart[stallId];
            if (!bucket || !bucket.items[key]) { return; }
            bucket.items[key].quantity += delta;
            if (bucket.items[key].quantity <= 0) {
                delete bucket.items[key];
            }
            if (Object.keys(bucket.items).length === 0) {
                delete state.cart[stallId];
            }
            persist();
            render();
        }

        function cartCount() {
            return Object.keys(state.cart).reduce(function (count, stallId) {
                return count + Object.keys(state.cart[stallId].items).reduce(function (inner, key) {
                    return inner + state.cart[stallId].items[key].quantity;
                }, 0);
            }, 0);
        }

        /**
         * The page's own running total is a PREVIEW only -- taxes and the
         * platform fee are computed server-side at checkout, so this
         * deliberately shows an items subtotal and says so, rather than
         * inventing a grand total that the server would then disagree with.
         */
        function cartSubtotal() {
            return Object.keys(state.cart).reduce(function (sum, stallId) {
                var items = state.cart[stallId].items;
                return sum + Object.keys(items).reduce(function (inner, key) {
                    return inner + items[key].unit * items[key].quantity;
                }, 0);
            }, 0);
        }

        function qtyInCart(stallId, itemId) {
            var bucket = state.cart[stallId];
            if (!bucket) { return 0; }
            return Object.keys(bucket.items).reduce(function (sum, key) {
                return sum + (bucket.items[key].id === itemId ? bucket.items[key].quantity : 0);
            }, 0);
        }

        function stallsPayload() {
            return Object.keys(state.cart).map(function (stallId) {
                var items = state.cart[stallId].items;
                return {
                    restaurant_id: Number(stallId),
                    items: Object.keys(items).map(function (key) {
                        var line = items[key];
                        return {
                            id: line.id,
                            quantity: line.quantity,
                            selected_variant: line.variant,
                            selected_add_ons: line.addOns,
                        };
                    }),
                };
            });
        }

        /* ---------------------------------------------------------------
           Rendering
        ---------------------------------------------------------------- */
        function show(view) {
            state.view = view;
            render();
            window.scrollTo({ top: 0, behavior: 'auto' });
        }

        function render() {
            ['Stalls', 'Menu', 'Cart', 'Auth', 'Status'].forEach(function (name) {
                el('view' + name).hidden = state.view !== name.toLowerCase();
            });
            el('backBtn').hidden = (state.view === 'stalls' || state.view === 'status');
            el('spinner').hidden = !state.busy;

            if (state.view === 'stalls') { renderStalls(); }
            if (state.view === 'menu') { renderMenu(); }
            if (state.view === 'cart') { renderCart(); }
            renderBar();
        }

        function renderStalls() {
            var list = el('stallList');
            list.innerHTML = '';

            if (!state.stalls.length) {
                list.innerHTML = '<p class="muted">No counters are open here right now.</p>';
                return;
            }

            state.stalls.forEach(function (stall) {
                var card = document.createElement('button');
                card.className = 'card';
                card.type = 'button';
                if (!stall.is_open) { card.setAttribute('disabled', 'disabled'); }

                card.innerHTML =
                    '<div class="logo">' + (stall.logo
                        ? '<img alt="" src="' + escapeAttr(stall.logo) + '">'
                        : escapeHtml((stall.name || '?').charAt(0).toUpperCase())) + '</div>' +
                    '<div style="flex:1 1 auto;min-width:0">' +
                        '<div class="name">' + escapeHtml(stall.name) + '</div>' +
                        '<div class="small muted">' + escapeHtml(stall.cuisine || 'Snacks & beverages') + '</div>' +
                        (stall.is_open ? '' : '<span class="chip closed">Closed</span>') +
                        (stall.is_pure_veg ? '<span class="chip">Pure veg</span>' : '') +
                    '</div>' +
                    '<div class="muted">&rsaquo;</div>';

                if (stall.is_open) {
                    card.addEventListener('click', function () { openStall(stall); });
                }
                list.appendChild(card);
            });
        }

        function openStall(stall) {
            state.activeStall = stall;
            el('menuTitle').textContent = stall.name;

            if (state.menus[stall.id]) {
                show('menu');
                return;
            }

            state.busy = true;
            show('menu');
            get('/stalls/' + stall.id + '/menu')
                .then(function (body) {
                    state.menus[stall.id] = body.data.categories || [];
                })
                .catch(function () {
                    state.menus[stall.id] = [];
                })
                .then(function () {
                    state.busy = false;
                    render();
                });
        }

        function renderMenu() {
            var stall = state.activeStall;
            var body = el('menuBody');
            body.innerHTML = '';
            if (!stall) { return; }

            var categories = state.menus[stall.id];
            if (!categories) { return; }

            if (!categories.length) {
                body.innerHTML = '<p class="muted">This counter has nothing on the menu right now.</p>';
                return;
            }

            categories.forEach(function (category) {
                var title = document.createElement('div');
                title.className = 'cat-title';
                title.textContent = category.category;
                body.appendChild(title);

                category.items.forEach(function (item) {
                    body.appendChild(renderItem(stall, item));
                });
            });
        }

        function renderItem(stall, item) {
            var row = document.createElement('div');
            row.className = 'item';

            var quantity = qtyInCart(stall.id, item.id);
            var hasOptions = (item.variants && item.variants.length) || (item.add_ons && item.add_ons.length);

            row.innerHTML =
                '<div class="info">' +
                    '<div class="nm">' + (item.is_veg ? '<span class="veg-dot"></span>' : '<span class="nonveg-dot"></span>') +
                        escapeHtml(item.name) + '</div>' +
                    (item.description ? '<div class="desc">' + escapeHtml(item.description) + '</div>' : '') +
                    '<div class="price">' + money(item.price) + '</div>' +
                '</div>';

            var control = document.createElement('div');

            // An item with options always re-opens the chooser rather than
            // incrementing, because a second Coke might be a different size.
            if (quantity > 0 && !hasOptions) {
                control.className = 'stepper';
                var minus = document.createElement('button');
                minus.type = 'button';
                minus.textContent = '−';
                minus.setAttribute('aria-label', 'Remove one ' + item.name);
                minus.addEventListener('click', function () { changeQty(stall.id, lineKey(item, null, []), -1); });

                var count = document.createElement('div');
                count.className = 'qty';
                count.textContent = quantity;

                var plus = document.createElement('button');
                plus.type = 'button';
                plus.textContent = '+';
                plus.setAttribute('aria-label', 'Add one ' + item.name);
                plus.addEventListener('click', function () { changeQty(stall.id, lineKey(item, null, []), 1); });

                control.appendChild(minus);
                control.appendChild(count);
                control.appendChild(plus);
            } else {
                control.className = '';
                var add = document.createElement('button');
                add.type = 'button';
                add.className = 'add-btn';
                add.textContent = quantity > 0 ? 'ADD MORE' : 'ADD';
                add.addEventListener('click', function () {
                    if (hasOptions) { openOptions(stall, item); } else { addToCart(stall, item, null, []); }
                });
                control.appendChild(add);
            }

            row.appendChild(control);
            return row;
        }

        /**
         * Variants and add-ons matter for price, and the server re-prices
         * from exactly these selections -- so the chooser sends back the
         * option objects verbatim rather than a client-computed price.
         */
        function openOptions(stall, item) {
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:50;display:flex;align-items:flex-end;';

            var sheet = document.createElement('div');
            sheet.style.cssText = 'background:var(--surface);width:100%;max-height:82vh;overflow:auto;border-radius:18px 18px 0 0;padding:18px 16px calc(18px + env(safe-area-inset-bottom));max-width:640px;margin:0 auto;';

            var html = '<div style="font-weight:800;font-size:17px;margin-bottom:4px">' + escapeHtml(item.name) + '</div>' +
                '<div class="muted small" style="margin-bottom:14px">' + money(item.price) + ' base</div>';

            if (item.variants && item.variants.length) {
                html += '<div class="cat-title" style="margin-top:0">Choose one</div>';
                item.variants.forEach(function (variant, index) {
                    html += '<label class="pay-option"><input type="radio" name="variant" value="' + index + '"' +
                        (index === 0 ? ' checked' : '') + '><span style="flex:1">' + escapeHtml(variant.name) + '</span>' +
                        '<span class="muted">+' + money(variant.price) + '</span></label>';
                });
            }

            if (item.add_ons && item.add_ons.length) {
                html += '<div class="cat-title">Add-ons</div>';
                item.add_ons.forEach(function (addOn, index) {
                    html += '<label class="pay-option"><input type="checkbox" name="addon" value="' + index + '">' +
                        '<span style="flex:1">' + escapeHtml(addOn.name) + '</span>' +
                        '<span class="muted">+' + money(addOn.price) + '</span></label>';
                });
            }

            html += '<button class="btn-primary" style="width:100%;margin-top:14px" id="optionsAdd">Add to order</button>';
            sheet.innerHTML = html;
            overlay.appendChild(sheet);
            document.body.appendChild(overlay);

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) { document.body.removeChild(overlay); }
            });

            sheet.querySelector('#optionsAdd').addEventListener('click', function () {
                var chosenVariant = null;
                var variantInput = sheet.querySelector('input[name="variant"]:checked');
                if (variantInput) { chosenVariant = item.variants[Number(variantInput.value)]; }

                var chosenAddOns = Array.prototype.slice
                    .call(sheet.querySelectorAll('input[name="addon"]:checked'))
                    .map(function (input) { return item.add_ons[Number(input.value)]; });

                document.body.removeChild(overlay);
                addToCart(stall, item, chosenVariant, chosenAddOns);
            });
        }

        function renderCart() {
            var body = el('cartBody');
            body.innerHTML = '';

            Object.keys(state.cart).forEach(function (stallId) {
                var bucket = state.cart[stallId];
                var block = document.createElement('div');
                block.className = 'card';
                block.style.cssText = 'display:block;cursor:default';

                var inner = '<div class="name" style="margin-bottom:8px">' + escapeHtml(bucket.name) + '</div>';
                block.innerHTML = inner;

                Object.keys(bucket.items).forEach(function (key) {
                    var line = bucket.items[key];
                    var row = document.createElement('div');
                    row.className = 'item';

                    var extras = [];
                    if (line.variant) { extras.push(line.variant.name); }
                    line.addOns.forEach(function (addOn) { extras.push(addOn.name); });

                    row.innerHTML = '<div class="info"><div class="nm">' + escapeHtml(line.name) + '</div>' +
                        (extras.length ? '<div class="desc">' + escapeHtml(extras.join(', ')) + '</div>' : '') +
                        '<div class="price">' + money(line.unit * line.quantity) + '</div></div>';

                    var stepper = document.createElement('div');
                    stepper.className = 'stepper';
                    stepper.innerHTML = '<button type="button" aria-label="Remove one">−</button>' +
                        '<div class="qty">' + line.quantity + '</div>' +
                        '<button type="button" aria-label="Add one">+</button>';
                    stepper.children[0].addEventListener('click', function () { changeQty(stallId, key, -1); });
                    stepper.children[2].addEventListener('click', function () { changeQty(stallId, key, 1); });

                    row.appendChild(stepper);
                    block.appendChild(row);
                });

                body.appendChild(block);
            });

            if (!Object.keys(state.cart).length) {
                body.innerHTML = '<p class="muted">Your order is empty.</p>';
            } else {
                var summary = document.createElement('div');
                summary.className = 'card';
                summary.style.cssText = 'display:block;cursor:default';
                summary.innerHTML =
                    '<div class="line"><span class="muted">Items</span><span>' + money(cartSubtotal()) + '</span></div>' +
                    '<div class="line"><span class="muted">Taxes &amp; fees</span><span class="muted">Calculated at payment</span></div>';
                body.appendChild(summary);
            }

            el('handoffNotice').hidden = !CTX.aisle_handoff_only;
            renderPayOptions();
        }

        function renderPayOptions() {
            var container = el('payOptions');
            var options = [];

            if (CTX.razorpay_available) {
                options.push({ id: 'razorpay', label: 'Pay online', hint: 'UPI, card, netbanking' });
            }
            options.push({ id: 'wallet', label: 'Wallet', hint: 'Your swaad balance' });
            if (CTX.cod_enabled) {
                options.push({ id: 'cod', label: 'Pay the runner', hint: 'Cash on delivery to your seat' });
            }

            if (!state.paymentMethod || !options.some(function (o) { return o.id === state.paymentMethod; })) {
                state.paymentMethod = options[0] ? options[0].id : null;
            }

            container.innerHTML = '';
            options.forEach(function (option) {
                var label = document.createElement('label');
                label.className = 'pay-option' + (state.paymentMethod === option.id ? ' selected' : '');
                label.innerHTML = '<input type="radio" name="pay" value="' + option.id + '"' +
                    (state.paymentMethod === option.id ? ' checked' : '') + '>' +
                    '<span style="flex:1"><span style="font-weight:700">' + escapeHtml(option.label) + '</span>' +
                    '<br><span class="muted small">' + escapeHtml(option.hint) + '</span></span>';
                label.addEventListener('click', function () {
                    state.paymentMethod = option.id;
                    renderPayOptions();
                });
                container.appendChild(label);
            });
        }

        function renderBar() {
            var bar = el('bottomBar');
            var summary = el('barSummary');
            var action = el('barAction');
            var count = cartCount();

            if (state.view === 'status') { bar.hidden = true; return; }

            if (state.view === 'cart') {
                bar.hidden = count === 0;
                summary.innerHTML = '<span class="muted">Items</span><br><strong>' + money(cartSubtotal()) + '</strong>';
                action.textContent = state.busy ? 'Please wait…' : 'Place order';
                action.disabled = state.busy || !state.paymentMethod;
                action.onclick = startCheckout;
                return;
            }

            if (state.view === 'auth') {
                bar.hidden = false;
                summary.innerHTML = '';
                var onOtp = !el('authOtpStep').hidden;
                action.textContent = state.busy ? 'Please wait…' : (onOtp ? 'Verify & place order' : 'Send OTP');
                action.disabled = state.busy;
                action.onclick = onOtp ? submitOtp : sendOtp;
                return;
            }

            bar.hidden = count === 0;
            summary.innerHTML = '<strong>' + count + (count === 1 ? ' item' : ' items') + '</strong><br>' +
                '<span class="muted">' + money(cartSubtotal()) + '</span>';
            action.textContent = 'View order';
            action.disabled = false;
            action.onclick = function () { show('cart'); };
        }

        /* ---------------------------------------------------------------
           Checkout
        ---------------------------------------------------------------- */
        function startCheckout() {
            el('cartError').hidden = true;
            if (!cartCount()) { return; }

            // A returning customer on the same seat still holds a token:
            // skip straight past the phone step.
            if (state.token) {
                placeOrder();
            } else {
                show('auth');
            }
        }

        function sendOtp() {
            var phone = el('phoneInput').value.trim();
            if (phone.length < 6) {
                return showAuthError('Enter your mobile number.');
            }

            setBusy(true);
            postLocal('/otp', { phone: phone })
                .then(function (body) {
                    state.otpFlow = body.data.flow;
                    state.phone = phone;
                    el('authPhoneStep').hidden = true;
                    el('authOtpStep').hidden = false;
                    el('nameField').hidden = !body.data.needs_name;
                    el('authError').hidden = true;
                    el('otpInput').focus();
                })
                .catch(function (error) { showAuthError(error.message); })
                .then(function () { setBusy(false); });
        }

        function submitOtp() {
            var otp = el('otpInput').value.trim();
            if (otp.length < 4) {
                return showAuthError('Enter the code we sent you.');
            }

            setBusy(true);
            postLocal('/otp/verify', {
                phone: state.phone,
                otp: otp,
                flow: state.otpFlow,
                name: el('nameInput').value.trim(),
            })
                .then(function (body) {
                    state.token = body.data.token;
                    state.customerName = body.data.name;
                    persist();
                    el('authError').hidden = true;
                    return placeOrder();
                })
                .catch(function (error) { showAuthError(error.message); })
                .then(function () { setBusy(false); });
        }

        function placeOrder() {
            setBusy(true);

            var payload = {
                theatre_id: CTX.theatre_id,
                screen_id: CTX.screen_id,
                seat_label: CTX.seat_label,
                // Proof of presence: the signature off the armrest sticker,
                // which is why this page never asks for location.
                seat_token: CTX.seat_token,
                stalls: stallsPayload(),
                payment_method: state.paymentMethod,
            };

            var flow = state.paymentMethod === 'razorpay'
                ? payWithRazorpay(payload)
                : Promise.resolve(payload);

            return flow
                .then(function (finalPayload) { return postApi('/theatre/checkout', finalPayload); })
                .then(function (body) {
                    state.orderGroupId = body.data.order_group_id;
                    state.cart = {};
                    persist();
                    show('status');
                    pollStatus();
                })
                .catch(function (error) {
                    if (error && error.cancelled) { return; }
                    showCartError(error.message);
                    if (state.view !== 'cart') { show('cart'); }
                })
                .then(function () { setBusy(false); });
        }

        /**
         * Razorpay's browser SDK, against a session the server priced from
         * the same cart it will re-price at checkout -- so the amount shown
         * in the gateway sheet can never drift from what is charged.
         */
        function payWithRazorpay(payload) {
            return postApi('/theatre/checkout/payment-session', payload)
                .then(function (body) { return loadScript('https://checkout.razorpay.com/v1/checkout.js').then(function () { return body.data; }); })
                .then(function (session) {
                    return new Promise(function (resolve, reject) {
                        var checkout = new window.Razorpay({
                            key: session.key,
                            order_id: session.order_id,
                            amount: Math.round(Number(session.amount) * 100),
                            currency: session.currency || 'INR',
                            name: CTX.theatre_name,
                            description: CTX.screen_name + ' · Seat ' + CTX.seat_label,
                            prefill: { contact: state.phone || '', name: state.customerName || '' },
                            theme: { color: '#f97316' },
                            handler: function (response) {
                                resolve(Object.assign({}, payload, {
                                    payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature,
                                }));
                            },
                            modal: {
                                ondismiss: function () {
                                    var cancelled = new Error('Payment cancelled.');
                                    cancelled.cancelled = true;
                                    reject(cancelled);
                                },
                            },
                        });
                        checkout.open();
                    });
                });
        }

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                if (document.querySelector('script[src="' + src + '"]')) { return resolve(); }
                var script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = function () { reject(new Error('Could not reach the payment provider.')); };
                document.head.appendChild(script);
            });
        }

        /* ---------------------------------------------------------------
           Status
        ---------------------------------------------------------------- */
        var STATUS_LABELS = {
            pending: 'Sent to the counter',
            confirmed: 'Accepted',
            preparing: 'Being prepared',
            ready_for_pickup: 'Ready — runner collecting',
            ready: 'Ready — runner collecting',
            picked_up: 'On the way to your seat',
            out_for_delivery: 'On the way to your seat',
            delivered: 'Delivered',
            cancelled: 'Cancelled',
        };

        function pollStatus() {
            if (!state.orderGroupId || !state.token) { return; }

            getApi('/theatre/order-groups/' + state.orderGroupId)
                .then(function (body) { renderStatus(body.data); })
                .catch(function () { /* keep the last painted state */ })
                .then(function () {
                    // 20s: fast enough to feel live, slow enough not to
                    // drain a battery through a two-hour film.
                    window.setTimeout(pollStatus, 20000);
                });
        }

        function renderStatus(data) {
            var body = el('statusBody');
            var orders = data.orders || [];
            var group = data.order_group || {};

            var html = '<div class="card" style="display:block;cursor:default">' +
                '<div class="muted small">Order</div>' +
                '<div class="name">' + escapeHtml(group.group_number || '') + '</div>' +
                '<div class="line total"><span>Paid</span><span>' + money(group.total_amount) + '</span></div>' +
                '</div>';

            orders.forEach(function (order) {
                var label = STATUS_LABELS[order.status] || order.status;
                var live = order.status !== 'delivered' && order.status !== 'cancelled';
                html += '<div class="card" style="display:block;cursor:default">' +
                    '<div class="name">' + escapeHtml((order.restaurant && order.restaurant.name) || 'Counter') + '</div>' +
                    '<div class="small" style="margin-top:6px">' +
                        '<span class="status-dot ' + (live ? 'live' : '') + '"></span>' + escapeHtml(label) +
                    '</div></div>';
            });

            body.innerHTML = html;
        }

        /* ---------------------------------------------------------------
           Helpers
        ---------------------------------------------------------------- */
        function setBusy(busy) {
            state.busy = busy;
            render();
        }

        function showAuthError(message) {
            var box = el('authError');
            box.textContent = message;
            box.hidden = false;
        }

        function showCartError(message) {
            var box = el('cartError');
            box.textContent = message;
            box.hidden = false;
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
            });
        }

        function escapeAttr(value) { return escapeHtml(value); }

        el('backBtn').addEventListener('click', function () {
            if (state.view === 'menu') { return show('stalls'); }
            if (state.view === 'cart') { return show(state.activeStall ? 'menu' : 'stalls'); }
            if (state.view === 'auth') { return show('cart'); }
        });

        el('changeNumberBtn').addEventListener('click', function () {
            el('authPhoneStep').hidden = false;
            el('authOtpStep').hidden = true;
            el('authError').hidden = true;
            el('phoneInput').focus();
            renderBar();
        });

        // A customer who already placed an order from this seat and came
        // back to the page should land on their live order, not an empty
        // stall list.
        if (state.orderGroupId && state.token) {
            show('status');
            pollStatus();
        } else {
            render();
        }
    })();
    </script>
</body>
</html>
