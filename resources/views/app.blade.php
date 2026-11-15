<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f5f7fb">
    <meta name="color-scheme" content="light dark">

    {{-- Day/night theme from the visitor's local clock, applied before first paint (kept in sync with resources/js/lib/theme.ts) --}}
    <script>
        (function () {
            var h = new Date().getHours();
            if (h >= 19 || h < 6) {
                document.documentElement.classList.add('dark');
                document.querySelector('meta[name="theme-color"]').setAttribute('content', '#07080c');
            }
        })();
    </script>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @php($seo ??= \App\Support\Seo::forRequest(request()))
    <title inertia>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <link rel="canonical" href="{{ $seo['url'] }}">
    <meta property="og:site_name" content="TUVIO">
    <meta property="og:type" content="{{ $seo['type'] === 'event' ? 'website' : $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['url'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:locale" content="en_UG">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    @foreach ($seo['jsonLd'] as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    <link rel="icon" href="/brand/favicon-64.png" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-canvas text-ink">
    @inertia
</body>
</html>
