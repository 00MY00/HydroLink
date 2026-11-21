<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Binary;

use Modules\HydroLink\Services\Support\Http;
use Modules\HydroLink\Services\Support\Settings;

/**
 * Release of the HydroLink repository: hydroxide-version.txt, hydroxide-linux-<arch>, SHA256SUMS
 * (built by build/build-hydroxide.sh in the CI, from the pinned commit of build/hydroxide.lock).
 */
class ReleaseSource
{
    private $http;
    private $base;

    public function __construct(?Http $http = null, ?string $base = null)
    {
        $this->http = $http ?: new Http();
        $this->base = rtrim($base ?? (string) Settings::config('release_url', ''), '/');
    }

    public static function assetName(string $arch): string
    {
        return 'hydroxide-linux-'.$arch;
    }

    public function url(string $file): string
    {
        return $this->base.'/'.$file;
    }

    /** Latest published version, '' when unreachable or not a version. */
    public function latestVersion(): string
    {
        $r = $this->http->get($this->url('hydroxide-version.txt'), [], 20);
        $version = trim($r['body']);

        return $r['status'] === 200 && preg_match('~^[0-9][0-9A-Za-z.\-]{0,40}$~', $version) ? $version : '';
    }

    public function sums(): string
    {
        $r = $this->http->get($this->url('SHA256SUMS'), [], 20);

        return $r['status'] === 200 ? $r['body'] : '';
    }

    public function download(string $arch, string $path): bool
    {
        $r = $this->http->download($this->url(self::assetName($arch)), $path, 300);

        return $r['status'] === 200 && is_file($path) && filesize($path) > 0;
    }
}
