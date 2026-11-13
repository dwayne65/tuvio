<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells platform admins that a new organizer or an event is waiting for their approval. */
class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    /**
     * @param  'organization'|'event'  $kind
     */
    public function __construct(public string $kind, public string $name, public string $requestedBy) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->kind === 'organization' ? 'organizer account' : 'event';

        return (new MailMessage)
            ->subject("Approval needed: {$this->name}")
            ->greeting('A new '.$what.' needs review')
            ->line("{$this->requestedBy} submitted \"{$this->name}\" and it is waiting for your approval.")
            ->action('Review now', route('admin.approvals'))
            ->line('Nothing goes live until an admin approves it.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => "{$this->kind}_review_requested",
            'title' => $this->kind === 'organization' ? 'Organizer awaiting approval' : 'Event awaiting approval',
            'message' => "{$this->requestedBy} submitted \"{$this->name}\".",
        ];
    }
}
