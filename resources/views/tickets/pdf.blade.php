<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* One landscape ticket per page: 220 × 92 mm (≈ 624 × 261 pt; the box stays just under so DomPDF never spills onto a new page). */
        @page { size: 220mm 92mm; margin: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; background: #ffffff; }
        .ticket { position: relative; width: 624pt; height: 259pt; overflow: hidden; }
        .layer { position: absolute; }
        img.layer { display: block; }
        .wordmark { font-size: 6.5pt; font-weight: bold; letter-spacing: 2pt; text-transform: uppercase; }
        .mark { display: inline-block; width: 7pt; height: 7pt; border-radius: 1.5pt; margin-right: 4pt; }
        .title { text-transform: uppercase; line-height: 1.08; }
        .title-first { font-weight: bold; }
        .date { font-size: 17pt; font-weight: bold; }
        .label { font-size: 6pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; }
        .value { font-size: 9pt; font-weight: bold; }
        .tier { font-size: 19pt; font-weight: bold; letter-spacing: 0.5pt; }
        .plate { background: #ffffff; border-radius: 10pt; text-align: center; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 7pt; font-weight: bold; letter-spacing: 0.6pt; color: #171C2B; }
        .status { display: inline-block; padding: 2pt 7pt; border-radius: 8pt; font-size: 6pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }
        .status-valid { background: #DCFCE7; color: #166534; }
        .status-used { background: #F0ECFF; color: #5B3FD6; }
        .status-refunded { background: #FEF3C7; color: #92400E; }
        .status-void { background: #FFE4E6; color: #BE123C; }
        .status-other { background: #EEF0F4; color: #475569; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $artwork = app(\App\Support\TicketArtwork::class);
@endphp
@foreach ($passes as $pass)
    @php
        $ticket = $pass['ticket'];
        $tier = $artwork->tier($ticket);
        [$statusLabel, $statusClass] = match ($ticket->status) {
            'issued' => ['Valid', 'status-valid'],
            'used' => ['Checked in', 'status-used'],
            'refunded' => ['Refunded', 'status-refunded'],
            'void' => ['Cancelled', 'status-void'],
            default => [ucfirst($ticket->status), 'status-other'],
        };
        // What the attendee paid, falling back to the ticket type's list price.
        $priceMinor = $ticket->orderItem?->unit_price_minor ?? $ticket->ticketType->price_minor;
        $price = $priceMinor === 0
            ? 'Free'
            : ($ticket->orderItem?->currency ?? $ticket->ticketType->currency).' '.number_format($priceMinor / 100, $priceMinor % 100 === 0 ? 0 : 2);
        $titleWords = preg_split('/\s+/', trim($ticket->event->title));
        $titleSize = match (true) {
            mb_strlen($ticket->event->title) <= 16 => 25,
            mb_strlen($ticket->event->title) <= 30 => 19,
            default => 15,
        };
        $venue = $ticket->event->type === 'online'
            ? 'Online event'
            : (collect([$ticket->event->venue_name, $ticket->event->city])->filter()->join(', ') ?: 'TBA');

        // Vector layer: diagonal photo edge, accent bars, perforation, notches and rounded corners.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="624" height="261" viewBox="0 0 624 261">'
            .'<polygon points="0,0 300,0 216,261 0,261" fill="'.$tier['panel'].'"/>'
            .'<polygon points="279,0 292,0 261,96 248,96" fill="'.$tier['accent'].'"/>'
            .'<line x1="231" y1="182" x2="206" y2="261" stroke="'.$tier['accent'].'" stroke-width="1"/>'
            .'<line x1="221" y1="208" x2="204" y2="261" stroke="'.$tier['accent'].'" stroke-width="0.8" stroke-opacity="0.7"/>'
            .'<line x1="456" y1="16" x2="456" y2="245" stroke="'.$tier['perforation'].'" stroke-opacity="0.55" stroke-width="1.4" stroke-dasharray="5,4"/>'
            .'<circle cx="456" cy="0" r="9" fill="#ffffff"/><circle cx="456" cy="261" r="9" fill="#ffffff"/>'
            .'<path d="M0,0 L16,0 A16,16 0 0,0 0,16 Z" fill="#ffffff"/>'
            .'<path d="M624,0 L608,0 A16,16 0 0,1 624,16 Z" fill="#ffffff"/>'
            .'<path d="M0,261 L16,261 A16,16 0 0,1 0,245 Z" fill="#ffffff"/>'
            .'<path d="M624,261 L608,261 A16,16 0 0,0 624,245 Z" fill="#ffffff"/>'
            .'</svg>';
    @endphp
    <div class="ticket">
        {{-- Background layers --}}
        <div class="layer" style="left: 0; top: 0; width: 460pt; height: 261pt; background: {{ $tier['panel'] }};"></div>
        <img class="layer" src="{{ $artwork->photo($ticket, $tier, 244, 261) }}" style="left: 216pt; top: 0; width: 244pt; height: 261pt;" alt="">
        <img class="layer" src="{{ $artwork->stub($tier, 168, 261) }}" style="left: 456pt; top: 0; width: 168pt; height: 261pt;" alt="">
        <img class="layer" src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" style="left: 0; top: 0; width: 624pt; height: 261pt;" alt="">
        @if ($tier['holo'])
            <img class="layer" src="{{ $artwork->holoStrip(456, 3) }}" style="left: 0; top: 0; width: 456pt; height: 3pt;" alt="">
        @endif

        {{-- Main panel --}}
        <div class="layer wordmark" style="left: 26pt; top: 22pt; color: {{ $tier['muted'] }};">
            <span class="mark" style="background: {{ $tier['accent'] }};"></span>TicketFlow · {{ \Illuminate\Support\Str::limit($ticket->ticketType->name, 30) }}
        </div>
        <div class="layer title" style="left: 26pt; top: 44pt; width: 232pt; font-size: {{ $titleSize }}pt; color: {{ $tier['ink'] }};">
            <span class="title-first" style="color: {{ $tier['title'] }};">{{ array_shift($titleWords) }}</span>
            {{ implode(' ', $titleWords) }}
        </div>

        <div class="layer" style="left: 26pt; top: 146pt; width: 186pt;">
            <div class="date" style="color: {{ $tier['ink'] }};">{{ optional($ticket->event->start_at)->format('D, M j, Y') ?? 'Date TBA' }}</div>
            <div class="label" style="margin-top: 3pt; color: {{ $tier['muted'] }};">Starts {{ optional($ticket->event->start_at)->format('g:i A') ?? 'TBA' }} &nbsp;<span class="status {{ $statusClass }}">{{ $statusLabel }}</span></div>
            <div class="value" style="margin-top: 10pt; color: {{ $tier['ink'] }};">{{ \Illuminate\Support\Str::limit($venue, 38) }}</div>
            <div style="margin-top: 4pt; font-size: 7.5pt; color: {{ $tier['muted'] }};">Hosted by {{ \Illuminate\Support\Str::limit($ticket->event->organization->name, 34) }}</div>
        </div>
        <div class="layer" style="left: 26pt; top: 236pt; font-size: 6pt; color: {{ $tier['muted'] }};">Present the QR code at the entrance · One entry per ticket</div>

        {{-- Stub --}}
        <div class="layer" style="left: 476pt; top: 16pt; width: 132pt;">
            <div class="tier" style="color: {{ $tier['tierText'] }};{{ mb_strlen($tier['label']) > 4 ? ' font-size: 14pt;' : '' }}">{{ $tier['glyph'] }} {{ $tier['label'] }}</div>
            <div class="label" style="margin-top: 5pt; color: {{ $tier['stubMuted'] }};">Ticket price</div>
            <div style="margin-top: 1pt; font-size: 11pt; font-weight: bold; color: {{ $tier['stubInk'] }};">{{ $price }}</div>
            <div class="label" style="margin-top: 5pt; color: {{ $tier['stubMuted'] }};">Admit one</div>
            <div class="value" style="margin-top: 1pt; color: {{ $tier['stubInk'] }};">{{ \Illuminate\Support\Str::limit($ticket->attendee_name ?? 'Guest', 22) }}</div>
        </div>
        <div class="layer plate" style="left: 482pt; top: 112pt; width: 116pt; height: 133pt;"></div>
        <img class="layer" src="{{ $pass['qr'] }}" style="left: 488pt; top: 117pt; width: 104pt; height: 104pt;" alt="QR">
        <div class="layer code" style="left: 482pt; top: 225pt; width: 116pt; text-align: center;">{{ $ticket->code }}</div>
    </div>
    @unless ($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach
</body>
</html>
