<?php

declare(strict_types=1);

namespace Beacon\Laravel\Tests;

use Beacon\Beacon;
use Beacon\Laravel\BeaconServiceProvider;
use Beacon\Laravel\Facades\Beacon as BeaconFacade;
use Beacon\Transport\Response;
use Beacon\Transport\Transport;
use Beacon\Webhook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;

final class BeaconLaravelTest extends TestCase
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    private array $sent = [];

    /** @var list<Response> */
    private array $responses = [];

    protected function getPackageProviders($app): array
    {
        return [BeaconServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Beacon' => BeaconFacade::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('beacon.token', 'bchk_laravel');
        $app['config']->set('beacon.url', 'https://beacon.test');
        $app['config']->set('beacon.page', 'acme');
        $app['config']->set('beacon.webhook_secret', 'whsec_laravel');

        $app->instance(Transport::class, new class($this) implements Transport
        {
            public function __construct(private BeaconLaravelTest $test) {}

            public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
            {
                return $this->test->record(compact('method', 'url', 'headers', 'body'));
            }
        });
    }

    public function record(array $request): Response
    {
        $this->sent[] = $request;

        return array_shift($this->responses) ?? new Response(200, [], '{"ok":true}');
    }

    public function test_config_and_facade_drive_the_client(): void
    {
        $this->responses[] = new Response(201, [], '{"id":"10","title":"Down"}');

        $incident = BeaconFacade::createIncident(['title' => 'Down', 'impact' => 'major']);

        $this->assertSame('10', $incident['id']);
        $this->assertSame($this->app->make(Beacon::class), $this->app->make('beacon'));
        $this->assertSame('POST', $this->sent[0]['method']);
        $this->assertSame('https://beacon.test/api/v1/pages/acme/incidents', $this->sent[0]['url']);
        $this->assertSame('Bearer bchk_laravel', $this->sent[0]['headers']['Authorization']);
        $this->assertSame(['title' => 'Down', 'impact' => 'major'], json_decode($this->sent[0]['body'], true));
    }

    public function test_config_defaults_to_the_hosted_url(): void
    {
        $this->assertSame('https://usebeacon.pro', (require __DIR__.'/../config/beacon.php')['url']);
    }

    public function test_heartbeat_command(): void
    {
        $this->artisan('beacon:heartbeat', ['token' => 'hb_123'])->assertSuccessful()->expectsOutput('Beacon heartbeat sent.');
        $this->assertSame('POST', $this->sent[0]['method']);
        $this->assertSame('https://beacon.test/api/v1/heartbeat/hb_123', $this->sent[0]['url']);
        $this->assertArrayNotHasKey('Authorization', $this->sent[0]['headers']);

        $this->responses[] = new Response(404, [], '{"message":"Unknown heartbeat token.","code":"not_found"}');
        $this->artisan('beacon:heartbeat', ['token' => 'nope', '--method' => 'get'])->assertFailed();
        $this->assertSame('GET', $this->sent[1]['method']);
    }

    public function test_scheduler_macro_pings_only_after_success(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $ok = $schedule->call(fn () => true)->everyMinute()->pingBeacon('hb_ok');
        $failing = $schedule->call(fn () => throw new \RuntimeException('boom'))->everyMinute()->pingBeacon('hb_fail');

        $ok->run($this->app);
        try {
            $failing->run($this->app);
        } catch (\RuntimeException) {
            // The task's own exception still surfaces.
        }

        $this->assertSame(['https://beacon.test/api/v1/heartbeat/hb_ok'], array_column($this->sent, 'url'));
    }

    public function test_scheduler_macro_never_throws_when_the_ping_fails(): void
    {
        $this->responses[] = new Response(500, [], '');
        $event = $this->app->make(Schedule::class)->call(fn () => true)->pingBeacon('hb');

        $event->run($this->app);

        $this->assertCount(1, $this->sent);
    }

    public function test_webhook_middleware_verifies_signatures(): void
    {
        Route::post('/beacon-hook', fn () => 'ok')->middleware('beacon.webhook');
        $body = '{"event":"incident.opened"}';
        $send = fn (array $headers) => $this->call('POST', '/beacon-hook', [], [], [], $this->serverHeaders($headers), $body);

        $now = time();
        $send(['X-Beacon-Timestamp' => (string) $now, 'X-Beacon-Signature' => Webhook::sign($body, 'whsec_laravel', $now)])->assertOk()->assertSee('ok');
        $send(['X-Beacon-Timestamp' => (string) $now, 'X-Beacon-Signature' => Webhook::sign($body, 'wrong', $now)])->assertForbidden();
        $send(['X-Beacon-Timestamp' => (string) ($now - 600), 'X-Beacon-Signature' => Webhook::sign($body, 'whsec_laravel', $now - 600)])->assertForbidden();
        $send([])->assertForbidden();
    }

    private function serverHeaders(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }
}
