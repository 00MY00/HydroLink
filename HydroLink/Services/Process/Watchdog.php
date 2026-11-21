<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

use Modules\HydroLink\Services\Accounts\AccountService;
use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Binary\ReleaseSource;
use Modules\HydroLink\Services\Env\EnvironmentCheck;
use Modules\HydroLink\Services\Health\HealthChecker;
use Modules\HydroLink\Services\Notify\Alerts;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;

/**
 * One cron run (hydrolink:tick, every minute): environment, binary requests and automatic updates, removals,
 * start / restart of hydroxide according to desired.json, health check. Never run twice at the same time.
 */
final class Watchdog
{
    const LOG_MAX = 5242880;

    public static function tick(): array
    {
        $lock = Lock::acquire('watchdog');
        if (!$lock) {
            return ['skipped' => true];
        }
        Paths::ensure();
        State::report(['tick' => time()]);
        $env = EnvironmentCheck::runCli();
        $report = ['env' => $env];
        if ($env['proc_open']) {
            $report['binary'] = self::binary();
            $report['service'] = self::service();
            if (HealthChecker::due()) {
                $report['health'] = HealthChecker::run();
            }
        }
        self::rotateLog();
        $lock->release();

        return $report;
    }

    /** Update asked in the panel, first installation, or the periodic automatic update. */
    public static function binary(): array
    {
        $desired = State::desired();
        $real = State::real();
        $asked = (int) ($desired['binary_request'] ?? 0) > (int) ($real['binary']['request_done'] ?? 0);
        $pendingVt = ($real['binary']['last']['result'] ?? '') === 'pending_vt';
        $periodic = time() - (int) ($real['binary']['checked_at'] ?? 0) >= (int) Settings::config('release_interval', 21600);
        if (!$asked && !$pendingVt && !$periodic) {
            return [];
        }
        // first installation (no binary yet) is automatic, later updates only when "automatic update" is on
        $wantsInstall = $asked || $pendingVt || ($periodic && (Settings::autoUpdate() || !is_file(Paths::bin())));
        $result = $wantsInstall ? (new BinaryManager())->update() : ['result' => 'checked', 'code' => '', 'version' => ''];
        $latest = $result['version'] !== '' ? $result['version'] : (new ReleaseSource())->latestVersion();
        State::report(['binary' => [
            'checked_at'   => time(),
            'latest'       => $latest,
            'request_done' => $asked ? (int) $desired['binary_request'] : (int) ($real['binary']['request_done'] ?? 0),
            'last'         => $result + ['at' => time()],
        ]]);
        self::binaryAlert($result);

        return $result;
    }

    private static function binaryAlert(array $result): void
    {
        if ($result['code'] !== '') {
            Alerts::raise('binary', $result['code'], $result['detail'] ?? '');
        } elseif (in_array($result['result'], ['installed', 'up_to_date'], true)) {
            Alerts::resolve('binary');
        }
    }

    /** Desired state => process state. */
    public static function service(): array
    {
        $desired = State::desired();
        $real = State::real();
        $want = !empty($desired['running']) && AccountService::servable() > 0 && is_file(Paths::bin());
        $alive = HydroxideProcess::running() || HydroxideProcess::find() > 0;
        if (AccountService::hasRemovals()) {
            if ($alive) {
                HydroxideProcess::stop();
                $alive = false;
            }
            AccountService::applyRemovals();
        }
        if ((int) ($desired['restart'] ?? 0) > (int) ($real['restart_done'] ?? 0)) {
            State::report(['restart_done' => (int) $desired['restart']]);
            State::put('starts', []);
            if ($alive) {
                HydroxideProcess::stop();
                $alive = false;
            }
        }
        if (!$want) {
            if ($alive) {
                HydroxideProcess::stop();
            }
            Alerts::resolvePrefix('service:');

            return self::serviceState('stopped');
        }

        return $alive ? self::serviceState('running') : self::startGuarded();
    }

    /** Starts hydroxide unless it already stopped too often (crash loop): then HL-SVC-01 until a manual restart. */
    private static function startGuarded(): array
    {
        $window = (int) Settings::config('max_starts_window', 600);
        $starts = array_values(array_filter((array) (State::real()['starts'] ?? []), function ($t) use ($window) {
            return (int) $t > time() - $window;
        }));
        if (count($starts) >= (int) Settings::config('max_starts', 5)) {
            Alerts::raise('service:process', 'HL-SVC-01', count($starts).' starts');

            return self::serviceState('failed', ['HL-SVC-01']);
        }
        $starts[] = time();
        State::put('starts', $starts);
        $start = HydroxideProcess::start();
        if (!$start['ok']) {
            Alerts::raise('service:process', $start['error']);

            return self::serviceState('failed', [$start['error']]);
        }
        if (!HydroxideProcess::waitReady(15)) {
            Alerts::raise('service:process', 'HL-SVC-03', 'ports not ready');

            return self::serviceState('starting');
        }
        Alerts::resolve('service:process');
        State::report(['health' => ['at' => 0]]);

        return self::serviceState('running');
    }

    private static function serviceState(string $status, array $errors = []): array
    {
        $service = ['status' => $status, 'errors' => $errors, 'at' => time()];
        State::put('service', $service);

        return $service;
    }

    /** hydroxide.log above 5 MB: copied to hydroxide.log.1 and emptied (hydroxide keeps writing in append mode). */
    private static function rotateLog(): void
    {
        $log = Paths::serveLog();
        if (is_file($log) && filesize($log) > self::LOG_MAX) {
            @copy($log, $log.'.1');
            @file_put_contents($log, '');
        }
    }
}
