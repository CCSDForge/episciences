<?php

declare(strict_types=1);

namespace Episciences\Messenger\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as BridgeConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * Hand-wires a Symfony Messenger DoctrineTransport since this app has no
 * Symfony Kernel/DI container (see scripts/console.php) — everything a
 * normal Symfony app gets for free from FrameworkBundle's MessengerPass is
 * assembled here explicitly, generically for any transport described by a
 * TransportConfig.
 */
final class TransportFactory
{
    public static function createTransport(DbalConnection $connection, TransportConfig $config, bool $autoSetup = false): DoctrineTransport
    {
        return self::buildTransport($connection, $config->messagesTable, $config, $autoSetup);
    }

    public static function createFailureTransport(DbalConnection $connection, TransportConfig $config, bool $autoSetup = false): DoctrineTransport
    {
        return self::buildTransport($connection, $config->failedTable, $config, $autoSetup);
    }

    private static function buildTransport(DbalConnection $connection, string $tableName, TransportConfig $config, bool $autoSetup): DoctrineTransport
    {
        $bridgeConnection = new BridgeConnection(
            [
                'table_name' => $tableName,
                'queue_name' => $config->name,
                'redeliver_timeout' => $config->redeliverTimeoutSeconds,
                'auto_setup' => $autoSetup,
            ],
            $connection,
        );

        return new DoctrineTransport($bridgeConnection, new PhpSerializer());
    }
}
