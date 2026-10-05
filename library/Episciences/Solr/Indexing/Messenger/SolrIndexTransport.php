<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger;

use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

/**
 * Describes the Solr indexing queue transport for the generic
 * Episciences\Messenger\* infrastructure: its name, message routing and
 * retry policy.
 *
 * The retry/failure-transport pairing directly fixes the two confirmed
 * legacy reliability bugs: Ccsd_Search_Solr_Indexer's zero-retry behaviour on
 * genuine Solr/network failures (Indexer.php:517-544 swallows the exception,
 * leaving INDEX_QUEUE rows stuck in STATUS='locked' forever), and the
 * resulting lack of any audit trail for what failed (failed envelopes
 * persist in the failure transport table instead of just disappearing on
 * success like INDEX_QUEUE rows do).
 */
final class SolrIndexTransport
{
    public const NAME = 'solr_index';

    // Prevents a crashed worker from blocking a message indefinitely (fixes
    // the legacy bug where INDEX_QUEUE rows got stuck in STATUS='locked' forever).
    private const REDELIVER_TIMEOUT_SECONDS = 3600;

    private const RETRY_MAX_RETRIES = 5;
    private const RETRY_DELAY_MS = 5000;
    private const RETRY_MULTIPLIER = 3.0;
    private const RETRY_MAX_DELAY_MS = 1_800_000;

    public static function config(): TransportConfig
    {
        return new TransportConfig(self::NAME, self::REDELIVER_TIMEOUT_SECONDS);
    }

    /** @return list<class-string> */
    public static function messageClasses(): array
    {
        return [IndexPaperMessage::class, DeletePaperMessage::class];
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
