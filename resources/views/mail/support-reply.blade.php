<x-mail::message>
# Hi {{ $ticket->name }},

{{ $ticket->reply }}

---

**Your original message** ({{ $ticket->reference }}):

<x-mail::panel>
{{ $ticket->message }}
</x-mail::panel>

Need anything else? Just reply to this email.

Thanks,<br>
{{ $ticket->organization?->name ?? config('app.name') }}
</x-mail::message>
