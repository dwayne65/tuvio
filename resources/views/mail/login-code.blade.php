@php
    $logo = isset($message) ? $message->embed(public_path('brand/tuvio-logo-mail.png')) : asset('brand/tuvio-logo-mail.png');
    $font = 'font-family:Arial,Helvetica,sans-serif;';
@endphp
<x-email.layout
    :logo="$logo"
    :preheader="'Your TUVIO sign-in code is '.$code.'. It expires in '.$minutes.' minutes.'"
    :footer="'This code was requested for '.$email.' on '.$requestedAt.'.'"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="background-color:#101126; background-image:linear-gradient(135deg,#6415F5 0%,#3a0c95 45%,#101126 100%); padding:40px 40px 38px; border-radius:24px 24px 0 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background-color:rgba(255,255,255,0.14); border-radius:999px; padding:6px 13px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.6px; color:#e9e1ff; text-transform:uppercase;">
                            Sign-in verification
                        </td>
                    </tr>
                </table>
                <h1 class="tf-hero-title" style="margin:18px 0 0; {{ $font }} font-size:34px; line-height:39px; font-weight:800; letter-spacing:-1px; color:#FFFFFF;">
                    Hi {{ $firstName }}, here's your code
                </h1>
                <p style="margin:12px 0 0; {{ $font }} font-size:16px; line-height:24px; color:rgba(255,255,255,0.78);">
                    Enter it on the sign-in page to finish signing in to your TUVIO account.
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:32px 40px 8px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F7F8FC; border-radius:18px;">
                    <tr>
                        <td align="center" style="padding:26px 22px;">
                            <p style="margin:0; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#8a90a6;">Your sign-in code</p>
                            <p style="margin:10px 0 0; font-family:'Courier New',Courier,monospace; font-size:40px; line-height:46px; font-weight:800; letter-spacing:10px; color:#6415F5;">{{ $code }}</p>
                            <p style="margin:8px 0 0; {{ $font }} font-size:13px; line-height:20px; color:#475569;">Expires in {{ $minutes }} minutes &middot; works once</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:24px 40px 36px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFF4E5; border-radius:18px;">
                    <tr>
                        <td style="padding:18px 22px; {{ $font }} font-size:13px; line-height:21px; color:#7a4a05;">
                            <strong>Didn't try to sign in?</strong> Someone may know your password. Don't share this code with anyone, TUVIO staff will never ask for it. Change your password from your account settings.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</x-email.layout>
