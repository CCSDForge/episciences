<?php

declare(strict_types=1);

namespace Episciences\Notify;

/**
 * Parses preprint repository URLs into their structured components.
 *
 * Pure logic — no side effects, no external dependencies, fully testable.
 */
final class PreprintUrlParser
{
    /**
     * Extracts identifier and version number from a preprint URL.
     *
     * Examples:
     *   https://hal.science/hal-03697346v3 → ['identifier' => 'hal-03697346', 'version' => 3]
     *   https://hal.science/hal-03697346   → ['identifier' => 'hal-03697346', 'version' => 1]
     *   https://hal.science/hal-03697346v  → ['identifier' => 'hal-03697346', 'version' => 1]
     *   ''                                 → ['identifier' => '',              'version' => 1]
     *
     * @return array{identifier: string, version: int}
     */
    public function parseUrl(string $url): array
    {
        $default = ['version' => 1, 'identifier' => ''];

        if ($url === '') {
            return $default;
        }

        $parts = parse_url($url);

        if (!$parts || !isset($parts['path'])) {
            return $default;
        }

        $path = $parts['path'];
        $vPos = mb_strpos($path, 'v');

        // $vPos must be strictly > 0: position 0 means the path starts with 'v' which
        // doesn't match any known preprint identifier format (e.g. /hal-xxxvN).
        if ($vPos !== false && $vPos > 0) {
            $versionStr = mb_substr($path, $vPos + 1);
            $version    = $versionStr !== '' ? (int) $versionStr : 1;
            $flatPath   = str_replace('/', '', $path);
            $identifier = mb_substr($flatPath, 0, mb_strlen($flatPath) - mb_strlen($versionStr) - 1);
        } else {
            $version    = 1;
            $identifier = str_replace('/', '', $path);
        }

        return ['version' => $version, 'identifier' => $identifier];
    }

    /**
     * Extracts the journal code (rvcode) from a journal URL.
     *
     * Example: https://revue-test.episciences.org → 'revue-test' (given domain = 'episciences.org')
     *
     * @return string Empty string if extraction fails.
     */
    public function extractRvCode(string $url, string $domain): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (!isset($parts['host'])) {
            return '';
        }

        $host         = $parts['host'];
        $domainSuffix = '.' . $domain;

        if (!str_ends_with($host, $domainSuffix)) {
            return '';
        }

        return mb_substr($host, 0, mb_strlen($host) - mb_strlen($domainSuffix));
    }
}
