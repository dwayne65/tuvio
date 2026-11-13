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
        .wordmark { font-size: 7pt; font-weight: bold; letter-spacing: 2.4pt; text-transform: uppercase; }
        .mark { display: inline-block; width: 7pt; height: 7pt; border-radius: 2pt; margin-right: 5pt; }
        .serial { font-family: DejaVu Sans Mono, monospace; font-size: 6.5pt; letter-spacing: 1pt; text-align: right; }
        .pill { display: inline-block; padding: 2.5pt 8pt; border-radius: 9pt; font-size: 6pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; }
        .title { text-transform: uppercase; font-weight: bold; line-height: 0.98; letter-spacing: -0.5pt; }
        .label { font-size: 5.8pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; }
        .value { font-size: 10pt; font-weight: bold; margin-top: 3pt; }
        .tier { font-size: 21pt; font-weight: bold; letter-spacing: 1pt; text-align: center; }
        .plate { background: #ffffff; border-radius: 12pt; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 7.4pt; font-weight: bold; letter-spacing: 0.8pt; color: #12121C; text-align: center; }
        .scan { font-size: 5.2pt; font-weight: bold; letter-spacing: 1.6pt; color: #8A8AA0; text-align: center; text-transform: uppercase; }
        .status-valid { background: #22C55E; color: #052E14; }
        .status-used { background: #EDE9FE; color: #4C1D95; }
        .status-refunded { background: #FDE68A; color: #78350F; }
        .status-void { background: #FECDD3; color: #9F1239; }
        .status-other { background: #E5E7EB; color: #374151; }
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
        $event = $ticket->event;
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
        $titleWords = preg_split('/\s+/', trim($event->title));
        $titleLength = mb_strlen($event->title);
        $titleSize = match (true) {
            $titleLength <= 12 => 38,
            $titleLength <= 20 => 30,
            $titleLength <= 32 => 23,
            $titleLength <= 48 => 18,
            default => 14,
        };
        $venue = $event->type === 'online'
            ? 'Online event'
            : (collect([$event->venue_name, $event->city])->filter()->join(', ') ?: 'Venue TBA');
        $start = $event->start_at;
    @endphp
    <div class="ticket">
        {{-- Artwork --}}
        <img class="layer" src="{{ $artwork->poster($ticket, $tier, 456, 261) }}" style="left: 0; top: 0; width: 456pt; height: 261pt;" alt="">
        <img class="layer" src="{{ $artwork->stub($tier, 168, 261) }}" style="left: 456pt; top: 0; width: 168pt; height: 261pt;" alt="">
        <img class="layer" src="{{ $artwork->decoration($tier) }}" style="left: 0; top: 0; width: 456pt; height: 261pt;" alt="">
        <img class="layer" src="{{ $artwork->foil($tier, 624, 4) }}" style="left: 0; top: 0; width: 624pt; height: 4pt;" alt="">
        <img class="layer" src="{{ $artwork->cutouts($tier) }}" style="left: 0; top: 0; width: 624pt; height: 261pt;" alt="">

        {{-- Header --}}
        <div class="layer wordmark" style="left: 26pt; top: 20pt; color: {{ $tier['ink'] }};">
            <span class="mark" style="background: {{ $tier['accent'] }};"></span>TUVIO <span style="color: {{ $tier['muted'] }}; font-weight: normal;">&nbsp;presents</span>
        </div>
        <div class="layer serial" style="left: 300pt; top: 238pt; width: 130pt; color: {{ $tier['muted'] }};">
            Nº {{ str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT) }}
        </div>

        {{-- Title block --}}
        <div class="layer" style="left: 26pt; top: 42pt;">
            @if ($event->category)
                <span class="pill" style="border: 0.8pt solid {{ $tier['accent'] }}; color: {{ $tier['accent'] }};">{{ \Illuminate\Support\Str::limit($event->category->name, 28) }}</span>
            @endif
            <span class="pill {{ $statusClass }}" style="margin-left: {{ $event->category ? 4 : 0 }}pt;">{{ $statusLabel }}</span>
        </div>
        <div class="layer title" style="left: 26pt; top: 64pt; width: 290pt; font-size: {{ $titleSize }}pt; color: {{ $tier['ink'] }};">
            <span style="color: {{ $tier['title'] }};">{{ array_shift($titleWords) }}</span>
            {{ implode(' ', $titleWords) }}
        </div>
        <div class="layer" style="left: 26pt; top: 162pt; width: 300pt; font-size: 7.5pt; color: {{ $tier['muted'] }};">
            Hosted by <span style="color: {{ $tier['ink'] }}; font-weight: bold;">{{ \Illuminate\Support\Str::limit($event->organization->name, 40) }}</span>
        </div>

        {{-- Info bar --}}
        <div class="layer" style="left: 26pt; top: 198pt; width: 112pt;">
            <div class="label" style="color: {{ $tier['accent'] }};">Date</div>
            <div class="value" style="color: {{ $tier['ink'] }};">{{ $start?->format('D, M j, Y') ?? 'TBA' }}</div>
        </div>
        <div class="layer" style="left: 156pt; top: 198pt; width: 76pt;">
            <div class="label" style="color: {{ $tier['accent'] }};">Time</div>
            <div class="value" style="color: {{ $tier['ink'] }};">{{ $start?->format('g:i A') ?? 'TBA' }}</div>
        </div>
        <div class="layer" style="left: 248pt; top: 198pt; width: 186pt;">
            <div class="label" style="color: {{ $tier['accent'] }};">Venue</div>
            <div class="value" style="color: {{ $tier['ink'] }};">{{ \Illuminate\Support\Str::limit($venue, 30) }}</div>
        </div>
        <div class="layer" style="left: 26pt; top: 238pt; font-size: 5.8pt; letter-spacing: 0.3pt; color: {{ $tier['muted'] }};">
            Show this QR code at the entrance &nbsp;·&nbsp; One entry per ticket &nbsp;·&nbsp; Do not share this ticket
        </div>

        {{-- Stub --}}
        <div class="layer" style="left: 466pt; top: 18pt; width: 148pt;">
            <div class="tier" style="color: {{ $tier['tierText'] }};{{ mb_strlen($tier['label']) > 5 ? ' font-size: 14pt;' : '' }}">{{ $tier['glyph'] }} {{ $tier['label'] }}</div>
            <div class="label" style="margin-top: 7pt; text-align: center; color: {{ $tier['stubMuted'] }};">Admit one</div>
            <div style="margin-top: 2pt; font-size: 10pt; font-weight: bold; text-align: center; color: {{ $tier['stubInk'] }};">{{ \Illuminate\Support\Str::limit($ticket->attendee_name ?? 'Guest', 22) }}</div>
            <div style="margin-top: 5pt; font-size: 7.5pt; text-align: center; color: {{ $tier['stubMuted'] }};">{{ $price }}</div>
        </div>
        <div class="layer plate" style="left: 482pt; top: 100pt; width: 116pt; height: 146pt;"></div>
        <img class="layer" src="{{ $pass['qr'] }}" style="left: 488pt; top: 105pt; width: 104pt; height: 104pt;" alt="QR">
        <div class="layer code" style="left: 482pt; top: 212pt; width: 116pt;">{{ $ticket->code }}</div>
        <div class="layer scan" style="left: 482pt; top: 228pt; width: 116pt;">Scan at entry</div>
    </div>
    @unless ($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach
</body>
</html>
