@props(['preheader' => '', 'logo' => null, 'footer' => null])
{{--
    TUVIO branded email shell. Table layout with inline styles, so it renders in
    Gmail, Outlook and Apple Mail alike. $logo is a CID from $message->embed() when
    sending, or a URL when previewing.
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
    <style>
        @media (max-width: 620px) {
            .tf-container { width: 100% !important; }
            .tf-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .tf-stack { display: block !important; width: 100% !important; }
            .tf-stack-gap { padding-top: 16px !important; padding-left: 0 !important; }
            .tf-hero-title { font-size: 30px !important; line-height: 34px !important; }
        }
        a { color: #6415F5; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#F7F8FC; -webkit-text-size-adjust:100%;">
    {{-- Inbox preview text --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ $preheader }}&#8203;&#847;&zwnj;&nbsp;&#8203;&#847;&zwnj;&nbsp;&#8203;&#847;&zwnj;&nbsp;</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F7F8FC;">
        <tr>
            <td align="center" style="padding:28px 12px 40px;">
                <table role="presentation" class="tf-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">
                    {{-- Logo bar --}}
                    <tr>
                        <td class="tf-pad" style="padding:0 8px 18px;">
                            <a href="{{ url('/') }}" style="text-decoration:none;">
                                @if ($logo)
                                    <img src="{{ $logo }}" width="132" height="35" alt="TUVIO" style="display:block; border:0; width:132px; height:auto;">
                                @else
                                    <span style="font-family:Arial,Helvetica,sans-serif; font-size:24px; font-weight:800; color:#101126; letter-spacing:-0.5px;">TUVIO</span>
                                @endif
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#FFFFFF; border-radius:24px; overflow:hidden; box-shadow:0 18px 40px -24px rgba(30,50,110,0.25);">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td class="tf-pad" align="center" style="padding:28px 24px 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:19px; color:#8a90a6;">
                            @if ($footer)
                                {{ $footer }}<br>
                            @endif
                            <strong style="color:#101126;">TUVIO</strong> · Life Happens Here.<br>
                            Secure QR tickets · Mobile Money · Visa · Mastercard<br>
                            <a href="{{ url('/events') }}" style="color:#6415F5; text-decoration:none;">Discover events</a>
                            &nbsp;·&nbsp;
                            <a href="{{ url('/contact') }}" style="color:#6415F5; text-decoration:none;">Help</a>
                            &nbsp;·&nbsp;
                            <a href="{{ url('/privacy') }}" style="color:#6415F5; text-decoration:none;">Privacy</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
