<?php

namespace App\Providers;

use App\Domain\Sms\AfricasTalkingSmsSender;
use App\Domain\Sms\LogSmsSender;
use App\Domain\Sms\SmsSender;
use App\Http\Controllers\HomeController;
use App\Models\Event as EventModel;
use App\Models\EventCategory;
use App\Models\TicketType;
use App\Support\DatabaseFilesystemAdapter;
use App\Support\ResilientPostgresConnector;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.pgsql', ResilientPostgresConnector::class);

        $this->app->bind(SmsSender::class, fn () => match (config('ticketflow.sms.driver')) {
            'africastalking' => new AfricasTalkingSmsSender(config('ticketflow.sms.africastalking')),
            default => new LogSmsSender,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('database', function ($app, array $config): FilesystemAdapter {
            $adapter = new DatabaseFilesystemAdapter($app['db']->connection($config['connection'] ?? null));

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        // The homepage caches its event list; refresh it whenever what it shows changes.
        foreach ([EventModel::class, TicketType::class, EventCategory::class] as $model) {
            $model::saved(fn () => HomeController::forgetCache());
            $model::deleted(fn () => HomeController::forgetCache());
        }

        // A persistent connection outlives the request that opened it. If that request died
        // mid-transaction, roll it back so the next request's writes are not silently lost.
        // inTransaction() reads libpq's local state, so this costs no round trip.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'pgsql' || ! ($event->connection->getConfig('options')[PDO::ATTR_PERSISTENT] ?? false)) {
                return;
            }

            $pdo = $event->connection->getPdo();

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        });

        // Keep this app's dev-server marker isolated from stale workspace hot files.
        $hotFile = public_path('hot-ticketflow');

        // The dev server only listens on this machine, so visitors arriving through a
        // tunnel or the LAN (e.g. a phone) must always get the compiled build.
        $isLocalVisitor = $this->app->runningInConsole()
            || in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true);

        if (! $isLocalVisitor) {
            $hotFile = storage_path('framework/vite-unavailable');
        } elseif (is_file($hotFile)) {
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

        // Platform administrators bypass all authorization checks, but only once
        // their account is protected by two-factor authentication.
        Gate::before(function ($user) {
            return $user->is_admin && $user->hasTwoFactorEnabled() ? true : null;
        });

        // Long, mixed passwords; in production also refuse ones exposed in known breaches.
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
