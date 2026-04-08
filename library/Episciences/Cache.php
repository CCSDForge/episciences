<?php

/**
 * @deprecated Use Symfony\Component\Cache\Adapter\FilesystemAdapter (PSR-6) instead.
 *             This class was removed from all internal usages. Kept for third-party
 *             compatibility only and will be removed in a future release.
 */
class Episciences_Cache extends Ccsd_Cache
{
    protected static $_cachePath = CACHE_PATH;
}