<?php

namespace App\Support;

use App\Models\Event;

/** Transforms an Event into the shape the React front-end expects (EventSummary). */
class EventPresenter
{
    public static function summary(Event $event): array
    {
        $onSale = $event->ticketTypes->filter(fn ($t) => ! $t->is_hidden);
        $paid = $onSale->where('kind', '!=', 'free')->where('price_minor', '>', 0);
        $minPrice = $paid->min('price_minor');
        $isFree = $onSale->isNotEmpty() && $paid->isEmpty();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'cover_image_path' => $event->cover_url,
            'start_at' => $event->start_at?->toIso8601String(),
            'venue_name' => $event->venue_name,
            'city' => $event->city,
            'type' => $event->type,
            'category' => $event->category ? [
                'id' => $event->category->id,
                'name' => $event->category->name,
                'slug' => $event->category->slug,
                'icon' => $event->category->icon,
                'color' => $event->category->color,
            ] : null,
            'organization' => $event->organization ? [
                'id' => $event->organization->id,
                'name' => $event->organization->name,
                'slug' => $event->organization->slug,
            ] : null,
            'price_from' => $minPrice !== null ? [
                'minor' => $minPrice,
                'currency' => $event->currency,
                'formatted' => $event->currency.' '.number_format($minPrice / 100),
            ] : null,
            'is_free' => $isFree,
            'is_featured' => (bool) $event->is_featured,
        ];
    }
}
