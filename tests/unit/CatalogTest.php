<?php

use Modules\HydroLink\Services\Support\ErrorCatalog;

function hl_root()
{
    return __DIR__.'/../../HydroLink';
}

function hl_flat(array $a, $prefix = '')
{
    $out = [];
    foreach ($a as $k => $v) {
        if (is_array($v)) {
            $out += hl_flat($v, $prefix.$k.'.');
        } else {
            $out[$prefix.$k] = $v;
        }
    }

    return $out;
}

test('Translations: same keys in fr and en, no empty text, no long dash', function () {
    foreach (['messages', 'errors'] as $file) {
        $fr = hl_flat(require hl_root()."/Resources/lang/fr/$file.php");
        $en = hl_flat(require hl_root()."/Resources/lang/en/$file.php");
        same([], array_values(array_diff(array_keys($fr), array_keys($en))), "$file: keys only in fr");
        same([], array_values(array_diff(array_keys($en), array_keys($fr))), "$file: keys only in en");
        foreach ($fr + $en as $k => $v) {
            check(trim($v) !== '', "$file.$k empty");
        }
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../..', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $path = str_replace('\\', '/', $f->getPathname());
        if (preg_match('~/(\.git|dist|vendor|node_modules)/~', $path) || !preg_match('~\.(php|js|css|md|sh|yml|json)$~', $path)) {
            continue;
        }
        check(!preg_match('~\x{2014}|\x{2013}~u', (string) file_get_contents($path)), 'long dash in '.$path);
    }
});

test('Every error code has its four texts in both languages', function () {
    foreach (['fr', 'en'] as $lang) {
        $texts = require hl_root()."/Resources/lang/$lang/errors.php";
        foreach (array_keys(ErrorCatalog::CODES) as $code) {
            foreach (['title', 'expected', 'effect', 'action'] as $part) {
                check(!empty($texts[$code][$part]), "$lang $code.$part");
            }
        }
        same([], array_values(array_diff(array_keys($texts), array_keys(ErrorCatalog::CODES))), "$lang: texts of unknown codes");
    }
});

test('Every translation key used in the code exists, every HL code used is declared', function () {
    $messages = hl_flat(require hl_root().'/Resources/lang/en/messages.php');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(hl_root(), FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('~\.php$~', $f->getFilename())) {
            continue;
        }
        $src = (string) file_get_contents($f->getPathname());
        preg_match_all("~hydrolink::messages\.([a-z0-9_]*[a-z0-9])(['\"])~", $src, $m);
        foreach ($m[1] as $key) {
            check(isset($messages[$key]), 'missing message '.$key.' ('.$f->getFilename().')');
        }
        preg_match_all("~'(HL-[A-Z]+-\d\d)'~", $src, $c);
        foreach ($c[1] as $code) {
            check(ErrorCatalog::exists($code), 'undeclared code '.$code.' ('.$f->getFilename().')');
        }
    }
    // dynamic keys: one per status / action
    foreach (['pending', 'running', 'waiting_2fa', 'waiting_captcha', 'needs_password', 'connected', 'error', 'removing'] as $s) {
        check(isset($messages['acc_'.$s]), 'acc_'.$s);
    }
    foreach (['running', 'starting', 'failed', 'stopped'] as $s) {
        check(isset($messages['svc_'.$s]), 'svc_'.$s);
    }
    foreach (['start', 'stop', 'restart'] as $s) {
        check(isset($messages['queued_'.$s]), 'queued_'.$s);
    }
    foreach (['problem', 'reminder', 'recovered', 'test'] as $s) {
        check(isset($messages['notify_'.$s]), 'notify_'.$s);
    }
    foreach (['refused', 'quota', 'error'] as $s) {
        check(isset($messages['vt_'.$s]), 'vt_'.$s);
    }
});

test('module.json: UpdateGuard contract and version consistency', function () {
    $m = json_decode(file_get_contents(hl_root().'/module.json'), true);
    same('hydrolink', $m['alias']);
    same('hydrolink:health', $m['healthCommand']);
    check(preg_match('~^\d+\.\d+\.\d+$~', $m['version']) === 1, 'semantic version');
    check(substr($m['latestVersionUrl'], -12) === '/version.txt', 'latestVersionUrl');
    check(substr($m['latestVersionZipUrl'], -14) === '/HydroLink.zip', 'latestVersionZipUrl');
    $install = file_get_contents(__DIR__.'/../../install.sh');
    check(strpos($install, 'SCRIPT_VERSION="'.$m['version'].'"') !== false, 'install.sh has the same version');
    check(strpos(file_get_contents(__DIR__.'/../../CHANGELOG.md'), '## ['.$m['version'].']') !== false, 'CHANGELOG entry');
});

test('Feature registry: every feature file implements the interface, keys unique', function () {
    $keys = [];
    foreach (glob(hl_root().'/Features/*Feature.php') as $file) {
        $class = 'Modules\\HydroLink\\Features\\'.basename($file, '.php');
        if ($class === 'Modules\\HydroLink\\Features\\Feature') {
            continue;
        }
        check(is_subclass_of($class, 'Modules\\HydroLink\\Features\\Feature'), $class);
        $f = new $class();
        check(!isset($keys[$f->key()]), 'duplicate key '.$f->key());
        $keys[$f->key()] = $f->order();
        foreach ($f->settings() as $k => $def) {
            check(array_key_exists('default', $def) && isset($def['rules']), "$class setting $k");
        }
    }
    asort($keys);
    same(['service', 'accounts', 'keepalive', 'binary', 'notifications', 'virustotal'], array_keys($keys), 'cards in order');
});
