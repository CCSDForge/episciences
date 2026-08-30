<?php

declare(strict_types=1);

namespace Episciences\Next\Messenger;

use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

/**
 * Describes the Next.js revalidation queue transport. Backoff is
 * deliberately short compared to SolrIndexTransport's 5/5000ms/x3/30min:
 *
 * - A tag revalidated 30 minutes late is worthless — the ISR TTL would have
 *   expired on its own by then. Landing in messenger_failed after ~81s keeps
 *   it visible in --list-failed while it still matters.
 * - redeliver_timeout=300 (not 3600): a POST with a 5s timeout can never
 *   legitimately hold a message for an hour, so a crashed worker should
 *   release it within 5 minutes, not 60.
 */
final class NextRevalidationTransport
{
    public const NAME = 'next_revalidation';

    private const REDELIVER_TIMEOUT_SECONDS = 300;
    private const RETRY_MAX_RETRIES = 4;
    private const RETRY_DELAY_MS = 1_000;
    private const RETRY_MULTIPLIER = 4.0;
    private const RETRY_MAX_DELAY_MS = 60_000; // 1s -> 4s -> 16s -> 60s, dead-letter at ~81s

    public static function config(): TransportConfig
    {
        return new TransportConfig(self::NAME, self::REDELIVER_TIMEOUT_SECONDS);
    }

    /** @return list<class-string> */
    public static function messageClasses(): array
    {
        return [RevalidateTagMessage::class];
    }

    public static function retryStrategy(): RetryStrategyInterface
    {
        return new MultiplierRetryStrategy(
            self::RETRY_MAX_RETRIES,
            self::RETRY_DELAY_MS,
            self::RETRY_MULTIPLIER,
            self::RETRY_MAX_DELAY_MS,
        );
    }
}
