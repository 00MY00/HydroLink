<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Services\Binary\BinaryManager;
use Modules\HydroLink\Services\Binary\ReleaseSource;
use Modules\HydroLink\Services\Env\EnvironmentCheck;
use Modules\HydroLink\Services\Support\Http;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\VirusTotal\RateLimiter;
use Modules\HydroLink\Services\VirusTotal\Verdict;
use Modules\HydroLink\Services\VirusTotal\VirusTotalClient;
use Modules\HydroLink\Tests\TestCase;

/** A release served by a fake HTTP client: url suffix => [status, body]. */
class ReleaseHttp extends Http
{
    public $files = [];

    public function get(string $url, array $headers = [], int $timeout = 30): array
    {
        foreach ($this->files as $suffix => $answer) {
            if (substr($url, -strlen($suffix)) === $suffix) {
                return ['status' => $answer[0], 'body' => $answer[1], 'error' => ''];
            }
        }

        return ['status' => 404, 'body' => '', 'error' => ''];
    }
}

/** Update of the binary: success, wrong checksum, VirusTotal above the threshold, start failure with rollback. */
class BinaryTest extends TestCase
{
    private function release(string $version, string $binary, ?string $sumOverride = null): ReleaseHttp
    {
        $asset = ReleaseSource::assetName(EnvironmentCheck::arch());
        $http = new ReleaseHttp();
        $http->files = [
            'hydroxide-version.txt' => [200, $version."\n"],
            'SHA256SUMS'            => [200, ($sumOverride ?: hash('sha256', $binary)).'  '.$asset."\n"],
            $asset                  => [200, $binary],
        ];

        return $http;
    }

    private function manager(ReleaseHttp $http, ?Verdict $verdict = null, bool $healthy = true): BinaryManager
    {
        $m = new BinaryManager(new ReleaseSource($http, 'https://example.test/download'), $verdict ?: new Verdict(null));
        $m->restarter = function () use ($healthy) {
            return $healthy;
        };

        return $m;
    }

    private function fake(): string
    {
        return file_get_contents(__DIR__.'/../Support/fake-hydroxide.php');
    }

    public function testInstallThenUpToDate()
    {
        $r = $this->manager($this->release('2.0.0', $this->fake()))->update();
        $this->assertSame('installed', $r['result']);
        $this->assertSame('2.0.0', BinaryManager::installed()['version']);
        $this->assertTrue(is_executable(Paths::bin()));
        $this->assertSame('up_to_date', $this->manager($this->release('2.0.0', $this->fake()))->update()['result']);
    }

    public function testWrongChecksumKeepsTheCurrentBinary()
    {
        $this->installFake();
        $before = hash_file('sha256', Paths::bin());
        $r = $this->manager($this->release('2.0.0', $this->fake().'#', str_repeat('0', 64)))->update();
        $this->assertSame('HL-BIN-02', $r['code']);
        $this->assertSame($before, hash_file('sha256', Paths::bin()));
        $this->assertSame([], glob(Paths::tmp('hydroxide-*')), 'download deleted');
    }

    public function testVirusTotalAboveThresholdQuarantines()
    {
        $vtHttp = new ReleaseHttp();
        $binary = $this->fake().'# vt';
        $vtHttp->files['/files/'.hash('sha256', $binary)] = [200, json_encode(['data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 3]]]])];
        $client = new VirusTotalClient('k', $vtHttp, new RateLimiter(Paths::run('b.json'), 10, 100));
        $r = $this->manager($this->release('2.0.0', $binary), new Verdict($client, 0, Paths::run('vt.json')))->update();
        $this->assertSame('HL-BIN-05', $r['code']);
        $this->assertFileDoesNotExist(Paths::bin());
        $this->assertCount(1, glob(Paths::quarantine('hydroxide-2.0.0-*')));
    }

    public function testBinaryThatDoesNotStartIsRefused()
    {
        $r = $this->manager($this->release('2.0.0', "#!/bin/sh\nexit 1\n"))->update();
        $this->assertSame('HL-BIN-03', $r['code']);
        $this->assertFileDoesNotExist(Paths::bin());
    }

    public function testFailedRestartRollsBack()
    {
        $this->installFake();
        $before = hash_file('sha256', Paths::bin());
        $r = $this->manager($this->release('2.0.0', $this->fake()."\n// new"), null, false)->update();
        $this->assertSame('rolled_back', $r['result']);
        $this->assertSame('HL-BIN-06', $r['code']);
        $this->assertSame($before, hash_file('sha256', Paths::bin()), 'previous binary back');
        $this->assertSame('test-1', BinaryManager::installed()['version']);
    }

    public function testUnreachableRelease()
    {
        $this->assertSame('HL-BIN-04', $this->manager(new ReleaseHttp())->update()['code']);
    }
}
