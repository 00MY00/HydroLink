<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Services\Health\ErrorClassifier;

/**
 * Reads the output of "hydroxide auth" as it comes (cmd/hydroxide/main.go, with build/patches):
 *   stdout "2FA TOTP code: "                                          -> a 2FA code is expected on stdin
 *   stderr "Human verification required." + "https://verify.proton.me/?..." -> URL to open, then a line on stdin
 *   stdout "Authentication saved using the configured bridge password." -> success
 *   stderr last line before a non-zero exit                            -> error message (log.Fatal)
 */
final class OutputParser
{
    const TOTP_PROMPT = '2FA TOTP code:';
    const SAVED = 'Authentication saved using the configured bridge password.';
    const VERIFY_URL = '~https://verify\.proton\.me/\?\S+~';

    private $out = '';
    private $err = '';
    private $totpSeen = 0;
    private $urlSeen = 0;

    public function feed(string $stdout, string $stderr): void
    {
        $this->out .= $stdout;
        $this->err .= $stderr;
    }

    /** One more 2FA prompt than already handled. */
    public function newTotpPrompt(): bool
    {
        $count = substr_count($this->out, self::TOTP_PROMPT);
        if ($count > $this->totpSeen) {
            $this->totpSeen = $count;

            return true;
        }

        return false;
    }

    /** A new verification URL ('' when none). */
    public function newVerificationUrl(): string
    {
        if (!preg_match_all(self::VERIFY_URL, $this->err, $m) || count($m[0]) <= $this->urlSeen) {
            return '';
        }
        $this->urlSeen = count($m[0]);

        return (string) end($m[0]);
    }

    public function saved(): bool
    {
        return strpos($this->out, self::SAVED) !== false;
    }

    /** Last meaningful stderr line, without the Go log date. */
    public function lastError(): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('~\R~', $this->err)), 'strlen'));

        return $lines ? ErrorClassifier::stripLogPrefix((string) end($lines)) : '';
    }

    public function output(): string
    {
        return $this->out."\n".$this->err;
    }
}
