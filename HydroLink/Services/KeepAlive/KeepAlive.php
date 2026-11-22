<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\KeepAlive;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Notify\Alerts;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Secrets;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Keep-alive: a real action on each Proton account at a regular interval, so that a free account is not seen as
 * inactive (Proton terms, section 3 "Inactive Accounts": free accounts inactive for 24 consecutive months may be
 * deleted; activity = signing in on Proton's apps or, once signed in, using a product, e.g. accessing the inbox or
 * reading an e-mail). Proton does not say whether third-party clients such as hydroxide count: see the README.
 *
 * Methods (run by the cron, through hydroxide, never by the web panel):
 *   read   open the inbox and read the newest e-mail (BODY.PEEK: its unread state does not change, so FreeScout
 *          still imports it if it was new)
 *   self   send an e-mail to the account itself, then (next runs) read it, mark it read and move it to the trash;
 *          FreeScout skips it (filter fetch_emails.should_save_thread)
 *   other  send an e-mail to another address chosen in the panel, then open the inbox as "read" does
 *
 * Sent e-mails carry "[HydroLink keep-alive] <token>" in their subject; the tokens are kept in run/keepalive.json.
 */
final class KeepAlive
{
    const SUBJECT = '[HydroLink keep-alive]';
    const METHODS = ['read', 'self', 'other'];

    /** Runs the due keep-alives and the clean-up of the e-mails sent to the account itself. */
    public static function run(): array
    {
        $out = ['cleaned' => self::cleanup(), 'done' => []];
        $requests = (array) (State::desired()['keepalive_now'] ?? []);
        $max = max(1, (int) Settings::config('keepalive_per_tick', 3));
        $accounts = Account::whereNotNull('bridge_password')->where('status', Account::CONNECTED)->orderBy('id')->get();
        foreach ($accounts as $account) {
            if (count($out['done']) >= $max) {
                break;   // the others at the next run of the cron
            }
            if (self::due($account, $requests)) {
                $out['done'][$account->id] = self::perform($account);
            }
        }
        self::prune();

        return $out;
    }

    public static function due(Account $account, array $requests = []): bool
    {
        $last = $account->keepalive_at ? strtotime((string) $account->keepalive_at) : 0;
        if ((int) ($requests[$account->id] ?? 0) > $last && !self::recentAttempt($account, (int) ($requests[$account->id] ?? 0))) {
            return true;   // "Now" in the panel
        }
        if (!Settings::keepAlive() || self::recentAttempt($account)) {
            return false;
        }
        // first run counted from the connection (a sign-in is itself an activity)
        $base = $last ?: ($account->connected_at ? strtotime((string) $account->connected_at) : 0);

        return time() - $base >= Settings::keepAliveDays() * 86400;
    }

    /** @return string 'ok' or an HL-KA-* code */
    public static function perform(Account $account): string
    {
        self::store(function (array $s) use ($account) {
            $s['attempts'][$account->id] = time();

            return $s;
        });
        $method = Settings::keepAliveMethod();
        $bridge = $account->bridgePassword();
        $code = 'HL-KA-01';
        try {
            if ($method !== 'read') {
                $code = 'HL-KA-02';
                $to = $method === 'self' ? $account->email : Settings::keepAliveTo();
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new KeepAliveError('HL-KA-03', 'no destination address');
                }
                $token = self::token($account);
                Smtp::send(Settings::smtpPort(), $account->email, $bridge, $to, self::message($account->email, $to, $token));
                self::store(function (array $s) use ($token, $account, $method) {
                    $s['tokens'][$token] = ['account' => $account->id, 'method' => $method, 'at' => time(), 'done' => $method !== 'self'];

                    return $s;
                });
                $code = 'HL-KA-01';
            }
            self::readNewest($account->email, $bridge);
        } catch (KeepAliveError $e) {
            return self::failed($account, $e->code(), $e->getMessage());
        } catch (\Throwable $e) {
            return self::failed($account, $code, Secrets::mask($e->getMessage(), [$bridge]));
        }
        $account->keepalive_at = now();
        $account->keepalive_code = null;
        $account->save();
        Alerts::resolve('keepalive:'.$account->id);

