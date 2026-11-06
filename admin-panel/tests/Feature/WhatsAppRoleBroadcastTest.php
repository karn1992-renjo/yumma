<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Broadcasts can target people by their role. */
class WhatsAppRoleBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $name, ?string $phone): User
    {
        $u = User::factory()->create(['name' => $name, 'phone' => $phone]);
        $u->assignRole(Role::findOrCreate($role, 'web'));

        return $u;
    }

    private function recipients(string $audience): array
    {
        $b = WhatsAppBroadcast::create(['title' => 'T', 'template' => 'tpl', 'params' => [], 'audience' => $audience]);
        app(WhatsAppBroadcastService::class)->start($b);

        return $b->recipients()->pluck('first_name', 'phone')->all();
    }

    public function test_each_role_audience_reaches_only_that_role(): void
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        $this->user('customer', 'Asha Verma', '9876543210');
        $this->user('restaurant_owner', 'Ravi Kumar', '9811111111');
        $this->user('restaurant_staff', 'Sita Devi', '9822222222');
        $this->user('delivery_partner', 'Dev Singh', '9833333333');
        $this->user('branch_manager', 'Mira Shah', '9844444444');
        $this->user('support', 'Tara Roy', '9855555555');
        $this->user('customer', 'No Phone', null);

        $this->assertSame(['+919876543210' => 'Asha'], $this->recipients('role_customers'));
        $this->assertSame(['+919811111111' => 'Ravi', '+919822222222' => 'Sita'], $this->recipients('role_restaurants'));
        $this->assertSame(['+919833333333' => 'Dev'], $this->recipients('role_drivers'));
        $this->assertSame(['+919844444444' => 'Mira'], $this->recipients('role_branches'));
        $this->assertSame(['+919855555555' => 'Tara'], $this->recipients('role_staff'));
        $this->assertSame([], $this->recipients('role_franchise'));
    }

    public function test_the_broadcast_form_lists_the_role_audiences(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()->assertSee('Role: delivery partners (drivers)')->assertSee('Role: restaurant owners and staff');
    }
}
