<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirms to a customer that their support request was received. */
class SupportReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We got your message [{$this->ticket->reference}]");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-received', with: ['ticket' => $this->ticket]);
    }
}
