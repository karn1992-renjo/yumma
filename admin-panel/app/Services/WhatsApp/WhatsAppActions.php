<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RestaurantController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Things a person can DO from WhatsApp. Each one runs the app's own controller action as that
 * (WhatsApp-number-verified) user, so every existing rule applies unchanged: cancellation
 * windows, refund policy, order limits, rider auto-assignment, suspension and gig checks.
 * Nothing here re-implements business logic.
 */
class WhatsAppActions
{
    /** @return array{success: bool, message: string} */
    public function cancelOrder(User $customer, Order $order, string $reason): array
    {
        return $this->run($customer, fn (Request $r) => app(OrderController::class)->cancel($r, $order->id), ['reason' => $reason]);
    }

    /** @return array{success: bool, message: string} */
    public function acceptOrder(User $owner, Order $order, ?int $prepMinutes = null): array
    {
        $payload = $prepMinutes ? ['preparation_time_minutes' => max(5, min(180, $prepMinutes))] : [];

        return $this->run($owner, fn (Request $r) => app(RestaurantController::class)->acceptOrder($r, $order->id), $payload);
    }

    /** @return array{success: bool, message: string} */
    public function rejectOrder(User $owner, Order $order, string $reason): array
    {
        return $this->run($owner, fn (Request $r) => app(RestaurantController::class)->rejectOrder($r, $order->id), ['reason' => $reason]);
    }

    /** Prices a cart exactly like the app's checkout summary (fees, taxes, coupon, Gold, minimum order). @return array{success: bool, message: string, data: array} */
    public function priceOrder(User $customer, array $payload): array
    {
        return $this->run($customer, fn (Request $r) => app(OrderController::class)->summary($r), $payload);
    }

    /** Places the order through the app's own checkout (online payments stay unpaid and unreleased until confirmed). @return array{success: bool, message: string, data: array} */
    public function placeOrder(User $customer, array $payload): array
    {
        return $this->run($customer, fn (Request $r) => app(OrderController::class)->store($r), $payload);
    }

    /** Starts an online payment for a cart (same code as the app's checkout). @return array{success: bool, message: string, data: array} */
    public function startCheckoutPayment(User $customer, array $payload): array
    {
        return $this->run($customer, fn (Request $r) => app(PaymentController::class)->createCheckoutPayment($r), $payload);
    }

    /** Verifies the payment proof and creates the order (same code as the app's checkout). @return array{success: bool, message: string, data: array} */
    public function completeCheckoutPayment(User $customer, array $payload): array
    {
        return $this->run($customer, fn (Request $r) => app(PaymentController::class)->verifyCheckoutPayment($r), $payload);
    }

    /** Sends the login / sign-up SMS code through the app's own OTP login. @return array{success: bool, message: string, data: array, code: ?string} */
    public function sendLoginOtp(string $phone, string $flow): array
    {
        return $this->run(null, fn (Request $r) => app(AuthController::class)->sendOtp($r), ['phone' => $phone, 'flow' => $flow, 'role' => 'customer']);
    }

    /** @return array{success: bool, message: string, data: array, code: ?string} */
    public function verifyLoginOtp(string $phone, string $otp, string $flow): array
    {
        return $this->run(null, fn (Request $r) => app(AuthController::class)->verifyOtp($r), ['phone' => $phone, 'otp' => $otp, 'flow' => $flow, 'role' => 'customer']);
    }

    /** Creates the customer account (needs the token a verified sign-up code returns). @return array{success: bool, message: string, data: array, code: ?string} */
    public function registerCustomer(string $name, string $phone, string $verifiedToken): array
    {
        return $this->run(null, fn (Request $r) => app(AuthController::class)->register($r), [
            'name' => $name, 'phone' => $phone, 'verified_phone_token' => $verifiedToken, 'role' => 'customer',
        ]);
    }

    /** @return array{success: bool, message: string} */
    public function setDriverOnline(User $driver, bool $online): array
    {
        return $this->run($driver, fn (Request $r) => app(DriverController::class)->toggleStatus($r), ['is_online' => $online]);
    }

    /**
     * Runs a controller action as $user and returns its JSON answer. The previous
     * identity (usually none, in a webhook) is restored afterwards.
     *
     * @return array{success: bool, message: string, data: array}
     */
    private function run(?User $user, callable $action, array $payload): array
    {
        $guard = Auth::guard();
        $previous = $guard->user();
        $originalRequest = app('request');
        try {
            // A null user runs the action as a guest (the login / sign-up steps).
            $user ? $guard->setUser($user) : $guard->forgetUser();
            $request = Request::create('/whatsapp-action', 'POST', $payload);
            $request->setUserResolver(fn () => $user);
            app()->instance('request', $request);

            $response = $action($request);
            $data = $response instanceof JsonResponse ? $response->getData(true) : [];

            $message = (string) ($data['message'] ?? (($data['success'] ?? false) ? 'Done.' : 'That could not be completed.'));
            if (! ($data['success'] ?? false) && ! empty($data['errors']) && is_array($data['errors'])) {
                $message = (string) (collect($data['errors'])->flatten()->first() ?: $message);
            }

            return [
                'success' => (bool) ($data['success'] ?? false),
                'message' => $message,
                'data' => is_array($data['data'] ?? null) ? $data['data'] : [],
                'code' => $data['code'] ?? null,
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ['success' => false, 'message' => (string) collect($e->errors())->flatten()->first(), 'data' => [], 'code' => null];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['success' => false, 'message' => $e->getMessage() ?: 'That could not be completed.', 'data' => [], 'code' => null];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp action failed.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Something went wrong. Please try in the app.', 'data' => [], 'code' => null];
        } finally {
            app()->instance('request', $originalRequest);
            $previous ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }
}
