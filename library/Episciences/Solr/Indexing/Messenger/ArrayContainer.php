<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Minimal PSR-11 container backed by a plain array. Symfony Messenger's
 * SendersLocator/SendFailedMessageForRetryListener/etc. are typed against
 * ContainerInterface so they can be wired via a full DI container in a normal
 * Symfony app — this app has none (see scripts/console.php), so a tiny array
 * lookup is the whole implementation needed.
 */
final class ArrayContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class (sprintf('Service "%s" is not defined.', $id)) extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
