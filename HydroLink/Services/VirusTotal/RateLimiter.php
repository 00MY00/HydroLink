<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\VirusTotal;

use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;

/** Budget of the VirusTotal public API (4 requests per minute, 500 per day by default), shared by web and cron. */
class RateLimiter
{
    private $file;
    private $perMinute;
    private $perDay;
    /** @var callable */
    public $clock;

    public function __construct(?string $file = null, ?int $perMinute = null, ?int $perDay = null)
    {
        $this->file = $file ?? Paths::run('virustotal-budget.json');
        $this->perMinute = $perMinute ?? (int) Settings::config('vt_per_minute', 4);
        $this->perDay = $perDay ?? (int) Settings::config('vt_per_day', 500);
        $this->clock = function () {
            return time();
        };
    }

    /** Takes one request from the budget; false when the budget is used up. */
    public function take(): bool
    {
        $now = ($this->clock)();
        $granted = false;
        JsonFile::update($this->file, function (array $d) use ($now, &$granted) {
            $calls = array_values(array_filter((array) ($d['calls'] ?? []), function ($t) use ($now) {
                return (int) $t > $now - 86400;
            }));
            $lastMinute = count(array_filter($calls, function ($t) use ($now) {
                return (int) $t > $now - 60;
            }));
            if ($lastMinute < $this->perMinute && count($calls) < $this->perDay) {
                $calls[] = $now;
                $granted = true;
            }

            return ['calls' => $calls];
        });

        return $granted;
    }

    /** Seconds before a request is possible again (0 = now). */
    public function wait(): int
    {
        $now = ($this->clock)();
        $calls = (array) (JsonFile::read($this->file)['calls'] ?? []);
        $day = array_filter($calls, function ($t) use ($now) {
            return (int) $t > $now - 86400;
        });
        if (count($day) >= $this->perDay) {
            return (int) min($day) + 86400 - $now;
        }
        $minute = array_filter($day, function ($t) use ($now) {
            return (int) $t > $now - 60;
        });

        return count($minute) >= $this->perMinute ? (int) min($minute) + 60 - $now : 0;
    }
}
