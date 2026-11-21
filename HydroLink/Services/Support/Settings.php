<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/**
 * Settings saved in FreeScout's options table (\Option), names prefixed "hydrolink.". The secrets (VirusTotal key)
 * are encrypted with APP_KEY. Defaults come from Config/config.php and from the features (Features/*).
 */
final class Settings
{
    const PREFIX = 'hydrolink.';

    /** @var array|null in-memory store used by the unit tests instead of \Option */
    public static $memory;

    public static function get(string $key, $default = null)
    {
        if (self::$memory !== null) {
            return array_key_exists($key, self::$memory) ? self::$memory[$key] : $default;
        }

        // without Option's static cache: Option::set() does not update it, so a value saved earlier in the same
        // request (automatic save, cron run) would otherwise be read with its old value
        return \Option::get(self::PREFIX.$key, $default, true, false);
    }

    public static function set(string $key, $value): void
    {
        if (self::$memory !== null) {
            self::$memory[$key] = $value;

            return;
        }
        \Option::set(self::PREFIX.$key, $value);
    }

    public static function secret(string $key): string
    {
        $value = (string) self::get($key, '');
        if ($value === '') {
            return '';
        }
        try {
            return (string) decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function setSecret(string $key, string $value): void
    {
        self::set($key, $value === '' ? '' : encrypt($value));
    }

    public static function config(string $key, $default = null)
    {
        try {
            return config('hydrolink.'.$key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function imapPort(): int
    {
        return (int) self::config('imap_port', 1143);
    }

    public static function smtpPort(): int
    {
        return (int) self::config('smtp_port', 1025);
    }

    public static function autoUpdate(): bool
    {
        return (bool) self::get('auto_update', false);
    }

    public static function vtKey(): string
    {
        return self::secret('vt_key');
    }

    public static function vtThreshold(): int
    {
        return max(0, (int) self::get('vt_threshold', 0));
    }

    /** @return string[] notification channels chosen in the panel */
    public static function channels(): array
    {
        $value = self::get('channels', ['mail']);
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : ['mail'];
    }

    public static function reminderHours(): int
    {
        return max(0, (int) self::get('reminder_hours', self::config('reminder_hours', 24)));
    }

    public static function notifyRecovery(): bool
    {
        return (bool) self::get('notify_recovery', true);
    }
}
