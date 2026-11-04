# beacon-status/laravel

Laravel 11–13 integration for [Beacon](https://usebeacon.pro), built on [`beacon-status/beacon-php`](../php):

- `Beacon` facade / container binding configured from `BEACON_API_TOKEN` and `BEACON_URL`
- `php artisan beacon:heartbeat {token}`
- Scheduler macro `->pingBeacon($token)` (pings only when the task succeeds)
- `beacon.webhook` route middleware that verifies `X-Beacon-Signature`

> **Not yet published to Packagist.** Until it is, add both packages as Composer `vcs` repositories (or `path` repositories pointing at local clones):
>
> ```jsonc
> // your app's composer.json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/shaferllc/beacon-php" },
>     { "type": "vcs", "url": "https://github.com/shaferllc/beacon-laravel" }
> ],
> "require": {
>     "beacon-status/beacon-php": "^1.0",
>     "beacon-status/laravel": "^1.0"
> }
> ```

## Configuration

```dotenv
BEACON_API_TOKEN=bchk_...
BEACON_URL=https://usebeacon.pro      # optional
BEACON_PAGE_SLUG=acme                 # default page for createIncident / updateComponent
BEACON_WEBHOOK_SECRET=...             # for the beacon.webhook middleware
```

`php artisan vendor:publish --tag=beacon-config` copies `config/beacon.php` (also `BEACON_TIMEOUT`, `BEACON_WEBHOOK_TOLERANCE`).

## Usage

```php
use Beacon\Laravel\Facades\Beacon;

$incident = Beacon::createIncident([
    'title' => 'Payment processing delays',
    'impact' => 'major',
    'message' => 'Stripe webhook delivery is delayed.',
]);
Beacon::resolveIncident($incident['id'], 'Webhook delivery restored.');

Beacon::updateComponent(12, ['status' => 'degraded_performance']);

Beacon::monitors()->create('acme', ['name' => 'API', 'url' => 'https://api.acme.com/health']);
```

Every method of `Beacon\Beacon` is available — see the [beacon-php README](../php/README.md). You can also inject `Beacon\Beacon`.

## Heartbeats

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run')->daily()->pingBeacon('YOUR-HEARTBEAT-TOKEN');
```

The ping runs only when the task succeeds; a failed ping is reported to your exception handler and never breaks the schedule. From cron or a deploy script:

```bash
php artisan beacon:heartbeat YOUR-HEARTBEAT-TOKEN
php artisan beacon:heartbeat YOUR-HEARTBEAT-TOKEN --method=GET
```

Heartbeats don't need `BEACON_API_TOKEN`.

## Receiving webhooks

```php
Route::post('/beacon/webhook', BeaconWebhookController::class)->middleware('beacon.webhook');
```

Requests without a valid signature, or with a timestamp more than 300 seconds off, get a 403. Exclude the route from CSRF protection.

## Testing your app

Rebind the transport to record requests instead of sending them:

```php
$this->app->instance(\Beacon\Transport\Transport::class, $fake);
```

## Development

```bash
composer install   # pulls beacon-php from ../php
vendor/bin/phpunit
```
