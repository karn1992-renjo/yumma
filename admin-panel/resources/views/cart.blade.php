{{-- resources/views/cart.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Your Cart - {{ $appName ?? config('app.name') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>

    <style>
        :root {
            --primary: {{ $primaryColor ?? '#2563EB' }};
            --primary-dark: {{ $primaryDark ?? '#1D4ED8' }};
            --app-ink: #111827;
            --app-muted: #6B7280;
            --app-border: #E5E7EB;
            --app-bg: #FAFAFA;
            --app-success: #22C55E;
            --app-card-radius: 22px;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            background: var(--app-bg);
            padding-bottom: 140px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .cart-container {
            max-width: 620px;
            margin: 0 auto;
            padding: 20px 16px;
        }

        .cart-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .cart-header-logo {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            object-fit: cover;
            background: #F3F4F6;
        }

        .cart-header-name {
            font-size: 18px;
            font-weight: 900;
            color: var(--app-ink);
        }

        .cart-header-sub {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--app-muted);
        }

        .cart-item-card {
            background: #fff;
            border: 1px solid var(--app-border);
            border-radius: var(--app-card-radius);
            box-shadow: 0 6px 18px rgba(17, 34, 58, 0.06);
            padding: 16px;
            margin-bottom: 14px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--app-ink);
        }

        .cart-item-option {
            font-size: 12px;
            font-weight: 600;
            color: var(--app-muted);
            margin-top: 2px;
        }

        .cart-item-price {
            font-size: 14px;
            font-weight: 800;
            color: var(--app-ink);
            margin-top: 8px;
        }

        .qty-stepper {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 34px;
            border-radius: 12px;
            border: 1.4px solid color-mix(in srgb, var(--primary) 45%, transparent);
            padding: 0 8px;
            flex-shrink: 0;
        }

        .qty-stepper button {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            border: none;
            background: color-mix(in srgb, var(--primary) 10%, white);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 14px;
        }

        .qty-stepper span {
            min-width: 16px;
            text-align: center;
            font-weight: 800;
            font-size: 14px;
            color: var(--app-ink);
        }

        .cart-bill-card {
            background: #fff;
            border: 1px solid var(--app-border);
            border-radius: var(--app-card-radius);
            padding: 18px;
            margin-top: 10px;
        }

        .cart-bill-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 700;
            color: var(--app-ink);
        }

        .cart-bill-note {
            font-size: 12px;
            font-weight: 600;
            color: var(--app-muted);
            margin-top: 10px;
        }

        .cart-empty {
            text-align: center;
            padding: 60px 20px;
        }

        .cart-empty i {
            font-size: 48px;
            color: var(--app-border);
            margin-bottom: 16px;
        }

        .cart-bottom-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1035;
            background: #fff;
            border-radius: 24px 24px 0 0;
            box-shadow: 0 -8px 24px rgba(17, 34, 58, 0.12);
            padding: 14px 18px calc(14px + env(safe-area-inset-bottom, 0px));
            display: flex;
            gap: 12px;
        }

        .cart-add-more-btn {
            flex: 1;
            height: 54px;
            border-radius: 16px;
            border: 1.5px solid color-mix(in srgb, var(--primary) 30%, transparent);
            background: color-mix(in srgb, var(--primary) 6%, white);
            color: var(--primary);
            font-weight: 800;
            font-size: 14px;
        }

        .cart-checkout-btn {
            flex: 2;
            height: 54px;
            border-radius: 16px;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-weight: 900;
            font-size: 14px;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 30%, transparent);
        }

        .toast-notification {
            position: fixed;
            bottom: 100px;
            right: 24px;
            background: var(--app-ink);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold fs-5" href="{{ route('home') }}">
            <i class="fas fa-utensils text-primary me-2"></i>{{ $appName ?? config('app.name') }}
        </a>
        <a href="{{ route('restaurant.show', $restaurant->slug) }}" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="fas fa-arrow-left me-2"></i>Back to Menu
        </a>
    </div>
</nav>

<div class="cart-container">
    <div class="cart-header">
        @if($restaurantLogoUrl)
            <img src="{{ $restaurantLogoUrl }}" alt="" class="cart-header-logo">
        @else
            <div class="cart-header-logo d-flex align-items-center justify-content-center"><i class="fas fa-store text-muted"></i></div>
        @endif
        <div>
            <div class="cart-header-name">{{ $restaurant->name }}</div>
            <div class="cart-header-sub">Your cart</div>
        </div>
    </div>

    <div id="cartItemsList"></div>

    <div id="cartBillCard" class="cart-bill-card" style="display:none;">
        <div class="cart-bill-row">
            <span>Item total</span>
            <span>{{ $currencySymbol }}<span id="cartSubtotal">0</span></span>
        </div>
        <div class="cart-bill-note">Taxes &amp; delivery fee calculated at checkout.</div>
    </div>

    <div id="cartEmptyState" class="cart-empty" style="display:none;">
        <i class="fas fa-bag-shopping"></i>
        <h5 class="fw-bold mb-2">Your cart is empty</h5>
        <p class="text-muted mb-3">Add items from the menu to get started.</p>
        <a href="{{ route('restaurant.show', $restaurant->slug) }}" class="btn btn-primary rounded-pill px-4">Browse Menu</a>
    </div>
</div>

<div id="cartBottomBar" class="cart-bottom-bar" style="display:none;">
    <button class="cart-add-more-btn" onclick="window.location.href='{{ route('restaurant.show', $restaurant->slug) }}'">
        Add more
    </button>
    <button class="cart-checkout-btn" onclick="proceedToCheckoutFromCart()">
        Checkout <i class="fas fa-arrow-right ms-1"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const restaurantId = @json($restaurant->id);
    const restaurantIsOpen = @json($restaurant->is_open);
    window.currencySymbol = '{{ $currencySymbol }}';
    window.currencyDecimals = {{ $currencyDecimals }};

    let cart = [];
    try {
        const savedCart = localStorage.getItem('cart_' + restaurantId);
        if (savedCart) cart = JSON.parse(savedCart);
    } catch (e) {
        cart = [];
    }

    function saveCart() {
        localStorage.setItem('cart_' + restaurantId, JSON.stringify(cart));
        renderCart();
        if (window.updateHeaderCartCount) window.updateHeaderCartCount();
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function changeQuantity(index, delta) {
        if (!cart[index]) return;
        cart[index].quantity = (cart[index].quantity || 0) + delta;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        saveCart();
    }

    function renderCart() {
        const listEl = document.getElementById('cartItemsList');
        const billEl = document.getElementById('cartBillCard');
        const emptyEl = document.getElementById('cartEmptyState');
        const barEl = document.getElementById('cartBottomBar');

        if (cart.length === 0) {
            listEl.innerHTML = '';
            billEl.style.display = 'none';
            barEl.style.display = 'none';
            emptyEl.style.display = 'block';
            return;
        }

        emptyEl.style.display = 'none';
        billEl.style.display = 'block';
        barEl.style.display = 'flex';

        var subtotal = 0;
        listEl.innerHTML = cart.map(function (item, index) {
            subtotal += (item.price || 0) * (item.quantity || 0);
            var variantHtml = item.selected_variant
                ? '<div class="cart-item-option">' + escapeHtml(item.selected_variant.name) + '</div>'
                : '';
            var extrasHtml = (item.selected_add_ons && item.selected_add_ons.length)
                ? '<div class="cart-item-option">+ ' + item.selected_add_ons.map(function (a) { return escapeHtml(a.name); }).join(', ') + '</div>'
                : '';
            return '<div class="cart-item-card">' +
                '<div class="cart-item-info">' +
                '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>' +
                variantHtml + extrasHtml +
                '<div class="cart-item-price">' + window.currencySymbol + (((item.price || 0) * (item.quantity || 0)).toFixed(window.currencyDecimals)) + '</div>' +
                '</div>' +
                '<div class="qty-stepper">' +
                '<button type="button" onclick="changeQuantity(' + index + ', -1)" aria-label="Decrease quantity"><i class="fas fa-minus"></i></button>' +
                '<span>' + item.quantity + '</span>' +
                '<button type="button" onclick="changeQuantity(' + index + ', 1)" aria-label="Increase quantity"><i class="fas fa-plus"></i></button>' +
                '</div>' +
                '</div>';
        }).join('');

        document.getElementById('cartSubtotal').innerText = subtotal.toFixed(window.currencyDecimals);
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.background = type === 'error' ? '#EF4444' : '#111827';
        toast.innerHTML = '<span>' + escapeHtml(message) + '</span>';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 2500);
    }

    function proceedToCheckoutFromCart() {
        if (!restaurantIsOpen) {
            showToast('Restaurant is currently closed.', 'error');
            return;
        }
        if (cart.length === 0) {
            showToast('Your cart is empty!', 'error');
            return;
        }

        var checkoutData = cart.map(function (item) {
            return {
                id: item.id,
                name: item.name,
                price: parseFloat(item.price),
                quantity: parseInt(item.quantity, 10),
                selected_variant: item.selected_variant || null,
                selected_add_ons: item.selected_add_ons || [],
                restaurant_id: restaurantId
            };
        });

        @guest
            showToast('Please login to continue', 'error');
            setTimeout(function () {
                window.location.href = "{{ route('login', ['redirect' => route('checkout.index')]) }}";
            }, 900);
            return;
        @endguest

        localStorage.setItem('checkout_cart', JSON.stringify(checkoutData));
        localStorage.setItem('checkout_restaurant_id', restaurantId);
        window.location.href = '/checkout?cart=' + encodeURIComponent(JSON.stringify(checkoutData));
    }

    renderCart();
</script>
</body>
</html>
