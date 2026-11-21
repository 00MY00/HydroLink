<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/** Small JSON files written atomically (temporary file + rename), readable only by the web user. */
final class JsonFile
{
    public static function read(string $path, array $default = []): array
    {
        if (!is_file($path)) {
            return $default;
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : $default;
    }

    public static function write(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            return false;
        }
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($tmp, $json."\n") === false) {
            return false;
        }
        @chmod($tmp, 0600);

        return @rename($tmp, $path);
    }

    /** Read-modify-write under an exclusive lock (the web and the cron may write the same file). */
    public static function update(string $path, callable $change): array
    {
        $lock = @fopen($path.'.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        try {
            $data = $change(self::read($path));
            self::write($path, $data);

            return $data;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
