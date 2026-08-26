<?php

declare(strict_types=1);

namespace Episciences\Messenger\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Zend_Db_Adapter_Abstract;

/**
 * Builds the Doctrine\DBAL\Connection used by the Messenger transport tables,
 * reusing the same main application database ("EPI" in config/pwd.json) the
 * already-bootstrapped Zend_Db adapter is connected to — rather than opening a
 * config source of its own.
 *
 * Memoized per process (keyed by connection parameters): every Messenger-based
 * producer (Solr indexing, Next.js revalidation, ...) built in the same
 * request/process shares one underlying DBAL connection instead of each
 * opening its own on top of the existing Zend_Db PDO connection.
 *
 * Note this is still a *separate* connection from Zend_Db's own PDO handle,
 * not a shared transaction: an enqueue performed while a Zend_Db transaction
 * is open commits immediately on its own connection and survives a later
 * rollback of that outer transaction.
 */
final class DbalConnectionFactory
{
    /** @var array<string, Connection> */
    private static array $connections = [];

    public static function fromZendAdapter(Zend_Db_Adapter_Abstract $adapter): Connection
    {
        $config = $adapter->getConfig();

        $key = implode('|', [
            (string)($config['host'] ?? ''),
            (string)($config['port'] ?? 3306),
            (string)($config['dbname'] ?? ''),
            (string)($config['username'] ?? ''),
        ]);

        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = DriverManager::getConnection([
                'driver' => 'pdo_mysql',
                'host' => $config['host'],
                'port' => isset($config['port']) ? (int)$config['port'] : 3306,
                'dbname' => $config['dbname'],
                'user' => $config['username'],
                'password' => $config['password'],
                'charset' => $config['charset'] ?? 'utf8mb4',
            ]);
        }

        return self::$connections[$key];
    }

    /**
     * Clears the memoized connections. Tests only — a long-running process
     * (worker, request) never needs to call this.
     */
    public static function reset(): void
    {
        self::$connections = [];
    }
}
