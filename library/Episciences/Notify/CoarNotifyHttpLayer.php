<?php

declare(strict_types=1);

namespace Episciences\Notify;

use coarnotify\http\CurlHttpResponse;
use coarnotify\http\HttpLayer;
use RuntimeException;

/**
 * Replaces coarnotify\http\CurlHttpLayer, whose post()/get() pass the associative
 * $headers array documented by HttpLayer straight to CURLOPT_HTTPHEADER. cURL only
 * reads the array values, so associative keys (e.g. 'Content-Type') are silently
 * dropped: the caller-supplied Content-Type never reaches the request, and the
 * bare value is sent as a malformed header line with no field name. Against a
 * spec-compliant COAR Notify inbox (e.g. HAL's) this produces a 400 response.
 */
class CoarNotifyHttpLayer implements HttpLayer
{
    /**
     * @param array<int|string, string>|null $headers
     */
    public function post(string $url, string $data, ?array $headers = [], ...$args): CurlHttpResponse
    {
        return $this->request($url, $headers, $data);
    }

    /**
     * @param array<int|string, string>|null $headers
     */
    public function get(string $url, ?array $headers = [], ...$args): CurlHttpResponse
    {
        return $this->request($url, $headers, null);
    }

    /**
     * @param array<int|string, string>|null $headers
     */
    private function request(string $url, ?array $headers, ?string $data): CurlHttpResponse
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaderLines($headers));

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);

        if ($response === false || curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $httpStatusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string)$response, 0, $headerSize);

        curl_close($ch);

        return new CurlHttpResponse($httpStatusCode, $this->parseHeaders($rawHeaders));
    }

    /**
     * Turns an associative (or already-flat) headers array into "Name: Value" lines
     * for CURLOPT_HTTPHEADER, defaulting Content-Type only if the caller didn't set one.
     *
     * @param array<int|string, string>|null $headers
     * @return list<string>
     */
    public function buildHeaderLines(?array $headers): array
    {
        $lines = [];
        $hasContentType = false;

        foreach ($headers ?? [] as $name => $value) {
            if (is_int($name)) {
                $lines[] = $value;
                if (str_starts_with(strtolower($value), 'content-type:')) {
                    $hasContentType = true;
                }
                continue;
            }

            $lines[] = sprintf('%s: %s', $name, $value);
            if (strcasecmp($name, 'Content-Type') === 0) {
                $hasContentType = true;
            }
        }

        if (!$hasContentType) {
            $lines[] = 'Content-Type: application/json';
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $parsed = [];

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(': ', $line, 2);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }
}