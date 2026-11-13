<x-mail::message>
# Thanks, {{ $ticket->name }}

We've received your message and will reply to this email address as soon as we can.

**Reference:** {{ $ticket->reference }}<br>
**Subject:** {{ $ticket->subject }}

<x-mail::panel>
{{ $ticket->message }}
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
