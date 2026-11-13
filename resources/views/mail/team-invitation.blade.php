@php
    // $message only exists while sending; previews fall back to the public URL.
    $logo = isset($message) ? $message->embed(public_path('brand/tuvio-logo-mail.png')) : asset('brand/tuvio-logo-mail.png');
    $font = 'font-family:Arial,Helvetica,sans-serif;';
@endphp
<x-email.layout
    :logo="$logo"
    :preheader="$invitedBy.' invited you to join '.$organization.' as '.$role.'. Accept within '.$ttlDays.' days.'"
    :footer="'This invitation was sent to '.$email.'. If you were not expecting it, you can ignore this email.'"
>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="background-color:#101126; background-image:linear-gradient(135deg,#6415F5 0%,#3a0c95 45%,#101126 100%); padding:40px 40px 38px; border-radius:24px 24px 0 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="background-color:rgba(255,255,255,0.14); border-radius:999px; padding:6px 13px; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.6px; color:#e9e1ff; text-transform:uppercase;">
                            Team invitation
                        </td>
                    </tr>
                </table>
                <h1 class="tf-hero-title" style="margin:18px 0 0; {{ $font }} font-size:34px; line-height:39px; font-weight:800; letter-spacing:-1px; color:#FFFFFF;">
                    Join {{ $organization }} on TUVIO
                </h1>
                <p style="margin:12px 0 0; {{ $font }} font-size:16px; line-height:24px; color:rgba(255,255,255,0.78);">
                    <strong style="color:#FFFFFF;">{{ $invitedBy }}</strong> invited you to help run events as part of the {{ $organization }} team.
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:32px 40px 8px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F7F8FC; border-radius:18px;">
                    <tr>
                        <td style="padding:20px 22px;">
                            <p style="margin:0; {{ $font }} font-size:11px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#8a90a6;">Your role</p>
                            <p style="margin:6px 0 0; {{ $font }} font-size:20px; line-height:26px; font-weight:800; color:#6415F5;">{{ $role }}</p>
                            <p style="margin:6px 0 0; {{ $font }} font-size:14px; line-height:22px; color:#475569;">{{ $roleDescription }}</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
                    <tr>
                        <td align="center">
                            <x-email.button :href="$url">{{ $hasAccount ? 'Accept invitation' : 'Create account & join' }}</x-email.button>
                        </td>
                    </tr>
                </table>
                <p style="margin:14px 0 0; {{ $font }} font-size:13px; line-height:20px; color:#8a90a6; text-align:center;">
                    @if ($hasAccount)
                        Sign in as {{ $email }} to accept.
                    @else
                        It takes a minute: choose a name and password, and you're in.
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="tf-pad" style="padding:24px 40px 36px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEE9FF; border-radius:18px;">
                    <tr>
                        <td style="padding:18px 22px; {{ $font }} font-size:13px; line-height:21px; color:#3a2a6b;">
                            &bull; This link expires in {{ $ttlDays }} days and works only for {{ $email }}.<br>
                            &bull; Button not working? Paste this into your browser:<br>
                            <a href="{{ $url }}" style="color:#6415F5; word-break:break-all;">{{ $url }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</x-email.layout>
