<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoriesPlanFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);
    }

    public function test_the_stories_form_creates_a_pay_as_you_go_plan_with_the_fixed_fields(): void
    {
        $this->admin();
        $this->get(route('admin.subscription-plans.index', ['audience' => 'restaurant']))->assertOk()->assertSee('New Stories Plan');
        $this->get(route('admin.subscription-plans.stories.create'))->assertOk()->assertSee('Stories plan (pay as you go)')->assertSee('What restaurants will see');

        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Stories Monthly', 'duration_days' => 30, 'price' => 299, 'compare_at_price' => 399, 'description' => 'Post Stories', 'is_active' => 1])
            ->assertRedirect(route('admin.subscription-plans.index', ['audience' => 'restaurant']));

        $plan = SubscriptionPlan::firstWhere('name', 'Stories Monthly');
        $this->assertSame('restaurant', $plan->audience);
        $this->assertSame('stories_addon', $plan->scope);
        $this->assertSame(30, $plan->validity_days);
        $this->assertTrue((bool) $plan->includes_stories);
        $this->assertTrue($plan->is_active);
        $this->assertNull($plan->order_limit);
        $this->assertSame(0, (int) $plan->trial_days);
        $this->assertSame('stories-monthly', $plan->slug);
    }

    public function test_edit_updates_it_and_the_normal_edit_link_redirects_to_the_stories_form(): void
    {
        $this->admin();
        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Stories 7 days', 'duration_days' => 7, 'price' => 99]);
        $plan = SubscriptionPlan::firstOrFail();
        $this->assertFalse($plan->is_active);                                    // the switch was off

        $this->get(route('admin.subscription-plans.edit', $plan))->assertRedirect(route('admin.subscription-plans.stories.edit', $plan));
        $this->get(route('admin.subscription-plans.stories.edit', $plan))->assertOk()->assertSee('Stories 7 days');

        $this->put(route('admin.subscription-plans.stories.update', $plan), ['name' => 'Stories Weekly', 'duration_days' => 14, 'price' => 149, 'is_active' => 1])->assertRedirect();
        $plan->refresh();
        $this->assertSame(['Stories Weekly', 14, true], [$plan->name, $plan->validity_days, $plan->is_active]);
    }

    public function test_the_form_validates_and_only_edits_stories_plans(): void
    {
        $this->admin();
        $this->post(route('admin.subscription-plans.stories.store'), ['name' => '', 'duration_days' => 0, 'price' => -1])->assertSessionHasErrors(['name', 'duration_days', 'price']);
        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'X', 'duration_days' => 30, 'price' => 100, 'compare_at_price' => 50])->assertSessionHasErrors('compare_at_price');

        $full = SubscriptionPlan::create(['audience' => 'restaurant', 'scope' => 'full_plan', 'name' => 'Pro', 'slug' => 'pro', 'billing_period' => 'monthly', 'price' => 999, 'currency' => 'INR']);
        $this->get(route('admin.subscription-plans.stories.edit', $full))->assertNotFound();
    }
}
