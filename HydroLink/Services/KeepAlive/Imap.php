<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\KeepAlive;

use Modules\HydroLink\Services\Health\Probes;
use Modules\HydroLink\Services\Process\Ports;

/**
 * Minimal IMAP client towards hydroxide on 127.0.0.1 (RFC 3501): tagged commands, literals ({n}) read inline.
 * Only what the keep-alive needs: LOGIN, SELECT, FETCH, UID FETCH / STORE / MOVE, LOGOUT.
 */
final class Imap
{
    /** @var resource */
    private $s;

    private $tag = 0;

    private function __construct($socket)
    {
        $this->s = $socket;
    }

    /** @throws \RuntimeException with the server's answer */
    public static function login(int $port, string $user, string $password, int $timeout = 60): self
    {
        $s = Ports::open($port, 3.0);
        if (!$s) {
            throw new \RuntimeException('no connection to the local IMAP server');
        }
        stream_set_timeout($s, $timeout);
        $greeting = fgets($s, 4096);
        if ($greeting === false || strpos($greeting, '* OK') !== 0) {
            fclose($s);
            throw new \RuntimeException('no IMAP greeting');
        }
        $imap = new self($s);
        $r = $imap->command('LOGIN '.Probes::quote($user).' '.Probes::quote($password));
        if (!$r['ok']) {
            $imap->close();
            throw new \RuntimeException('LOGIN: '.$r['text']);
        }

        return $imap;
    }

    /** @return array{ok:bool,text:string,lines:string[]} lines = untagged answers, literals included */
    public function command(string $command): array
    {
        $tag = 'k'.(++$this->tag);
        fwrite($this->s, $tag.' '.$command."\r\n");
        $lines = [];
        while (true) {
            $line = $this->readLine();
            if ($line === null) {
                return ['ok' => false, 'text' => 'timeout', 'lines' => $lines];
            }
            if (strpos($line, $tag.' ') === 0) {
                $text = substr($line, strlen($tag) + 1);

                return ['ok' => strpos($text, 'OK') === 0, 'text' => mb_substr($text, 0, 300), 'lines' => $lines];
            }
            $lines[] = $line;
        }
    }

    /** SELECT a mailbox, @return int number of messages (EXISTS) */
    public function select(string $mailbox = 'INBOX'): int
    {
        $r = $this->command('SELECT '.Probes::quote($mailbox));
        if (!$r['ok']) {
            throw new \RuntimeException('SELECT: '.$r['text']);
        }
        foreach ($r['lines'] as $line) {
            if (preg_match('~^\* (\d+) EXISTS~', $line, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    public function close(): void
    {
        if (is_resource($this->s)) {
            @fwrite($this->s, 'k'.(++$this->tag)." LOGOUT\r\n");
            @fclose($this->s);
        }
    }

    /** One logical line: a line ending with {n} is followed by n bytes and the rest of the line. */
    private function readLine(): ?string
    {
        $out = '';
        while (true) {
            $line = fgets($this->s, 65536);
            if ($line === false) {
                return null;
            }
            $line = rtrim($line, "\r\n");
            if (!preg_match('~\{(\d+)\}$~', $line, $m)) {
                return $out.$line;
            }
            $out .= $line;
            $left = (int) $m[1];
            while ($left > 0) {
                $chunk = fread($this->s, min($left, 65536));
                if ($chunk === false || $chunk === '') {
                    return null;
                }
                $out .= $chunk;
                $left -= strlen($chunk);
            }
        }
    }
}
