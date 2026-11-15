<?php

namespace App\Domain\Tickets;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders tickets as a PDF, one per page, in the design for each ticket's category.
 * "ticket" pages are the ticket itself; "print" pages put it at actual size on A4
 * landscape with crop marks.
 */
class TicketPdf
{
    public const FORMATS = ['ticket', 'print'];

    /** @param Ticket|Collection<int,Ticket> $tickets */
    public function render(Ticket|Collection $tickets, string $format = 'ticket'): DomPdf
    {
        $tickets = $tickets instanceof Ticket ? new Collection([$tickets]) : $tickets;
        $tickets->loadMissing(['event.organization', 'event.category', 'ticketType', 'orderItem']);

        $passes = $tickets->map(fn (Ticket $t) => [
            'ticket' => $t,
            // About 250 dpi at its printed size, so it stays sharp; the white plate around it is the quiet zone.
            'qr' => (new PngWriter)
                ->write(new QrCode(data: $t->qr_token, size: 400, margin: 0))
                ->getDataUri(),
        ]);

        return Pdf::loadView('tickets.pdf', [
            'passes' => $passes,
            'format' => in_array($format, self::FORMATS, true) ? $format : 'ticket',
        ]);
    }
}
