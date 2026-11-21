<?php

declare(strict_types=1);

namespace Modules\HydroLink\Console;

use Illuminate\Console\Command;
use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Accounts\MailboxLinker;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Before removing the module (install.sh --uninstall): stops hydroxide, then deletes storage/app/hydrolink.
 * --keep-accounts keeps the hydroxide sessions and the accounts table (a later reinstall finds them again);
 * otherwise the FreeScout mailboxes stop using hydroxide (they and their tickets are kept).
 */
class UninstallCommand extends Command
{
    protected $signature = 'hydrolink:uninstall {--keep-accounts : keep the hydroxide sessions and accounts} {--dry-run : only list what would be done}';

    protected $description = 'HydroLink: stop hydroxide and clean its files before the module is removed';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');
        $keep = (bool) $this->option('keep-accounts');
        $this->line(($dry ? '(dry-run) ' : '').'stop hydroxide');
        if (!$dry) {
            State::want(['running' => false]);
            HydroxideProcess::stop();
            HydroxideProcess::find() > 0 && HydroxideProcess::stop();
        }
        if (!$keep) {
            foreach (Account::all() as $account) {
                $this->line(($dry ? '(dry-run) ' : '').'mailbox of '.$account->email.': stop using hydroxide');
                $dry || MailboxLinker::unlink($account);
            }
        }
        foreach (['bin', 'run', 'logs', 'tmp', 'quarantine'] + ($keep ? [] : [5 => 'config']) as $dir) {
            $this->line(($dry ? '(dry-run) ' : '').'delete storage/app/hydrolink/'.$dir);
            $dry || self::deleteTree(Paths::base().'/'.$dir);
        }

        return 0;
    }

    private static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
