<?php

namespace Tests\Feature;

use App\Mail\GoldMembershipWelcomeMail;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GoldSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GoldMembershipWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_gold_emails_the_customer_a_branded_welcome(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Riya Sharma', 'email' => 'riya@example.com']);
        $plan = SubscriptionPlan::create([
            'audience' => 'customer', 'scope' => 'full_plan', 'name' => 'Gold', 'slug' => 'gold-test',
            'billing_period' => 'monthly', 'validity_days' => 30, 'price' => 99, 'currency' => 'INR', 'is_active' => true,
        ]);

        app(GoldSubscriptionService::class)->grantManually($user, $plan, 'Admin');

        $sent = null;
        Mail::assertSent(GoldMembershipWelcomeMail::class, function ($m) use (&$sent) {
            $sent = $m;

            return $m->hasTo('riya@example.com');
        });

        $html = $sent->render();
        $this->assertStringContainsString('Welcome to Gold, Riya!', $html);
        $this->assertStringContainsString('What you get with Gold', $html);
        $this->assertStringContainsString('Free delivery', $html);
        $this->assertStringContainsString('Valid till', $html);
        $this->assertStringContainsString('Granted by', $html);
    }

    public function test_no_email_is_attempted_without_a_valid_address(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'not-an-email']);
        $plan = SubscriptionPlan::create([
            'audience' => 'customer', 'scope' => 'full_plan', 'name' => 'Gold', 'slug' => 'gold-test2',
            'billing_period' => 'monthly', 'validity_days' => 30, 'price' => 99, 'currency' => 'INR', 'is_active' => true,
        ]);

        app(GoldSubscriptionService::class)->grantManually($user, $plan, 'Admin');

        Mail::assertNothingSent();
    }
}
