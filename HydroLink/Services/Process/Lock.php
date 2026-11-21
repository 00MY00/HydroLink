<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Process;

use Modules\HydroLink\Services\Support\Paths;

/** Non-blocking exclusive lock (flock) so that two cron runs never start hydroxide twice. */
final class Lock
{
    private $handle;

    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    public static function acquire(string $name): ?self
    {
        Paths::ensure();
        $handle = @fopen(Paths::run($name.'.lock'), 'c');
        if (!$handle) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return new self($handle);
    }

    public function release(): void
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
