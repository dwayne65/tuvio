<?php

namespace Tests\Feature;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function organizerWithOrg(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);

        return [$user, Organization::first()];
    }

    private function plan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'Professional', 'slug' => 'professional-'.uniqid(),
            'price_minor' => 15000000, 'currency' => 'UGX', 'interval' => 'monthly',
            'trial_days' => 14, 'event_limit' => 25, 'is_active' => true, 'is_public' => true, 'sort' => 2,
        ], $overrides));
    }

    public function test_free_plan_activates_immediately(): void
    {
        [, $org] = $this->organizerWithOrg();
        $free = $this->plan(['price_minor' => 0, 'trial_days' => 0, 'event_limit' => 3]);

        app(SubscriptionService::class)->subscribe($org, $free);

        $sub = $org->refresh()->activeSubscription();
        $this->assertNotNull($sub);
        $this->assertSame('active', $sub->status);
        $this->assertTrue($org->hasActiveSubscription());
    }

    public function test_paid_plan_with_trial_starts_trialing(): void
    {
        [, $org] = $this->organizerWithOrg();
        $pro = $this->plan();

        $sub = app(SubscriptionService::class)->subscribe($org, $pro);

        $this->assertSame('trialing', $sub->status);
        $this->assertNotNull($sub->trial_ends_at);
        $this->assertDatabaseHas('subscription_invoices', ['subscription_id' => $sub->id]);
        $this->assertTrue($org->refresh()->hasActiveSubscription());
    }

    public function test_publish_gate_blocks_without_subscription_and_respects_limit(): void
    {
        [, $org] = $this->organizerWithOrg();
        $svc = app(SubscriptionService::class);

        $this->assertFalse($svc->canPublishEvents($org));

        $svc->subscribe($org, $this->plan(['price_minor' => 0, 'trial_days' => 0, 'event_limit' => 1]));
        $this->assertTrue($svc->canPublishEvents($org->refresh()));
    }

    public function test_cancel_and_resume(): void
    {
        [$user, $org] = $this->organizerWithOrg();
        $svc = app(SubscriptionService::class);
        $svc->subscribe($org, $this->plan());

        $this->actingAs($user)->post('/dashboard/subscription/cancel')->assertRedirect();
        $this->assertTrue($org->subscription()->latest()->first()->cancel_at_period_end);

        $this->actingAs($user)->post('/dashboard/subscription/resume')->assertRedirect();
        $this->assertFalse($org->subscription()->latest()->first()->cancel_at_period_end);
    }

    public function test_subscription_page_renders(): void
    {
        [$user] = $this->organizerWithOrg();
        $this->plan(['slug' => 'starter-x', 'price_minor' => 0, 'trial_days' => 0]);

        $this->actingAs($user)->get('/dashboard/subscription')->assertOk();
    }
}
