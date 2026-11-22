<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Health;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Features\Registry;
use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Health of the module for UpdateGuard (README of UpdateGuard, "For module developers").
 *
 *   code()    : what a broken update would break (files, classes, tables, routes, views, translations, storage,
 *               scheduled tasks). One failure = exit 1 of hydrolink:health = UpdateGuard rolls the update back.
 *   runtime() : state of hydroxide, of the cron and of the accounts. Shown as PASS or WARN through UpdateGuard's
 *               "updateguard.health_check" filter, never FAIL: a Proton outage or an account waiting for its 2FA
 *               code is not a broken update and must not trigger a rollback.
 *
 * Fast, read-only (except creating the storage folders if missing), no network call.
 */
class ModuleHealth
{
    const FILES = [
        'module.json',
        'Providers/HydroLinkServiceProvider.php',
        'Http/routes.php',
        'Config/config.php',
        'Public/css/hydrolink.css',
        'Public/js/hydrolink.js',
        'Public/img/module.svg',
        'Resources/lang/en/messages.php',
        'Resources/lang/fr/messages.php',
        'Resources/lang/en/errors.php',
        'Resources/lang/fr/errors.php',
    ];

    const CLASSES = [
        \Modules\HydroLink\Features\Registry::class,
        \Modules\HydroLink\Services\Process\Watchdog::class,
        \Modules\HydroLink\Services\Accounts\AuthWorker::class,
        \Modules\HydroLink\Services\Accounts\AccountService::class,
        \Modules\HydroLink\Services\Binary\BinaryManager::class,
        \Modules\HydroLink\Services\Health\HealthChecker::class,
        \Modules\HydroLink\Services\Notify\Notifier::class,
        \Modules\HydroLink\Services\KeepAlive\KeepAlive::class,
        \Modules\HydroLink\Http\Controllers\PanelController::class,
        \Modules\HydroLink\Http\Controllers\AccountsController::class,
        \Modules\HydroLink\Http\Controllers\SettingsController::class,
    ];

    const COLUMNS = [
        'hydrolink_accounts' => ['email', 'name', 'mailbox_id', 'bridge_password', 'status', 'error_code', 'captcha_url', 'paused', 'api_errors', 'keepalive_at', 'keepalive_code'],
        'hydrolink_alerts'   => ['alert_key', 'code', 'active', 'notified_at'],
    ];

    const ROUTES = ['hydrolink.panel', 'hydrolink.state', 'hydrolink.settings', 'hydrolink.accounts.store', 'hydrolink.accounts.rename', 'hydrolink.accounts.keepalive', 'hydrolink.service', 'hydrolink.binary'];

    const COMMANDS = ['hydrolink:tick', 'hydrolink:auth', 'hydrolink:update-binary', 'hydrolink:diagnose'];

    /** @return array<string,string> name => "ok" or "fail: reason" */
    public static function code(): array
    {
        $checks = [];
        foreach (['files', 'classes', 'database', 'routes', 'views', 'translations', 'storage', 'commands', 'schedule'] as $name) {
            try {
                $problem = self::{'check'.ucfirst($name)}();
            } catch (\Throwable $e) {
                $problem = get_class($e).': '.$e->getMessage();
            }
            $checks[$name] = $problem === '' ? 'ok' : 'fail: '.mb_substr($problem, 0, 300);
        }

        return $checks;
    }

