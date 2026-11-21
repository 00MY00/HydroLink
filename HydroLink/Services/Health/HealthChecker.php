<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Health;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Notify\Alerts;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Secrets;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Periodic health check (cron, every 5 minutes by default): local SMTP and IMAP, IMAP login of each account,
 * FreeScout's last successful fetch, "broken API" detection. Each problem is an alert key (anti-spam in Alerts).
 */
final class HealthChecker
{
    public static function due(): bool
    {
        $last = (int) (State::real()['health']['at'] ?? 0);

        return time() - $last >= (int) Settings::config('health_interval', 300);
    }

    public static function run(): array
    {
        $result = ['at' => time(), 'smtp' => null, 'imap' => null, 'accounts' => []];
        if (HydroxideProcess::running()) {
            $result['smtp'] = self::service('smtp', Probes::smtp(Settings::smtpPort()), 'HL-SVC-02');
            $result['imap'] = self::service('imap', Probes::imap(Settings::imapPort()), 'HL-SVC-03');
            if ($result['imap']) {
                $result['accounts'] = self::accounts();
            }
        }
        self::apiBroken();
        self::fetchFreshness();
        State::put('health', $result);

        return $result;
    }

    private static function service(string $key, array $probe, string $code): bool
    {
        if ($probe['ok']) {
            Alerts::resolve('service:'.$key);
        } else {
            Alerts::raise('service:'.$key, $code, $probe['detail']);
        }

        return $probe['ok'];
    }

    private static function accounts(): array
    {
        $out = [];
        $accounts = Account::whereNotNull('bridge_password')->where('paused', false)
            ->whereIn('status', [Account::CONNECTED, Account::ERROR])->get();
        foreach ($accounts as $account) {
            $out[$account->id] = self::account($account);
        }

        return $out;
    }

    public static function account(Account $account): string
    {
        $bridge = $account->bridgePassword();
        $probe = Probes::imapLogin(Settings::imapPort(), $account->email, $bridge);
        $account->checked_at = now();
        if ($probe['ok']) {
            $account->clearError();
            $account->status = Account::CONNECTED;
            $account->api_errors = 0;
            $account->save();
            Alerts::resolve('account:'.$account->id);

            return 'ok';
        }
        $detail = Secrets::mask($probe['detail'], [$bridge]);
        $code = $probe['detail'] === 'timeout' ? 'HL-NET-01' : ErrorClassifier::classify($detail);
        $account->api_errors = $code === 'HL-API-01' ? $account->api_errors + 1 : 0;
        $account->setError($code, $detail);
        $account->save();
        Alerts::raise('account:'.$account->id, $code, $account->email.': '.$detail);

        return $code;
    }

    /** Unknown answers on several accounts at once, or repeated on one account: the Proton API probably changed. */
    private static function apiBroken(): void
    {
        $now = Account::where('error_code', 'HL-API-01')->count();
        $repeated = Account::where('api_errors', '>=', 3)->count();
        if ($now >= 2 || $repeated >= 1) {
            Alerts::raise('api', 'HL-API-02', $now.' / '.$repeated);
        } else {
            Alerts::resolve('api');
        }
    }

    /** FreeScout's own record of its last successful fetch (option "fetch_emails_last_successful_run"). */
    private static function fetchFreshness(): void
    {
        $active = Account::whereNotNull('mailbox_id')->where('paused', false)->where('status', Account::CONNECTED)->count();
        $last = (int) \Option::get('fetch_emails_last_successful_run', 0);
        $stale = (int) Settings::config('fetch_stale_after', 1800);
        if ($active > 0 && $last > 0 && time() - $last > $stale) {
            Alerts::raise('fetch', 'HL-SVC-04', date('c', $last));
        } else {
            Alerts::resolve('fetch');
        }
    }
}
