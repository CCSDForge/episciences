<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Zend_Db_Adapter_Pdo_Sqlite;

/**
 * In-memory SQLite copy of the review_request_type table and of the columns of USER_ASSIGNMENT the review
 * request type code reads and Episciences_User_Assignment::save() writes, and of USER_INVITATION
 */
final class RequestTypeTestDatabase
{
    public static function create(): Zend_Db_Adapter_Pdo_Sqlite
    {
        $db = new Zend_Db_Adapter_Pdo_Sqlite(['dbname' => ':memory:']);
        $db->query(
            'CREATE TABLE review_request_type (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rvid INTEGER NOT NULL,
                default_type INTEGER NULL,
                labels TEXT NOT NULL,
                instructions TEXT NULL,
                light_answer INTEGER NOT NULL DEFAULT 0,
                default_deadline TEXT NULL,
                position INTEGER NOT NULL DEFAULT 0,
                enabled INTEGER NOT NULL DEFAULT 1,
                counts_as_report INTEGER NOT NULL DEFAULT 0,
                visible_to_author INTEGER NOT NULL DEFAULT 0,
                UNIQUE (rvid, default_type)
            )'
        );
        $db->query(
            'CREATE TABLE USER_ASSIGNMENT (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                INVITATION_ID INTEGER NULL,
                RVID INTEGER NOT NULL,
                ITEMID INTEGER NOT NULL,
                ITEM TEXT NOT NULL DEFAULT \'paper\',
                UID INTEGER NOT NULL,
                FROM_UID INTEGER NULL,
                TMP_USER INTEGER NOT NULL DEFAULT 0,
                ROLEID TEXT NOT NULL,
                REQUEST_TYPE_ID INTEGER NULL,
                LIGHT_ANSWER INTEGER NOT NULL DEFAULT 0,
                STATUS TEXT NOT NULL,
                "WHEN" TEXT NULL,
                DEADLINE TEXT NULL
            )'
        );
        $db->query(
            'CREATE TABLE USER_INVITATION (
                ID INTEGER PRIMARY KEY AUTOINCREMENT,
                AID INTEGER NOT NULL,
                STATUS TEXT NOT NULL,
                SENDING_DATE TEXT NULL,
                EXPIRATION_DATE TEXT NULL
            )'
        );
        // Episciences_User_Assignment::save() timestamps a new row with the MySQL function NOW()
        $db->getConnection()->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'), 0);

        return $db;
    }
}
