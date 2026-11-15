<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts the pages people open, for the admin dashboard. Only successful page loads and
 * Inertia page visits count; bots, prefetches, partial reloads, the admin area and
 * platform admins themselves are skipped. Recording never affects the response.
 */
class RecordPageView
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|fetch|monitor|lighthouse|headless|curl|wget|python|go-http|java\/|okhttp|axios|node-fetch|php|scrapy|facebookexternalhit|whatsapp|telegram|google-|bingpreview|uptime/i';

    private const SKIP_PATHS = ['admin', 'admin/*', 'up', 'media/*', 'build/*', 'sitemap.xml', 'robots.txt', 'webhooks/*', 'internal/*'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->isPageView($request, $response)) {
            return;
        }

        rescue(fn () => PageView::create([
            'visited_on' => now()->toDateString(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
            'visitor' => $this->visitor($request),
            'is_entry' => ! $request->header('X-Inertia'),
            'referrer_host' => $request->header('X-Inertia') ? null : $this->referrerHost($request),
            'device' => $this->device((string) $request->userAgent()),
        ]));
    }

    private function isPageView(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $request->is(self::SKIP_PATHS)) {
            return false;
        }

        $userAgent = (string) $request->userAgent();
        if ($userAgent === '' || preg_match(self::BOTS, $userAgent)) {
            return false;
        }

        if ($request->header('X-Inertia-Partial-Component') || $request->header('Purpose') === 'prefetch' || $request->header('Sec-Purpose') === 'prefetch') {
            return false;
        }

        if ($request->user()?->is_admin) {
            return false;
        }

        // An Inertia visit (client-side navigation) or a full HTML page.
        return $request->header('X-Inertia')
            ? $response->headers->has('X-Inertia')
            : str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /** Same visitor all day, unlinkable across days, never storing the IP. */
    private function visitor(Request $request): string
    {
        return substr(hash_hmac('sha256', $request->ip().'|'.$request->userAgent().'|'.now()->toDateString(), (string) config('app.key')), 0, 32);
    }

    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (! $host) {
            return null;
        }
        $host = Str::lower(Str::after($host, 'www.'));

        return $host === Str::after($request->getHost(), 'www.') ? null : Str::limit($host, 120, '');
    }

    private function device(string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk/i', $userAgent) => 'tablet',
            (bool) preg_match('/mobi|android|iphone|ipod|opera mini/i', $userAgent) => 'mobile',
            default => 'desktop',
        };
    }
}
