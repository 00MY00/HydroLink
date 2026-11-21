<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\VirusTotal;

use Modules\HydroLink\Services\Support\Http;

/**
 * VirusTotal API v3 (https://docs.virustotal.com/reference/overview), header "x-apikey":
 *   GET  /files/{sha256}     report: data.attributes.last_analysis_stats (404 = unknown file)
 *   POST /files              upload (multipart field "file", up to 32 MB): data.id = analysis id
 *   GET  /analyses/{id}      data.attributes.status ("queued", "in-progress", "completed") and .stats
 * A key is tested with the report of the empty file's SHA-256 (same method as the UpdateGuard module).
 * Every call takes one request from the RateLimiter; without budget the call returns status "quota".
 */
class VirusTotalClient
{
    const BASE = 'https://www.virustotal.com/api/v3';
    const EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    private $key;
    private $http;
    private $limiter;

    public function __construct(string $key, ?Http $http = null, ?RateLimiter $limiter = null)
    {
        $this->key = $key;
        $this->http = $http ?: new Http();
        $this->limiter = $limiter ?: new RateLimiter();
    }

    /** @return array{status:string,stats:array} status: found | unknown | quota | refused | error */
    public function report(string $sha256): array
    {
        $r = $this->call('GET', '/files/'.strtolower($sha256));
        if (is_string($r)) {
            return ['status' => $r, 'stats' => []];
        }
        if ($r['status'] === 404) {
            return ['status' => 'unknown', 'stats' => []];
        }
        $stats = $this->json($r)['data']['attributes']['last_analysis_stats'] ?? null;

        return is_array($stats) ? ['status' => 'found', 'stats' => $stats] : ['status' => 'error', 'stats' => []];
    }

    /** @return array{status:string,id:string} status: sent | quota | refused | error */
    public function upload(string $path): array
    {
        if (!$this->limiter->take()) {
            return ['status' => 'quota', 'id' => ''];
        }
        $r = $this->http->postFile(self::BASE.'/files', $path, 'file', $this->headers(), 300);
        $status = $this->status($r);
        if ($status !== '') {
            return ['status' => $status, 'id' => ''];
        }
        $id = (string) ($this->json($r)['data']['id'] ?? '');

        return ['status' => $id !== '' ? 'sent' : 'error', 'id' => $id];
    }

    /** @return array{status:string,stats:array} status: completed | pending | quota | refused | error */
    public function analysis(string $id): array
    {
        $r = $this->call('GET', '/analyses/'.rawurlencode($id));
        if (is_string($r)) {
            return ['status' => $r, 'stats' => []];
        }
        $attributes = $this->json($r)['data']['attributes'] ?? [];
        if (($attributes['status'] ?? '') !== 'completed') {
            return ['status' => 'pending', 'stats' => []];
        }

        return ['status' => 'completed', 'stats' => (array) ($attributes['stats'] ?? [])];
    }

    /** valid | refused | quota | error: report of the empty file's hash (200 or 404 both prove the key works). */
    public function test(): string
    {
        $r = $this->call('GET', '/files/'.self::EMPTY_SHA256);

        return is_string($r) ? $r : 'valid';
    }

    /** Number of engines that flag the file (malicious). */
    public static function detections(array $stats): int
    {
        return (int) ($stats['malicious'] ?? 0);
    }

    /** @return array|string the response, or a status string (quota, refused, error) */
    private function call(string $method, string $path)
    {
        if (!$this->limiter->take()) {
            return 'quota';
        }
        $r = $this->http->get(self::BASE.$path, $this->headers(), 30);
        if ($r['status'] === 404) {
            return $r;
        }
        $status = $this->status($r);

        return $status !== '' ? $status : $r;
    }

    private function status(array $r): string
    {
        if ($r['status'] === 401 || $r['status'] === 403) {
            return 'refused';
        }
        if ($r['status'] === 429) {
            return 'quota';
        }

        return $r['status'] >= 200 && $r['status'] < 300 ? '' : 'error';
    }

    private function headers(): array
    {
        return ['x-apikey' => $this->key, 'accept' => 'application/json'];
    }

    private function json(array $r): array
    {
        $data = json_decode($r['body'], true);

        return is_array($data) ? $data : [];
    }
}
