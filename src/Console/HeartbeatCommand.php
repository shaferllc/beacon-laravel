<?php

declare(strict_types=1);

namespace Beacon\Laravel\Console;

use Beacon\Beacon;
use Beacon\Transport\Transport;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'beacon:heartbeat {token : Heartbeat token or full heartbeat URL} {--method=POST : GET, HEAD or POST}';

    protected $description = 'Send a Beacon heartbeat check-in';

    public function handle(): int
    {
        try {
            Beacon::ping($this->argument('token'), config('beacon.url'), strtoupper((string) $this->option('method')), (float) config('beacon.timeout', 10), $this->laravel->make(Transport::class));
        } catch (\Throwable $e) {
            $this->error('Beacon heartbeat failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Beacon heartbeat sent.');

        return self::SUCCESS;
    }
}
