<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\VirusTotal;

use Modules\HydroLink\Services\Support\JsonFile;
use Modules\HydroLink\Services\Support\Paths;

/**
 * Optional VirusTotal step of a binary update: look the SHA-256 up, upload when unknown, then wait for the analysis
 * across cron runs (the analysis id is kept in run/virustotal.json), within the API budget.
 *   skipped  no key            clean     detections <= threshold
 *   pending  not finished yet  blocked   detections > threshold
 *   error    key refused, quota for a day, unexpected answer
 */
class Verdict
{
    private $client;
    private $threshold;
    private $file;

    public function __construct(?VirusTotalClient $client = null, int $threshold = 0, ?string $file = null)
    {
        $this->client = $client;
        $this->threshold = $threshold;
        $this->file = $file ?? Paths::run('virustotal.json');
    }

    /** With the key and threshold of the panel; without a key the step is skipped. */
    public static function fromSettings(): self
    {
        $key = \Modules\HydroLink\Services\Support\Settings::vtKey();

        return new self($key !== '' ? new VirusTotalClient($key) : null, \Modules\HydroLink\Services\Support\Settings::vtThreshold());
    }

    /** @return array{verdict:string,detections:int,detail:string} */
    public function check(string $sha256, string $path): array
    {
        if (!$this->client) {
            return self::out('skipped');
        }
        $pending = (string) (JsonFile::read($this->file)[$sha256] ?? '');
        if ($pending !== '') {
            return $this->fromAnalysis($sha256, $pending);
        }
        $report = $this->client->report($sha256);
        if ($report['status'] === 'found') {
            return $this->judge($report['stats']);
        }
        if ($report['status'] !== 'unknown') {
            return $this->problem($report['status']);
        }
        $upload = $this->client->upload($path);
        if ($upload['status'] !== 'sent') {
            return $this->problem($upload['status']);
        }
        $this->remember($sha256, $upload['id']);

        return self::out('pending', 0, 'uploaded');
    }

    private function fromAnalysis(string $sha256, string $id): array
    {
        $a = $this->client->analysis($id);
        if ($a['status'] === 'pending') {
            return self::out('pending', 0, 'analysis');
        }
        if ($a['status'] !== 'completed') {
            return $this->problem($a['status']);
        }
        $this->remember($sha256, '');

        return $this->judge($a['stats']);
    }

    private function judge(array $stats): array
    {
        $n = VirusTotalClient::detections($stats);

        return self::out($n > $this->threshold ? 'blocked' : 'clean', $n);
    }

    private function problem(string $status): array
    {
        // quota of the minute: try again on the next run; refused key or daily quota: error shown in the panel
        return $status === 'quota' ? self::out('pending', 0, 'quota') : self::out('error', 0, $status);
    }

    private function remember(string $sha256, string $id): void
    {
        JsonFile::update($this->file, function (array $d) use ($sha256, $id) {
            if ($id === '') {
                unset($d[$sha256]);
            } else {
                $d[$sha256] = $id;
            }

            return $d;
        });
    }

    private static function out(string $verdict, int $detections = 0, string $detail = ''): array
    {
        return ['verdict' => $verdict, 'detections' => $detections, 'detail' => $detail];
    }
}
