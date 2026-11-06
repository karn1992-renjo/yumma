<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\PaymentAttempt;
use App\Payments\RazorpayGateway;
use App\Support\RazorpayCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** A charged-but-"authorized" Razorpay payment must be captured, not shown as failed. */
class RazorpayAuthorizedCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('razorpay_key', 'rzp_test_key');
        AppSetting::setValue('razorpay_secret', 'secret');
    }

    public function test_an_authorized_payment_with_the_expected_amount_is_captured(): void
    {
        Http::fake(['api.razorpay.com/v1/payments/pay_1/capture' => Http::response(['id' => 'pay_1', 'status' => 'captured', 'amount' => 25000])]);

        $out = RazorpayCapture::ensureCaptured(['id' => 'pay_1', 'status' => 'authorized', 'amount' => 25000, 'currency' => 'INR'], 'k', 's', 25000);

        $this->assertSame('captured', $out['status']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_1/capture') && $r['amount'] === 25000 && $r['currency'] === 'INR');
    }

    public function test_a_different_amount_is_never_captured_and_captured_payments_are_left_alone(): void
    {
        Http::fake();

        $wrong = RazorpayCapture::ensureCaptured(['id' => 'pay_2', 'status' => 'authorized', 'amount' => 99900], 'k', 's', 25000);
        $done = RazorpayCapture::ensureCaptured(['id' => 'pay_3', 'status' => 'captured', 'amount' => 25000], 'k', 's', 25000);

        $this->assertSame('authorized', $wrong['status']);
        $this->assertSame('captured', $done['status']);
        Http::assertNothingSent();
    }

    public function test_a_refused_capture_falls_back_to_the_latest_state(): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/pay_4/capture' => Http::response(['error' => ['description' => 'This payment has already been captured']], 400),
            'api.razorpay.com/v1/payments/pay_4' => Http::response(['id' => 'pay_4', 'status' => 'captured', 'amount' => 25000]),
        ]);

        $out = RazorpayCapture::ensureCaptured(['id' => 'pay_4', 'status' => 'authorized', 'amount' => 25000], 'k', 's', 25000);

        $this->assertSame('captured', $out['status']);
    }

    public function test_the_driver_qr_poll_returns_the_payment_once_an_authorized_one_is_captured(): void
    {
        $attempt = new PaymentAttempt([
            'order_id' => 1, 'gateway' => 'razorpay', 'source' => PaymentAttempt::SOURCE_DRIVER_QR, 'status' => 'active', 'amount' => 261.98,
            'currency' => 'INR', 'gateway_reference' => 'qr_abc',
        ]);
        $attempt->id = 7;
        Http::fake([
            'api.razorpay.com/v1/payments/qr_codes/qr_abc/payments*' => Http::response(['items' => [['id' => 'pay_qr', 'status' => 'authorized', 'amount' => 26198, 'currency' => 'INR']]]),
            'api.razorpay.com/v1/payments/pay_qr/capture' => Http::response(['id' => 'pay_qr', 'status' => 'captured', 'amount' => 26198]),
        ]);

        $event = app(RazorpayGateway::class)->fetchPaymentForAttempt($attempt);

        $this->assertSame('pay_qr', $event['transaction_id']);
        $this->assertSame('payment.captured', $event['event']);
        $this->assertSame(261.98, $event['amount']);
    }
}
