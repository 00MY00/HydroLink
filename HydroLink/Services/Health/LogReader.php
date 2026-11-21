<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Health;

use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Secrets;

/** Last lines of the logs, for the panel ("Logs" of an account). Secrets are masked again when read. */
final class LogReader
{
    const MAX_BYTES = 262144;

    public static function tail(string $file, int $lines = 200): array
    {
        if (!is_file($file)) {
            return [];
        }
        $size = (int) filesize($file);
        $h = @fopen($file, 'r');
        if (!$h) {
            return [];
        }
        fseek($h, max(0, $size - self::MAX_BYTES));
        $text = (string) stream_get_contents($h);
        fclose($h);
        $all = preg_split('~\R~', trim($text));

        return array_map(function ($l) {
            return Secrets::mask((string) $l);
        }, array_slice($all, -$lines));
    }

    /** Login log of the account + the lines of hydroxide.log that name it. */
    public static function forAccount(int $id, string $email): array
    {
        $serve = array_values(array_filter(self::tail(Paths::serveLog(), 2000), function ($l) use ($email) {
            return stripos($l, $email) !== false || stripos($l, '-- hydrolink') === 0;
        }));

        return ['auth' => self::tail(Paths::authLog($id), 100), 'serve' => array_slice($serve, -100)];
    }
}
