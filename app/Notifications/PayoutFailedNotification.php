<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutFailedNotification extends Notification
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
            ->subject('Your payout could not be completed')
            ->line("Payout {$this->payout->reference} of {$this->amount()} could not be sent.")
            ->line('Reason: '.($this->payout->notes ?: 'The transfer was declined.'))
            ->line('No money has left your balance. Check your payout account details, then request the payout again.')
            ->action('Go to payouts', route('dashboard.payouts.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_failed',
            'title' => 'Payout failed',
            'message' => "Payout {$this->payout->reference} of {$this->amount()} could not be sent. The money is back in your balance.",
            'reference' => $this->payout->reference,
        ];
    }

    private function amount(): string
    {
        return $this->payout->currency.' '.number_format($this->payout->amount_minor / 100);
    }
}
