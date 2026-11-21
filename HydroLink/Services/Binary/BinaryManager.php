<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Binary;

use Modules\HydroLink\Services\Env\EnvironmentCheck;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\ProcessRunner;
use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\VirusTotal\Verdict;

/**
 * Installation and update of the hydroxide binary (cron only):
 *   download to tmp/ -> SHA-256 against SHA256SUMS (required) -> VirusTotal (optional) -> test run ("-h")
 *   -> atomic replacement (previous kept as hydroxide.prev) -> restart -> health -> rollback on failure.
 */
class BinaryManager
{
    private $source;
    private $verdict;
    /** @var callable|null fn(): bool restarts hydroxide and says whether it is healthy (tests replace it) */
    public $restarter;

    public function __construct(?ReleaseSource $source = null, ?Verdict $verdict = null)
    {
        $this->source = $source ?: new ReleaseSource();
        $this->verdict = $verdict ?: Verdict::fromSettings();
    }

    public static function installed(): array
    {
        return is_file(Paths::bin()) ? JsonFile::read(Paths::binMeta(), ['version' => '?']) : [];
    }

    /** @return array{result:string,code:string,version:string,detail:string} */
    public function update(bool $force = false, bool $dryRun = false): array
    {
        $arch = EnvironmentCheck::arch();
        if ($arch === '') {
            return self::out('failed', 'HL-ENV-04');
        }
        $latest = $this->source->latestVersion();
        if ($latest === '') {
            return self::out('failed', 'HL-BIN-04', '', 'hydroxide-version.txt');
        }
        $current = (string) (self::installed()['version'] ?? '');
        if (!$force && $current === $latest) {
            return self::out('up_to_date', '', $latest);
        }
        if ($dryRun) {
            return self::out('would_install', '', $latest, $current);
        }

        return $this->install($arch, $latest);
    }

    private function install(string $arch, string $version): array
    {
        Paths::ensure();
        $tmp = Paths::tmp('hydroxide-'.$version);
        $sums = $this->source->sums();
        $expected = Checksum::expected($sums, ReleaseSource::assetName($arch));
        if ($expected === '') {
            return self::out('failed', 'HL-BIN-04', $version, 'SHA256SUMS');
        }
        if (!is_file($tmp) || !Checksum::matches($expected, Checksum::file($tmp))) {
            @unlink($tmp);
            if (!$this->source->download($arch, $tmp)) {
                return self::out('failed', 'HL-BIN-04', $version, ReleaseSource::assetName($arch));
            }
        }
        $actual = Checksum::file($tmp);
        if (!Checksum::matches($expected, $actual)) {
            @unlink($tmp);

            return self::out('failed', 'HL-BIN-02', $version, 'expected '.$expected.', got '.$actual);
        }

        return $this->afterChecksum($tmp, $version, $actual);
    }

    private function afterChecksum(string $tmp, string $version, string $sha): array
    {
        $vt = $this->verdict->check($sha, $tmp);
        if ($vt['verdict'] === 'pending') {
            return self::out('pending_vt', '', $version, $vt['detail']);
        }
        if ($vt['verdict'] === 'blocked') {
            @rename($tmp, Paths::quarantine('hydroxide-'.$version.'-'.substr($sha, 0, 12)));

            return self::out('failed', 'HL-BIN-05', $version, $vt['detections'].' detection(s)');
        }
        if ($vt['verdict'] === 'error') {
            return self::out('failed', $vt['detail'] === 'refused' ? 'HL-VT-01' : 'HL-VT-02', $version, $vt['detail']);
        }
        @chmod($tmp, 0700);
        if (!self::selfTest($tmp)) {
            @unlink($tmp);

            return self::out('failed', 'HL-BIN-03', $version);
        }

        return $this->swap($tmp, $version, $sha, $vt['verdict']);
    }

    /** The binary starts and prints hydroxide's usage. */
    public static function selfTest(string $path): bool
    {
        $r = ProcessRunner::run([$path, '-h'], Paths::processEnv(), 15);

        return strpos($r['out'].$r['err'], 'usage: hydroxide') !== false;
    }

    private function swap(string $tmp, string $version, string $sha, string $vt): array
    {
        $hadBinary = is_file(Paths::bin());
        if ($hadBinary) {
            @copy(Paths::bin(), Paths::previousBin());
            @chmod(Paths::previousBin(), 0700);
            @copy(Paths::binMeta(), Paths::previousMeta());
        }
        if (!@rename($tmp, Paths::bin())) {
            return self::out('failed', 'HL-ENV-06', $version);
        }
        JsonFile::write(Paths::binMeta(), ['version' => $version, 'sha256' => $sha, 'virustotal' => $vt, 'installed_at' => time()]);
        if ($this->restartHealthy()) {
            return self::out('installed', '', $version);
        }
        if ($hadBinary) {
            $this->rollback();

            return self::out('rolled_back', 'HL-BIN-06', $version);
        }

        return self::out('installed', '', $version, 'not started');
    }

    /** Puts the previous binary back and restarts it. */
    public function rollback(): bool
    {
        if (!is_file(Paths::previousBin())) {
            return false;
        }
        @rename(Paths::previousBin(), Paths::bin());
        @rename(Paths::previousMeta(), Paths::binMeta());
        $this->restartHealthy();

        return true;
    }

    /** Restarts hydroxide when it was running (or when there is something to serve) and checks the local ports. */
    private function restartHealthy(): bool
    {
        if ($this->restarter) {
            return (bool) call_user_func($this->restarter);
        }
        if (!HydroxideProcess::running()) {
            return true;
        }
        HydroxideProcess::stop();
        $start = HydroxideProcess::start();

        return $start['ok'] && HydroxideProcess::waitReady(20);
    }

    private static function out(string $result, string $code = '', string $version = '', string $detail = ''): array
    {
        return ['result' => $result, 'code' => $code, 'version' => $version, 'detail' => $detail];
    }
}
