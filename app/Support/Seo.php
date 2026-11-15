<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What search engines and link previews see before any JavaScript runs: the page title,
 * description, share image, canonical URL, robots rule and schema.org structured data.
 * The root Blade view renders it for every Inertia page.
 */
class Seo
{
    public const SITE_NAME = 'TUVIO';

    public const TAGLINE = 'Online event tickets in Uganda';

    public const DEFAULT_DESCRIPTION = 'Buy tickets for concerts, parties, conferences and events in Kampala and across Uganda. Pay with MTN or Airtel Mobile Money, Visa or Mastercard and get your QR ticket instantly. Free account.';

    public const CONTACT_EMAIL = 'dicksonfx4@gmail.com';

    public const CONTACT_PHONE = '+256761664422';

    /**
     * Public marketing pages by path: [title, description]. Paths not listed here and not
     * public content (dashboards, account, checkout, sign-in) are kept out of search results.
     *
     * @var array<string, array{string, string}>
     */
    private const PAGES = [
        '/' => ['TUVIO · Online event tickets in Uganda', self::DEFAULT_DESCRIPTION],
        '/events' => ['Upcoming events & tickets in Uganda', 'Find upcoming concerts, parties, festivals, conferences and more in Kampala and across Uganda, and buy tickets online with Mobile Money.'],
        '/categories' => ['Event categories', 'Browse events by category: music, nightlife, conferences, sports, arts, church and more. Book tickets online on TUVIO.'],
        '/pricing' => ['Pricing for event organizers', 'Sell tickets online for free. Create your event, set ticket tiers and get paid to Mobile Money as soon as tickets sell.'],
        '/about' => ['About TUVIO', 'TUVIO is an online ticketing platform for events in Uganda: discover events, buy tickets with Mobile Money and enter with a QR code.'],
        '/contact' => ['Contact us', 'Get help with tickets, payments or your event. Email '.self::CONTACT_EMAIL.' or call +256 761 664 422.'],
        '/terms' => ['Terms of service', 'The terms for using TUVIO to buy and sell event tickets.'],
        '/privacy' => ['Privacy policy', 'How TUVIO collects, uses and protects your personal information.'],
    ];

    /**
     * @return array{title: string, description: string, image: string, url: string, type: string, robots: string, jsonLd: list<array<string, mixed>>}
     */
    public static function forRequest(Request $request): array
    {
        $path = '/'.ltrim($request->path(), '/');
        [$title, $description] = self::PAGES[$path] ?? [self::SITE_NAME, self::DEFAULT_DESCRIPTION];

        return [
            'title' => $path === '/' || ! isset(self::PAGES[$path]) ? $title : "{$title} · ".self::SITE_NAME,
            'description' => $description,
            'image' => self::defaultImage(),
            'url' => url($path === '/' ? '/' : $path),
            'type' => 'website',
            'robots' => isset(self::PAGES[$path]) ? 'index, follow' : 'noindex, follow',
            'jsonLd' => $path === '/' ? [self::organization(), self::website()] : [],
        ];
    }

    /**
     * @return array{title: string, description: string, image: string, url: string, type: string, robots: string, jsonLd: list<array<string, mixed>>}
     */
    public static function forEvent(Event $event): array
    {
        $event->loadMissing(['organization', 'ticketTypes']);
        $where = $event->type === 'online' ? 'Online' : collect([$event->venue_name, $event->city])->filter()->implode(', ');
        $when = $event->start_at?->timezone($event->timezone ?: config('app.timezone'))->format('D j M Y');
        $summary = self::plain($event->description);
        $description = Str::limit(collect([
            collect([$when, $where])->filter()->implode(' · '),
            $summary ?: 'Get your tickets online on TUVIO.',
        ])->filter()->implode('. '), 300);

        return [
            'title' => $event->title.' · Tickets · '.self::SITE_NAME,
            'description' => $description,
            'image' => self::absolute($event->cover_url) ?? self::defaultImage(),
            'url' => route('events.show', $event),
            'type' => 'event',
            // Unlisted or unapproved events stay reachable by link but out of search results.
            'robots' => $event->isPublished() && $event->visibility === 'public' ? 'index, follow, max-image-preview:large' : 'noindex, nofollow',
            'jsonLd' => [self::eventSchema($event, $summary)],
        ];
    }

    /** @return array<string, mixed> */
    private static function eventSchema(Event $event, string $summary): array
    {
        $online = $event->type === 'online';
        $hybrid = $event->type === 'hybrid';
        $place = [
            '@type' => 'Place',
            'name' => $event->venue_name ?: $event->city ?: 'Uganda',
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $event->venue_address,
                'addressLocality' => $event->city,
                'addressCountry' => 'UG',
            ]),
        ];
        if ($event->latitude && $event->longitude) {
            $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $event->latitude, 'longitude' => (float) $event->longitude];
        }
        $virtual = ['@type' => 'VirtualLocation', 'url' => route('events.show', $event)];

        $offers = $event->ticketTypes->where('is_hidden', false)->map(fn ($type) => array_filter([
            '@type' => 'Offer',
            'name' => $type->name,
            'price' => number_format($type->price_minor / 100, 2, '.', ''),
            'priceCurrency' => $type->currency,
            'availability' => $type->availableQuantity() > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url' => route('events.show', $event),
            'validFrom' => ($type->sales_start_at ?? $event->published_at ?? $event->created_at)?->toIso8601String(),
        ]))->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => $summary ?: null,
            'startDate' => $event->start_at?->toIso8601String(),
            'endDate' => $event->end_at?->toIso8601String(),
            'eventStatus' => $event->status === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/'.($online ? 'OnlineEventAttendanceMode' : ($hybrid ? 'MixedEventAttendanceMode' : 'OfflineEventAttendanceMode')),
            'location' => $online ? $virtual : ($hybrid ? [$place, $virtual] : $place),
            'image' => array_values(array_filter([self::absolute($event->cover_url)])),
            'organizer' => $event->organization ? ['@type' => 'Organization', 'name' => $event->organization->name, 'url' => url('/')] : null,
            'offers' => $offers ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    private static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => self::SITE_NAME,
            'alternateName' => 'TUVIO Event Ticketing',
            'url' => url('/'),
            'logo' => url('/brand/icon-512.png'),
            'slogan' => 'Life Happens Here.',
            'email' => self::CONTACT_EMAIL,
            'telephone' => self::CONTACT_PHONE,
            'areaServed' => 'UG',
            'contactPoint' => [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => self::CONTACT_EMAIL,
                'telephone' => self::CONTACT_PHONE,
                'areaServed' => 'UG',
                'availableLanguage' => ['English'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::SITE_NAME,
            'alternateName' => ['TUVIO Tickets', 'tuvio.org'],
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/events').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private static function defaultImage(): string
    {
        return url('/brand/og-image.png');
    }

    private static function absolute(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /** Description text without markup, collapsed onto one line. */
    private static function plain(?string $html): string
    {
        return Str::squish(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5));
    }
}
