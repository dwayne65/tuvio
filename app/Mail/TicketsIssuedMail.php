<?php

namespace App\Mail;

use App\Domain\Tickets\TicketPdf;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Support\TicketArtwork;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Sent to the buyer once an order's tickets are issued: a branded confirmation
 * with each ticket's QR code inline, a receipt, and the tickets as a PDF.
 */
class TicketsIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're going! Your tickets for {$this->order->event->title}");
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['event.organization', 'tickets.ticketType', 'items', 'payments']);
        $event = $order->event;
        $start = $event->start_at;
        $end = $event->end_at ?? $start?->copy()->addHours(3);
        $place = $event->type === 'online'
            ? 'Online event'
            : collect([$event->venue_name, $event->venue_address, $event->city])->filter()->implode(', ');
        $payment = $order->payments->firstWhere('status', 'successful') ?? $order->payments->last();
        $artwork = app(TicketArtwork::class);

        return new Content(
            view: 'mail.tickets-issued',
            text: 'mail.tickets-issued-text',
            with: [
                'order' => $order,
                'event' => $event,
                'organizer' => $event->organization,
                'place' => $place ?: 'Venue to be announced',
                'when' => $start ? $start->format('l, F j, Y') : 'Date to be announced',
                'time' => $start ? $start->format('g:i A').($event->end_at ? ' – '.$event->end_at->format('g:i A') : '') : null,
                'countdown' => $this->countdown($start),
                'firstName' => Str::of((string) $order->buyer_name)->trim()->before(' ')->title()->value() ?: 'there',
                'coverPath' => $this->localCoverPath($event->cover_image_path),
                'tickets' => $order->tickets->map(fn (Ticket $ticket) => [
                    'code' => $ticket->code,
                    'type' => $ticket->ticketType->name,
                    'attendee' => $ticket->attendee_name ?: $order->buyer_name,
                    'qr_png' => (new PngWriter)->write(new QrCode(data: $ticket->qr_token, size: 360, margin: 10))->getString(),
                    'download_url' => URL::signedRoute('tickets.download', $ticket->code),
                    'tier' => Arr::only($artwork->tier($ticket), ['label', 'glyph', 'accent', 'accent2', 'title', 'muted', 'baseHex']),
                ]),
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'label' => $item->description,
                    'quantity' => $item->quantity,
                    'total' => $this->money($item->line_total_minor),
                ]),
                'totals' => array_filter([
                    'Subtotal' => $this->money($order->subtotal_minor),
                    'Discount' => $order->discount_minor > 0 ? '− '.$this->money($order->discount_minor) : null,
                    'Fees' => $order->fee_minor > 0 ? $this->money($order->fee_minor) : null,
                    config('ticketflow.tax.label') => $order->buyerTaxMinor() > 0 ? $this->money($order->buyerTaxMinor()) : null,
                ]),
                'total' => $order->total_minor > 0 ? $this->money($order->total_minor) : 'Free',
                'paidWith' => $payment ? $this->paymentLabel($payment->method, $payment->provider) : null,
                'paidAt' => ($order->paid_at ?? now())->format('M j, Y · g:i A'),
                'calendarUrl' => $start ? $this->calendarUrl($event->title, $start, $end, $place, $event->slug) : null,
                'directionsUrl' => $event->type !== 'online' && $place ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($place) : null,
                'eventUrl' => route('events.show', $event->slug),
            ],
        );
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

    /** "Today", "Tomorrow" or "In 12 days"; nothing once the event has started. */
    private function countdown(?Carbon $start): ?string
    {
        if (! $start || $start->isPast()) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($start->copy()->startOfDay());

        return match ($days) {
            0 => 'Today',
            1 => 'Tomorrow',
            default => "In {$days} days",
        };
    }

    private function money(int $minor): string
    {
        return $this->order->currency.' '.number_format($minor / 100);
    }

    /** Covers on the public disk are embedded; remote covers are skipped so the email never hotlinks. */
    private function localCoverPath(?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'http') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    private function paymentLabel(?string $method, ?string $provider): string
    {
        // Providers report e.g. "mobile_money", "mobilemoneyuganda" or "card".
        $method = str_replace(['_', '-', ' '], '', strtolower((string) $method));

        return match (true) {
            str_contains($method, 'mobilemoney') || $method === 'momo' => 'Mobile Money',
            str_contains($method, 'card') => 'Card',
            str_contains($method, 'bank') => 'Bank transfer',
            $method === 'ussd' => 'USSD',
            default => $provider ? ucfirst($provider) : 'Online payment',
        };
    }

    private function calendarUrl(string $title, Carbon $start, Carbon $end, string $place, string $slug): string
    {
        $format = fn (Carbon $date) => $date->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $format($start).'/'.$format($end),
            'location' => $place,
            'details' => 'Your TUVIO tickets: '.route('events.show', $slug),
        ]);
    }
}
