<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Notify;

use Modules\HydroLink\Entities\Alert;
use Modules\HydroLink\Services\Support\Secrets;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Anti-spam of the notifications, one row per problem key ("service", "binary", "account:3"...):
 *   raise()   notifies when the key becomes active or its code changes, then a reminder every N hours
 *   resolve() notifies the return to normal once (setting "notify_recovery")
 */
final class Alerts
{
    public static function raise(string $key, string $code, string $detail = ''): void
    {
        try {
            $detail = Secrets::mask($detail);
            $alert = Alert::firstOrNew(['alert_key' => $key]);
            $changed = !$alert->exists || !$alert->active || $alert->code !== $code;
            if ($changed) {
                $alert->fill(['code' => $code, 'active' => true, 'detail' => $detail, 'since' => now(), 'notified_at' => now()])->save();
                Notifier::send('problem', $code, $detail);

                return;
            }
            if (self::reminderDue($alert)) {
                $alert->fill(['notified_at' => now(), 'detail' => $detail])->save();
                Notifier::send('reminder', $code, $detail);
            }
        } catch (\Throwable $e) {
            \Log::warning('[HydroLink] alert '.$key.': '.$e->getMessage());
        }
    }

    public static function resolve(string $key): void
    {
        try {
            $alert = Alert::where('alert_key', $key)->where('active', true)->first();
            if (!$alert) {
                return;
            }
            $alert->fill(['active' => false, 'notified_at' => now()])->save();
            if (Settings::notifyRecovery()) {
                Notifier::send('recovered', $alert->code, '');
            }
        } catch (\Throwable $e) {
            \Log::warning('[HydroLink] alert '.$key.': '.$e->getMessage());
        }
    }

    /** Resolves every key starting with $prefix except the ones listed (e.g. accounts removed). */
    public static function resolvePrefix(string $prefix, array $keep = []): void
    {
        try {
            foreach (Alert::where('alert_key', 'like', $prefix.'%')->where('active', true)->pluck('alert_key') as $key) {
                if (!in_array($key, $keep, true)) {
                    self::resolve((string) $key);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /** @return Alert[] active problems, newest first */
    public static function active(): array
    {
        try {
            return Alert::where('active', true)->orderBy('since', 'desc')->get()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function reminderDue(Alert $alert): bool
    {
        $hours = Settings::reminderHours();
        if ($hours <= 0 || !$alert->notified_at) {
            return false;
        }

        return $alert->notified_at->lt(now()->subHours($hours));
    }
}
