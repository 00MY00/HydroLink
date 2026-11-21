<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Env\EnvironmentCheck;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\ErrorCatalog;

/** Readable summary for support: environment, binary, service, accounts (no secret is printed). */
class DiagnoseCommand extends Command
{
    protected $signature = 'hydrolink:diagnose';

    protected $description = 'HydroLink: environment, binary, service and accounts summary';

    public function handle()
    {
        $env = EnvironmentCheck::runCli();
        $this->info('Environment');
        foreach (['proc_open', 'writable', 'exec'] as $k) {
            $this->line('  '.str_pad($k, 10).($env[$k] ? 'OK' : 'FAIL'));
        }
        $this->line('  arch      '.($env['arch'] !== '' ? $env['arch'] : 'unsupported ('.$env['machine'].')').', PHP '.$env['php']);
        foreach (EnvironmentCheck::problems() as $code) {
            $this->error('  '.$code.' '.ErrorCatalog::describe($code)['title']);
        }
        $bin = BinaryManager::installed();
        $this->info('Binary');
        $this->line('  installed '.($bin['version'] ?? 'none').', latest '.(State::real()['binary']['latest'] ?? '?'));
        $this->info('Service');
        $this->line('  '.(HydroxideProcess::running() ? 'running, pid '.HydroxideProcess::pid() : 'stopped'));
        $this->info('Accounts');
        foreach (Account::orderBy('email')->get() as $a) {
            $this->line('  #'.$a->id.' '.$a->email.' '.$a->status.($a->paused ? ' (paused)' : '').($a->error_code ? ' '.$a->error_code : ''));
        }

        return 0;
    }
}
