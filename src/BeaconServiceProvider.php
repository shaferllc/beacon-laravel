<?php

declare(strict_types=1);

namespace Beacon\Laravel;

use Beacon\Beacon;
use Beacon\Laravel\Console\HeartbeatCommand;
use Beacon\Laravel\Http\VerifyBeaconWebhook;
use Beacon\Transport\CurlTransport;
use Beacon\Transport\Transport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class BeaconServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beacon.php', 'beacon');

        // Rebind Transport to use your own (e.g. a Psr18Transport around Guzzle, or a fake in tests).
        $this->app->bindIf(Transport::class, CurlTransport::class);

        $this->app->singleton(Beacon::class, fn ($app) => new Beacon(
            (string) $app['config']->get('beacon.token'),
            $app['config']->get('beacon.url'),
            $app['config']->get('beacon.page'),
            (float) $app['config']->get('beacon.timeout', 10),
            transport: $app->make(Transport::class),
        ));
        $this->app->alias(Beacon::class, 'beacon');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/beacon.php' => $this->app->configPath('beacon.php')], 'beacon-config');

        if ($this->app->runningInConsole()) {
            $this->commands([HeartbeatCommand::class]);
        }

        $this->app->make(Router::class)->aliasMiddleware('beacon.webhook', VerifyBeaconWebhook::class);

        /*
         * $schedule->command('backup:run')->daily()->pingBeacon('heartbeat-token');
         * Pings only when the task succeeds; a failed ping is reported, never thrown.
         */
        Event::macro('pingBeacon', function (string $tokenOrUrl): Event {
            /** @var Event $this */
            return $this->onSuccess(fn () => rescue(
                fn () => Beacon::ping($tokenOrUrl, config('beacon.url'), timeout: (float) config('beacon.timeout', 10), transport: app(Transport::class)),
            ));
        });
    }
}
