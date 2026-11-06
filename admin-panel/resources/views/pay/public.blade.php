<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Pay · {{ $appName }}</title>
    <style>
        :root { --brand: {{ $brand }}; --bg: #f6f7fb; --card: #fff; --ink: #1c2434; --muted: #6b7280; --line: #e5e7eb; --ok: #16a34a; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f1420; --card: #181f2e; --ink: #eef1f7; --muted: #9aa3b5; --line: #2a3347; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--ink); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .card { width: 100%; max-width: 420px; background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.08); text-align: center; }
        .brand { font-weight: 800; font-size: 18px; margin-bottom: 4px; }
        .muted { color: var(--muted); font-size: 14px; }
        .amount { font-size: 38px; font-weight: 900; margin: 14px 0 2px; }
        button { width: 100%; padding: 15px; border: 0; border-radius: 14px; background: var(--brand); color: #fff; font-size: 17px; font-weight: 800; cursor: pointer; margin-top: 18px; }
        button[disabled] { opacity: .6; cursor: default; }
        .msg { margin-top: 14px; font-size: 14px; min-height: 20px; }
        .err { color: #b91c1c; } .okc { color: var(--ok); font-weight: 700; }
        a.btn { display: inline-block; margin-top: 14px; padding: 12px 22px; border-radius: 12px; background: var(--ok); color: #fff; text-decoration: none; font-weight: 700; }
        .lock { margin-top: 14px; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
<div class="card" id="card">
    <div class="brand">{{ $appName }}</div>
    @if($expired)
        <div class="amount">⌛</div>
        <div class="muted">This payment link has expired. Please go back to WhatsApp and checkout again; your cart is saved.</div>
    @elseif(! $gateway)
        <div class="amount">⚠️</div>
        <div class="muted">Online payment is not available right now. Please reply on WhatsApp to pay another way.</div>
    @else
        <div class="muted">Your order from {{ $restaurantName }}</div>
        <div class="amount">{{ $symbol }}{{ $amount }}</div>
        <div class="muted">Secure payment by {{ ucfirst($gateway) }}. Your order is placed as soon as the payment succeeds.</div>
        <button id="payBtn">Pay now</button>
        <div class="msg" id="msg"></div>
        <div class="lock">🔒 Your card or UPI details go straight to the payment provider and are never shared with us.</div>
    @endif
</div>

@if(! $expired && $gateway)
    <script>
        const START_URL = @json($startUrl);
        const COMPLETE_URL = @json($completeUrl);
        const BRAND = @json($brand);
        const APP = @json($appName);
    </script>
    @verbatim
    <script>
        const btn = document.getElementById('payBtn'), msg = document.getElementById('msg'), card = document.getElementById('card');
        const say = (t, cls = '') => { msg.className = 'msg ' + cls; msg.textContent = t; };
        const load = src => new Promise((res, rej) => { const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
        let completing = false;

        // After the customer has paid: verify and create the order. Retried, because the money has already moved.
        async function complete(proof) {
            if (completing) return; completing = true; btn.disabled = true;
            say('Payment received. Placing your order…');
            for (let i = 0; i < 6; i++) {
                try {
                    const r = await fetch(COMPLETE_URL, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(proof) });
                    const j = await r.json();
                    if (r.ok && j.success) {
                        card.innerHTML = `<div class="brand">${APP}</div><div class="amount">✅</div><div class="okc">Payment received</div>
                            <div class="muted" style="margin-top:6px">Order #${j.order_number} is confirmed. We have also messaged you on WhatsApp.</div>
                            ${j.tracking_url ? `<a class="btn" href="${j.tracking_url}">Track my order</a>` : ''}`;
                        return;
                    }
                    if (r.status === 410 || r.status === 422) { say(j.message || 'Could not confirm the order.', 'err'); break; }
                } catch (e) {}
                await new Promise(r => setTimeout(r, 2500 * (i + 1)));
            }
            completing = false; btn.disabled = false;
            say('We could not confirm your order yet. If money was deducted, do not pay again: it will be checked and refunded or the order confirmed. Contact us on WhatsApp.', 'err');
        }

        btn.addEventListener('click', async () => {
            btn.disabled = true; say('Starting secure payment…');
            let data;
            try {
                const r = await fetch(START_URL, { method: 'POST', headers: { Accept: 'application/json' } });
                const j = await r.json();
                if (!r.ok || !j.success) throw new Error(j.message || 'Could not start the payment.');
                data = j.data;
            } catch (e) { say(e.message, 'err'); btn.disabled = false; return; }

            try {
                if (data.gateway === 'razorpay') {
                    await load('https://checkout.razorpay.com/v1/checkout.js');
                    new Razorpay({
                        key: data.key, amount: data.amount_minor, currency: data.currency, order_id: data.order_id,
                        name: APP, description: 'Food order', prefill: data.prefill || { contact: data.phone || '' },
                        theme: { color: BRAND },
                        handler: resp => complete({ payment_id: resp.razorpay_payment_id, razorpay_order_id: resp.razorpay_order_id, razorpay_signature: resp.razorpay_signature }),
                        modal: { ondismiss: () => { if (!completing) { btn.disabled = false; say('Payment was not completed. You can try again.'); } } },
                    }).open();
                    say('');
                } else if (data.gateway === 'cashfree') {
                    await load('https://sdk.cashfree.com/js/v3/cashfree.js');
                    const cf = Cashfree({ mode: data.environment === 'production' ? 'production' : 'sandbox' });
                    cf.checkout({ paymentSessionId: data.payment_session_id, redirectTarget: '_modal' }).then(res => {
                        if (res && res.error) { btn.disabled = false; say(res.error.message || 'Payment was not completed. You can try again.', 'err'); return; }
                        complete({ payment_id: data.order_id });
                    });
                    say('');
                } else {
                    say('This payment method cannot be opened on this page. Please reply on WhatsApp.', 'err'); btn.disabled = false;
                }
            } catch (e) { say('Could not open the payment window. Please check your connection and try again.', 'err'); btn.disabled = false; }
        });
    </script>
    @endverbatim
@endif
</body>
</html>
