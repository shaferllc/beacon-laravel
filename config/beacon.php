<?php

return [

    // Workspace API token (bchk_…) from Settings → API tokens. Heartbeats don't need it.
    'token' => env('BEACON_API_TOKEN'),

    // Beacon host, without /api/v1.
    'url' => env('BEACON_URL', 'https://usebeacon.pro'),

    // Default status page slug for Beacon::createIncident() / Beacon::updateComponent().
    'page' => env('BEACON_PAGE_SLUG'),

    // Signing secret checked by the beacon.webhook middleware.
    'webhook_secret' => env('BEACON_WEBHOOK_SECRET'),

    // Seconds a webhook timestamp may differ from this server's clock.
    'webhook_tolerance' => (int) env('BEACON_WEBHOOK_TOLERANCE', 300),

    // Request timeout in seconds.
    'timeout' => (float) env('BEACON_TIMEOUT', 10),

];
