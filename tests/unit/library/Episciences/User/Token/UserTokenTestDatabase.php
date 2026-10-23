<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Token;

use Zend_Db_Adapter_Pdo_Sqlite;

/**
 * In-memory SQLite copy of the user_token table, for the account token tests
 */
final class UserTokenTestDatabase
{
    public static function create(): Zend_Db_Adapter_Pdo_Sqlite
    {
        $db = new Zend_Db_Adapter_Pdo_Sqlite(['dbname' => ':memory:']);
        $db->query(
            'CREATE TABLE user_token (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uid INTEGER NOT NULL,
                email TEXT NOT NULL,
                purpose TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL
            )'
        );

        return $db;
    }
}
