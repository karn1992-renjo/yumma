<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\OrderController as ApiOrderController;
use App\Http\Controllers\Controller;
use App\Models\PaymentRecoveryQueue;
use App\Services\PaymentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolves rows written by PaymentController::createOrderFromVerifiedCheckout()
 * when a payment was confirmed charged by the gateway but order creation
 * failed even after its own retries. Each row holds the exact payload
 * needed to replay order creation; this controller re-verifies the payment
 * is still genuinely paid (never trusts the stored row alone) and then
 * replays creation through the same Api\OrderController::store() path a
 * normal checkout uses.
 */
class PaymentRecoveryController extends Controller
{
    public function index(Request $request)
    {
        $rows = PaymentRecoveryQueue::with('user', 'resolvedOrder', 'resolvedBy')
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payment-recovery.index', ['rows' => $rows]);
    }

    public function resolve(Request $request, PaymentRecoveryQueue $paymentRecoveryQueue, PaymentVerificationService $verification)
    {
        $row = $paymentRecoveryQueue;

        if ($row->status === 'resolved') {
            return back()->with('error', 'This entry has already been resolved.');
        }

        $result = $verification->check($row->payment_method, (string) $row->payment_id, (string) $row->gateway_reference);

        if (! $result['paid']) {
            $row->status = 'not_paid';
            $row->last_error = $result['message'];
            $row->save();

            return back()->with('error', 'Gateway says this payment is not paid: ' . $result['message']);
        }

        if (! $row->user) {
            $row->status = 'failed';
            $row->last_error = 'The customer this payment belongs to (user #' . $row->user_id . ') no longer exists.';
            $row->save();

            return back()->with('error', $row->last_error);
        }

        $payload = $row->checkout_payload ?? [];

        $orderRequest = Request::create('/api/v1/orders', 'POST', $payload);
        $orderRequest->setUserResolver(fn () => $row->user);
        $orderRequest->attributes->set('confirmed_online_payment', [
            'payment_method' => $row->payment_method,
            'payment_id' => $row->payment_id,
            'gateway_reference' => $row->gateway_reference,
            'payload' => $row->verification_payload ?? [],
        ]);

        DB::reconnect();

        // OrderController::store() reads the acting customer via the global
        // auth()->id() helper, not $request->user() -- so the admin
        // triggering this resolve action must be swapped out for the
        // original paying customer for the duration of this call, or the
        // replayed order would be created under the ADMIN's account
        // instead of the customer who actually paid. setUser() (unlike
        // login()) only affects this request's in-memory auth state, never
        // touches the session, so the admin's own session is untouched.
        $admin = Auth::guard('web')->user();
        Auth::guard('web')->setUser($row->user);

        try {
            $response = app(ApiOrderController::class)->store($orderRequest);
        } catch (\Throwable $e) {
            Auth::guard('web')->setUser($admin);
            Log::error('Payment recovery replay threw.', ['id' => $row->id, 'error' => $e->getMessage()]);

            $row->status = 'failed';
            $row->last_error = $e->getMessage();
            $row->save();

            return back()->with('error', 'Order creation threw an error: ' . $e->getMessage());
        }

        Auth::guard('web')->setUser($admin);

        $body = json_decode($response->getContent(), true) ?? [];

        if ($response->getStatusCode() >= 300 || empty($body['success'])) {
            $row->status = 'failed';
            $row->last_error = $response->getContent();
            $row->save();

            return back()->with('error', 'Order creation failed: ' . ($body['message'] ?? $response->getContent()));
        }

        $orderId = $body['data']['order']['id'] ?? null;

        $row->status = 'resolved';
        $row->resolved_order_id = $orderId;
        $row->resolved_at = now();
        $row->resolved_by_user_id = $admin?->id;
        $row->last_error = null;
        $row->save();

        activity()
            ->performedOn($row)
            ->causedBy($admin)
            ->withProperties(['resolved_order_id' => $orderId])
            ->log('Payment recovery entry resolved into order #' . $orderId);

        return back()->with('success', 'Order #' . $orderId . ' created from this recovered payment.');
    }
}
