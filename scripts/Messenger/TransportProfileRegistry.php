<?php

declare(strict_types=1);

require_once __DIR__ . '/TransportProfileInterface.php';
require_once __DIR__ . '/SolrIndexProfile.php';
require_once __DIR__ . '/NextRevalidationProfile.php';

/**
 * Maps a --transport name to its TransportProfileInterface. Deliberately
 * does no bootstrapping itself, so --transport validation in
 * episciences:worker / episciences:queue can run before any bootstrap.
 */
final class TransportProfileRegistry
{
    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::map());
    }

    public static function get(string $name): TransportProfileInterface
    {
        $profiles = self::map();

        if (!isset($profiles[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown transport "%s". Known transports: %s.',
                $name,
                implode(', ', array_keys($profiles))
            ));
        }

        return $profiles[$name];
    }

    /**
     * Keys are deliberately literal strings, not
     * SolrIndexTransport::NAME / NextRevalidationTransport::NAME: those
     * Episciences\* classes are only autoloadable once a profile's
     * bootstrap() has registered the Zend fallback autoloader, but names()
     * and get() both need to work *before* any bootstrap() call (that's the
     * whole point of --transport being validated first) —
     * TransportProfileRegistryTest asserts these literals stay in sync with
     * the constants they mirror.
     *
     * @return array<string, TransportProfileInterface>
     */
    private static function map(): array
    {
        return [
            'solr_index' => new SolrIndexProfile(),
            'next_revalidation' => new NextRevalidationProfile(),
        ];
    }
}
