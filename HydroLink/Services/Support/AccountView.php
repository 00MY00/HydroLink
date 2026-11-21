<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

use Modules\HydroLink\Entities\Account;

/** Presentation of an account: status pill (color + word) and the reason a button is disabled. */
final class AccountView
{
    const LEVELS = [
        Account::PENDING         => 'info',
        Account::RUNNING         => 'info',
        Account::WAITING_2FA     => 'warning',
        Account::WAITING_CAPTCHA => 'warning',
        Account::NEEDS_PASSWORD  => 'warning',
        Account::CONNECTED       => 'ok',
        Account::ERROR           => 'error',
        Account::REMOVING        => 'off',
    ];

    /** @return array{level:string,label:string} */
    public static function pill(Account $account): array
    {
        if ($account->paused && $account->status === Account::CONNECTED) {
            return ['level' => 'off', 'label' => __('hydrolink::messages.acc_paused')];
        }

        return [
            'level' => self::LEVELS[$account->status] ?? 'off',
            'label' => __('hydrolink::messages.acc_'.$account->status),
        ];
    }

    /** Why "Pause" cannot be used now ('' = it can). */
    public static function pauseBlocked(Account $account): string
    {
        return $account->status === Account::CONNECTED || $account->paused ? '' : __('hydrolink::messages.why_not_connected');
    }

    /** Why "Reconnect" cannot be used now. */
    public static function reconnectBlocked(Account $account): string
    {
        return in_array($account->status, [Account::PENDING, Account::RUNNING], true) ? __('hydrolink::messages.why_busy') : '';
    }
}