        return 'ok';
    }

    /** An e-mail of HydroLink's keep-alive (FreeScout must not import it as a ticket). */
    public static function isOwnMessage(string $subject): bool
    {
        if (strpos($subject, self::SUBJECT) === false || !preg_match('~\[HydroLink keep-alive\] ([a-f0-9]{16})~', $subject, $m)) {
            return false;
        }
        $known = self::state()['tokens'][$m[1]] ?? null;

        return is_array($known) && time() - (int) ($known['at'] ?? 0) < 14 * 86400;
    }

    public static function state(): array
    {
        return JsonFile::read(Paths::run('keepalive.json'));
    }

    public static function message(string $from, string $to, string $token): string
    {
        $date = date('r');
        $text = "Message automatique de HydroLink : maintien en vie du compte Proton, rien a faire.\n"
            ."Automatic message from HydroLink: keeps the Proton account active, nothing to do.\n";

        return 'From: <'.$from.">\n"
            .'To: <'.$to.">\n"
            .'Subject: '.self::SUBJECT.' '.$token."\n"
            .'Date: '.$date."\n"
            .'Message-ID: <'.$token.'.'.time()."@hydrolink.invalid>\n"
            .'X-HydroLink-Keepalive: '.$token."\n"
            ."Auto-Submitted: auto-generated\n"
            ."MIME-Version: 1.0\n"
            ."Content-Type: text/plain; charset=utf-8\n"
            ."Content-Transfer-Encoding: 8bit\n"
            ."\n".$text;
    }

    private static function readNewest(string $email, string $bridge): void
    {
        $imap = Imap::login(Settings::imapPort(), $email, $bridge);
        try {
            $count = $imap->select('INBOX');
            if ($count > 0) {
                $r = $imap->command('FETCH '.$count.' (UID BODY.PEEK[])');
                if (!$r['ok']) {
                    throw new \RuntimeException('FETCH: '.$r['text']);
                }
            }
        } finally {
            $imap->close();
        }
    }

    /**
     * E-mails sent to the account itself: read, marked read and moved to the trash once hydroxide sees them.
     * Given up after one day (the message may never come back to the inbox).
     */
    private static function cleanup(): int
    {
        $cleaned = 0;
        foreach ((array) (self::state()['tokens'] ?? []) as $token => $t) {
            if (!empty($t['done'])) {
                continue;
            }
            $account = Account::find((int) ($t['account'] ?? 0));
            $found = false;
            if ($account && $account->bridge_password && $account->status === Account::CONNECTED) {
                try {
                    $found = self::clean($account->email, $account->bridgePassword(), (string) $token);
                } catch (\Throwable $e) {
                    $found = false;
                }
            }
            if ($found || !$account || time() - (int) ($t['at'] ?? 0) > 86400) {
                self::store(function (array $s) use ($token, $found) {
                    $s['tokens'][$token]['done'] = true;
                    $s['tokens'][$token]['cleaned'] = $found;

                    return $s;
                });
                $cleaned += $found ? 1 : 0;
            }
        }

        return $cleaned;
    }

    private static function clean(string $email, string $bridge, string $token): bool
    {
        $imap = Imap::login(Settings::imapPort(), $email, $bridge);
        try {
            $count = $imap->select('INBOX');
            if ($count === 0) {
                return false;
            }
            $r = $imap->command('FETCH '.max(1, $count - 29).':'.$count.' (UID ENVELOPE)');
            $uid = 0;
            foreach ($r['lines'] as $line) {
                if (strpos($line, $token) !== false && preg_match('~UID (\d+)~', $line, $m)) {
                    $uid = (int) $m[1];
                }
            }
            if ($uid === 0) {
                return false;
            }
            $imap->command('UID FETCH '.$uid.' (BODY[])');          // read it
            $imap->command('UID STORE '.$uid.' +FLAGS (\\Seen)');   // mark it read (Proton API: messages read)
            $imap->command('UID MOVE '.$uid.' "Trash"');           // to the trash; if refused it stays, read

            return true;
        } finally {
            $imap->close();
        }
    }

    private static function failed(Account $account, string $code, string $detail): string
    {
        $account->keepalive_code = $code;
        $account->save();
        Alerts::raise('keepalive:'.$account->id, $code, $account->email.': '.$detail);

        return $code;
    }

    /** No new try within the retry delay after an attempt (failed or not). */
    private static function recentAttempt(Account $account, int $since = 0): bool
    {
        $at = (int) (self::state()['attempts'][$account->id] ?? 0);

        return $at > $since && time() - $at < (int) Settings::config('keepalive_retry', 3600);
    }

    private static function token(Account $account): string
    {
        return substr(hash_hmac('sha256', $account->email.'|'.microtime(true).'|'.random_int(0, PHP_INT_MAX), (string) config('app.key')), 0, 16);
    }

    private static function prune(): void
    {
        self::store(function (array $s) {
            foreach ((array) ($s['tokens'] ?? []) as $token => $t) {
                if (time() - (int) ($t['at'] ?? 0) > 30 * 86400) {
                    unset($s['tokens'][$token]);
                }
            }

            return $s;
        });
    }

    private static function store(callable $change): void
    {
        JsonFile::update(Paths::run('keepalive.json'), $change);
    }
}
