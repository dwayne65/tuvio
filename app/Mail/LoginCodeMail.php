<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The one-time code that completes a sign-in. Sent at once, never queued, as the user is waiting for it. */
class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your TUVIO sign-in code");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.login-code',
            text: 'mail.login-code-text',
            with: [
                'firstName' => str($this->user->name)->trim()->before(' ')->title()->value() ?: 'there',
                'code' => $this->code,
                'minutes' => $this->minutes,
                'email' => $this->user->email,
                'requestedAt' => now()->timezone($this->user->timezone ?: config('app.timezone'))->format('j M Y, g:i A T'),
            ],
        );
    }
}
