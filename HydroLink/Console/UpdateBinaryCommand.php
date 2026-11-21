<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Process\Lock;
use Modules\HydroLink\Services\Support\ErrorCatalog;

/** Installs or updates the hydroxide binary now (checksum, optional VirusTotal, test, rollback). */
class UpdateBinaryCommand extends Command
{
    protected $signature = 'hydrolink:update-binary {--force : reinstall even when up to date} {--dry-run : only say what would be done}';

    protected $description = 'HydroLink: install or update the hydroxide binary';

    public function handle()
    {
        $lock = Lock::acquire('watchdog');
        if (!$lock) {
            $this->error('The cron is working on HydroLink: try again in a minute.');

            return 1;
        }
        $r = (new BinaryManager())->update((bool) $this->option('force'), (bool) $this->option('dry-run'));
        $lock->release();
        $this->line($r['result'].($r['version'] !== '' ? ' '.$r['version'] : '').($r['detail'] !== '' ? ' ('.$r['detail'].')' : ''));
        if ($r['code'] !== '') {
            $info = ErrorCatalog::describe($r['code']);
            $this->error($r['code'].' '.$info['title']);
            $this->line($info['action']);

            return 1;
        }

        return 0;
    }
}
