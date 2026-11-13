@props(['href', 'variant' => 'primary'])
{{-- Bulletproof email button: a table cell link that survives Outlook. --}}
@php
    $styles = $variant === 'primary'
        ? 'background-color:#6415F5; color:#FFFFFF; border:1px solid #6415F5;'
        : 'background-color:#FFFFFF; color:#101126; border:1px solid #dcd6f3;';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="display:inline-table;">
    <tr>
        <td align="center" style="border-radius:999px; {{ $styles }}">
            <a href="{{ $href }}" target="_blank" style="display:inline-block; padding:13px 26px; font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.3px; text-decoration:none; border-radius:999px; {{ $styles }}">{{ $slot }}</a>
        </td>
    </tr>
</table>
