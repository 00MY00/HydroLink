<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Hand-over of a login from the panel to the cron worker, in run/requests/ (0700, files 0600):
 *   <id>.json        password(s) encrypted with APP_KEY, deleted as soon as hydroxide auth ends or after 15 min
 *   <id>.input.json  what the person gives during the login: 2FA code (encrypted), "verification done"
 * HydroLink keeps no Proton password: only hydroxide keeps its own encrypted session.
 */
final class AuthRequests
{
    public static function create(int $accountId, string $email, string $password, string $mailboxPassword = ''): void
    {
        Paths::ensure();
        self::clearInput($accountId);
        JsonFile::write(self::file($accountId), [
            'account_id'       => $accountId,
            'email'            => $email,
            'password'         => encrypt($password),
            'mailbox_password' => $mailboxPassword !== '' ? encrypt($mailboxPassword) : '',
            'created_at'       => time(),
        ]);
    }

    /** @return array|null decrypted request, or null when missing / expired (an expired one is deleted) */
    public static function get(int $accountId): ?array
    {
        $r = JsonFile::read(self::file($accountId));
        if (!$r) {
            return null;
        }
        if (time() - (int) ($r['created_at'] ?? 0) > (int) Settings::config('auth_request_ttl', 900)) {
            self::delete($accountId);

            return null;
        }
        try {
            $r['password'] = (string) decrypt($r['password']);
            $r['mailbox_password'] = $r['mailbox_password'] !== '' ? (string) decrypt($r['mailbox_password']) : '';
        } catch (\Throwable $e) {
            self::delete($accountId);

            return null;
        }

        return $r;
    }

    /** @return int[] account ids with a waiting request, oldest first */
    public static function pending(): array
    {
        $files = (array) glob(Paths::requests('*.json'));
        $ids = [];
        foreach ($files as $f) {
            if (preg_match('~/(\d+)\.json$~', (string) $f, $m)) {
                $ids[(int) $m[1]] = (int) @filemtime((string) $f);
            }
        }
        asort($ids);

        return array_keys($ids);
    }

    public static function delete(int $accountId): void
    {
        @unlink(self::file($accountId));
        self::clearInput($accountId);
    }

    public static function giveTotp(int $accountId, string $code): void
    {
        JsonFile::update(self::inputFile($accountId), function (array $d) use ($code) {
            $d['totp'] = encrypt($code);

            return $d;
        });
    }

    public static function verificationDone(int $accountId): void
    {
        JsonFile::update(self::inputFile($accountId), function (array $d) {
            $d['verified'] = time();

            return $d;
        });
    }

    /** Takes (and removes) one input: "totp" (decrypted) or "verified". */
    public static function takeInput(int $accountId, string $key): string
    {
        $value = '';
        JsonFile::update(self::inputFile($accountId), function (array $d) use ($key, &$value) {
            if (isset($d[$key])) {
                $value = (string) $d[$key];
                unset($d[$key]);
            }

            return $d;
        });
        if ($value !== '' && $key === 'totp') {
            try {
                return (string) decrypt($value);
            } catch (\Throwable $e) {
                return '';
            }
        }

        return $value;
    }

    private static function clearInput(int $accountId): void
    {
        @unlink(self::inputFile($accountId));
        @unlink(self::inputFile($accountId).'.lock');
    }

    private static function file(int $accountId): string
    {
        return Paths::requests($accountId.'.json');
    }

    private static function inputFile(int $accountId): string
    {
        return Paths::requests($accountId.'.input.json');
    }
}
