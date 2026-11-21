<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;

/**
 * hydroxide's auth.json: a JSON object user name => encrypted session (auth/auth.go, readCachedAuths/saveAuths).
 * hydroxide has no "logout" command: an account is removed by deleting its key, while hydroxide is stopped.
 */
final class AuthStore
{
    public static function usernames(?string $file = null): array
    {
        return array_keys(JsonFile::read($file ?? Paths::authFile()));
    }

    public static function has(string $email, ?string $file = null): bool
    {
        return in_array($email, self::usernames($file), true);
    }

    public static function remove(string $email, ?string $file = null): bool
    {
        $file = $file ?? Paths::authFile();
        $auths = JsonFile::read($file);
        if (!array_key_exists($email, $auths)) {
            return false;
        }
        unset($auths[$email]);
        if ($auths) {
            return JsonFile::write($file, $auths);
        }
        // an empty PHP array would be written "[]": hydroxide decodes the file into a map and needs "{}"
        $tmp = $file.'.tmp';

        return @file_put_contents($tmp, "{}\n") !== false && @chmod($tmp, 0600) && @rename($tmp, $file);
    }
}
