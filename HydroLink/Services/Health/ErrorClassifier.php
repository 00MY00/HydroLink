<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Health;

/**
 * Classifies a hydroxide message (log line, fatal error of "auth", IMAP NO answer) into an HL code.
 * Formats from hydroxide's source: Proton API errors are "[<code>] <message>" (protonmail.APIError.Error()),
 * 9001 = human verification, 10013 = invalid refresh token (auth/auth.go), HTTP 401 = expired access token,
 * "cannot re-authenticate: ..." when the stored session cannot be renewed.
 */
final class ErrorClassifier
{
    const NETWORK = ['dial tcp', 'no such host', 'i/o timeout', 'connection refused', 'connection reset',
        'tls handshake', 'network is unreachable', 'context deadline exceeded', 'eof', 'temporary failure in name resolution'];

    public static function classify(string $message): string
    {
        $m = strtolower($message);
        if (trim($m) === '') {
            return '';
        }
        if (strpos($m, '[9001]') !== false || strpos($m, 'human verification') !== false) {
            return 'HL-AUTH-01';
        }
        if (strpos($m, 'only totp is supported') !== false) {
            return 'HL-AUTH-04';
        }
        if (strpos($m, '[10013]') !== false || strpos($m, 'cannot re-authenticate') !== false
            || strpos($m, 'invalid username or password') !== false || preg_match('~\b401\b~', $m)) {
            return 'HL-AUTH-02';
        }
        if (strpos($m, '/dev/tty') !== false || strpos($m, 'mailbox password') !== false) {
            return 'HL-AUTH-06';
        }
        foreach (self::NETWORK as $needle) {
            if (strpos($m, $needle) !== false) {
                return 'HL-NET-01';
            }
        }
        // any other Proton API code, or an answer hydroxide did not expect
        return 'HL-API-01';
    }

    /** "[8002] Incorrect login credentials" => 8002, or 0. */
    public static function apiCode(string $message): int
    {
        return preg_match('~\[(\d{3,5})\]~', $message, $m) ? (int) $m[1] : 0;
    }

    /** Message of a Go log line without its "2006/01/02 15:04:05 " prefix. */
    public static function stripLogPrefix(string $line): string
    {
        return (string) preg_replace('~^\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2} ~', '', trim($line));
    }
}
