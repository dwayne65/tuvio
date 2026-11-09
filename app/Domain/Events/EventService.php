<?php

namespace App\Domain\Events;

use App\Domain\Subscriptions\SubscriptionService;
use App\Models\Event;
use App\Models\Organization;
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
     * Publish an event. Enforces: active subscription within plan limits, and at
     * least one ticket type. Throws with a human message otherwise.
     */
    public function publish(Event $event): Event
    {
        if (! $this->subscriptions->canPublishEvents($event->organization)) {
            throw new RuntimeException('Your subscription does not allow publishing more events. Upgrade your plan to continue.');
        }

        if ($event->ticketTypes()->count() === 0) {
            throw new RuntimeException('Add at least one ticket type before publishing.');
        }

        $event->update([
            'status' => 'published',
            'published_at' => $event->published_at ?? now(),
        ]);

        return $event->fresh();
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
            $copy = $event->replicate(['slug', 'status', 'published_at', 'views_count']);
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
