<?php

namespace App\Mail;

use App\Domain\Tickets\TicketPdf;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Sent to the buyer once an order's tickets are issued, with the tickets attached as a PDF. */
class TicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your tickets for {$this->order->event->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tickets-issued', with: [
            'order' => $this->order,
            'tickets' => $this->order->tickets->map(fn ($t) => [
                'code' => $t->code,
                'type' => $t->ticketType->name,
                'download_url' => URL::signedRoute('tickets.download', $t->code),
            ]),
        ]);
    }

    /** @return array<int,Attachment> */
    public function attachments(): array
    {
        $order = $this->order;

        return [
            Attachment::fromData(
                fn () => app(TicketPdf::class)->render($order->tickets)->output(),
                "tickets-{$order->reference}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
