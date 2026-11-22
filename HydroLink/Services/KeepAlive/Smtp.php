<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\KeepAlive;

use Modules\HydroLink\Services\Process\Ports;

/**
 * Minimal SMTP client towards hydroxide on 127.0.0.1: EHLO, AUTH PLAIN (hydroxide allows it without TLS on its
 * local port, cmd/hydroxide/main.go AllowInsecureAuth), one message. hydroxide sends it through the Proton API.
 */
final class Smtp
{
    /** @throws \RuntimeException with the step and the server's answer */
    public static function send(int $port, string $user, string $password, string $to, string $message, int $timeout = 90): void
    {
        $s = Ports::open($port, 3.0);
        if (!$s) {
            throw new \RuntimeException('no connection to the local SMTP server');
        }
        stream_set_timeout($s, $timeout);
        try {
            self::expect($s, null, '220', 'greeting');
            self::expect($s, 'EHLO hydrolink.local', '250', 'EHLO');
            self::expect($s, 'AUTH PLAIN '.base64_encode("\0".$user."\0".$password), '235', 'AUTH');
            self::expect($s, 'MAIL FROM:<'.$user.'>', '250', 'MAIL FROM');
            self::expect($s, 'RCPT TO:<'.$to.'>', '250', 'RCPT TO');
            self::expect($s, 'DATA', '354', 'DATA');
            // dot-stuffing (RFC 5321 4.5.2), then the final dot
            $body = preg_replace('~^\.~m', '..', str_replace(["\r\n", "\r"], "\n", $message));
            self::expect($s, str_replace("\n", "\r\n", rtrim($body, "\n"))."\r\n.", '250', 'message');
            @fwrite($s, "QUIT\r\n");
        } finally {
            @fclose($s);
        }
    }

    private static function expect($s, ?string $command, string $code, string $step): void
    {
        if ($command !== null) {
            fwrite($s, $command."\r\n");
        }
        $reply = '';
        for ($i = 0; $i < 50; $i++) {
            $line = fgets($s, 4096);
            if ($line === false) {
                throw new \RuntimeException($step.': no answer');
            }
            $line = rtrim($line, "\r\n");
            $reply = $reply ?: $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                $reply = $line;
                break;
            }
        }
        if (strpos($reply, $code) !== 0) {
            throw new \RuntimeException($step.': '.mb_substr($reply, 0, 300));
        }
    }
}
