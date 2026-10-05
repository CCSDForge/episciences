<?php

declare(strict_types=1);

/**
 * Extracted from the former SolrWorkerCommand — shared by episciences:worker
 * regardless of which transport it's consuming.
 */
trait ParsesMemoryLimit
{
    private function parseMemoryLimit(string $limit): int
    {
        // Accepts a bare byte count (512), a unit suffix (512K/512M/512G), or
        // either with a trailing "b" (512MB) — PHP's own memory_limit ini
        // syntax only supports the former, this is more forgiving on purpose.
        if (preg_match('/^\s*(\d+)\s*([kmg])?b?\s*$/i', $limit, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid --memory-limit value: %s', $limit));
        }

        $value = (int)$matches[1];
        $unit = strtolower($matches[2] ?? '');

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
