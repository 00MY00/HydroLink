<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

/** Local TCP checks on 127.0.0.1 (nothing else is ever contacted). */
final class Ports
{
    const HOST = '127.0.0.1';

    public static function open(int $port, float $timeout = 1.0)
    {
        $socket = @fsockopen(self::HOST, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return null;
        }
        stream_set_timeout($socket, (int) ceil($timeout * 5));

        return $socket;
    }

    public static function listening(int $port): bool
    {
        $socket = self::open($port, 0.5);
        if (!$socket) {
            return false;
        }
        fclose($socket);

        return true;
    }

    /** Valid unprivileged port. */
    public static function valid(int $port): bool
    {
        return $port > 1024 && $port < 65536;
    }
}
