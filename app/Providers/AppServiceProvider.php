<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep this app's dev-server marker isolated from stale workspace hot files.
        $hotFile = public_path('hot-ticketflow');

        if (is_file($hotFile)) {
            $hotUrl = trim((string) file_get_contents($hotFile));
            $host = parse_url($hotUrl, PHP_URL_HOST);
            $port = parse_url($hotUrl, PHP_URL_PORT) ?: 5173;
            $host = $host ? trim($host, '[]') : null;
            $socket = $host && $host !== '::1'
                ? @fsockopen($host, $port, $errno, $error, 0.1)
                : false;

            if (is_resource($socket)) {
                fclose($socket);
            } else {
                // Fall back to the compiled manifest when a dev server is gone.
                $hotFile = storage_path('framework/vite-unavailable');
            }
        }

        Vite::useHotFile($hotFile);

        // Platform administrators bypass all authorization checks.
        Gate::before(function ($user) {
            return $user->is_admin ? true : null;
        });
    }
}
