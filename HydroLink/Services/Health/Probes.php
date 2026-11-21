<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Health;

use Modules\HydroLink\Services\Process\Ports;

/** Light protocol checks of hydroxide on 127.0.0.1: SMTP EHLO, IMAP greeting and LOGIN. */
final class Probes
{
    /** @return array{ok:bool,detail:string} */
    public static function smtp(int $port): array
    {
        $s = Ports::open($port, 2.0);
        if (!$s) {
            return self::out(false, 'no connection');
        }
        $greeting = self::line($s);
        if (strpos($greeting, '220') !== 0) {
            fclose($s);

            return self::out(false, $greeting);
        }
        fwrite($s, "EHLO hydrolink.local\r\n");
        $reply = self::untilFinal($s);
        fwrite($s, "QUIT\r\n");
        fclose($s);

        return self::out(strpos($reply, '250') === 0, $reply);
    }

    /** Greeting only (no login): the IMAP server is up. */
    public static function imap(int $port): array
    {
        $s = Ports::open($port, 2.0);
        if (!$s) {
            return self::out(false, 'no connection');
        }
        $greeting = self::line($s);
        fwrite($s, "a1 LOGOUT\r\n");
        fclose($s);

        return self::out(strpos($greeting, '* OK') === 0, $greeting);
    }

    /** LOGIN with the bridge password: hydroxide opens (or renews) the Proton session of this account. */
    public static function imapLogin(int $port, string $user, string $password, int $timeout = 30): array
    {
        $s = Ports::open($port, 2.0);
        if (!$s) {
            return self::out(false, 'no connection');
        }
        stream_set_timeout($s, $timeout);
        if (strpos(self::line($s), '* OK') !== 0) {
            fclose($s);

            return self::out(false, 'no IMAP greeting');
        }
        fwrite($s, 'a1 LOGIN '.self::quote($user).' '.self::quote($password)."\r\n");
        $reply = '';
        while (($line = self::line($s)) !== '') {
            if (strpos($line, 'a1 ') === 0) {
                $reply = substr($line, 3);
                break;
            }
        }
        @fwrite($s, "a2 LOGOUT\r\n");
        fclose($s);

        return self::out(strpos($reply, 'OK') === 0, $reply !== '' ? $reply : 'timeout');
    }

    /** IMAP quoted string (RFC 3501): backslash and double quote escaped. */
    public static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private static function line($s): string
    {
        $line = fgets($s, 4096);

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    /** SMTP multi-line reply: lines "250-..." until "250 ...". */
    private static function untilFinal($s): string
    {
        $first = '';
        for ($i = 0; $i < 50; $i++) {
            $line = self::line($s);
            if ($line === '') {
                break;
            }
            $first = $first ?: $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        return $first;
    }

    private static function out(bool $ok, string $detail): array
    {
        return ['ok' => $ok, 'detail' => mb_substr($detail, 0, 300)];
    }
}
