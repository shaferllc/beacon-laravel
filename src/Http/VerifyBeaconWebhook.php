<?php

declare(strict_types=1);

namespace Beacon\Laravel\Http;

use Beacon\Webhook;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware `beacon.webhook`: rejects deliveries without a valid X-Beacon-Signature (403). */
class VerifyBeaconWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $valid = Webhook::verify(
            $request->getContent(),
            $request->headers->all(),
            (string) config('beacon.webhook_secret'),
            (int) config('beacon.webhook_tolerance', Webhook::DEFAULT_TOLERANCE),
        );

        abort_unless($valid, 403, 'Invalid Beacon webhook signature.');

        return $next($request);
    }
}
