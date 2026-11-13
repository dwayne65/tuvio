<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Mail\SupportReceivedMail;
use App\Mail\SupportReplyMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\TicketType;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GrowthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake', 'ticketflow.demo_mode' => false, 'ticketflow.commission_bps' => 500]);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /** @return array{0: User, 1: Organization} */
    private function organizer(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events '.uniqid()]);
        Organization::where('status', Organization::STATUS_PENDING)->update(['status' => Organization::STATUS_ACTIVE]);
        auth()->logout();
        $org = $user->ownedOrganizations()->firstOrFail();
        $this->onPaidPlan($org);

        return [$user, $org];
    }

    /** @return array{0: Event, 1: TicketType} */
    private function eventFor(Organization $org, ?string $startsAt = '+1 month'): array
    {
        $event = $org->events()->create([
            'title' => 'Gig', 'slug' => 'gig-'.uniqid(), 'status' => 'published', 'type' => 'physical',
            'visibility' => 'public', 'start_at' => now()->modify($startsAt), 'currency' => 'UGX',
        ]);
        $type = $event->ticketTypes()->create([
            'name' => 'GA', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX',
            'quantity_total' => 50, 'max_per_order' => 5, 'status' => 'active',
        ]);

        return [$event, $type];
    }

    private function checkout(Event $event, TicketType $type, int $qty = 2, ?string $promo = null): void
    {
        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => $qty]]]);
        if ($promo !== null) {
            $this->post('/checkout/promo', ['code' => $promo])->assertSessionHasNoErrors();
        }
        $this->post('/checkout', ['name' => 'Ada Buyer', 'email' => 'ada@example.com']);
    }

    private function paidOrder(Event $event, TicketType $type, string $email = 'ada@example.com'): Order
    {
        $order = app(OrderService::class)->create($event, ['name' => 'Ada Buyer', 'email' => $email], [['ticket_type_id' => $type->id, 'quantity' => 1]]);
        $payment = Payment::create([
            'reference' => 'P'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake',
            'provider_ref' => 'R'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $order->total_minor,
            'currency' => 'UGX', 'status' => 'pending',
        ]);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return $order->fresh();
    }

    // ---- Discounts ---------------------------------------------------------

    public function test_percent_promo_code_reduces_total_and_commission_and_is_redeemed(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $promo = $org->promoCodes()->create(['code' => 'EARLY', 'type' => 'percent', 'value' => 2000, 'max_redemptions' => 5]);

        $this->checkout($event, $type, qty: 2, promo: 'early');

        $order = Order::firstOrFail();
        $this->assertSame(10000000, $order->subtotal_minor);
        $this->assertSame(2000000, $order->discount_minor);
        $this->assertSame(8000000, $order->total_minor);
        $this->assertSame(400000, $order->commission_minor); // 5% of the discounted total
        $this->assertSame($promo->id, $order->promo_code_id);
        $this->assertSame(1, $promo->fresh()->redeemed_count);
        $this->assertDatabaseHas('promo_redemptions', ['order_id' => $order->id, 'amount_discounted_minor' => 2000000]);
    }

    public function test_fixed_promo_never_discounts_more_than_the_subtotal(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $org->promoCodes()->create(['code' => 'BIG', 'type' => 'fixed', 'value' => 99000000, 'currency' => 'UGX']);

        $this->checkout($event, $type, qty: 1, promo: 'BIG');

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->total_minor);
        $this->assertSame('paid', $order->status); // free orders are fulfilled immediately
    }

    public function test_exhausted_promo_code_is_rejected_at_checkout(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $org->promoCodes()->create(['code' => 'ONCE', 'type' => 'percent', 'value' => 1000, 'max_redemptions' => 1, 'redeemed_count' => 1]);

        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => 1]]]);

        $this->post('/checkout/promo', ['code' => 'ONCE'])
            ->assertSessionHasErrors(['code' => 'This promo code is invalid or has expired.']);
    }

    public function test_another_organizations_promo_code_does_not_apply(): void
    {
        [, $org] = $this->organizer();
        [, $other] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $other->promoCodes()->create(['code' => 'THEIRS', 'type' => 'percent', 'value' => 5000]);

        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => 1]]]);

        $this->post('/checkout/promo', ['code' => 'THEIRS'])->assertSessionHasErrors('code');
    }

    public function test_promo_below_minimum_order_is_rejected(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $org->promoCodes()->create(['code' => 'BULK', 'type' => 'percent', 'value' => 1000, 'min_order_minor' => 20000000]);

        $this->post("/events/{$event->slug}/checkout", ['items' => [['ticket_type_id' => $type->id, 'quantity' => 1]]]);

        $this->post('/checkout/promo', ['code' => 'BULK'])
            ->assertSessionHasErrors(['code' => 'Your order is below the minimum for this promo code.']);
    }

    public function test_expired_reservation_gives_the_promo_use_back(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $promo = $org->promoCodes()->create(['code' => 'EARLY', 'type' => 'percent', 'value' => 1000, 'max_redemptions' => 1]);

        $order = app(OrderService::class)->create($event, ['name' => 'A', 'email' => 'a@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]], promoCode: 'EARLY');
        $this->assertFalse($promo->fresh()->isRedeemable());

        app(OrderService::class)->releaseReservation($order);

        $this->assertSame(0, $promo->fresh()->redeemed_count);
        $this->assertDatabaseMissing('promo_redemptions', ['order_id' => $order->id]);
    }

    public function test_organizer_creates_percent_code_stored_in_basis_points(): void
    {
        [$user, $org] = $this->organizer();

        $this->actingAs($user)->post('/dashboard/discounts', ['code' => 'vip', 'type' => 'percent', 'value' => 15])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('promo_codes', ['organization_id' => $org->id, 'code' => 'VIP', 'value' => 1500, 'currency' => null]);
    }

    public function test_organizer_cannot_toggle_another_organizations_code(): void
    {
        [$user] = $this->organizer();
        [, $other] = $this->organizer();
        $code = $other->promoCodes()->create(['code' => 'THEIRS', 'type' => 'percent', 'value' => 1000]);

        $this->actingAs($user)->post("/dashboard/discounts/{$code->id}/toggle")->assertNotFound();
        $this->assertTrue($code->fresh()->is_active);
    }

    public function test_used_code_is_paused_instead_of_deleted(): void
    {
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $code = $org->promoCodes()->create(['code' => 'USED', 'type' => 'percent', 'value' => 1000]);
        app(OrderService::class)->create($event, ['name' => 'A', 'email' => 'a@example.com'], [['ticket_type_id' => $type->id, 'quantity' => 1]], promoCode: 'USED');

        $this->actingAs($user)->delete("/dashboard/discounts/{$code->id}");

        $this->assertDatabaseHas('promo_codes', ['id' => $code->id, 'is_active' => false]);
    }

    // ---- Promoters ---------------------------------------------------------

    public function test_referral_link_counts_click_and_credits_the_order(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $promoter = $org->promoters()->create(['name' => 'DJ', 'code' => 'DJ1', 'commission_bps' => 1000]);

        $this->get("/events/{$event->slug}?ref=dj1")->assertOk();
        $this->checkout($event, $type);

        $this->assertSame(1, $promoter->fresh()->clicks);
        $this->assertSame($promoter->id, Order::firstOrFail()->promoter_id);
    }

    public function test_referral_for_a_different_event_is_not_credited(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        [$otherEvent] = $this->eventFor($org);
        $org->promoters()->create(['name' => 'DJ', 'code' => 'DJ1', 'event_id' => $otherEvent->id]);

        $this->get("/events/{$event->slug}?ref=DJ1");
        $this->checkout($event, $type);

        $this->assertNull(Order::firstOrFail()->promoter_id);
    }

    public function test_paused_promoter_is_not_credited(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $promoter = $org->promoters()->create(['name' => 'DJ', 'code' => 'DJ1', 'is_active' => false]);

        $this->get("/events/{$event->slug}?ref=DJ1");
        $this->checkout($event, $type);

        $this->assertSame(0, $promoter->fresh()->clicks);
        $this->assertNull(Order::firstOrFail()->promoter_id);
    }

    public function test_promoters_page_reports_sales_and_commission(): void
    {
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $promoter = $org->promoters()->create(['name' => 'DJ', 'code' => 'DJ1', 'commission_bps' => 1000]);
        $order = $this->paidOrder($event, $type);
        $order->update(['promoter_id' => $promoter->id]);

        $this->actingAs($user)->get('/dashboard/promoters')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Promoters', false)
                ->where('promoters.0.orders', 1)
                ->where('promoters.0.revenue_minor', 5000000)
                ->where('promoters.0.earned_minor', 500000));
    }

    // ---- Reviews -----------------------------------------------------------

    public function test_buyer_reviews_an_event_that_has_started(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org, '-2 hours');
        $order = $this->paidOrder($event, $type);

        $this->post("/events/{$event->slug}/reviews", ['reference' => strtolower($order->reference), 'email' => 'ADA@example.com', 'rating' => 5, 'body' => 'Amazing'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_reviews', ['event_id' => $event->id, 'order_id' => $order->id, 'rating' => 5, 'name' => 'Ada Buyer']);
        $this->get("/events/{$event->slug}")->assertInertia(fn ($page) => $page->where('reviews.count', 1)->where('reviews.average', 5));
    }

    public function test_review_with_wrong_email_is_rejected(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org, '-2 hours');
        $order = $this->paidOrder($event, $type);

        $this->post("/events/{$event->slug}/reviews", ['reference' => $order->reference, 'email' => 'someone@else.com', 'rating' => 5])
            ->assertSessionHasErrors('reference');
        $this->assertDatabaseCount('event_reviews', 0);
    }

    public function test_review_before_event_starts_is_rejected(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $order = $this->paidOrder($event, $type);

        $this->post("/events/{$event->slug}/reviews", ['reference' => $order->reference, 'email' => 'ada@example.com', 'rating' => 4])
            ->assertSessionHasErrors(['reference' => 'Reviews open once the event starts or your ticket is scanned at the gate.']);
    }

    public function test_scanned_ticket_can_review_before_event_start(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $order = $this->paidOrder($event, $type);
        $order->tickets()->update(['status' => 'used']);

        $this->post("/events/{$event->slug}/reviews", ['reference' => $order->reference, 'email' => 'ada@example.com', 'rating' => 4])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_reviews', 1);
    }

    public function test_order_can_only_be_reviewed_once(): void
    {
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org, '-2 hours');
        $order = $this->paidOrder($event, $type);
        $payload = ['reference' => $order->reference, 'email' => 'ada@example.com', 'rating' => 5];

        $this->post("/events/{$event->slug}/reviews", $payload);
        $this->post("/events/{$event->slug}/reviews", $payload)->assertSessionHasErrors('reference');

        $this->assertDatabaseCount('event_reviews', 1);
    }

    public function test_hidden_review_is_not_shown_on_event_page(): void
    {
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org, '-2 hours');
        $order = $this->paidOrder($event, $type);
        $review = $order->review()->create(['event_id' => $event->id, 'name' => 'Ada', 'rating' => 1, 'body' => 'spam']);

        $this->actingAs($user)->post("/dashboard/reviews/{$review->id}/toggle");

        $this->get("/events/{$event->slug}")->assertInertia(fn ($page) => $page->where('reviews.count', 0));
    }

    // ---- Support -----------------------------------------------------------

    public function test_support_request_with_order_reference_goes_to_that_organizer(): void
    {
        Mail::fake();
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $order = $this->paidOrder($event, $type);

        $this->post('/support', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Payment problem',
            'message' => 'Charged twice', 'order_reference' => $order->reference,
        ])->assertSessionHas('success');

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($org->id, $ticket->organization_id);
        $this->assertSame($order->id, $ticket->order_id);
        Mail::assertSent(SupportReceivedMail::class, fn ($m) => $m->hasTo('ada@example.com'));

        $this->actingAs($user)->get('/dashboard/support')
            ->assertInertia(fn ($page) => $page->where('tickets.data.0.reference', $ticket->reference));
    }

    public function test_order_reference_with_wrong_email_goes_to_platform_support(): void
    {
        Mail::fake();
        [, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $order = $this->paidOrder($event, $type);

        $this->post('/support', [
            'name' => 'Eve', 'email' => 'eve@example.com', 'subject' => 'Other',
            'message' => 'Hi', 'order_reference' => $order->reference,
        ]);

        $this->assertNull(SupportTicket::firstOrFail()->organization_id);
    }

    public function test_support_request_requires_fields(): void
    {
        $this->post('/support', [])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_organizer_reply_is_emailed_and_marks_answered(): void
    {
        Mail::fake();
        [$user, $org] = $this->organizer();
        $ticket = SupportTicket::create(['reference' => 'SUP-ABC123', 'organization_id' => $org->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Hi', 'message' => 'Help']);

        $this->actingAs($user)->post('/dashboard/support/SUP-ABC123/reply', ['reply' => 'Sorted!'])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame('answered', $ticket->status);
        $this->assertSame('Sorted!', $ticket->reply);
        $this->assertSame($user->id, $ticket->replied_by);
        Mail::assertSent(SupportReplyMail::class, fn ($m) => $m->hasTo('ada@example.com'));
    }

    public function test_organizer_cannot_reply_to_another_organizations_request(): void
    {
        Mail::fake();
        [$user] = $this->organizer();
        [, $other] = $this->organizer();
        SupportTicket::create(['reference' => 'SUP-OTHER1', 'organization_id' => $other->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Hi', 'message' => 'Help']);

        $this->actingAs($user)->post('/dashboard/support/SUP-OTHER1/reply', ['reply' => 'x'])->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_platform_requests_are_admin_only(): void
    {
        SupportTicket::create(['reference' => 'SUP-PLAT01', 'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Hi', 'message' => 'Help']);

        $this->actingAs(User::factory()->create())->get('/admin/support')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/support')
            ->assertInertia(fn ($page) => $page->where('tickets.data.0.reference', 'SUP-PLAT01'));
    }

    // ---- Reports & live analytics -----------------------------------------

    public function test_report_shows_sales_and_attendance_per_event(): void
    {
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $this->paidOrder($event, $type);
        $this->paidOrder($event, $type)->tickets()->update(['status' => 'used']);

        $this->actingAs($user)->get("/dashboard/reports?event={$event->slug}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Reports', false)
                ->where('rows.0.sold', 2)
                ->where('rows.0.checked_in', 1)
                ->where('rows.0.attendance', 50)
                ->where('rows.0.gross_minor', 10000000)
                ->where('selected.types.0.revenue_minor', 10000000));

        $csv = $this->actingAs($user)->get('/dashboard/reports/export');
        $csv->assertOk();
        $this->assertStringContainsString('Attendance %', $csv->streamedContent());
    }

    public function test_analytics_includes_today_figures(): void
    {
        [$user, $org] = $this->organizer();
        [$event, $type] = $this->eventFor($org);
        $order = $this->paidOrder($event, $type);

        $this->actingAs($user)->get('/dashboard/analytics')
            ->assertInertia(fn ($page) => $page
                ->where('today.orders', 1)
                ->where('today.gross_minor', 5000000)
                ->where('recent.0.reference', $order->reference));
    }

    public function test_new_dashboard_pages_require_login(): void
    {
        foreach (['/dashboard/reports', '/dashboard/discounts', '/dashboard/promoters', '/dashboard/reviews', '/dashboard/support'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
