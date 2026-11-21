<?php

use Modules\HydroLink\Services\Accounts\AuthStore;
use Modules\HydroLink\Services\Accounts\OutputParser;
use Modules\HydroLink\Services\Binary\Checksum;
use Modules\HydroLink\Services\Health\ErrorClassifier;
use Modules\HydroLink\Services\Health\Probes;
use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Secrets;
use Modules\HydroLink\Services\Env\EnvironmentCheck;

test('SHA256SUMS: text and binary mode, unknown file, comparison', function () {
    $h = str_repeat('ab', 32);
    $sums = "$h  hydroxide-linux-amd64\n".strtoupper(str_repeat('cd', 32))." *HydroLink.zip\nnot a line\n";
    same($h, Checksum::expected($sums, 'hydroxide-linux-amd64'));
    same(str_repeat('cd', 32), Checksum::expected($sums, 'HydroLink.zip'));
    same('', Checksum::expected($sums, 'hydroxide-linux-arm64'));
    check(Checksum::matches($h, strtoupper($h)), 'case-insensitive');
    check(!Checksum::matches('', ''), 'empty never matches');
    check(!Checksum::matches($h, str_repeat('ab', 31).'ac'), 'one digit differs');
});

test('Secrets: known values, bridge passwords, verification tokens are masked', function () {
    $bridge = base64_encode(str_repeat("\x01", 32));
    $text = "login pass=Hunter2Secret bridge=$bridge url=https://verify.proton.me/?methods=captcha&token=abc.DEF-123 Bridge password: xyz";
    $masked = Secrets::mask($text, ['Hunter2Secret']);
    check(strpos($masked, 'Hunter2Secret') === false, 'password masked');
    check(strpos($masked, $bridge) === false, 'bridge password masked');
    check(strpos($masked, 'abc.DEF-123') === false, 'token masked');
    check(strpos($masked, 'methods=captcha') !== false, 'rest of the URL kept');
    check(strpos($masked, 'xyz') === false, 'printed bridge password masked');
    same(44, strlen(Secrets::newBridgePassword()), 'base64 of 32 bytes');
    same(32, strlen(base64_decode(Secrets::newBridgePassword())));
});

test('ErrorClassifier: hydroxide messages to HL codes', function () {
    same('HL-AUTH-01', ErrorClassifier::classify('[9001] Human verification required'));
    same('HL-AUTH-02', ErrorClassifier::classify('cannot re-authenticate: [10013] Invalid refresh token'));
    same('HL-AUTH-02', ErrorClassifier::classify('cannot re-authenticate: two factor authentication enabled, please login again manually'));
    same('HL-AUTH-02', ErrorClassifier::classify('NO Invalid username or password'));
    same('HL-AUTH-04', ErrorClassifier::classify('Only TOTP is supported as a 2FA method'));
    same('HL-AUTH-06', ErrorClassifier::classify('open /dev/tty: no such device or address'));
    same('HL-NET-01', ErrorClassifier::classify('Post "https://mail.proton.me/api/auth": dial tcp: lookup mail.proton.me: no such host'));
    same('HL-API-01', ErrorClassifier::classify('[2001] Some new error'));
    same('HL-API-01', ErrorClassifier::classify('json: cannot unmarshal string into Go value'));
    same('', ErrorClassifier::classify('  '));
    same(8002, ErrorClassifier::apiCode('[8002] Incorrect login credentials'));
    same('fatal thing', ErrorClassifier::stripLogPrefix('2026/11/21 14:00:00 fatal thing'));
});

test('OutputParser: 2FA prompt, verification URL, success, last error', function () {
    $p = new OutputParser();
    $p->feed('', "2026/11/21 10:00:00 Human verification required.\n2026/11/21 10:00:00 Please open the following URL and press Enter once the challenge has been completed:\n");
    same('', $p->newVerificationUrl(), 'no URL yet');
    $p->feed('', "2026/11/21 10:00:00 https://verify.proton.me/?methods=captcha&token=T1\n");
    same('https://verify.proton.me/?methods=captcha&token=T1', $p->newVerificationUrl());
    same('', $p->newVerificationUrl(), 'URL reported once');
    check(!$p->newTotpPrompt(), 'no prompt yet');
    $p->feed('2FA TOTP code: ', '');
    check($p->newTotpPrompt(), 'prompt seen');
    check(!$p->newTotpPrompt(), 'prompt reported once');
    check(!$p->saved(), 'not saved yet');
    $p->feed("Authentication saved using the configured bridge password.\n", '');
    check($p->saved(), 'saved');
    $q = new OutputParser();
    $q->feed('', "2026/11/21 10:00:00 [8002] Incorrect login credentials\n");
    same('[8002] Incorrect login credentials', $q->lastError());
});

test('AuthStore: removing a session keeps the others, an empty store stays a JSON object', function () {
    $file = tmpdir().'/auth.json';
    JsonFile::write($file, ['a@example.com' => 'x', 'b@example.com' => 'y']);
    check(AuthStore::has('a@example.com', $file), 'present');
    check(AuthStore::remove('a@example.com', $file), 'removed');
    same(['b@example.com'], AuthStore::usernames($file));
    check(!AuthStore::remove('a@example.com', $file), 'already removed');
    check(AuthStore::remove('b@example.com', $file), 'last one');
    same('{}', trim(file_get_contents($file)), 'hydroxide decodes a map: {} not []');
});

test('JsonFile: atomic write, update under lock, default on broken file', function () {
    $file = tmpdir().'/state.json';
    same(['d' => 1], JsonFile::read($file, ['d' => 1]));
    JsonFile::write($file, ['a' => 1]);
    JsonFile::update($file, function ($d) { $d['b'] = 2; return $d; });
    same(['a' => 1, 'b' => 2], JsonFile::read($file));
    file_put_contents($file, '{broken');
    same([], JsonFile::read($file));
});

test('IMAP quoting and architectures', function () {
    same('"a\\"b\\\\c"', Probes::quote('a"b\\c'));
    same('amd64', EnvironmentCheck::arch('x86_64'));
    same('arm64', EnvironmentCheck::arch('aarch64'));
    same('', EnvironmentCheck::arch('armv7l'));
});
