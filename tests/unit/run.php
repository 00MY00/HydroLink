<?php

/*
 * Unit tests of the parts of HydroLink that do not need FreeScout. Plain PHP >= 7.1, no Composer:
 *
 *   php tests/unit/run.php
 *
 * The parts that need FreeScout (database, mailboxes, processes, panel) are tested in a real FreeScout by
 * tests/integration/run.sh (PHPUnit, HydroLink/Tests).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

spl_autoload_register(function ($class) {
    $prefix = 'Modules\\HydroLink\\';
    if (strpos($class, $prefix) === 0) {
        $file = __DIR__.'/../../HydroLink/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// Laravel helpers used by the tested classes: minimal stand-ins
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return $default;
    }
}
if (!function_exists('config')) {
    function config($key, $default = null)
    {
        return $default;
    }
}
if (!function_exists('encrypt')) {
    function encrypt($v)
    {
        return 'enc:'.base64_encode((string) $v);
    }
    function decrypt($v)
    {
        return base64_decode(substr((string) $v, 4));
    }
}
if (!function_exists('__')) {
    function __($key, $replace = [])
    {
        return $key;
    }
}

$GLOBALS['hl_tests'] = [];
$GLOBALS['hl_assertions'] = 0;

function test($name, callable $fn)
{
    $GLOBALS['hl_tests'][$name] = $fn;
}

function check($condition, $message)
{
    $GLOBALS['hl_assertions']++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same($expected, $actual, $message = '')
{
    check($expected === $actual, ($message !== '' ? $message.' - ' : '').'expected '.var_export($expected, true).', got '.var_export($actual, true));
}

function tmpdir()
{
    $dir = sys_get_temp_dir().'/hl-test-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);

    return $dir;
}

foreach (glob(__DIR__.'/*Test.php') as $file) {
    require $file;
}

$failed = 0;
foreach ($GLOBALS['hl_tests'] as $name => $fn) {
    try {
        \Modules\HydroLink\Services\Support\Settings::$memory = [];
        \Modules\HydroLink\Services\Support\Paths::$base = tmpdir();
        $fn();
        echo "  ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL $name\n       ".$e->getMessage()."\n";
    }
}
printf("\n%d tests, %d assertions, %d failed\n", count($GLOBALS['hl_tests']), $GLOBALS['hl_assertions'], $failed);
exit($failed ? 1 : 0);
