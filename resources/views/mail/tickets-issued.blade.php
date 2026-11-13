@php
    // $message only exists while sending; previews fall back to inline/URL images.
    $embed = fn (string $data, string $name) => isset($message)
        ? $message->embedData($data, $name, 'image/png')
        : 'data:image/png;base64,'.base64_encode($data);
    $logo = isset($message) ? $message->embed(public_path('brand/tuvio-logo-mail.png')) : asset('brand/tuvio-logo-mail.png');
    $cover = $coverPath ? (isset($message) ? $message->embed($coverPath) : null) : null;
    $font = 'font-family:Arial,Helvetica,sans-serif;';
    $mono = "font-family:'Courier New',Courier,monospace;";
    $count = count($tickets);
    $venueName = $event->type === 'online' ? 'Online event' : ($event->venue_name ?: 'Venue to be announced');
    $venueArea = $event->type !== 'online' ? collect([$event->venue_address, $event->city])->filter()->implode(', ') : null;
@endphp
<x-email.layout
    :logo="$logo"
    :preheader="'Your '.($count === 1 ? 'ticket' : $count.' tickets').' for '.$event->title.' · '.$when.'. Show the QR code at the gate.'"
    :footer="'You received this email because you bought tickets on TUVIO. Order '.$order->reference.'.'"
