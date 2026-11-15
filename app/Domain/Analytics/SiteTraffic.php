<?php

namespace App\Domain\Analytics;

use App\Models\Event;
use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Platform traffic for the admin dashboard, built from recorded page views.
 * Visitors are unique per day (the visitor hash changes daily), so multi-day
 * visitor totals add up each day's unique visitors.
 */
class SiteTraffic
{
    /** Well-known referrers shown by name instead of host. */
    private const SOURCES = [
        'google.' => 'Google', 'bing.com' => 'Bing', 'yahoo.' => 'Yahoo', 'duckduckgo.com' => 'DuckDuckGo',
        'facebook.com' => 'Facebook', 'fb.' => 'Facebook', 'instagram.com' => 'Instagram', 'whatsapp.' => 'WhatsApp',
        't.co' => 'X (Twitter)', 'x.com' => 'X (Twitter)', 'twitter.com' => 'X (Twitter)', 'tiktok.com' => 'TikTok',
        'linkedin.com' => 'LinkedIn', 'youtube.com' => 'YouTube', 't.me' => 'Telegram',
    ];

    /**
     * @return array{
     *     today: array{views: int, visitors: int},
     *     week: array{views: int, visitors: int},
     *     month: array{views: int, visitors: int, views_change: float|null},
     *     all_time_views: int,
     *     series: list<array{date: string, views: int, visitors: int}>,
     *     top_pages: list<array{path: string, label: string, views: int}>,
     *     sources: list<array{source: string, visits: int}>,
     *     devices: array<string, int>
     * }
     */
    public function summary(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->toDateString();
        $daily = PageView::query()
            ->where('visited_on', '>=', now()->subDays(2 * $days - 1)->toDateString())
            ->selectRaw('visited_on, count(*) as views, count(distinct visitor) as visitors')
            ->groupBy('visited_on')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->visited_on)->toDateString());

        $series = collect(range($days - 1, 0))->map(function (int $ago) use ($daily) {
            $day = now()->subDays($ago)->toDateString();

            return ['date' => $day, 'views' => (int) ($daily[$day]->views ?? 0), 'visitors' => (int) ($daily[$day]->visitors ?? 0)];
        });
        $previousViews = $daily->filter(fn ($row, $day) => $day < $start)->sum('views');
        $monthViews = $series->sum('views');

        return [
            'today' => ['views' => $series->last()['views'], 'visitors' => $series->last()['visitors']],
            'week' => ['views' => $series->slice(-7)->sum('views'), 'visitors' => $series->slice(-7)->sum('visitors')],
            'month' => [
                'views' => $monthViews,
                'visitors' => $series->sum('visitors'),
                'views_change' => $previousViews > 0 ? round(($monthViews - $previousViews) / $previousViews * 100, 1) : null,
            ],
            'all_time_views' => PageView::count(),
            'series' => $series->values()->all(),
            'top_pages' => $this->topPages($start),
            'sources' => $this->sources($start),
            'devices' => PageView::where('visited_on', '>=', $start)->where('is_entry', true)
                ->selectRaw('device, count(*) as visits')->groupBy('device')->pluck('visits', 'device')
                ->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /** @return list<array{path: string, label: string, views: int}> */
    private function topPages(string $start): array
    {
        $rows = PageView::where('visited_on', '>=', $start)
            ->selectRaw('path, count(*) as views')->groupBy('path')->orderByDesc('views')->orderBy('path')->limit(8)->get();

        $slugs = $rows->pluck('path')->filter(fn ($p) => Str::startsWith($p, '/events/'))->map(fn ($p) => Str::after($p, '/events/'));
        $titles = Event::whereIn('slug', $slugs)->pluck('title', 'slug');

        return $rows->map(fn ($row) => [
            'path' => $row->path,
            'label' => $this->label($row->path, $titles->all()),
            'views' => (int) $row->views,
        ])->all();
    }

    /** @param array<string, string> $eventTitles */
    private function label(string $path, array $eventTitles): string
    {
        if (Str::startsWith($path, '/events/') && isset($eventTitles[Str::after($path, '/events/')])) {
            return $eventTitles[Str::after($path, '/events/')];
        }

        return match ($path) {
            '/' => 'Home',
            '/events' => 'Browse events',
            default => Str::of($path)->after('/')->replace(['/', '-'], [' › ', ' '])->ucfirst()->value(),
        };
    }

    /** Where visits started: search engines, social apps, other sites, or direct. @return list<array{source: string, visits: int}> */
    private function sources(string $start): array
    {
        return PageView::where('visited_on', '>=', $start)->where('is_entry', true)
            ->selectRaw('referrer_host, count(*) as visits')->groupBy('referrer_host')->get()
            ->groupBy(fn ($row) => $this->sourceName($row->referrer_host))
            ->map(fn ($rows, $source) => ['source' => $source, 'visits' => (int) $rows->sum('visits')])
            ->sortBy([['visits', 'desc'], ['source', 'asc']])->take(6)->values()->all();
    }

    private function sourceName(?string $host): string
    {
        if (! $host) {
            return 'Direct';
        }
        foreach (self::SOURCES as $needle => $name) {
            // "google." matches google.com, google.co.ug and news.google.com; "t.co" only t.co and its subdomains.
            $matches = str_ends_with($needle, '.')
                ? Str::startsWith($host, $needle) || Str::contains($host, '.'.$needle)
                : $host === $needle || Str::endsWith($host, '.'.$needle);
            if ($matches) {
                return $name;
            }
        }

        return $host;
    }
}
