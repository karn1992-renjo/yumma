<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppSidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_menu_with_submenu_is_in_the_admin_sidebar_on_every_whatsapp_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        foreach (['inbox', 'broadcasts', 'reports', 'messages', 'settings'] as $page) {
            $html = $this->get(route("admin.whatsapp.{$page}"))->assertOk()->getContent();
            $this->assertStringContainsString('fa-whatsapp', $html, $page);
            foreach (['Inbox', 'Broadcasts', 'Reports', 'Message Log'] as $label) {
                $this->assertStringContainsString($label, $html, "{$page} lacks {$label}");
            }
            $this->assertStringContainsString(route('admin.whatsapp.broadcasts'), $html);
        }

    }
}
