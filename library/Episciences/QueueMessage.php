<?php

namespace Episciences;

use Episciences_Tools;
use Episciences_View_Helper_Log;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use Zend_Db_Adapter_Exception;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use JsonException;

class QueueMessage
{
    private ?int $id;
    private ?string $rvcode;
    private ?string $message;
    private ?int $timeout;
    private ?int $created_at;
    private ?int $processed;
    private ?int $updated_at;
    private string $type;

    public const TABLE = 'queue_messages';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_DEFAULT_TIMEOUT = 120;
    public const MAX_RECEIVE = 1000;
    public const DEFAULT_RECEIVE = 10;
    public const UNPROCESSED = 0;
    public const PROCESSED = 1;

    public const VALID_TYPES = [self::TYPE_STATUS_CHANGED];


    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options): void
    {
        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRvcode(): ?string
    {
        return $this->rvcode;
    }


    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getCreatedAt(): ?int
    {
        return $this->created_at;
    }

    public function getProcessed(): ?int
    {
        return $this->processed;
    }

    public function getUpdatedAt(): ?int
    {
        return $this->updated_at;
    }

    private function getTable(): string
    {
        return self::TABLE;
    }

    private function getDefaultTimeout(): int
    {
        return self::TYPE_DEFAULT_TIMEOUT;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setProcessed(?int $processed): void
    {
        $this->processed = $processed;
    }

    private function setType(string $type = ''): self
    {

        if (!in_array($type, self::VALID_TYPES)) {
            throw new InvalidArgumentException("Invalid Type: $type");
        }

        $this->type = $type;
        return $this;
    }

    private function setId(?int $id): void
    {
        $this->id = $id;
    }

    private function setRvcode(?string $rvcode): void
    {
        $this->rvcode = $rvcode;
    }

    private function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    private function setTimeout(?int $timeout): void
    {
        $this->timeout = $timeout;
    }

    private function setCreatedAt(?int $created_at): void
    {
        $this->created_at = $created_at;
    }

    private function setUpdatedAt(?int $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'rvcode' => $this->getRvcode(),
            'message' => $this->getMessage(),
            'timeout' => $this->getTimeout(),
            'created_at' => $this->getCreatedAt(),
            'processed' => $this->getProcessed(),
            'updated_at' => $this->getUpdatedAt()
        ];
    }

    public function send(array $data, string $rvCode): int
    {
        $message = $this->dataToJson($data);
        $unprocessed = self::UNPROCESSED;

        if ($message === '') {
            throw new InvalidArgumentException("Message cannot be empty");
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = "INSERT INTO {$this->getTable()} (rvcode, type, message, created_at, timeout, processed) 
        VALUES (:rvcode, :type, :message, UNIX_TIMESTAMP(), {$this->getDefaultTimeout()}, {$unprocessed})";

        $stmt = $db?->prepare($sql);

        $stmt->execute([
            'rvcode' => $rvCode,
            ':type' => $this->getType(),
            ':message' => $message
        ]);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        return (int)$db?->lastInsertId();
    }

    public function delete(int $id, bool $forceDelete = false): int
    {

        if ($id < 1) {
            throw new InvalidArgumentException("Invalid ID: $id");
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($forceDelete) {
            return $db?->delete($this->getTable(), ['id = ?' => $id]);
        }

        try {
            return $db?->update($this->getTable(), ['processed' => self::PROCESSED], ['id = ?' => $id]);
        } catch (Zend_Db_Adapter_Exception $e) {
            return 0;
        }
    }

    public function receive(int $max = self::DEFAULT_RECEIVE, string $fetch = 'object'): array
    {

        if ($max < 1 || $max > self::MAX_RECEIVE) {
            throw new InvalidArgumentException("Invalid max: $max . Must be between 1 and " . self::MAX_RECEIVE);
        }

        $type = $this->getType();
        $select = $this->allQuery();

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

        if ($fetch !== 'object') {
            return $result;
        }

        return array_map(static function ($values) {
            return new self($values);
        }, $result);

    }

    private function dataToJson(array $data): string
    {
        $message = '';

        try {
            $message = json_encode([
                $data
            ], JSON_THROW_ON_ERROR);

        } catch (JsonException $e) {
            Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
        }

        return $message;

    }

    public function allQuery(array $fields = ['*']): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from($this->getTable(), $fields);
    }
}
