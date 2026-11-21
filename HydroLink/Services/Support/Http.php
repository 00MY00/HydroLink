<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Support;

/**
 * Minimal HTTPS client (curl extension, else PHP streams for GET). HTTPS only, certificates verified, redirects
 * followed (GitHub release downloads redirect to their storage). Replaced by a fake in the tests.
 */
class Http
{
    const UA = 'HydroLink (FreeScout module)';

    /** @return array{status:int,body:string,error:string} */
    public function get(string $url, array $headers = [], int $timeout = 30): array
    {
        return $this->request('GET', $url, $headers, null, $timeout);
    }

    /** Multipart upload of one file in field $field. */
    public function postFile(string $url, string $path, string $field, array $headers = [], int $timeout = 300): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'curl extension missing'];
        }

        return $this->request('POST', $url, $headers, [$field => new \CURLFile($path, 'application/octet-stream', basename($path))], $timeout);
    }

    public function download(string $url, string $path, int $timeout = 300): array
    {
        $r = $this->get($url, [], $timeout);
        if ($r['status'] === 200 && @file_put_contents($path, $r['body']) === false) {
            $r['error'] = 'cannot write '.basename($path);
            $r['status'] = 0;
        }
        $r['body'] = '';

        return $r;
    }

    protected function request(string $method, string $url, array $headers, $post, int $timeout): array
    {
        if (strpos($url, 'https://') !== 0) {
            return ['status' => 0, 'body' => '', 'error' => 'only https:// URLs are allowed'];
        }
        if (function_exists('curl_init')) {
            return $this->curl($method, $url, $headers, $post, $timeout);
        }

        return $this->stream($url, $headers, $timeout);
    }

    private function curl(string $method, string $url, array $headers, $post, int $timeout): array
    {
        $ch = curl_init($url);
        $lines = ['User-Agent: '.self::UA];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $lines,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = $body === false ? curl_error($ch) : '';
        curl_close($ch);

        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $error];
    }

    private function stream(string $url, array $headers, int $timeout): array
    {
        $lines = ['User-Agent: '.self::UA];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }
        $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $lines), 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ((array) ($http_response_header ?? []) as $h) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $h, $m)) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $body === false ? 'request failed' : ''];
    }
}
