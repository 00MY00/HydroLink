#!/usr/bin/env php
<?php
/*
 * Test double of hydroxide (no Proton account is used in the tests). Same command line, same messages and the
 * same files as the real program (cmd/hydroxide/main.go with build/patches/0001), for these scenarios:
 *
 *   auth plain@example.test    signs in directly
 *   auth totp@example.test     asks "2FA TOTP code: ", accepts 123456
 *   auth captcha@example.test  prints the human verification URL, waits for a line, then signs in
 *   auth twopw@example.test    needs HYDROXIDE_MAILBOX_PASSWORD (otherwise the /dev/tty error of askPass)
 *   auth wrong@example.test    "[8002] Incorrect login credentials"
 *   serve                      IMAP and SMTP on 127.0.0.1; LOGIN checks the bridge password saved by auth;
 *                              api@example.test answers an unknown Proton error. Mailboxes in mail.json
 *                              (SELECT, FETCH with literals, UID FETCH / STORE / MOVE), SMTP AUTH PLAIN and
 *                              sending (a message to a signed-in account lands in its inbox, RCPT
 *                              reject@example.test is refused); every action is written to actions.log
 *   -h                         usage
 */

$args = array_slice($argv, 1);
$opts = ['imap-port' => '1143', 'smtp-port' => '1025'];
while ($args && $args[0][0] === '-') {
    $flag = ltrim(array_shift($args), '-');
    if ($flag === 'h' || $flag === 'help') {
        echo "usage: hydroxide [options...] <command>\n";
        exit(0);
    }
    if (in_array($flag, ['disable-carddav', 'disable-imap', 'disable-smtp', 'debug'], true)) {
        continue;
    }
    $opts[$flag] = array_shift($args);
}
$cmd = array_shift($args) ?? '';
$config = (getenv('XDG_CONFIG_HOME') ?: sys_get_temp_dir()).'/hydroxide';
@mkdir($config, 0700, true);
$authFile = $config.'/auth.json';

function logline($text)
{
    fwrite(STDERR, date('Y/m/d H:i:s').' '.$text."\n");
}

function fatal($text)
{
    logline($text);
    exit(1);
}

function save($authFile, $user)
{
    $auths = is_file($authFile) ? (json_decode(file_get_contents($authFile), true) ?: []) : [];
    $auths[$user] = base64_encode((string) getenv('HYDROXIDE_BRIDGE_PASSWORD'));
    file_put_contents($authFile, json_encode($auths));
    echo "Authentication saved using the configured bridge password.\n";
    exit(0);
}

if ($cmd === 'auth') {
    $user = $args[0] ?? '';
    if ((string) getenv('HYDROXIDE_PASSWORD') === '') {
        fatal('open /dev/tty: no such device or address');
    }
    switch ($user) {
        case 'wrong@example.test':
            fatal('[8002] Incorrect login credentials');
        case 'captcha@example.test':
            logline('Human verification required.');
            logline('Please open the following URL and press Enter once the challenge has been completed:');
            logline('https://verify.proton.me/?methods=captcha&token=TEST-TOKEN-123');
            fgets(STDIN);
            save($authFile, $user);
        case 'totp@example.test':
            echo '2FA TOTP code: ';
            $code = trim((string) fgets(STDIN));
            $code === '123456' ? save($authFile, $user) : fatal('[12062] Incorrect code');
        case 'twopw@example.test':
            (string) getenv('HYDROXIDE_MAILBOX_PASSWORD') !== '' ? save($authFile, $user) : fatal('open /dev/tty: no such device or address');
        default:
            save($authFile, $user);
    }
}

if ($cmd !== 'serve') {
    fatal('Unrecognized command');
}

$imap = stream_socket_server('tcp://127.0.0.1:'.$opts['imap-port'], $e1, $s1);
$smtp = stream_socket_server('tcp://127.0.0.1:'.$opts['smtp-port'], $e2, $s2);
if (!$imap || !$smtp) {
    fatal('listen tcp: address already in use');
}
logline('IMAP server listening on 127.0.0.1:'.$opts['imap-port']);
logline('SMTP server listening on 127.0.0.1:'.$opts['smtp-port']);
$clients = [];
$mailFile = $config.'/mail.json';
$actions = $config.'/actions.log';

