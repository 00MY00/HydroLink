<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Process\Lock;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Runs the logins asked from the panel (command hydrolink:auth, every minute from FreeScout's cron, in the
 * background). One login at a time; the run stays while a person answers (2FA, verification), then ends.
 */
final class AuthWorker
{
    const POLL_US = 500000;

    /** @return int number of logins handled */
    public static function run(int $maxSeconds = 900): int
    {
        $lock = Lock::acquire('auth');
        if (!$lock) {
            return 0;
        }
        $deadline = time() + $maxSeconds;
        $done = 0;
        while (time() < $deadline && ($id = self::next()) !== null) {
            self::handle($id, $deadline);
            $done++;
        }
        $lock->release();

        return $done;
    }

    private static function next(): ?int
    {
        foreach (AuthRequests::pending() as $id) {
            return $id;
        }

        return null;
    }

    private static function handle(int $id, int $deadline): void
    {
        $account = Account::find($id);
        $request = AuthRequests::get($id);
        if (!$account || !$request) {
            AuthRequests::delete($id);
            if ($account && $account->isBusy()) {
                $account->status = Account::NEEDS_PASSWORD;
                $account->save();
            }

            return;
        }
        if (!is_file(Paths::bin())) {
            $account->setError('HL-BIN-01');
            $account->save();
            AuthRequests::delete($id);

            return;
        }
        $session = new AuthSession($account, $request);
        if (!$session->start()) {
            $account->setError('HL-ENV-01');
            $account->save();
            AuthRequests::delete($id);

            return;
        }
        while ($session->step()) {
            usleep(self::POLL_US);
        }
    }
}
