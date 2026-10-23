<?php

declare(strict_types=1);

namespace Episciences\Messenger\Transport;

/**
 * Everything TransportFactory needs to build a DoctrineTransport for one
 * Messenger queue, without hard-coding table names — every transport shares
 * the same messenger_messages/messenger_failed tables by default, keyed
 * apart by `name` (the bridge's queue_name column).
 */
final class TransportConfig
{
    public const DEFAULT_MESSAGES_TABLE = 'messenger_messages';
    public const DEFAULT_FAILED_TABLE = 'messenger_failed';

    public function __construct(
        public readonly string $name,
        public readonly int $redeliverTimeoutSeconds,
        public readonly string $messagesTable = self::DEFAULT_MESSAGES_TABLE,
        public readonly string $failedTable = self::DEFAULT_FAILED_TABLE,
    ) {
    }
}
