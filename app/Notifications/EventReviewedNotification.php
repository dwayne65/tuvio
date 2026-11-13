<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The admin's decision on an event submitted for publishing. */
class EventReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public Event $event, public bool $approved) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject("{$this->event->title} is live on TUVIO")
                ->greeting('Your event is published!')
                ->line("An admin approved \"{$this->event->title}\". It is now live and tickets are on sale.")
                ->action('View event page', route('events.show', $this->event->slug));
        }

        return (new MailMessage)
            ->subject("Changes needed: {$this->event->title}")
            ->greeting('Your event was not approved yet')
            ->line("An admin reviewed \"{$this->event->title}\" and asked for changes before it can go live.")
            ->line('Reviewer note: '.($this->event->review_note ?: 'Not given.'))
            ->action('Edit event', route('dashboard.events.edit', $this->event->slug))
            ->line('Make the changes, then submit it for review again.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->approved ? 'event_approved' : 'event_rejected',
            'title' => $this->approved ? 'Event approved and live' : 'Event needs changes',
            'message' => $this->approved
                ? "\"{$this->event->title}\" is published."
                : "\"{$this->event->title}\": ".($this->event->review_note ?: 'changes requested').'.',
        ];
    }
}
