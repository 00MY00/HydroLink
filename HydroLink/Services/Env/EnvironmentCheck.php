<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Env;

use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\Ports;
use Modules\HydroLink\Services\Process\ProcessRunner;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Checks of the environment. The ones that need the CLI (proc_open, executing from storage/) are done by the cron
 * and saved in state.json; the panel only reads them (its own PHP may differ: PHP-FPM can disable proc_open).
 */
final class EnvironmentCheck
{
    /** Linux architectures of the published binaries (uname -m => Go GOARCH). */
    const ARCHES = ['x86_64' => 'amd64', 'amd64' => 'amd64', 'aarch64' => 'arm64', 'arm64' => 'arm64'];

    public static function arch(?string $machine = null): string
    {
        $machine = strtolower($machine ?? php_uname('m'));

        return self::ARCHES[$machine] ?? '';
    }

    public static function storageWritable(): bool
    {
        Paths::ensure();

        return is_dir(Paths::base()) && is_writable(Paths::base());
    }

    /** A tiny script in storage/app/hydrolink/tmp is executed: fails when the filesystem is mounted noexec. */
    public static function storageExecutable(): bool
    {
        $script = Paths::tmp('exec-test.sh');
        if (@file_put_contents($script, "#!/bin/sh\necho hydrolink-ok\n") === false) {
            return false;
        }
        @chmod($script, 0700);
        $r = ProcessRunner::run([$script], [], 5);
        @unlink($script);

        return strpos($r['out'], 'hydrolink-ok') !== false;
    }

    /** A local port is used by another program (not by our hydroxide). */
    public static function portBusy(int $port): bool
    {
        return !HydroxideProcess::running() && Ports::listening($port);
    }

    /** Run by the cron: results saved in state.json "env". */
    public static function runCli(): array
    {
        $procOpen = ProcessRunner::available();
        $env = [
            'checked_at' => time(),
            'proc_open'  => $procOpen,
            'writable'   => self::storageWritable(),
            'exec'       => $procOpen && self::storageExecutable(),
            'arch'       => self::arch(),
            'machine'    => php_uname('m'),
            'php'        => PHP_VERSION,
        ];
        State::put('env', $env);

        return $env;
    }

    /**
     * Problems for the panel and the health check, from the cron's last results + what the web can check itself.
     *
     * @return string[] error codes
     */
    public static function problems(?array $real = null): array
    {
        $real = $real ?? State::real();
        if (!State::cronAlive($real)) {
            return ['HL-ENV-05'];
        }
        $env = (array) ($real['env'] ?? []);
        $codes = [];
        if (empty($env['proc_open'])) {
            $codes[] = 'HL-ENV-01';
        }
        if (empty($env['writable'])) {
            $codes[] = 'HL-ENV-06';
        } elseif (!empty($env['proc_open']) && empty($env['exec'])) {
            $codes[] = 'HL-ENV-02';
        }
        if (empty($env['arch'])) {
            $codes[] = 'HL-ENV-04';
        }
        if (in_array('HL-ENV-03', (array) ($real['service']['errors'] ?? []), true)) {
            $codes[] = 'HL-ENV-03';
        }

        return $codes;
    }

    /** Ports for the panel ("1143 / 1025"). */
    public static function ports(): array
    {
        return ['imap' => Settings::imapPort(), 'smtp' => Settings::smtpPort()];
    }
}
