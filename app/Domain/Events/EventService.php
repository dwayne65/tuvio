<?php

namespace App\Domain\Events;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EventService
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function createDraft(Organization $org, array $data): Event
    {
        return $org->events()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => 'draft',
            'currency' => $data['currency'] ?? $org->default_currency,
        ]);
    }

    public function update(Event $event, array $data): Event
    {
        // Keep slug stable once published to protect shared links.
        if (! $event->isPublished() && isset($data['title']) && $data['title'] !== $event->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $event->id);
        }

        $event->update($data);

        return $event->fresh();
    }

    /**
     * Send an event to the platform admins for approval. Nothing goes live until an
     * admin approves it; the same checks as publishing run now, so organizers hear
     * about missing ticket types or plan limits straight away.
     */
    public function submitForReview(Event $event): Event
    {
        if (! $event->organization->isApproved()) {
            throw new RuntimeException('Your organizer account must be approved before events can be published.');
        }

        if ($event->isPublished() || $event->isInReview()) {
            throw new RuntimeException($event->isInReview() ? 'This event is already waiting for review.' : 'This event is already published.');
        }

        $this->ensurePublishable($event);

        $event->forceFill([
            'status' => 'in_review',
            'review_requested_at' => now(),
            'review_note' => null,
        ])->save();

        return $event->fresh();
    }

    /**
     * An admin approves an event in review and it goes live. The plan limit is checked
     * again, because other events may have gone live since it was submitted.
     */
    public function approve(Event $event, User $admin): Event
    {
        if (! $event->isInReview()) {
            throw new RuntimeException('Only events waiting for review can be approved.');
        }

        $this->ensurePublishable($event);

        $event->forceFill([
            'status' => 'published',
            'published_at' => $event->published_at ?? now(),
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_note' => null,
        ])->save();

        return $event->fresh();
    }

    /** An admin sends an event in review back to the organizer with a note on what to change. */
    public function reject(Event $event, User $admin, string $reason): Event
    {
        if (! $event->isInReview()) {
            throw new RuntimeException('Only events waiting for review can be rejected.');
        }

        $event->forceFill([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'review_note' => $reason,
        ])->save();

        return $event->fresh();
    }

    /** Throws with a human message unless the plan allows another live event and it has ticket types. */
    private function ensurePublishable(Event $event): void
    {
        if (! $this->subscriptions->canPublishEvents($event->organization)) {
            throw new RuntimeException($event->organization->currentPlan()?->lifetime_event_limit
                ? 'Your free Starter event has been used. Switch to Professional, which has no monthly fee, to keep publishing.'
                : 'Your plan does not allow publishing more events. Upgrade your plan to continue.');
        }

        if ($event->ticketTypes()->count() === 0) {
            throw new RuntimeException('Add at least one ticket type before publishing.');
        }
    }

    /** Switch USSD sales on or off for an event, within the plan's USSD event limit. */
    public function setSellOnUssd(Event $event, bool $enabled): Event
    {
        if ($enabled && ! $event->sell_on_ussd) {
            if ($event->start_at?->isPast() ?? true) {
                throw new RuntimeException('Only upcoming events can be sold on USSD.');
            }

            if (! $this->subscriptions->canAddUssdEvent($event->organization)) {
                $limit = $this->subscriptions->ussdEvents($event->organization)['limit'];

                throw new RuntimeException($limit === 0
                    ? 'Your plan does not include USSD ticket sales. Upgrade your plan to sell on USSD.'
                    : "Your plan allows USSD sales for {$limit} upcoming events at a time. Switch another event off, or upgrade your plan.");
            }
        }

        $event->update(['sell_on_ussd' => $enabled]);

        if ($enabled && ! $event->ussd_code) {
            $this->assignUssdCode($event);
        }

        return $event->fresh();
    }

    /**
     * Give the event the next unused dial-in code. Codes start at two digits and are
     * never reused; the unique index stops two events from ever sharing one.
     */
    private function assignUssdCode(Event $event): void
    {
        $event->forceFill(['ussd_code' => max(10, (int) Event::withTrashed()->max('ussd_code') + 1)])->save();
    }

    public function unpublish(Event $event): Event
    {
        $event->update(['status' => 'unpublished']);

        return $event->fresh();
    }

    public function cancel(Event $event): Event
    {
        $event->update(['status' => 'cancelled']);

        return $event->fresh();
    }

    /** Deep-copy an event and its ticket types as a new draft. */
    public function duplicate(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $copy = $event->replicate(['slug', 'status', 'published_at', 'views_count', 'sell_on_ussd', 'ussd_code']);
            $copy->title = $event->title.' (Copy)';
            $copy->slug = $this->uniqueSlug($copy->title);
            $copy->status = 'draft';
            $copy->published_at = null;
            $copy->views_count = 0;
            $copy->save();

            foreach ($event->ticketTypes as $type) {
                $newType = $type->replicate(['quantity_sold', 'quantity_reserved']);
                $newType->event_id = $copy->id;
                $newType->quantity_sold = 0;
                $newType->quantity_reserved = 0;
                $newType->save();
            }

            return $copy;
        });
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 1;
        while (Event::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
