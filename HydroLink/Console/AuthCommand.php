<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Services\Accounts\AuthWorker;

/** Cron, every minute: runs the logins asked from the panel (stays while a person answers 2FA / verification). */
class AuthCommand extends Command
{
    protected $signature = 'hydrolink:auth {--max=900 : maximum duration in seconds}';

    protected $description = 'HydroLink: run the Proton logins asked from the panel';

    public function handle()
    {
        $n = AuthWorker::run(max(10, (int) $this->option('max')));
        $this->line($n.' login(s) handled.');

        return 0;
    }
}
