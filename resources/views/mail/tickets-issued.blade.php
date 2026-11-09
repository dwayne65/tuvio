<x-mail::message>
# You're going, {{ $order->buyer_name }}!

Your tickets for **{{ $order->event->title }}** are confirmed.

**When:** {{ optional($order->event->start_at)->format('D, M j, Y · g:i A') ?? 'TBA' }}<br>
**Order:** {{ $order->reference }}

Your tickets are attached to this email as a PDF. Show the QR code on each ticket at the gate — print it or show it on your phone.

<x-mail::table>
| Ticket | Code | |
|:--|:--|:--|
@foreach ($tickets as $t)
| {{ $t['type'] }} | {{ $t['code'] }} | [Download]({{ $t['download_url'] }}) |
@endforeach
</x-mail::table>

Keep this email safe: anyone holding a ticket's QR code can use it to enter.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
