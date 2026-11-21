<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Notify\Alerts;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;

/**
 * What the panel does with accounts. Nothing here starts a process: the panel writes requests that the cron runs.
 */
final class AccountService
{
    /** Adds an account (or reconnects an existing one) and queues its login for the cron worker. */
    public static function login(string $email, string $password, string $mailboxPassword = ''): Account
    {
        $email = strtolower(trim($email));
        $account = Account::firstOrNew(['email' => $email]);
        $account->clearError();
        $account->status = Account::PENDING;
        $account->save();
        AuthRequests::create((int) $account->id, $email, $password, $mailboxPassword);

        return $account;
    }

    public static function giveTotp(Account $account, string $code): void
    {
        AuthRequests::giveTotp((int) $account->id, $code);
    }

    public static function verificationDone(Account $account): void
    {
        AuthRequests::verificationDone((int) $account->id);
    }

    /** Pause: FreeScout stops fetching this mailbox (filter "mailbox.in_active"); hydroxide keeps the session. */
    public static function setPaused(Account $account, bool $paused): void
    {
        $account->paused = $paused;
        $account->save();
        self::$pausedCache = null;
    }

    /**
     * Removal: the FreeScout mailbox and its tickets are kept, but stop using hydroxide; the session is removed
     * from hydroxide's auth.json by the cron (hydroxide is stopped meanwhile).
     */
    public static function remove(Account $account): void
    {
        AuthRequests::delete((int) $account->id);
        MailboxLinker::unlink($account);
        JsonFile::update(Paths::run('removals.json'), function (array $d) use ($account) {
            $d[] = $account->email;

            return array_values(array_unique($d));
        });
        Alerts::resolve('account:'.$account->id);
        @unlink(Paths::authLog((int) $account->id));
        $account->delete();
    }

    /** Cron: applies the removals (hydroxide stopped by the caller). @return int sessions removed */
    public static function applyRemovals(): int
    {
        $emails = JsonFile::read(Paths::run('removals.json'));
        $n = 0;
        foreach ($emails as $email) {
            if (AuthStore::remove((string) $email)) {
                $n++;
            }
        }
        JsonFile::write(Paths::run('removals.json'), []);

        return $n;
    }

    public static function hasRemovals(): bool
    {
        return JsonFile::read(Paths::run('removals.json')) !== [];
    }

    /** Accounts hydroxide has to serve (a working session). */
    public static function servable(): int
    {
        return Account::whereNotNull('bridge_password')->count();
    }

    /** @var int[]|null paused mailbox ids, read once per request (the filter runs for every mailbox) */
    private static $pausedCache;

    /** Paused mailboxes, for the filter "mailbox.in_active". */
    public static function pausedMailboxIds(): array
    {
        if (self::$pausedCache === null) {
            try {
                self::$pausedCache = Account::where('paused', true)->whereNotNull('mailbox_id')->pluck('mailbox_id')->map(function ($id) {
                    return (int) $id;
                })->all();
            } catch (\Throwable $e) {
                self::$pausedCache = [];
            }
        }

        return self::$pausedCache;
    }

    public static function restartService(): void
    {
        State::requestRestart();
    }
}
