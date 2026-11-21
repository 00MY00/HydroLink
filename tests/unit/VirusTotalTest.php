<?php

use Modules\HydroLink\Services\Support\Http;
use Modules\HydroLink\Services\VirusTotal\RateLimiter;
use Modules\HydroLink\Services\VirusTotal\Verdict;
use Modules\HydroLink\Services\VirusTotal\VirusTotalClient;

/** Scripted HTTP answers: [method url-suffix => [status, body]], every call recorded. */
class FakeHttp extends Http
{
    public $answers = [];
    public $calls = [];

    public function get(string $url, array $headers = [], int $timeout = 30): array
    {
        return $this->answer('GET', $url, $headers);
    }

    public function postFile(string $url, string $path, string $field, array $headers = [], int $timeout = 300): array
    {
        return $this->answer('POST', $url, $headers);
    }

    private function answer($method, $url, $headers)
    {
        $this->calls[] = [$method, $url, $headers];
        foreach ($this->answers as $key => $answer) {
            list($m, $suffix) = explode(' ', $key, 2);
            if ($m === $method && substr($url, -strlen($suffix)) === $suffix) {
                $a = is_array($answer[0]) ? array_shift($this->answers[$key]) : $answer;

                return ['status' => $a[0], 'body' => is_array($a[1]) ? json_encode($a[1]) : $a[1], 'error' => ''];
            }
        }

        return ['status' => 500, 'body' => '', 'error' => 'no scripted answer'];
    }
}

function vt($http, $limiter = null)
{
    return new VirusTotalClient('KEY123', $http, $limiter ?: new RateLimiter(tmpdir().'/b.json', 100, 1000));
}

$sha = str_repeat('ab', 32);
$stats = function ($m) { return ['data' => ['attributes' => ['last_analysis_stats' => ['malicious' => $m, 'suspicious' => 1, 'undetected' => 60]]]]; };

test('VirusTotal: known file under / above the threshold, key header', function () use ($sha, $stats) {
    $http = new FakeHttp();
    $http->answers['GET /files/'.$sha] = [200, $stats(0)];
    $v = new Verdict(vt($http), 0, tmpdir().'/vt.json');
    same('clean', $v->check($sha, __FILE__)['verdict']);
    same('KEY123', $http->calls[0][2]['x-apikey']);
    $http->answers['GET /files/'.$sha] = [200, $stats(2)];
    $r = (new Verdict(vt($http), 1, tmpdir().'/vt.json'))->check($sha, __FILE__);
    same('blocked', $r['verdict']);
    same(2, $r['detections']);
    same('clean', (new Verdict(vt($http), 2, tmpdir().'/vt.json'))->check($sha, __FILE__)['verdict'], 'equal to the threshold passes');
});

test('VirusTotal: unknown file uploaded, analysis awaited across runs', function () use ($sha) {
    $http = new FakeHttp();
    $http->answers['GET /files/'.$sha] = [404, ''];
    $http->answers['POST /files'] = [200, ['data' => ['id' => 'AN1']]];
    $http->answers['GET /analyses/AN1'] = [[200, ['data' => ['attributes' => ['status' => 'queued']]]], [200, ['data' => ['attributes' => ['status' => 'completed', 'stats' => ['malicious' => 0]]]]]];
    $file = tmpdir().'/vt.json';
    same('pending', (new Verdict(vt($http), 0, $file))->check($sha, __FILE__)['verdict'], 'after upload');
    same('pending', (new Verdict(vt($http), 0, $file))->check($sha, __FILE__)['verdict'], 'queued');
    same('clean', (new Verdict(vt($http), 0, $file))->check($sha, __FILE__)['verdict'], 'completed');
    same(1, count(array_filter($http->calls, function ($c) { return $c[0] === 'POST'; })), 'uploaded once');
});

test('VirusTotal: refused key is an error, quota postpones, no key skips', function () use ($sha) {
    $http = new FakeHttp();
    $http->answers['GET /files/'.$sha] = [401, ''];
    $r = (new Verdict(vt($http), 0, tmpdir().'/vt.json'))->check($sha, __FILE__);
    same(['error', 'refused'], [$r['verdict'], $r['detail']]);
    $http->answers['GET /files/'.$sha] = [429, ''];
    same('pending', (new Verdict(vt($http), 0, tmpdir().'/vt.json'))->check($sha, __FILE__)['verdict']);
    same('skipped', (new Verdict(null))->check($sha, __FILE__)['verdict']);
    $http->answers['GET /files/'.VirusTotalClient::EMPTY_SHA256] = [404, ''];
    same('valid', vt($http)->test(), '404 on the empty file proves the key works');
});

test('Rate limiter: 4 per minute, 500 per day, waiting time', function () {
    $now = 1000000;
    $l = new RateLimiter(tmpdir().'/b.json', 4, 6);
    $l->clock = function () use (&$now) { return $now; };
    for ($i = 0; $i < 4; $i++) {
        check($l->take(), 'call '.$i);
    }
    check(!$l->take(), '5th in the same minute refused');
    same(60, $l->wait());
    $now += 61;
    check($l->take() && $l->take(), 'next minute');
    check(!$l->take(), 'daily budget (6) used');
    check($l->wait() > 3600, 'wait until the oldest call is a day old');
});
