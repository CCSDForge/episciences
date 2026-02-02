<?php

namespace Episciences;

use Episciences_View_Helper_Log;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use Zend_Db_Adapter_Exception;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use JsonException;

class QueueMessageManager
{
    public const TABLE = 'queue_messages';
    public const TYPE_DEFAULT_TIMEOUT = 120;

    public const UNPROCESSED = 0;
    public const PROCESSED = 1;
    public const MAX_RECEIVE = 1000;
    public const DEFAULT_RECEIVE = 10;
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const VALID_TYPES = [self::TYPE_STATUS_CHANGED];


    public static function add(QueueMessage $queue): int
    {
        if (empty($queue->getRvcode())) {
            throw new InvalidArgumentException("rvcode cannot be empty");
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $unprocessed = self::UNPROCESSED;
        $table = self::TABLE;
        $defaultTimeOut = self::TYPE_DEFAULT_TIMEOUT;

        $sql = "INSERT INTO {$table} (rvcode, type, message, created_at, timeout, processed) 
        VALUES (:rvcode, :type, :message, UNIX_TIMESTAMP(), {$defaultTimeOut}, {$unprocessed})";

        $stmt = $db?->prepare($sql);

        $stmt->execute([
            'rvcode' => $queue->getRvcode(),
            ':type' => $queue->getType(),
            ':message' => self::dataToJson($queue->getMessage())
        ]);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int)$db?->lastInsertId();
    }

    public static function deleteById(int $id, bool $forceDelete = false): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException("Invalid ID: $id");
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($forceDelete) {
            return $db?->delete(self::TABLE, ['id = ?' => $id]);
        }

        try {
            return $db?->update(self::TABLE, ['processed' => self::PROCESSED], ['id = ?' => $id]);
        } catch (Zend_Db_Adapter_Exception $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            return 0;
        }
    }

    public static function receive(string $type = null, int $max = self::DEFAULT_RECEIVE, string $fetch = 'object'): array
    {

        if ($max < 1 || $max > self::MAX_RECEIVE) {
            throw new InvalidArgumentException("Invalid max: $max . Must be between 1 and " . self::MAX_RECEIVE);
        }

        $select = self::allQuery();

        $select->where('processed = ?', self::UNPROCESSED);
        $select->where('timeout > (UNIX_TIMESTAMP() - created_at)');

        if ($type !== null) {

            if (!in_array($type, self::VALID_TYPES)) {
                throw new InvalidArgumentException("Type invalide: $type");
            }

            $select->where('type = ?', $type);
        }

        $select->limit($max);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $result = $db?->fetchAssoc($select);

        $array_map = [];
        $isObject = ($fetch === 'object');

        foreach ($result as $key => $values) {

            if (isset($values['message'])) {
                $values['message'] = self::jsonToArray($values['message']);
            }

            $array_map[$key] = $isObject ? (new QueueMessage($values)) : $values;
        }
        return $array_map;
    }

    public static function allQuery(array $fields = ['*']): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(self::TABLE, $fields);
    }


    private static function dataToJson(array $data): string
    {
        $message = '';

        try {
            $message = json_encode($data,JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
        }

        return $message;
    }

    private static function jsonToArray(string $message): ?array
    {
        try {
            return json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            return null;
        }
    }
}
