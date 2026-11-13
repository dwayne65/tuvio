You're going, {{ $order->buyer_name }}!

Payment confirmed. Your tickets for {{ $event->title }} are ready.

WHEN:  {{ $when }}{{ $time ? ' · '.$time : '' }}
WHERE: {{ $place }}

YOUR TICKETS
@foreach ($tickets as $ticket)
- {{ $ticket['type'] }} · {{ $ticket['code'] }} · {{ $ticket['attendee'] }}
  Download: {{ $ticket['download_url'] }}
@endforeach

Show each ticket's QR code at the gate. Your tickets are also attached as a PDF.

RECEIPT
@foreach ($items as $item)
{{ $item['label'] }} x {{ $item['quantity'] }}: {{ $item['total'] }}
@endforeach
Total paid: {{ $total }}
Order {{ $order->reference }}{{ $paidWith ? ' · Paid with '.$paidWith : '' }} · {{ $paidAt }}

Each QR code works once. Keep it private, since anyone holding it can enter.

Event page: {{ $eventUrl }}
@if ($organizer)
Organized by {{ $organizer->name }}{{ $organizer->support_email ? ' · '.$organizer->support_email : '' }}
@endif

TUVIO · Life Happens Here.
