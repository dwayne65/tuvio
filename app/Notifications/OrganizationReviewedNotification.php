<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The admin's decision on a new organizer account. */
class OrganizationReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public Organization $organization, public bool $approved) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject("{$this->organization->name} is approved on TUVIO")
                ->greeting('You are in!')
                ->line("Your organizer account for {$this->organization->name} has been approved. Your dashboard is now open.")
                ->action('Open my dashboard', route('dashboard'))
                ->line('Create your first event and submit it for review when you are ready to go live.');
        }

        return (new MailMessage)
            ->subject('Update on your TUVIO organizer account')
            ->greeting('About your application')
            ->line("We could not approve the organizer account for {$this->organization->name}.")
            ->line('Reason: '.($this->organization->rejection_reason ?: 'Not given.'))
            ->line('Reply to this email or contact support if you think this is a mistake.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->approved ? 'organization_approved' : 'organization_rejected',
            'title' => $this->approved ? 'Organizer account approved' : 'Organizer account not approved',
            'message' => $this->approved
                ? "{$this->organization->name} is approved. Your dashboard is open."
                : "{$this->organization->name} was not approved: ".($this->organization->rejection_reason ?: 'no reason given').'.',
        ];
    }
}
