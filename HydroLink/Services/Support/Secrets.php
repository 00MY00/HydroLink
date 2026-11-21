<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/** Masking of secrets in every text that is logged or shown (logs, errors, diagnostics). */
final class Secrets
{
    const MASK = '***';

    /** @param string[] $secrets known secret values (passwords, keys, codes) */
    public static function mask(string $text, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            $secret = (string) $secret;
            if (strlen($secret) >= 4) {
                $text = str_replace($secret, self::MASK, $text);
            }
        }
        // bridge passwords and other base64 keys of 32 bytes
        $text = (string) preg_replace('~[A-Za-z0-9+/]{43}=~', self::MASK, $text);
        // tokens in URLs (human verification link) and "Bridge password: ..." lines
        $text = (string) preg_replace('~([?&](?:token|Token)=)[^&\s]+~', '$1'.self::MASK, $text);
        $text = (string) preg_replace('~(Bridge password:\s*)\S+~i', '$1'.self::MASK, $text);

        return $text;
    }

    /** Shows only the start of a key: "abcd…" (the VirusTotal key in the panel). */
    public static function hint(string $value): string
    {
        return $value === '' ? '' : mb_substr($value, 0, 4).'…';
    }

    /** base64 of 32 random bytes: the bridge password format of hydroxide (auth.GeneratePassword). */
    public static function newBridgePassword(): string
    {
        return base64_encode(random_bytes(32));
    }
}
