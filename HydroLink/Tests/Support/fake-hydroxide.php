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
 *                              api@example.test answers an unknown Proton error
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
            if (stripos($line, 'EHLO') === 0) {
                fwrite($sock, "250-Hello\r\n250 AUTH PLAIN\r\n");
            } else {
                fwrite($sock, "221 Bye\r\n");
                fclose($sock);
                unset($clients[$id]);
            }
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
                fwrite($sock, $m[1]." OK LOGIN completed\r\n");
            } else {
                fwrite($sock, $m[1]." NO Invalid username or password\r\n");
            }
        } elseif (preg_match('~^(\S+) LOGOUT~', $line, $m)) {
            fwrite($sock, "* BYE\r\n".$m[1]." OK LOGOUT completed\r\n");
            fclose($sock);
            unset($clients[$id]);
        } else {
            fwrite($sock, "* BAD unknown\r\n");
        }
    }
}
