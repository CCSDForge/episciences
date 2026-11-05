<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Audit of the recipients searches: an unusual number of searches by the same user is logged.
 *
 * Searches are never refused (trusted roles, search limited to the journal). The searched text is never logged.
 */
final class RecipientSearchAudit
{
    public const DEFAULT_THRESHOLD = 300;
    public const DEFAULT_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ?LoggerInterface $logger,
        private readonly int $threshold = self::DEFAULT_THRESHOLD,
        private readonly int $windowSeconds = self::DEFAULT_WINDOW_SECONDS
    ) {
    }

    /**
     * Counts a search; logs once per window when the threshold is exceeded.
     *
     * @return int number of searches of the user in the current window
     */
    public function record(int $uid, int $rvid, ?int $now = null): int
    {
        $now ??= time();
        $windowStart = $now - ($now % $this->windowSeconds);

        try {
            $item = $this->cache->getItem(sprintf('recipient_search_%d_%d_%d', $rvid, $uid, $windowStart));
            $count = (int)($item->isHit() ? $item->get() : 0) + 1;
            $item->set($count);
            $item->expiresAfter($this->windowSeconds);
            $this->cache->save($item);
        } catch (InvalidArgumentException) {
            return 0;
        }

        if ($count === $this->threshold + 1) {
            $this->logger?->warning('Unusual number of mail recipients searches', [
                'uid' => $uid,
                'rvid' => $rvid,
                'searches' => $count,
                'windowSeconds' => $this->windowSeconds,
            ]);
        }

        return $count;
    }
}
