<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Services\Health\ModuleHealth;

/**
 * Native health check for UpdateGuard (exit 0 = healthy), run in a new process after an update, after a restore and
 * at the end of the observation period. Only the module's own code decides the exit code (ModuleHealth::code());
 * the state of hydroxide, of the cron and of the accounts is reported in "runtime" without failing the check.
 */
class HealthCommand extends Command
{
    protected $signature = 'hydrolink:health {--json : JSON output}';

    protected $description = 'HydroLink: health check of the module (used by UpdateGuard)';

    public function handle()
    {
        $checks = ModuleHealth::code();
        $ok = ModuleHealth::healthy($checks);
        $runtime = ModuleHealth::runtime();
        $version = (string) (json_decode((string) @file_get_contents(__DIR__.'/../module.json'), true)['version'] ?? '');
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status'  => $ok ? 'ok' : 'fail',
                'module'  => 'HydroLink',
                'version' => $version,
                'checks'  => $checks,
                'runtime' => $runtime,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('HydroLink '.$version);
            foreach ($checks as $name => $value) {
                $this->line(str_pad($name, 14).($value === 'ok' ? 'OK' : strtoupper($value)));
            }
            foreach ($runtime as $name => $check) {
                $this->line(str_pad(str_replace('hydrolink ', '', $name), 14).$check['result'].'  '.$check['detail']);
            }
        }

        return $ok ? 0 : 1;
    }
}
