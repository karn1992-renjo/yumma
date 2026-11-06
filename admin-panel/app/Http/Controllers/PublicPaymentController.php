<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\WhatsAppActions;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\GatewayRegistry;
use App\Support\PublicPayment;
use App\Support\PublicTracking;
use Illuminate\Http\Request;

/**
 * Pay for a cart started in WhatsApp. Follows the app's own checkout: the gateway payment is
 * created and verified by the same code the mobile app uses, and the order is created with the
 * payment proof (so a payment never leaves a half-made order, and pricing/coupons/rules are identical).
 */
class PublicPaymentController extends Controller
{
    public function __construct(private WhatsAppActions $actions)
    {
    }

    public function show(string $token)
    {
        $session = PublicPayment::session($token);
        $gateway = GatewayRegistry::availableCustomerPaymentGateway();

        return response()->view('pay.public', [
            'expired' => $session === null,
            'restaurantName' => $session['restaurant'] ?? '',
            'appName' => AppSetting::getValue('app_name', config('app.name', 'Swado')),
            'brand' => AppSetting::getValue('primary_color', '#F97316'),
            'symbol' => AppSetting::sanitizedCurrencySymbol(),
            'amount' => number_format((float) ($session['amount'] ?? 0), AppSetting::currencyDecimals()),
            'gateway' => $gateway,
            'startUrl' => PublicPayment::startUrl($token),
            'completeUrl' => PublicPayment::completeUrl($token),
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    /** Creates the gateway payment (same code as the app) and returns what the checkout window needs. */
    public function start(string $token)
    {
        $session = PublicPayment::session($token);
        $user = $session ? User::find($session['user_id']) : null;
        if (! $session || ! $user) {
            return response()->json(['success' => false, 'message' => 'This payment link has expired. Please start again on WhatsApp.'], 410);
        }
        $gateway = GatewayRegistry::availableCustomerPaymentGateway();
        if (! $gateway) {
            return response()->json(['success' => false, 'message' => 'Online payment is not available right now.'], 422);
        }

        $result = $this->actions->startCheckoutPayment($user, array_merge($session['order_payload'], ['payment_method' => $gateway]));
        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        PublicPayment::update($token, ['checkout_token' => $result['data']['checkout_payment_token'] ?? null, 'gateway' => $gateway]);

        return response()->json(['success' => true, 'data' => $result['data'] + ['gateway' => $gateway]]);
    }

    /** Called after the customer paid: verifies the payment and creates the order. */
    public function complete(Request $request, string $token)
    {
        $session = PublicPayment::session($token);
        $user = $session ? User::find($session['user_id']) : null;
        if (! $session || ! $user || empty($session['checkout_token'])) {
            return response()->json(['success' => false, 'message' => 'This payment session has expired. If money was deducted it will be reviewed and refunded; contact support on WhatsApp.'], 410);
        }

        $proof = $request->validate([
            'payment_id' => ['nullable', 'string', 'max:200'],
            'razorpay_order_id' => ['nullable', 'string', 'max:200'],
            'razorpay_signature' => ['nullable', 'string', 'max:500'],
        ]);

        WhatsAppNotifier::$quietOrderPlaced = true;   // the confirmation below is sent right in the chat
        try {
            $result = $this->actions->completeCheckoutPayment($user, array_filter($proof + [
                'checkout_payment_token' => $session['checkout_token'], 'payment_method' => $session['gateway'] ?? 'razorpay',
            ], fn ($v) => $v !== null));
        } finally {
            WhatsAppNotifier::$quietOrderPlaced = false;
        }

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $orderId = $result['data']['order']['id'] ?? null;
        $order = Order::find($orderId) ?: Order::where('order_number', $result['data']['order_number'] ?? '')->first();
        PublicPayment::forget($token);

        $tracking = $order ? PublicTracking::url($order) : null;
        $this->confirmInChat($session, $user, $order, $tracking);

        return response()->json([
            'success' => true,
            'order_number' => $order?->order_number ?? ($result['data']['order_number'] ?? null),
            'tracking_url' => $tracking,
        ]);
    }

    /** Tells the customer on WhatsApp, and empties their saved cart. */
    private function confirmInChat(array $session, User $user, ?Order $order, ?string $tracking): void
    {
        try {
            $contact = WhatsAppContact::where('phone', $session['contact_phone'])->first();
            if ($contact) {
                $context = $contact->bot_context ?? [];
                unset($context['cart']);
                $contact->forceFill(['bot_state' => null, 'bot_context' => $context ?: null])->save();
            }
            if ($order && $contact) {
                $symbol = AppSetting::sanitizedCurrencySymbol();
                app(WhatsAppService::class)->sendText($contact->phone,
                    "✅ Payment received. Order #{$order->order_number} is confirmed! ({$symbol}" . number_format((float) $order->total, AppSetting::currencyDecimals()) . ")\n"
                    . "{$session['restaurant']} has been notified.\n\n📍 Track live: {$tracking}\nReply RIDER for your rider's location.", 'bot');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WhatsApp payment confirmation message failed.', ['error' => $e->getMessage()]);
        }
    }
}
