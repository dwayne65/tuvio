<?php

namespace App\Notifications;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Sent on demand to the invite email, which may not belong to an account yet. */
class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public OrganizationMember $invite, public string $invitedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invite->organization->name;
        $email = Str::lower((string) $this->invite->invite_email);

        return (new MailMessage)
            ->subject("{$this->invitedBy} invited you to join {$organization} on TUVIO")
            ->view(['mail.team-invitation', 'mail.team-invitation-text'], [
                'organization' => $organization,
                'invitedBy' => $this->invitedBy,
                'role' => $this->invite->role->label(),
                'roleDescription' => $this->invite->role->description(),
                'email' => $email,
                'hasAccount' => User::whereRaw('lower(email) = ?', [$email])->exists(),
                'url' => route('invitations.show', $this->invite->invite_token),
                'ttlDays' => OrganizationMember::INVITE_TTL_DAYS,
            ]);
    }
}
