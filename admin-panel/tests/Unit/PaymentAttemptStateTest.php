<?php

namespace Tests\Unit;

use App\Models\PaymentAttempt;
use Tests\TestCase;

class PaymentAttemptStateTest extends TestCase
{
    public function test_pending_attempt_with_future_expiry_is_open(): void
    {
        $attempt = new PaymentAttempt([
            'status' => PaymentAttempt::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($attempt->isOpen());
        $this->assertFalse($attempt->isExpired());
    }

    public function test_success_attempt_is_not_open(): void
    {
        $attempt = new PaymentAttempt([
            'status' => PaymentAttempt::STATUS_SUCCESS,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertFalse($attempt->isOpen());
        $this->assertFalse($attempt->isExpired());
    }

    public function test_past_expiry_is_expired(): void
    {
        $attempt = new PaymentAttempt([
            'status' => PaymentAttempt::STATUS_ACTIVE,
            'expires_at' => now()->subSecond(),
        ]);

        $this->assertTrue($attempt->isOpen());
        $this->assertTrue($attempt->isExpired());
    }
}
