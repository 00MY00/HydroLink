<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/**
 * Error codes shown in the panel, the logs and the notifications. Texts (what is expected, the effect, the action)
 * are in Resources/lang/<lang>/errors.php, one entry per code.
 */
final class ErrorCatalog
{
    const ERROR = 'error';
    const WARNING = 'warning';

    const CODES = [
        'HL-ENV-01' => self::ERROR,    // proc_open missing in the CLI
        'HL-ENV-02' => self::ERROR,    // storage mounted noexec
        'HL-ENV-03' => self::ERROR,    // local port used by another program
        'HL-ENV-04' => self::ERROR,    // CPU architecture not supported
        'HL-ENV-05' => self::ERROR,    // FreeScout cron not running
        'HL-ENV-06' => self::ERROR,    // storage not writable
        'HL-BIN-01' => self::WARNING,  // binary not installed
        'HL-BIN-02' => self::ERROR,    // checksum mismatch
        'HL-BIN-03' => self::ERROR,    // downloaded binary does not start
        'HL-BIN-04' => self::WARNING,  // download / release not reachable
        'HL-BIN-05' => self::ERROR,    // VirusTotal above the threshold: quarantine
        'HL-BIN-06' => self::ERROR,    // new binary failed after start: rollback done
        'HL-AUTH-01' => self::WARNING, // human verification (CAPTCHA, Proton 9001)
        'HL-AUTH-02' => self::ERROR,   // invalid session / token (401, 10013): reconnect
        'HL-AUTH-03' => self::ERROR,   // login refused by Proton
        'HL-AUTH-04' => self::ERROR,   // 2FA method other than TOTP
        'HL-AUTH-05' => self::ERROR,   // 2FA code refused
        'HL-AUTH-06' => self::WARNING, // mailbox password needed or wrong
        'HL-AUTH-07' => self::WARNING, // no answer in time (2FA / verification)
        'HL-NET-01' => self::WARNING,  // network error towards Proton
        'HL-API-01' => self::ERROR,    // unexpected answer of the Proton API
        'HL-API-02' => self::ERROR,    // probably broken API (several accounts / repeated)
        'HL-SVC-01' => self::ERROR,    // hydroxide keeps stopping: watchdog gave up
        'HL-SVC-02' => self::ERROR,    // local SMTP does not answer
        'HL-SVC-03' => self::ERROR,    // local IMAP does not answer / login refused
        'HL-SVC-04' => self::WARNING,  // FreeScout has not fetched e-mails for a while
        'HL-VT-01' => self::WARNING,   // VirusTotal key refused
        'HL-VT-02' => self::WARNING,   // VirusTotal unavailable (quota of the day, unexpected answer)
    ];

    public static function exists(string $code): bool
    {
        return isset(self::CODES[$code]);
    }

    public static function severity(string $code): string
    {
        return self::CODES[$code] ?? self::ERROR;
    }

    /** @return array{code:string,title:string,expected:string,effect:string,action:string,severity:string} */
    public static function describe(string $code): array
    {
        $out = ['code' => $code, 'severity' => self::severity($code)];
        foreach (['title', 'expected', 'effect', 'action'] as $part) {
            $out[$part] = self::text($code, $part);
        }

        return $out;
    }

    private static function text(string $code, string $part): string
    {
        $id = 'hydrolink::errors.'.$code.'.'.$part;
        try {
            $text = __($id);
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($text) && $text !== $id ? $text : '';
    }
}
