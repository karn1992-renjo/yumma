<?php

namespace Tests\Feature;

use App\Models\AppOpenVideo;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** An admin-uploaded video the restaurant app plays on opening: skippable, once per person. */
class AppOpenVideoTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::findOrCreate('restaurant_owner', 'web'));

        return $owner;
    }

    private function video(array $extra = []): AppOpenVideo
    {
        return AppOpenVideo::create(array_merge(['title' => 'Welcome', 'audience' => 'restaurant', 'video_path' => 'app-open-videos/a.mp4', 'is_active' => true], $extra));
    }

    public function test_the_app_gets_the_video_until_it_has_been_watched_or_skipped(): void
    {
        $video = $this->video(['skip_after_seconds' => 3]);
        Sanctum::actingAs($this->owner());

        $data = $this->getJson('/api/restaurant/app-video')->assertOk()->json('data');
        $this->assertSame($video->id, $data['id']);
        $this->assertSame($video->versionKey(), $data['key']);
        $this->assertSame(3, $data['skip_after_seconds']);
        $this->assertStringContainsString('app-open-videos/a.mp4', $data['url']);

        $this->postJson("/api/restaurant/app-video/{$video->id}/viewed", ['skipped' => true])->assertOk();
        $this->postJson("/api/restaurant/app-video/{$video->id}/viewed")->assertOk();           // duplicate call is harmless

        $this->assertNull($this->getJson('/api/restaurant/app-video')->json('data'));          // never again
        $this->assertSame(1, $video->views()->count());
    }

    public function test_each_person_sees_it_once_and_a_second_person_still_gets_it(): void
    {
        $video = $this->video();
        $first = $this->owner();
        Sanctum::actingAs($first);
        $this->postJson("/api/restaurant/app-video/{$video->id}/viewed")->assertOk();

        Sanctum::actingAs($this->owner());
        $this->assertSame($video->id, $this->getJson('/api/restaurant/app-video')->json('data.id'));
    }

    public function test_only_live_videos_inside_their_window_are_offered(): void
    {
        $this->video(['title' => 'Off', 'is_active' => false]);
        $this->video(['title' => 'Future', 'starts_at' => now()->addDay()]);
        $this->video(['title' => 'Past', 'ends_at' => now()->subDay()]);
        $other = $this->video(['title' => 'Customer', 'audience' => 'customer']);
        Sanctum::actingAs($this->owner());
        $this->assertNull($this->getJson('/api/restaurant/app-video')->json('data'));

        $now = $this->video(['title' => 'Now', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'sort_order' => 5]);
        $first = $this->video(['title' => 'First', 'sort_order' => 1]);
        $this->assertSame($first->id, $this->getJson('/api/restaurant/app-video')->json('data.id'));     // lowest order first
    }

    public function test_a_new_file_changes_the_key_and_replays_for_everyone(): void
    {
        $video = $this->video();
        $oldKey = $video->versionKey();
        $owner = $this->owner();
        Sanctum::actingAs($owner);
        $this->postJson("/api/restaurant/app-video/{$video->id}/viewed");
        $this->assertNull($this->getJson('/api/restaurant/app-video')->json('data'));

        $video->update(['video_path' => 'app-open-videos/b.mp4']);
        $video->views()->delete();                                                    // what the admin upload does

        $data = $this->getJson('/api/restaurant/app-video')->json('data');
        $this->assertNotSame($oldKey, $data['key']);
    }

    public function test_admin_can_upload_edit_reset_toggle_and_delete(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.app-open-videos.index'))->assertOk()->assertSee('App Open Video');
        $this->get(route('admin.app-open-videos.create'))->assertOk()->assertSee('Video file');

        $this->post(route('admin.app-open-videos.store'), ['title' => 'Intro', 'audience' => 'restaurant', 'video' => UploadedFile::fake()->create('intro.mp4', 2000, 'video/mp4'), 'skip_after_seconds' => 5, 'is_active' => 1])
            ->assertRedirect(route('admin.app-open-videos.index'));
        $video = AppOpenVideo::firstOrFail();
        $this->assertSame(['Intro', 'restaurant', 5, true], [$video->title, $video->audience, $video->skip_after_seconds, $video->is_active]);
        $this->assertTrue(Storage::disk('public')->exists($video->video_path));

        $this->post(route('admin.app-open-videos.store'), ['title' => 'Bad', 'audience' => 'restaurant', 'video' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('video');

        $viewer = $this->owner();
        $video->views()->create(['user_id' => $viewer->id, 'viewed_at' => now()]);
        $this->post(route('admin.app-open-videos.reset-views', $video))->assertSessionHas('success');
        $this->assertSame(0, $video->views()->count());

        $this->post(route('admin.app-open-videos.toggle-status', $video));
        $this->assertFalse($video->fresh()->is_active);

        $oldPath = $video->video_path;
        $video->views()->create(['user_id' => $viewer->id, 'viewed_at' => now()]);
        $this->put(route('admin.app-open-videos.update', $video), ['title' => 'Intro v2', 'audience' => 'customer', 'video' => UploadedFile::fake()->create('v2.mp4', 1000, 'video/mp4')])->assertRedirect();
        $video->refresh();
        $this->assertNotSame($oldPath, $video->video_path);
        $this->assertSame('customer', $video->audience);
        $this->assertSame(0, $video->views()->count());                          // a new file plays for everyone again

        $this->delete(route('admin.app-open-videos.destroy', $video));
        $this->assertSame(0, AppOpenVideo::count());
    }

    public function test_customers_get_their_own_video_even_as_guests_and_never_the_restaurant_one(): void
    {
        $this->video(['title' => 'For restaurants']);
        $this->assertNull($this->getJson('/api/customer/app-video')->assertOk()->json('data'));

        $mine = $this->video(['title' => 'For customers', 'audience' => 'customer', 'skip_after_seconds' => 2]);

        $guest = $this->getJson('/api/customer/app-video')->assertOk()->json('data');                  // no login needed
        $this->assertSame([$mine->id, 2], [$guest['id'], $guest['skip_after_seconds']]);
    }

    public function test_customers_get_it_on_every_open_even_after_watching(): void
    {
        $mine = $this->video(['audience' => 'customer']);
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->assertSame($mine->id, $this->getJson('/api/customer/app-video')->json('data.id'));
        $this->postJson("/api/customer/app-video/{$mine->id}/viewed", ['skipped' => true])->assertOk();
        $this->assertSame($mine->id, $this->getJson('/api/customer/app-video')->json('data.id'));
    }

    public function test_marking_viewed_needs_login(): void
    {
        $mine = $this->video(['audience' => 'customer']);

        $this->postJson("/api/customer/app-video/{$mine->id}/viewed")->assertUnauthorized();
    }
}
