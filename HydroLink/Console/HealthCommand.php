<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Health of the module itself, for UpdateGuard after an update (exit 0 = healthy). The state of hydroxide and of
 * the accounts is reported, but does not make the module unhealthy (a Proton outage is not a broken update).
 */
class HealthCommand extends Command
{
    protected $signature = 'hydrolink:health {--json : JSON output}';

    protected $description = 'HydroLink: health check of the module (used by UpdateGuard)';

    public function handle()
    {
        $checks = [
            'tables'   => \Schema::hasTable('hydrolink_accounts') && \Schema::hasTable('hydrolink_alerts'),
            'storage'  => self::storageOk(),
            'features' => count(Registry::all()) > 0,
            'views'    => view()->exists('hydrolink::panel'),
        ];
        $ok = !in_array(false, $checks, true);
        $real = State::real();
        $info = ['service' => $real['service']['status'] ?? 'unknown', 'cron' => State::cronAlive($real)];
        $version = (string) (json_decode((string) @file_get_contents(__DIR__.'/../module.json'), true)['version'] ?? '');
        if ($this->option('json')) {
            $this->line((string) json_encode(['status' => $ok ? 'ok' : 'fail', 'module' => 'HydroLink', 'version' => $version, 'checks' => $checks, 'info' => $info]));
        } else {
            foreach ($checks as $name => $value) {
                $this->line(str_pad($name, 10).($value ? 'OK' : 'FAIL'));
            }
        }

        return $ok ? 0 : 1;
    }

    private static function storageOk(): bool
    {
        Paths::ensure();

        return is_dir(Paths::run()) && is_writable(Paths::run());
    }
}
