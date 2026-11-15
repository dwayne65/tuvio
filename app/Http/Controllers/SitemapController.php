<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /** Public pages that search engines should know about. */
    private const PAGES = ['/' => 'daily', '/events' => 'daily', '/categories' => 'weekly', '/pricing' => 'monthly', '/about' => 'monthly', '/contact' => 'monthly', '/terms' => 'yearly', '/privacy' => 'yearly'];

    /** Lists the public pages and every public event, so new events are found quickly. */
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(30), function (): string {
            $urls = collect(self::PAGES)->map(fn (string $frequency, string $path) => [
                'loc' => url($path), 'lastmod' => null, 'changefreq' => $frequency, 'priority' => $path === '/' ? '1.0' : '0.8',
            ])->values();

            // Upcoming events first; past ones stay listed for a while so their pages keep ranking.
            Event::query()->public()
                ->where('start_at', '>=', now()->subMonths(6))
                ->orderByDesc('start_at')
                ->limit(5000)
                ->get(['id', 'slug', 'start_at', 'updated_at'])
                ->each(fn (Event $event) => $urls->push([
                    'loc' => route('events.show', $event),
                    'lastmod' => $event->updated_at?->toAtomString(),
                    'changefreq' => $event->start_at?->isFuture() ? 'daily' : 'monthly',
                    'priority' => $event->start_at?->isFuture() ? '0.9' : '0.4',
                ]));

            $body = $urls->map(fn (array $url) => '<url><loc>'.e($url['loc']).'</loc>'
                .($url['lastmod'] ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '')
                .'<changefreq>'.$url['changefreq'].'</changefreq><priority>'.$url['priority'].'</priority></url>')->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
