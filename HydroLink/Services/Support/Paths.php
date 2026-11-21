<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/**
 * Files of HydroLink, all under FreeScout's storage/app/hydrolink/ (not in the module folder: a module update
 * replaces that folder, which would lose the binary and the hydroxide sessions).
 *
 *   bin/hydroxide, bin/hydroxide.prev, bin/version.json    binary, previous binary (rollback), its version
 *   config/hydroxide/auth.json                              hydroxide sessions (XDG_CONFIG_HOME=config)
 *   run/                                                    desired.json, state.json, locks, auth requests
 *   logs/                                                   hydroxide.log, auth-<id>.log (secrets masked)
 *   tmp/, quarantine/                                       downloads, binaries refused by VirusTotal
 */
final class Paths
{
    /** @var string|null set by the tests */
    public static $base;

    public static function base(): string
    {
        return self::$base !== null ? self::$base : storage_path('app/hydrolink');
    }

    public static function bin(): string
    {
        return self::base().'/bin/hydroxide';
    }

    public static function previousBin(): string
    {
        return self::base().'/bin/hydroxide.prev';
    }

    public static function binMeta(): string
    {
        return self::base().'/bin/version.json';
    }

    public static function previousMeta(): string
    {
        return self::base().'/bin/version.prev.json';
    }

    public static function configHome(): string
    {
        return self::base().'/config';
    }

    public static function authFile(): string
    {
        return self::configHome().'/hydroxide/auth.json';
    }

    public static function run(string $file = ''): string
    {
        return self::base().'/run'.($file !== '' ? '/'.$file : '');
    }

    public static function requests(string $file = ''): string
    {
        return self::run('requests').($file !== '' ? '/'.$file : '');
    }

    public static function logs(string $file = ''): string
    {
        return self::base().'/logs'.($file !== '' ? '/'.$file : '');
    }

    public static function serveLog(): string
    {
        return self::logs('hydroxide.log');
    }

    public static function authLog(int $accountId): string
    {
        return self::logs('auth-'.$accountId.'.log');
    }

    public static function tmp(string $file = ''): string
    {
        return self::base().'/tmp'.($file !== '' ? '/'.$file : '');
    }

    public static function quarantine(string $file = ''): string
    {
        return self::base().'/quarantine'.($file !== '' ? '/'.$file : '');
    }

    /** Creates the folders (0700: only the web user reads them). */
    public static function ensure(): void
    {
        foreach ([dirname(self::bin()), self::configHome().'/hydroxide', self::run(), self::requests(), self::logs(), self::tmp(), self::quarantine()] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
        }
    }

    /** Environment of every hydroxide process: its configuration stays in storage/app/hydrolink/config. */
    public static function processEnv(array $extra = []): array
    {
        return array_merge([
            'XDG_CONFIG_HOME' => self::configHome(),
            'HOME'            => self::base(),
            'PATH'            => '/usr/local/bin:/usr/bin:/bin',
            'LANG'            => 'C.UTF-8',
        ], $extra);
    }
}
