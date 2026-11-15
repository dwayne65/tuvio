<?php

namespace Tests\Feature;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CronEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-cron-token-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ScheduledTaskStarting::class]);
    }

    public function test_the_right_token_runs_the_scheduler(): void
    {
        config(['ticketflow.cron_token' => self::TOKEN]);

        $this->withToken(self::TOKEN)->post(route('internal.cron'))->assertOk();

        Event::assertDispatched(ScheduledTaskStarting::class, fn ($event) => str_contains($event->task->command, 'orders:release-expired'));
    }

    #[TestWith([null])]
    #[TestWith(['wrong-token'])]
    public function test_a_missing_or_wrong_token_is_refused(?string $token): void
    {
        config(['ticketflow.cron_token' => self::TOKEN]);

        $request = $token === null ? $this : $this->withToken($token);

        $request->post(route('internal.cron'))->assertForbidden();

        Event::assertNotDispatched(ScheduledTaskStarting::class);
    }

    public function test_the_endpoint_is_off_when_no_token_is_configured(): void
    {
        config(['ticketflow.cron_token' => null]);

        $this->withToken('')->post(route('internal.cron'))->assertForbidden();

        Event::assertNotDispatched(ScheduledTaskStarting::class);
    }
}