>
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        @if ($cover)
            <tr>
                <td style="line-height:0; font-size:0; background-color:#0D0A22; border-radius:24px 24px 0 0;">
                    <img src="{{ $cover }}" width="600" alt="{{ $event->title }}" style="display:block; width:100%; height:auto; max-height:280px; object-fit:cover; border:0; border-radius:24px 24px 0 0;">
                </td>
            </tr>
        @endif
        <tr>
            <td class="tf-pad" bgcolor="#0D0A22" style="background-color:#0D0A22; background-image:linear-gradient(160deg,#0D0A22 0%,#1B1150 55%,#4A1FC9 100%); padding:34px 40px 38px; {{ $cover ? '' : 'border-radius:24px 24px 0 0;' }}">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background-color:rgba(34,197,94,0.16); border:1px solid rgba(34,197,94,0.45); border-radius:999px; padding:6px 13px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.6px; color:#86EFAC; text-transform:uppercase;">
                            &#9679;&nbsp; Payment confirmed
                        </td>
                        @if ($countdown)
                            <td style="padding-left:8px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="background-color:rgba(255,255,255,0.10); border-radius:999px; padding:7px 13px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.6px; color:#E9E1FF; text-transform:uppercase;">
                                            {{ $countdown }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endif
                    </tr>
                </table>

                <h1 class="tf-hero-title" style="margin:20px 0 0; {{ $font }} font-size:44px; line-height:46px; font-weight:800; letter-spacing:-1.5px; color:#FFFFFF;">
                    You're in, {{ $firstName }}.
                </h1>
                <p style="margin:12px 0 0; {{ $font }} font-size:16px; line-height:25px; color:rgba(255,255,255,0.74);">
                    {{ $count === 1 ? 'Your ticket is' : 'Your '.$count.' tickets are' }} ready for
                    <strong style="color:#FFFFFF;">{{ $event->title }}</strong>. Show the QR code at the entrance, on your phone or printed.
                </p>

                {{-- Info bar --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:26px; background-color:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:18px;">
                    <tr>
                        <td class="tf-stack" width="34%" valign="top" style="padding:16px 18px;">
                            <p style="margin:0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#A78BFA;">Date</p>
                            <p style="margin:6px 0 0; {{ $font }} font-size:15px; line-height:20px; font-weight:700; color:#FFFFFF;">{{ $when }}</p>
                        </td>
                        <td class="tf-stack" width="26%" valign="top" style="padding:16px 18px; border-left:1px solid rgba(255,255,255,0.12);">
                            <p style="margin:0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#A78BFA;">Time</p>
                            <p style="margin:6px 0 0; {{ $font }} font-size:15px; line-height:20px; font-weight:700; color:#FFFFFF;">{{ $time ?? 'TBA' }}</p>
                        </td>
                        <td class="tf-stack" width="40%" valign="top" style="padding:16px 18px; border-left:1px solid rgba(255,255,255,0.12);">
                            <p style="margin:0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#A78BFA;">{{ $event->type === 'online' ? 'Where' : 'Venue' }}</p>
                            <p style="margin:6px 0 0; {{ $font }} font-size:15px; line-height:20px; font-weight:700; color:#FFFFFF;">{{ $venueName }}</p>
                            @if ($venueArea)
                                <p style="margin:2px 0 0; {{ $font }} font-size:13px; line-height:18px; color:rgba(255,255,255,0.6);">{{ $venueArea }}</p>
                            @endif
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
                    <tr>
                        @if ($calendarUrl)
                            <td style="padding:0 10px 0 0;">
                                <a href="{{ $calendarUrl }}" target="_blank" style="display:inline-block; padding:12px 20px; border-radius:999px; background-color:#FFFFFF; {{ $font }} font-size:13px; font-weight:700; color:#0D0A22; text-decoration:none;">Add to calendar</a>
                            </td>
                        @endif
                        @if ($directionsUrl)
                            <td style="padding:0 10px 0 0;">
                                <a href="{{ $directionsUrl }}" target="_blank" style="display:inline-block; padding:11px 20px; border-radius:999px; border:1px solid rgba(255,255,255,0.35); {{ $font }} font-size:13px; font-weight:700; color:#FFFFFF; text-decoration:none;">Get directions</a>
                            </td>
                        @endif
                        @if ($event->type === 'online' && $event->online_url)
                            <td style="padding:0 10px 0 0;">
                                <a href="{{ $event->online_url }}" target="_blank" style="display:inline-block; padding:12px 20px; border-radius:999px; background-color:#FFFFFF; {{ $font }} font-size:13px; font-weight:700; color:#0D0A22; text-decoration:none;">Join online</a>
                            </td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tickets --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:34px 40px 6px;">
                <p style="margin:0 0 4px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#6415F5;">{{ $count === 1 ? 'Your ticket' : 'Your tickets · '.$count }}</p>
                <p style="margin:0 0 18px; {{ $font }} font-size:14px; line-height:21px; color:#64748B;">Each QR code lets one person in. They're attached as a PDF too, and work offline.</p>

                @foreach ($tickets as $ticket)
                    @php($tier = $ticket['tier'])
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="{{ $tier['baseHex'] }}" style="background-color:{{ $tier['baseHex'] }}; background-image:linear-gradient(135deg,{{ $tier['baseHex'] }} 0%,{{ $tier['baseHex'] }} 55%,{{ $tier['accent'] }} 160%); border-radius:22px; margin-bottom:10px;">
                        <tr>
                            <td height="4" colspan="2" style="height:4px; line-height:4px; font-size:0; border-radius:22px 22px 0 0; background-color:{{ $tier['accent'] }}; background-image:linear-gradient(90deg,{{ $tier['accent2'] }},{{ $tier['accent'] }},{{ $tier['title'] }});">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="tf-stack" valign="middle" style="padding:22px 24px 24px; border-right:2px dashed rgba(255,255,255,0.18);">
                                <p style="margin:0; {{ $font }} font-size:12px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:{{ $tier['accent'] }};">{{ $tier['glyph'] }}&nbsp; {{ $tier['label'] }}</p>
                                <p style="margin:10px 0 0; {{ $font }} font-size:22px; line-height:26px; font-weight:800; letter-spacing:-0.4px; color:#FFFFFF; text-transform:uppercase;">{{ $event->title }}</p>
                                <p style="margin:4px 0 0; {{ $font }} font-size:13px; line-height:19px; color:{{ $tier['muted'] }};">{{ $ticket['type'] }} · {{ $when }}</p>

                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;">
                                    <tr>
                                        <td style="padding-right:22px;">
                                            <p style="margin:0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:{{ $tier['muted'] }};">Admit one</p>
                                            <p style="margin:4px 0 0; {{ $font }} font-size:15px; font-weight:700; color:#FFFFFF;">{{ $ticket['attendee'] }}</p>
                                        </td>
                                        <td>
                                            <p style="margin:0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:{{ $tier['muted'] }};">Ticket code</p>
                                            <p style="margin:4px 0 0; {{ $mono }} font-size:14px; font-weight:700; letter-spacing:0.5px; color:#FFFFFF;">{{ $ticket['code'] }}</p>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:18px 0 0; {{ $font }} font-size:13px;">
                                    <a href="{{ $ticket['download_url'] }}" style="color:{{ $tier['title'] }}; font-weight:700; text-decoration:none;">Download ticket (PDF) &rarr;</a>
                                </p>
                            </td>
                            <td class="tf-stack" width="176" align="center" valign="middle" style="padding:20px 20px 22px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" style="background-color:#FFFFFF; border-radius:18px;">
                                    <tr>
                                        <td align="center" style="padding:12px 12px 10px;">
                                            <img src="{{ $embed($ticket['qr_png'], $ticket['code'].'.png') }}" width="132" height="132" alt="QR code for ticket {{ $ticket['code'] }}" style="display:block; width:132px; height:132px; border:0;">
                                            <p style="margin:6px 0 0; {{ $font }} font-size:10px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:#8A8AA0;">Scan at entry</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                @endforeach
            </td>
        </tr>
    </table>

    {{-- How entry works --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:26px 40px 6px;">
                <p style="margin:0 0 14px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#6415F5;">How entry works</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        @foreach ([
                            ['1', 'Open your ticket', 'In this email or the attached PDF. No data needed.'],
                            ['2', 'Show the QR code', 'Turn up your screen brightness at the gate.'],
                            ['3', 'Enjoy the show', 'Each code scans once, so keep it to yourself.'],
                        ] as [$number, $heading, $text])
                            <td class="tf-stack" width="33%" valign="top" style="padding:0 {{ $loop->last ? 0 : 10 }}px 12px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F6F4FF; border-radius:16px;">
                                    <tr>
                                        <td style="padding:16px;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td width="28" height="28" align="center" valign="middle" bgcolor="#6415F5" style="width:28px; height:28px; border-radius:999px; background-color:#6415F5; {{ $font }} font-size:13px; font-weight:800; color:#FFFFFF;">{{ $number }}</td>
                                                </tr>
                                            </table>
                                            <p style="margin:12px 0 0; {{ $font }} font-size:14px; line-height:19px; font-weight:800; color:#101126;">{{ $heading }}</p>
                                            <p style="margin:4px 0 0; {{ $font }} font-size:12.5px; line-height:18px; color:#64748B;">{{ $text }}</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Receipt --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:20px 40px 8px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #ECE9F6; border-radius:18px;">
                    <tr>
                        <td style="padding:20px 22px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="{{ $font }} font-size:11px; font-weight:700; letter-spacing:1.8px; text-transform:uppercase; color:#8A90A6;">Receipt</td>
                                    <td align="right" style="{{ $mono }} font-size:12px; font-weight:700; color:#475569;">{{ $order->reference }}</td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:10px; {{ $font }} font-size:14px; color:#475569;">
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding:6px 0;">{{ $item['label'] }} <span style="color:#8A90A6;">× {{ $item['quantity'] }}</span></td>
                                        <td align="right" style="padding:6px 0; color:#101126;">{{ $item['total'] }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($totals as $label => $value)
                                    @continue($label === 'Subtotal' && count($totals) === 1)
                                    <tr>
                                        <td style="padding:6px 0; color:#8A90A6;">{{ $label }}</td>
                                        <td align="right" style="padding:6px 0; color:#101126;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding:14px 0 2px; border-top:1px dashed #DCD6F3; font-size:16px; font-weight:800; color:#101126;">Total paid</td>
                                    <td align="right" style="padding:14px 0 2px; border-top:1px dashed #DCD6F3; font-size:20px; font-weight:800; color:#6415F5;">{{ $total }}</td>
                                </tr>
                            </table>
                            <p style="margin:10px 0 0; {{ $font }} font-size:12px; line-height:19px; color:#8A90A6;">
                                @if ($paidWith) Paid with {{ $paidWith }} · @endif {{ $paidAt }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Closing --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" align="center" style="padding:26px 40px 38px;">
                <x-email.button :href="$eventUrl">View event page</x-email.button>

                @if ($organizer)
                    <p style="margin:22px 0 0; {{ $font }} font-size:13px; line-height:20px; color:#8A90A6; text-align:center;">
                        Organized by <strong style="color:#475569;">{{ $organizer->name }}</strong>.
                        @if ($organizer->support_email)
                            Questions about the event? <a href="mailto:{{ $organizer->support_email }}" style="color:#6415F5; text-decoration:none;">{{ $organizer->support_email }}</a>
                        @endif
                    </p>
                @endif
                <p style="margin:10px 0 0; {{ $font }} font-size:12px; line-height:18px; color:#A0A6B8; text-align:center;">
                    Bring a valid ID if the organizer asks for one. Never share your QR code: anyone holding it can enter.
                </p>
            </td>
        </tr>
    </table>
</x-email.layout>
