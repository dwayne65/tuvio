<?php

namespace App\Http\Controllers;

use App\Domain\Tickets\TicketPdf;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function __construct(private TicketPdf $renderer) {}

    /** Download a single ticket as a PDF with its QR code. */
    public function pdf(Request $request, string $code): Response
    {
        $ticket = Ticket::where('code', $code)->with('order')->firstOrFail();

        // Only the buyer (ticket owner) may download it.
        abort_unless($ticket->order && $ticket->order->user_id === $request->user()->id, 403);

        return $this->renderer->render($ticket)->download("ticket-{$ticket->code}.pdf");
    }

    /** Guest download via the signed link emailed to the buyer — no account needed. */
    public function download(string $code): Response
    {
        $ticket = Ticket::where('code', $code)->firstOrFail();

        return $this->renderer->render($ticket)->download("ticket-{$ticket->code}.pdf");
    }

    /** Organizer view of an attendee's ticket, rendered inline so it can be printed. */
    public function print(Request $request, string $code): Response
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('view', $org);

        $ticket = Ticket::where('code', $code)
            ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
            ->firstOrFail();

        return $this->renderer->render($ticket, 'print')->stream("ticket-{$ticket->code}.pdf");
    }
}
