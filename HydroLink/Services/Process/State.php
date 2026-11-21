<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Two files separate the web from the cron:
 *   run/desired.json  written by the panel: what the administrator wants (running, restart, binary update...)
 *   run/state.json    written by the cron only: what is really happening (pid, health, environment, binary)
 * The panel never starts a process, so a PHP-FPM without proc_open does not matter.
 */
final class State
{
    public static function desired(): array
    {
        return JsonFile::read(Paths::run('desired.json'), ['running' => true, 'restart' => 0, 'binary_request' => 0]);
    }

    public static function want(array $changes): array
    {
        return JsonFile::update(Paths::run('desired.json'), function (array $d) use ($changes) {
            return array_merge(['running' => true, 'restart' => 0, 'binary_request' => 0], $d, $changes);
        });
    }

    /** A new restart request (the cron compares it with the last one it handled). */
    public static function requestRestart(): void
    {
        self::want(['restart' => time()]);
    }

    public static function real(): array
    {
        return JsonFile::read(Paths::run('state.json'));
    }

    public static function report(array $changes): array
    {
        return JsonFile::update(Paths::run('state.json'), function (array $s) use ($changes) {
            return array_replace_recursive($s, $changes);
        });
    }

    /** Replaces one top-level key (lists that must not be merged, e.g. starts). */
    public static function put(string $key, $value): void
    {
        JsonFile::update(Paths::run('state.json'), function (array $s) use ($key, $value) {
            $s[$key] = $value;

            return $s;
        });
    }

    /** The cron ran recently (state.json "tick" less than 3 minutes old). */
    public static function cronAlive(?array $real = null): bool
    {
        $real = $real ?? self::real();

        return isset($real['tick']) && (time() - (int) $real['tick']) < 180;
    }
}