    public static function healthy(array $checks): bool
    {
        foreach ($checks as $value) {
            if ($value !== 'ok') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,array{result:string,detail:string}> UpdateGuard check format, PASS or WARN only */
    public static function runtime(): array
    {
        $out = [];
        try {
            $bin = BinaryManager::installed();
            $out['hydrolink binary'] = $bin
                ? self::pass('hydroxide '.($bin['version'] ?? '?'))
                : self::warn('hydroxide not installed yet (HydroLink panel, "Install")');

            $real = State::real();
            $out['hydrolink cron'] = State::cronAlive($real)
                ? self::pass('last tick '.max(0, time() - (int) $real['tick']).' s ago')
                : self::warn('no hydrolink:tick for 3 minutes: is FreeScout\'s schedule:run cron running? (HL-ENV-05)');

            $servable = Account::where('status', Account::CONNECTED)->where('paused', false)->count();
            $status = (string) ($real['service']['status'] ?? 'unknown');
            if ($servable === 0) {
                $out['hydrolink service'] = self::pass('no connected account to serve');
            } elseif ($status === 'running' && HydroxideProcess::running()) {
                $out['hydrolink service'] = self::pass('hydroxide serve running (pid '.HydroxideProcess::pid().')');
            } else {
                $out['hydrolink service'] = self::warn('hydroxide serve '.$status.(isset($real['service']['errors'][0]) ? ' ('.$real['service']['errors'][0].')' : ''));
            }

            $counts = Account::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
            $detail = $counts ? implode(', ', array_map(function ($s, $n) {
                return $n.' '.$s;
            }, array_keys($counts), $counts)) : 'no account';
            $errors = (int) ($counts[Account::ERROR] ?? 0) + (int) ($counts[Account::NEEDS_PASSWORD] ?? 0);
            // an account waiting for its owner is information; an account in error is a warning, never a failure
            $out['hydrolink accounts'] = $errors > 0 ? self::warn($detail) : self::pass($detail);
        } catch (\Throwable $e) {
            $out['hydrolink runtime'] = self::warn('state not readable: '.$e->getMessage());
        }

        return $out;
    }

    private static function pass(string $detail): array
    {
        return ['result' => 'PASS', 'detail' => $detail];
    }

    private static function warn(string $detail): array
    {
        return ['result' => 'WARN', 'detail' => $detail];
    }

    private static function moduleDir(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function checkFiles(): string
    {
        $missing = array_values(array_filter(self::FILES, function ($f) {
            return !is_file(self::moduleDir().'/'.$f);
        }));
        if ($missing) {
            return 'missing '.implode(', ', $missing);
        }
        $json = json_decode((string) file_get_contents(self::moduleDir().'/module.json'), true);

        return is_array($json) && ($json['alias'] ?? '') === 'hydrolink' ? '' : 'module.json unreadable';
    }

    private static function checkClasses(): string
    {
        $missing = array_values(array_filter(self::CLASSES, function ($c) {
            return !class_exists($c);
        }));

        return $missing ? 'missing '.implode(', ', $missing) : '';
    }

    private static function checkDatabase(): string
    {
        $problems = [];
        foreach (self::COLUMNS as $table => $columns) {
            if (!\Schema::hasTable($table)) {
                $problems[] = 'table '.$table.' missing (php artisan migrate)';
                continue;
            }
            foreach ($columns as $column) {
                if (!\Schema::hasColumn($table, $column)) {
                    $problems[] = $table.'.'.$column.' missing';
                }
            }
        }
        if (!$problems) {
            Account::query()->limit(1)->get();   // the model reads its table
        }

        return implode('; ', $problems);
    }

    private static function checkRoutes(): string
    {
        $missing = array_values(array_filter(self::ROUTES, function ($r) {
            return !\Route::has($r);
        }));

        return $missing ? 'missing '.implode(', ', $missing) : '';
    }

    private static function checkViews(): string
    {
        $views = ['hydrolink::panel'];
        foreach (Registry::all() as $feature) {
            if ($feature->view()) {
                $views[] = $feature->view();
            }
        }
        $missing = array_values(array_filter($views, function ($v) {
            return !view()->exists($v);
        }));

        return $missing ? 'missing '.implode(', ', $missing) : '';
    }

    private static function checkTranslations(): string
    {
        $problems = [];
        foreach (['en', 'fr'] as $locale) {
            foreach (['hydrolink::messages.service', 'hydrolink::errors.HL-SVC-01.title'] as $key) {
                if (\Lang::get($key, [], $locale) === $key) {
                    $problems[] = $locale.': '.$key;
                }
            }
        }

        return $problems ? 'missing '.implode(', ', $problems) : '';
    }

    private static function checkStorage(): string
    {
        Paths::ensure();
        foreach ([Paths::run(), Paths::logs(), Paths::requests()] as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                return 'not writable: '.$dir;
            }
        }

        return '';
    }

    private static function checkCommands(): string
    {
        $all = \Artisan::all();
        $missing = array_values(array_filter(self::COMMANDS, function ($c) use ($all) {
            return !isset($all[$c]);
        }));

        return $missing ? 'missing '.implode(', ', $missing) : '';
    }

    private static function checkSchedule(): string
    {
        if (!Registry::all()) {
            return 'no feature found';
        }
        $schedule = new \Illuminate\Console\Scheduling\Schedule();
        Registry::schedule($schedule);
        $commands = implode(' ', array_map(function ($e) {
            return (string) $e->command;
        }, $schedule->events()));
        $missing = array_values(array_filter(['hydrolink:tick', 'hydrolink:auth'], function ($c) use ($commands) {
            return strpos($commands, $c) === false;
        }));

        return $missing ? 'not scheduled: '.implode(', ', $missing) : '';
    }
}
