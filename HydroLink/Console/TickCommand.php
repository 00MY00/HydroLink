<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Services\Process\Watchdog;

/** Cron, every minute: environment, binary, start / restart of hydroxide, health check. */
class TickCommand extends Command
{
    protected $signature = 'hydrolink:tick';

    protected $description = 'HydroLink: watchdog of the hydroxide bridge (run by FreeScout\'s cron every minute)';

    public function handle()
    {
        $report = Watchdog::tick();
        if (!empty($report['skipped'])) {
            $this->line('Another run is in progress.');

            return 0;
        }
        $this->line('service: '.($report['service']['status'] ?? 'n/a'));

        return 0;
    }
}
