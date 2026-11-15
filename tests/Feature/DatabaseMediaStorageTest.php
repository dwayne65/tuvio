<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.public.driver' => 'database']);
        Storage::forgetDisk('public');
    }

    private function organizer(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);

        return $user;
    }

    private function createEventWithCover(User $user, UploadedFile $cover): Event
    {
        $this->actingAs($user)->post('/dashboard/events', [
            'title' => 'Kampala Jazz Night',
            'type' => 'physical',
            'visibility' => 'public',
            'start_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'venue_name' => 'Serena Hotel',
            'city' => 'Kampala',
            'ticket_types' => [['name' => 'Regular', 'kind' => 'paid', 'price_minor' => 5000000, 'quantity_total' => 100, 'max_per_order' => 5]],
            'cover' => $cover,
        ])->assertSessionHasNoErrors();

        return Event::firstOrFail();
    }

    public function test_an_uploaded_cover_is_kept_in_the_database_and_served_from_media(): void
    {
        $cover = UploadedFile::fake()->image('cover.jpg', 640, 360);
        $bytes = file_get_contents($cover->getRealPath());

        $event = $this->createEventWithCover($this->organizer(), $cover);

        $this->assertStringStartsWith('/media/event-covers/', $event->cover_url);
        $this->assertSame($bytes, Storage::disk('public')->get($event->cover_image_path));

        $response = $this->get($event->cover_url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame($bytes, $response->getContent());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_replacing_a_cover_removes_the_old_image(): void
    {
        $user = $this->organizer();
        $event = $this->createEventWithCover($user, UploadedFile::fake()->image('first.jpg'));
        $oldUrl = $event->cover_url;

        $this->actingAs($user)->post("/dashboard/events/{$event->slug}/cover", [
            'cover' => UploadedFile::fake()->image('second.png'),
        ])->assertSessionHasNoErrors();

        $this->get($oldUrl)->assertNotFound();
        $this->get($event->fresh()->cover_url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(1, DB::table('stored_files')->count());
    }

    public function test_an_unknown_media_path_is_not_found(): void
    {
        $this->get('/media/event-covers/missing.jpg')->assertNotFound();
    }
}
