<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;

/**
 * "hydroxide serve" for all the accounts at once (one process: the sessions of every account of auth.json are served
 * by the same auth.Manager, keyed by user name). CardDAV is disabled, IMAP and SMTP listen on 127.0.0.1 only.
 * Called by the cron only.
 */
final class HydroxideProcess
{
    public static function argv(): array
    {
        return [
            Paths::bin(),
            '-imap-host', Ports::HOST, '-imap-port', (string) Settings::imapPort(),
            '-smtp-host', Ports::HOST, '-smtp-port', (string) Settings::smtpPort(),
            '-disable-carddav',
            'serve',
        ];
    }

    public static function pid(): int
    {
        return (int) (State::real()['pid'] ?? 0);
    }

    /** The pid of state.json is alive AND is still hydroxide serve (pids are reused by the system). */
    public static function running(?int $pid = null): bool
    {
        $pid = $pid ?? self::pid();
        if (!ProcessRunner::isAlive($pid)) {
            return false;
        }
        $cmd = ProcessRunner::cmdline($pid);

        return $cmd === '' || (strpos($cmd, 'hydroxide') !== false && strpos($cmd, 'serve') !== false);
    }

    /**
     * Our hydroxide serve found by its command line in /proc (when the pid of state.json is lost or was the pid of a
     * wrapper). Updates state.json. 0 when none.
     */
    public static function find(): int
    {
        foreach ((array) @glob('/proc/[0-9]*', GLOB_ONLYDIR) as $dir) {
            $pid = (int) basename((string) $dir);
            $cmd = ProcessRunner::cmdline($pid);
            if ($cmd !== '' && strpos($cmd, Paths::bin().' ') === 0 && substr($cmd, -6) === ' serve') {
                State::report(['pid' => $pid]);

                return $pid;
            }
        }

        return 0;
    }

    /** @return array{ok:bool,pid:int,error:string} */
    public static function start(): array
    {
        if (!is_file(Paths::bin()) || !is_executable(Paths::bin())) {
            return ['ok' => false, 'pid' => 0, 'error' => 'HL-BIN-01'];
        }
        foreach ([Settings::imapPort(), Settings::smtpPort()] as $port) {
            if (Ports::listening($port)) {
                return ['ok' => false, 'pid' => 0, 'error' => 'HL-ENV-03'];
            }
        }
        Paths::ensure();
        self::markLog('start');
        $pid = ProcessRunner::spawnDetached(self::argv(), Paths::serveLog(), Paths::processEnv());
        if ($pid === 0) {
            return ['ok' => false, 'pid' => 0, 'error' => 'HL-ENV-01'];
        }
        State::report(['pid' => $pid, 'started_at' => time()]);

        return ['ok' => true, 'pid' => $pid, 'error' => ''];
    }

    public static function stop(): void
    {
        $pid = self::pid();
        if ($pid > 0 && self::running($pid)) {
            self::markLog('stop');
            ProcessRunner::terminate($pid);
        }
        State::report(['pid' => 0]);
    }

    /** Waits until both local ports answer (or the process died). */
    public static function waitReady(int $seconds = 15): bool
    {
        $deadline = time() + $seconds;
        while (time() <= $deadline) {
            if (!self::running()) {
                return false;
            }
            if (Ports::listening(Settings::imapPort()) && Ports::listening(Settings::smtpPort())) {
                return true;
            }
            usleep(300000);
        }

        return false;
    }

    /** A line in hydroxide.log so that the log shows where each run starts (the log reader uses it too). */
    private static function markLog(string $what): void
    {
        @file_put_contents(Paths::serveLog(), '-- hydrolink '.$what.' '.date('c')."\n", FILE_APPEND);
    }
}
