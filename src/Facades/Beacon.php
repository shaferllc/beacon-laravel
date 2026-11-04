<?php

declare(strict_types=1);

namespace Beacon\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beacon\Resources\StatusPages statusPages()
 * @method static \Beacon\Resources\Components components()
 * @method static \Beacon\Resources\PageResource monitors()
 * @method static \Beacon\Resources\Incidents incidents()
 * @method static \Beacon\Resources\PageResource maintenances()
 * @method static \Beacon\Resources\PageResource subscribers()
 * @method static \Beacon\Resources\Webhooks webhooks()
 * @method static \Beacon\Resources\PageResource imports()
 * @method static array createIncident(array $input, ?string $pageSlug = null)
 * @method static array updateIncident(int|string $incidentId, array $input)
 * @method static array addIncidentUpdate(int|string $incidentId, string $message)
 * @method static array resolveIncident(int|string $incidentId, ?string $message = null)
 * @method static array updateComponent(int|string $componentId, array $input, ?string $pageSlug = null)
 * @method static void heartbeat(string $tokenOrUrl, string $method = 'POST')
 * @method static array request(string $method, string $path, ?array $body = null, array $query = [])
 *
 * @see \Beacon\Beacon
 */
class Beacon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Beacon\Beacon::class;
    }
}
