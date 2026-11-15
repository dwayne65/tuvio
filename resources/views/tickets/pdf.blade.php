<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        /*
         | One ticket per page. "ticket" pages are the ticket itself (220 × 92 mm, for phones and
         | saving); "print" pages are A4 landscape with the ticket at actual size and crop marks.
        */
        $print = ($format ?? 'ticket') === 'print';
        $W = 624; $H = 259; $TEAR = 448;
        $offsetX = $print ? 109 : 0;
        $offsetY = $print ? 168 : 0;
        $fonts = resource_path('fonts');
    @endphp
    <style>
        @font-face { font-family: 'Instrument Serif'; font-style: normal; font-weight: normal; src: url('{{ $fonts }}/InstrumentSerif-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Instrument Serif'; font-style: italic; font-weight: normal; src: url('{{ $fonts }}/InstrumentSerif-Italic.ttf') format('truetype'); }
        @font-face { font-family: 'Jakarta'; font-style: normal; font-weight: normal; src: url('{{ $fonts }}/PlusJakartaSans-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Jakarta'; font-style: normal; font-weight: 600; src: url('{{ $fonts }}/PlusJakartaSans-SemiBold.ttf') format('truetype'); }
        @font-face { font-family: 'Jakarta'; font-style: normal; font-weight: bold; src: url('{{ $fonts }}/PlusJakartaSans-ExtraBold.ttf') format('truetype'); }

        @page { size: {{ $print ? '297mm 210mm' : '220mm 92mm' }}; margin: 0; }
        * { margin: 0; padding: 0; }
        body { font-family: 'Jakarta', DejaVu Sans, sans-serif; background: #ffffff; color: #111111; }
        .sheet { position: relative; width: {{ $print ? 842 : $W }}pt; height: {{ $print ? 593 : $H - 2 }}pt; overflow: hidden; }
        .ticket { position: absolute; left: {{ $offsetX }}pt; top: {{ $offsetY }}pt; width: {{ $W }}pt; height: {{ $H }}pt; }
        .layer { position: absolute; }
        img.layer { display: block; }
        .glyph { font-family: DejaVu Sans, sans-serif; }
        .eyebrow { font-size: 5.6pt; font-weight: bold; letter-spacing: 1.7pt; text-transform: uppercase; }
        .value { font-size: 8.6pt; font-weight: 600; margin-top: 2.5pt; line-height: 1.15; }
        .title { font-family: 'Instrument Serif', serif; line-height: 0.92; letter-spacing: -0.3pt; }
        .pill { display: inline-block; padding: 2pt 7pt; border-radius: 8pt; border: 0.7pt solid transparent; font-size: 5.4pt; font-weight: bold; letter-spacing: 1.3pt; text-transform: uppercase; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .status-valid { background: #1FB86A; color: #04220F; }
        .status-used { background: #E4E0FF; color: #3C2A99; }
        .status-refunded { background: #FDE68A; color: #78350F; }
        .status-void { background: #FECDD3; color: #9F1239; }
        .status-other { background: #E5E7EB; color: #374151; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@php
    $artwork = app(\App\Support\TicketArtwork::class);
    $logoWidth = \App\Support\TicketArtwork::LOGO_HEIGHT * 293 / 256;
@endphp
@foreach ($passes as $pass)
    @php
        $ticket = $pass['ticket'];
        $event = $ticket->event;
        $organization = $event->organization;
        $d = \App\Support\TicketDesign::forTicket($ticket);
        [$statusLabel, $statusClass] = match ($ticket->status) {
            'issued' => ['Valid', 'status-valid'],
            'used' => ['Checked in', 'status-used'],
            'refunded' => ['Refunded', 'status-refunded'],
            'void' => ['Cancelled', 'status-void'],
            default => [ucfirst($ticket->status), 'status-other'],
        };
        // What the attendee paid, falling back to the ticket type's list price.
        $priceMinor = $ticket->orderItem?->unit_price_minor ?? $ticket->ticketType->price_minor;
        $currency = $ticket->orderItem?->currency ?? $ticket->ticketType->currency;
        $price = $priceMinor === 0 ? 'Complimentary' : $currency.' '.number_format($priceMinor / 100, $priceMinor % 100 === 0 ? 0 : 2);
        $titleLength = mb_strlen($event->title);
        $titleSize = match (true) {
            $titleLength <= 12 => 44,
            $titleLength <= 20 => 36,
            $titleLength <= 30 => 29,
            $titleLength <= 46 => 23,
            default => 18,
        };
        $online = $event->type === 'online';
        $venue = $online ? 'Online event' : ($event->venue_name ?: 'Venue to be announced');
        $location = $online ? 'Link sent by the organizer' : (collect([$event->venue_address, $event->city])->filter()->join(', ') ?: '—');
        $start = $event->start_at;
        $help = $organization->support_email ?: 'tuvio.org';
        $labelSize = mb_strlen($d['label']) > 6 ? (mb_strlen($d['label']) > 10 ? 13 : 17) : 25;
        $stubX = $TEAR;
        $stubW = $W - $TEAR;
    @endphp
    <div class="sheet">
        @if ($print)
            <div class="layer" style="left: 109pt; top: 64pt; width: 624pt;">
                <img src="{{ $artwork->markOnWhite(16) }}" style="height: 16pt; vertical-align: middle;" alt="">
                <span style="font-size: 10pt; font-weight: bold; letter-spacing: 3pt; vertical-align: middle;">&nbsp;TUVIO</span>
                <div style="margin-top: 14pt; font-family: 'Instrument Serif', serif; font-size: 22pt;">Your ticket for {{ \Illuminate\Support\Str::limit($event->title, 60) }}</div>
                <div style="margin-top: 6pt; font-size: 8pt; color: #5B6170;">Print at 100% (actual size) and cut along the corner marks, or show this ticket on your phone. Each ticket admits one person once.</div>
            </div>
            @foreach ([[109, 168, -1, -1], [733, 168, 1, -1], [109, 427, -1, 1], [733, 427, 1, 1]] as [$cx, $cy, $sx, $sy])
                <div class="layer" style="left: {{ $sx < 0 ? $cx - 22 : $cx + 6 }}pt; top: {{ $cy }}pt; width: 16pt; height: 0; border-top: 0.5pt solid #9AA0AE;"></div>
                <div class="layer" style="left: {{ $cx }}pt; top: {{ $sy < 0 ? $cy - 22 : $cy + 6 }}pt; width: 0; height: 16pt; border-left: 0.5pt solid #9AA0AE;"></div>
            @endforeach
            <div class="layer" style="left: 109pt; top: 452pt; width: 624pt; font-size: 7pt; color: #5B6170;">
                Ticket {{ $ticket->code }} · {{ $d['badge'] }} · Verify and manage your tickets at tuvio.org
            </div>
        @endif

        <div class="ticket">
            {{-- Artwork --}}
            <img class="layer" src="{{ $artwork->panel($ticket, $d, $TEAR, $H) }}" style="left: 0; top: 0; width: {{ $TEAR }}pt; height: {{ $H }}pt;" alt="">
            <img class="layer" src="{{ $artwork->stub($d, $stubW, $H) }}" style="left: {{ $stubX }}pt; top: 0; width: {{ $stubW }}pt; height: {{ $H }}pt;" alt="">
            <img class="layer" src="{{ $artwork->pattern($d, $TEAR, $H) }}" style="left: 0; top: 0; width: {{ $TEAR }}pt; height: {{ $H }}pt;" alt="">
            <img class="layer" src="{{ $artwork->metal($d, $W, 3) }}" style="left: 0; top: 0; width: {{ $W }}pt; height: 3pt;" alt="">
            <img class="layer" src="{{ $artwork->cutouts($d, $W, $H, $TEAR) }}" style="left: 0; top: 0; width: {{ $W }}pt; height: {{ $H }}pt;" alt="">

            {{-- Branding --}}
            {{-- The logo itself is painted into the panel artwork at LOGO_X/LOGO_Y. --}}
            <div class="layer" style="left: {{ \App\Support\TicketArtwork::LOGO_X + $logoWidth + 5 }}pt; top: 23.5pt; width: 220pt;">
                <span style="font-size: 7.5pt; font-weight: bold; letter-spacing: 2.6pt; color: {{ $d['ink'] }};">TUVIO</span>
                <span style="font-size: 6pt; letter-spacing: 2pt; color: {{ $d['muted'] }};">&nbsp;&nbsp;PRESENTS</span>
            </div>
            <div class="layer" style="left: 236pt; top: 22pt; width: 192pt; text-align: right;">
                @if ($event->category)
                    <span class="pill" style="border: 0.7pt solid {{ $d['accent'] }}; color: {{ $d['dark'] ? $d['accent'] : $d['ink'] }};">{{ \Illuminate\Support\Str::limit($event->category->name, 22) }}</span>
                @endif
                <span class="pill {{ $statusClass }}" style="margin-left: 3pt;">{{ $statusLabel }}</span>
            </div>

            {{-- Event --}}
            <div class="layer eyebrow" style="left: 26pt; top: 47pt; width: 260pt; color: {{ $d['accent'] }};">
                {{ \Illuminate\Support\Str::limit($organization->name, 44) }}
            </div>
            <div class="layer title" style="left: 25pt; top: 60pt; width: 270pt; font-size: {{ $titleSize }}pt; color: {{ $d['ink'] }};">
                {{ \Illuminate\Support\Str::limit($event->title, 70) }}
            </div>
            <img class="layer" src="{{ $artwork->metal($d, 40, 2) }}" style="left: 26pt; top: 134pt; width: 40pt; height: 1.6pt;" alt="">

            @foreach ([
                ['Date', $start?->format('D, j M Y') ?? 'To be announced', 26, 90],
                ['Starts', $start?->format('g:i A') ?? 'TBA', 124, 60],
                ['Venue', \Illuminate\Support\Str::limit($venue, 26), 192, 118],
                ['Location', \Illuminate\Support\Str::limit($location, 26), 318, 110],
            ] as [$label, $value, $x, $width])
                <div class="layer" style="left: {{ $x }}pt; top: 146pt; width: {{ $width }}pt;">
                    <div class="eyebrow" style="color: {{ $d['muted'] }};">{{ $label }}</div>
                    <div class="value" style="color: {{ $d['ink'] }};">{{ $value }}</div>
                </div>
            @endforeach

            {{-- Ticket holder --}}
            @foreach ([
                ['Ticket holder', \Illuminate\Support\Str::limit($ticket->attendee_name ?: 'Guest', 26), 26, 152],
                ['Category', \Illuminate\Support\Str::limit($ticket->ticketType->name, 16), 192, 80],
                ['Price', $price, 276, 84],
                ['Ticket no.', 'Nº '.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), 364, 64],
            ] as [$label, $value, $x, $width])
                <div class="layer" style="left: {{ $x }}pt; top: 190pt; width: {{ $width }}pt;">
                    <div class="eyebrow" style="color: {{ $d['muted'] }};">{{ $label }}</div>
                    <div class="value" style="color: {{ $d['ink'] }};{{ $loop->first ? ' font-size: 10pt; font-weight: bold;' : '' }}">{{ $value }}</div>
                </div>
            @endforeach

            {{-- Footer --}}
            <div class="layer" style="left: 26pt; top: 233pt; width: 330pt; font-size: 5pt; line-height: 1.45; color: {{ $d['muted'] }};">
                Admits one, once. Valid only with a matching scan at entry; duplicated tickets are refused. Entry is subject to the
                venue's rules and security checks. Help: {{ \Illuminate\Support\Str::limit($help, 40) }}
            </div>
            <div class="layer" style="left: 356pt; top: 236pt; width: 72pt; text-align: right; font-size: 6pt; font-weight: bold; letter-spacing: 1.4pt; color: {{ $d['ink'] }};">
                TUVIO.ORG
            </div>

            {{-- Stub: category, privileges and QR --}}
            @if ($d['holo'])
                <img class="layer" src="{{ $artwork->seal($d, 24) }}" style="left: {{ $stubX + $stubW - 38 }}pt; top: 14pt; width: 24pt; height: 24pt;" alt="">
                <div class="layer glyph" style="left: {{ $stubX + $stubW - 38 }}pt; top: 19pt; width: 24pt; text-align: center; font-size: 9pt; color: {{ $d['metal'][1] }};">{{ $d['glyph'] }}</div>
            @endif
            <div class="layer eyebrow" style="left: {{ $stubX + 12 }}pt; top: 20pt; width: {{ $stubW - 24 }}pt; text-align: center; color: {{ $d['stubMuted'] }};">
                {{ $d['badge'] }}
            </div>
            <div class="layer" style="left: {{ $stubX + 8 }}pt; top: {{ 31 + (25 - $labelSize) / 2 }}pt; width: {{ $stubW - 16 }}pt; text-align: center; font-size: {{ $labelSize }}pt; font-weight: bold; letter-spacing: 1pt; color: {{ $d['metal'][1] }};">
                @unless ($d['holo'])<span class="glyph" style="font-size: {{ round($labelSize * 0.5) }}pt; color: {{ $d['accent'] }};">{{ $d['glyph'] }}</span> @endunless{{ $d['label'] }}
            </div>
            <img class="layer" src="{{ $artwork->metal($d, 44, 2) }}" style="left: {{ $stubX + ($stubW - 44) / 2 }}pt; top: 69pt; width: 44pt; height: 1.6pt;" alt="">
            <div class="layer" style="left: {{ $stubX + 8 }}pt; top: 76pt; width: {{ $stubW - 16 }}pt; text-align: center; font-size: 5.8pt; color: {{ $d['stubInk'] }};">
                Admit one · {{ \Illuminate\Support\Str::limit($d['privilege'], 34) }}
            </div>

            <div class="layer" style="left: {{ $stubX + 20 }}pt; top: 94pt; width: {{ $stubW - 40 }}pt; height: 150pt; background: #FFFFFF; border-radius: 11pt;"></div>
            <img class="layer" src="{{ $pass['qr'] }}" style="left: {{ $stubX + ($stubW - 112) / 2 }}pt; top: 100pt; width: 112pt; height: 112pt;" alt="Ticket QR code">
            <div class="layer mono" style="left: {{ $stubX + 20 }}pt; top: 214pt; width: {{ $stubW - 40 }}pt; text-align: center; font-size: 7.4pt; font-weight: bold; letter-spacing: 0.8pt; color: #0E1220;">{{ $ticket->code }}</div>
            <div class="layer" style="left: {{ $stubX + 20 }}pt; top: 225pt; width: {{ $stubW - 40 }}pt; text-align: center; font-size: 5.2pt; font-weight: bold; letter-spacing: 1.6pt; color: #6B7183;">SCAN AT ENTRY</div>
            <div class="layer" style="left: {{ $stubX + 20 }}pt; top: 233pt; width: {{ $stubW - 40 }}pt; text-align: center; font-size: 4.8pt; font-weight: bold; letter-spacing: 1.2pt; color: #1FB86A;">
                <span class="glyph">✓</span> TUVIO VERIFIED
            </div>
        </div>
    </div>
    @unless ($loop->last)
        <div class="page-break"></div>
    @endunless
@endforeach
</body>
</html>
