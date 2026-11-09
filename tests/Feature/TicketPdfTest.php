<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Mail\TicketsIssuedMail;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TicketPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ticketflow.payments.default' => 'fake']);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function issuedTicketFor(User $buyer, ?Organization $org = null): Ticket
    {
        $org ??= Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create(['title' => 'Gig', 'slug' => 'gig-'.uniqid(), 'status' => 'published', 'type' => 'physical', 'visibility' => 'public', 'start_at' => now()->addMonth(), 'currency' => 'UGX']);
        $type = $event->ticketTypes()->create(['name' => 'GA', 'kind' => 'paid', 'price_minor' => 5000000, 'currency' => 'UGX', 'quantity_total' => 10, 'max_per_order' => 5, 'status' => 'active']);

        $order = app(OrderService::class)->create($event, ['name' => $buyer->name, 'email' => $buyer->email], [['ticket_type_id' => $type->id, 'quantity' => 1]], $buyer);
        $payment = Payment::create(['reference' => 'P'.uniqid(), 'purpose' => 'ticket_order', 'order_id' => $order->id, 'provider' => 'fake', 'provider_ref' => 'R'.uniqid(), 'idempotency_key' => uniqid(), 'amount_minor' => $order->total_minor, 'currency' => 'UGX', 'status' => 'pending']);
        app(PaymentService::class)->verifyAndFulfill($payment);

        return Ticket::where('order_id', $order->id)->firstOrFail();
    }

    public function test_owner_can_download_ticket_pdf(): void
    {
        $buyer = User::factory()->create();
        $ticket = $this->issuedTicketFor($buyer);

        $res = $this->actingAs($buyer)->get("/my-account/tickets/{$ticket->code}/pdf");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_non_owner_cannot_download_ticket_pdf(): void
    {
        $buyer = User::factory()->create();
        $ticket = $this->issuedTicketFor($buyer);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/my-account/tickets/{$ticket->code}/pdf")->assertForbidden();
    }

    public function test_buyer_is_emailed_tickets_once_with_pdf_attached(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $ticket = $this->issuedTicketFor($buyer);

        // Re-running fulfilment must not issue or email again.
        app(PaymentService::class)->verifyAndFulfill($ticket->order->payments()->firstOrFail());

        Mail::assertSent(TicketsIssuedMail::class, 1);
        Mail::assertSent(TicketsIssuedMail::class, function (TicketsIssuedMail $mail) use ($buyer, $ticket) {
            return $mail->hasTo($buyer->email)
                && collect($mail->attachments())->contains(fn ($a) => $a->as === "tickets-{$ticket->order->reference}.pdf");
        });
    }

    public function test_guest_can_download_ticket_with_signed_link(): void
    {
        $ticket = $this->issuedTicketFor(User::factory()->create());

        $res = $this->get(URL::signedRoute('tickets.download', $ticket->code));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_ticket_download_rejects_unsigned_link(): void
    {
        $ticket = $this->issuedTicketFor(User::factory()->create());

        $this->get("/tickets/{$ticket->code}/download")->assertForbidden();
    }

    /** An organizer whose organization was created through the app (with membership). */
    private function organizer(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/organizations', ['name' => 'Acme Events']);

        return [$user, $user->organizations()->firstOrFail()];
    }

    public function test_organizer_can_print_attendee_ticket(): void
    {
        [$owner, $org] = $this->organizer();
        $ticket = $this->issuedTicketFor(User::factory()->create(), $org);

        $res = $this->actingAs($owner)->get("/dashboard/tickets/{$ticket->code}/print");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_organizer_cannot_print_another_organizations_ticket(): void
    {
        $ticket = $this->issuedTicketFor(User::factory()->create());
        [$otherOwner] = $this->organizer();

        $this->actingAs($otherOwner)->get("/dashboard/tickets/{$ticket->code}/print")->assertNotFound();
    }

    public function test_wallet_shows_the_price_paid_and_ticket_tier(): void
    {
        $buyer = User::factory()->create();
        $ticket = $this->issuedTicketFor($buyer);
        $ticket->ticketType->update(['tier' => 'vip', 'perks' => ['Fast-track entry']]);

        $this->actingAs($buyer)->get('/my-account/tickets')
            ->assertInertia(fn ($page) => $page
                ->component('Account/Tickets', false)
                ->where('tickets.0.price_minor', 5000000)
                ->where('tickets.0.currency', 'UGX')
                ->where('tickets.0.tier', 'vip')
                ->where('tickets.0.perks', ['Fast-track entry']));
    }
}