function mail_load($file)
{
    return is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}

function mail_save($file, $data)
{
    file_put_contents($file, json_encode($data));
}

function act($file, $text)
{
    file_put_contents($file, $text."\n", FILE_APPEND);
}

function raw_message($m)
{
    return 'Subject: '.$m['subject']."\r\nFrom: <".$m['from'].">\r\n\r\n".$m['body']."\r\n";
}

while (true) {
    $read = array_merge([$imap, $smtp], array_column($clients, 'sock'));
    $w = $x = null;
    if (@stream_select($read, $w, $x, 5) === false) {
        continue;
    }
    foreach ($read as $sock) {
        if ($sock === $imap || $sock === $smtp) {
            $c = @stream_socket_accept($sock, 1);
            if ($c) {
                $kind = $sock === $imap ? 'imap' : 'smtp';
                fwrite($c, $kind === 'imap' ? "* OK [CAPABILITY IMAP4rev1] Hydroxide ready\r\n" : "220 localhost ESMTP Service Ready\r\n");
                $clients[(int) $c] = ['sock' => $c, 'kind' => $kind];
            }
            continue;
        }
        $line = fgets($sock);
        $id = (int) $sock;
        if ($line === false) {
            fclose($sock);
            unset($clients[$id]);
            continue;
        }
        $line = rtrim($line, "\r\n");
        if ($clients[$id]['kind'] === 'smtp') {
            $c = &$clients[$id];
            if (!empty($c['data'])) {
                if ($line !== '.') {
                    $c['body'][] = substr($line, 0, 2) === '..' ? substr($line, 1) : $line;
                    continue;
                }
                $c['data'] = false;
                $subject = '';
                foreach ($c['body'] as $h) {
                    if (stripos($h, 'Subject: ') === 0) {
                        $subject = substr($h, 9);
                    }
                }
                $auths = is_file($authFile) ? (json_decode(file_get_contents($authFile), true) ?: []) : [];
                $mail = mail_load($mailFile);
                foreach ($c['rcpt'] as $to) {
                    act($actions, $c['user'].' sent '.$to.' '.$subject);
                    if (isset($auths[$to])) {
                        $mail[$to] = $mail[$to] ?? [];
                        $uid = 1 + max(array_merge([0], array_column($mail[$to], 'uid')));
                        $mail[$to][] = ['uid' => $uid, 'subject' => $subject, 'from' => $c['user'], 'body' => 'keep-alive', 'seen' => false];
                    }
                }
                mail_save($mailFile, $mail);
                fwrite($sock, "250 OK: queued\r\n");
            } elseif (stripos($line, 'EHLO') === 0) {
                fwrite($sock, "250-Hello\r\n250 AUTH PLAIN\r\n");
            } elseif (stripos($line, 'AUTH PLAIN ') === 0) {
                $parts = explode("\0", (string) base64_decode(substr($line, 11)));
                $auths = is_file($authFile) ? (json_decode(file_get_contents($authFile), true) ?: []) : [];
                $user = $parts[1] ?? '';
                if (isset($auths[$user]) && $auths[$user] === base64_encode($parts[2] ?? '')) {
                    $c['user'] = $user;
                    fwrite($sock, "235 2.7.0 Authentication successful\r\n");
                } else {
                    fwrite($sock, "535 5.7.8 Invalid username or password\r\n");
                }
            } elseif (stripos($line, 'MAIL FROM:') === 0) {
                $c['rcpt'] = [];
                fwrite($sock, empty($c['user']) ? "530 5.7.0 Authentication required\r\n" : "250 OK\r\n");
            } elseif (stripos($line, 'RCPT TO:') === 0) {
                $to = trim(substr($line, 8), '<> ');
                if ($to === 'reject@example.test') {
                    fwrite($sock, "550 cannot send to this address\r\n");
                } else {
                    $c['rcpt'][] = $to;
                    fwrite($sock, "250 OK\r\n");
                }
            } elseif (strtoupper($line) === 'DATA') {
                $c['data'] = true;
                $c['body'] = [];
                fwrite($sock, "354 Start mail input\r\n");
            } else {
                fwrite($sock, "221 Bye\r\n");
                fclose($sock);
                unset($clients[$id]);
            }
            unset($c);
            continue;
        }
        if (preg_match('~^(\S+) LOGIN "((?:[^"\\\\]|\\\\.)*)" "((?:[^"\\\\]|\\\\.)*)"~', $line, $m)) {
            $user = stripslashes($m[2]);
            $pass = stripslashes($m[3]);
            $auths = is_file($authFile) ? (json_decode(file_get_contents($authFile), true) ?: []) : [];
            if ($user === 'api@example.test' && isset($auths[$user])) {
                logline('cannot handle event: [2500] Unexpected new field');
                fwrite($sock, $m[1]." NO [2500] Unexpected new field\r\n");
            } elseif (isset($auths[$user]) && $auths[$user] === base64_encode($pass)) {
                logline('User "'.$user.'" logged in via IMAP');
                $clients[$id]['user'] = $user;
                fwrite($sock, $m[1]." OK LOGIN completed\r\n");
            } else {
                fwrite($sock, $m[1]." NO Invalid username or password\r\n");
            }
        } elseif (preg_match('~^(\S+) SELECT "?INBOX"?~i', $line, $m) && !empty($clients[$id]['user'])) {
            $box = mail_load($mailFile)[$clients[$id]['user']] ?? [];
            act($actions, $clients[$id]['user'].' select INBOX');
            fwrite($sock, '* '.count($box)." EXISTS\r\n".$m[1]." OK [READ-WRITE] SELECT completed\r\n");
        } elseif (preg_match('~^(\S+) (UID )?(FETCH|STORE|MOVE) (\d+)(?::(\d+))? (.*)$~i', $line, $m) && !empty($clients[$id]['user'])) {
            $user = $clients[$id]['user'];
            $mail = mail_load($mailFile);
            $box = array_values($mail[$user] ?? []);
            $byUid = $m[2] !== '';
            $from = (int) $m[4];
            $to = $m[5] !== '' ? (int) $m[5] : $from;
            $out = '';
            foreach ($box as $i => $msg) {
                $seq = $i + 1;
                $key = $byUid ? $msg['uid'] : $seq;
                if ($key < $from || $key > $to) {
                    continue;
                }
                $verb = strtoupper($m[3]);
                if ($verb === 'FETCH' && stripos($m[6], 'ENVELOPE') !== false) {
                    $out .= '* '.$seq.' FETCH (UID '.$msg['uid'].' ENVELOPE (NIL "'.addslashes($msg['subject']).'" NIL NIL NIL NIL NIL NIL NIL NIL))'."\r\n";
                } elseif ($verb === 'FETCH') {
                    $raw = raw_message($msg);
                    act($actions, $user.' read '.$msg['uid'].(stripos($m[6], 'PEEK') !== false ? ' peek' : ''));
                    $out .= '* '.$seq.' FETCH (UID '.$msg['uid'].' BODY[] {'.strlen($raw)."}\r\n".$raw.")\r\n";
                } elseif ($verb === 'STORE') {
                    $mail[$user][$i]['seen'] = true;
                    act($actions, $user.' seen '.$msg['uid']);
                    $out .= '* '.$seq." FETCH (FLAGS (\\Seen))\r\n";
                } else {
                    unset($mail[$user][$i]);
                    act($actions, $user.' trash '.$msg['uid']);
                }
            }
            if (isset($mail[$user])) {
                $mail[$user] = array_values($mail[$user]);
            }
            mail_save($mailFile, $mail);
            fwrite($sock, $out.$m[1].' OK '.strtoupper($m[3])." completed\r\n");
        } elseif (preg_match('~^(\S+) LOGOUT~', $line, $m)) {
            fwrite($sock, "* BYE\r\n".$m[1]." OK LOGOUT completed\r\n");
            fclose($sock);
            unset($clients[$id]);
        } else {
            $tag = strtok($line, ' ');
            fwrite($sock, ($tag !== false ? $tag : '*')." BAD unknown command\r\n");
        }
    }
}
