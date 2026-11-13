<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sends the organizer's or platform's answer back to the customer. */
class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Re: {$this->ticket->subject} [{$this->ticket->reference}]");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-reply', with: ['ticket' => $this->ticket]);
    }
}
