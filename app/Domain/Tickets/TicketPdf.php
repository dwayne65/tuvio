<?php

namespace App\Domain\Tickets;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Collection;

/** Renders one or more tickets (one per page) as a printable PDF with QR codes. */
class TicketPdf
{
    /** @param Ticket|Collection<int,Ticket> $tickets */
    public function render(Ticket|Collection $tickets): DomPdf
    {
        $tickets = $tickets instanceof Ticket ? new Collection([$tickets]) : $tickets;
        $tickets->loadMissing(['event.organization', 'ticketType', 'orderItem']);

        $passes = $tickets->map(fn (Ticket $t) => [
            'ticket' => $t,
            'qr' => (new PngWriter)
                ->write(new QrCode(data: $t->qr_token, size: 260, margin: 8))
                ->getDataUri(),
        ]);

        return Pdf::loadView('tickets.pdf', ['passes' => $passes])->setPaper('a5', 'portrait');
    }
}
