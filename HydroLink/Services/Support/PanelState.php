<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Env\EnvironmentCheck;
use Modules\HydroLink\Services\Notify\Alerts;
use Modules\HydroLink\Services\Process\State;

/** Everything the panel shows, read from files and two small tables (no network, no process). */
final class PanelState
{
    public static function build(): array
    {
        $real = State::real();
        $state = [
            'real'         => $real,
            'desired'      => State::desired(),
            'env_problems' => EnvironmentCheck::problems($real),
            'accounts'     => Account::orderBy('email')->get(),
            'binary'       => BinaryManager::installed(),
            'alerts'       => Alerts::active(),
        ];
        $state['statuses'] = [];
        foreach (Registry::all() as $key => $feature) {
            $state['statuses'][$key] = $feature->status($state);
        }

        return $state;
    }

    /** Compact JSON for the refresh of the page (every 20 s while the tab is visible). */
    public static function json(): array
    {
        $state = self::build();
        $accounts = [];
        foreach ($state['accounts'] as $a) {
            $accounts[$a->id] = [
                'status'  => $a->status,
                'paused'  => (bool) $a->paused,
                'error'   => $a->error_code,
                'captcha' => $a->captcha_url,
                'pill'    => AccountView::pill($a),
            ];
        }

        return [
            'statuses' => $state['statuses'],
            'accounts' => $accounts,
            'env'      => $state['env_problems'],
            'tick'     => (int) ($state['real']['tick'] ?? 0),
        ];
    }
}
