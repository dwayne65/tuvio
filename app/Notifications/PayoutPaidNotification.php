<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaidNotification extends Notification
{
    use Queueable;

    public function __construct(public Payout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your payout has been processed')
            ->greeting('Good news!')
            ->line("Payout {$this->payout->reference} of {$this->payout->currency} ".number_format($this->payout->amount_minor / 100).' has been processed.')
            ->line('Thank you for using TicketFlow.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_paid',
            'title' => 'Payout processed',
            'message' => "Payout {$this->payout->reference} of {$this->payout->currency} ".number_format($this->payout->amount_minor / 100).' is on its way.',
            'reference' => $this->payout->reference,
        ];
    }
}
