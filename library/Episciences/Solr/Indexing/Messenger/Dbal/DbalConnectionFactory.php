<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Zend_Db_Adapter_Abstract;

/**
 * Builds the Doctrine\DBAL\Connection used by the Messenger transport tables,
 * reusing the same main application database ("EPI" in config/pwd.json) the
 * already-bootstrapped Zend_Db adapter is connected to — rather than opening a
 * config source of its own. This keeps the door open to eventually enqueue a
 * Solr message in the same SQL transaction as the paper state change that
 * triggers it (not possible if the queue lived in a separate database).
 */
final class DbalConnectionFactory
{
    public static function fromZendAdapter(Zend_Db_Adapter_Abstract $adapter): Connection
    {
        $config = $adapter->getConfig();

        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $config['host'],
            'port' => isset($config['port']) ? (int)$config['port'] : 3306,
            'dbname' => $config['dbname'],
            'user' => $config['username'],
            'password' => $config['password'],
            'charset' => $config['charset'] ?? 'utf8mb4',
        ]);
    }
}
