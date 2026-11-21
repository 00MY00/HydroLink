<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Binary;

/** SHA256SUMS in the format of sha256sum: "<64 hex>  <file>" or "<64 hex> *<file>", one per line. */
final class Checksum
{
    /** @return array file => hash */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('~\R~', $text) as $line) {
            if (preg_match('~^([0-9a-fA-F]{64})\s+\*?(\S.*)$~', trim($line), $m)) {
                $out[trim($m[2])] = strtolower($m[1]);
            }
        }

        return $out;
    }

    public static function expected(string $sums, string $file): string
    {
        return self::parse($sums)[$file] ?? '';
    }

    public static function file(string $path): string
    {
        $hash = is_file($path) ? hash_file('sha256', $path) : false;

        return $hash === false ? '' : $hash;
    }

    public static function matches(string $expected, string $actual): bool
    {
        return strlen($expected) === 64 && hash_equals(strtolower($expected), strtolower($actual));
    }
}
