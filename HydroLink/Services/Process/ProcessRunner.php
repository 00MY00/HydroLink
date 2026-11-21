<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

/**
 * proc_open wrapper. Commands are always arrays of arguments: PHP >= 7.4 runs them without a shell; older PHP gets
 * every argument escaped with escapeshellarg() and "exec", so no value is ever interpreted by a shell.
 */
final class ProcessRunner
{
    public static function available(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    /** @return array|string what proc_open receives */
    public static function command(array $argv)
    {
        $argv = array_map('strval', $argv);
        if (PHP_VERSION_ID >= 70400) {
            return $argv;
        }

        return 'exec '.implode(' ', array_map('escapeshellarg', $argv));
    }

    /**
     * Runs a command to the end (or kills it after $timeout seconds).
     *
     * @return array{code:int,out:string,err:string,timeout:bool}
     */
    public static function run(array $argv, array $env = [], int $timeout = 30, string $input = ''): array
    {
        $proc = @proc_open(self::command($argv), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env ?: null);
        if (!is_resource($proc)) {
            return ['code' => -1, 'out' => '', 'err' => 'proc_open failed', 'timeout' => false];
        }
        if ($input !== '') {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $deadline = microtime(true) + $timeout;
        $timedOut = false;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $code = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                $timedOut = true;
                $code = -1;
                break;
            }
            usleep(50000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return ['code' => $code, 'out' => $out, 'err' => $err, 'timeout' => $timedOut];
    }

    /**
     * Starts a long-running process detached from PHP (it survives the end of the cron run) and returns its pid.
     * The only shell used is "sh -c" with a fixed script; the program and its arguments are passed as positional
     * parameters ("$@"), never inserted into the script text.
     */
    public static function spawnDetached(array $argv, string $logFile, array $env = []): int
    {
        $script = 'log="$1"; shift; '
            .'if command -v setsid >/dev/null 2>&1; then setsid "$@" >>"$log" 2>&1 </dev/null & '
            .'else "$@" >>"$log" 2>&1 </dev/null & fi; echo $!';
        $r = self::run(array_merge(['/bin/sh', '-c', $script, 'hydrolink', $logFile], $argv), $env, 10);
        $pid = (int) trim($r['out']);

        return $pid > 1 ? $pid : 0;
    }

    /** Interactive process (hydroxide auth): pipes left open, stdout/stderr non-blocking. */
    public static function open(array $argv, array $env)
    {
        $proc = @proc_open(self::command($argv), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            return null;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return ['proc' => $proc, 'pipes' => $pipes];
    }

    public static function isAlive(int $pid): bool
    {
        if ($pid <= 1) {
            return false;
        }
        if (is_dir('/proc/'.$pid)) {
            $stat = (string) @file_get_contents('/proc/'.$pid.'/stat');

            // a zombie ("Z") is not alive
            return !preg_match('~^\d+ \(.*\) Z~', $stat);
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return false;
    }

    /** Command line of a process (to make sure a pid is still our hydroxide and not a reused number). */
    public static function cmdline(int $pid): string
    {
        $raw = (string) @file_get_contents('/proc/'.$pid.'/cmdline');

        return trim(str_replace("\0", ' ', $raw));
    }

    /** SIGTERM, then SIGKILL after $grace seconds. */
    public static function terminate(int $pid, int $grace = 5): void
    {
        if (!self::isAlive($pid)) {
            return;
        }
        self::signal($pid, 15);
        $deadline = microtime(true) + $grace;
        while (microtime(true) < $deadline) {
            if (!self::isAlive($pid)) {
                return;
            }
            usleep(100000);
        }
        self::signal($pid, 9);
    }

    private static function signal(int $pid, int $signal): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, $signal);

            return;
        }
        self::run(['kill', '-'.$signal, (string) $pid], [], 5);
    }
}
