<?php

namespace Episciences;

use Episciences_Tools;
use InvalidArgumentException;


class QueueMessage
{
    private ?int $id;
    private ?string $rvcode;
    private ?array $message;
    private ?int $timeout;
    private ?int $created_at;
    private ?int $processed;
    private ?int $updated_at;
    private string $type;

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


    public function getMessage(): ?array
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

    public function getType(): ?string
    {
        return $this->type;
    }

    private function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setRvcode(?string $rvcode): void
    {
        $this->rvcode = $rvcode;
    }

    public function setProcessed(?int $processed): void
    {
        $this->processed = $processed;
    }

    public function setType(string $type = ''): self
    {

        if (!in_array($type, QueueMessageManager::VALID_TYPES)) {
            throw new InvalidArgumentException("Invalid Type: $type");
        }

        $this->type = $type;
        return $this;
    }


    public function setMessage(?array $message): void
    {
        $this->message = $message;
    }

    public function setTimeout(?int $timeout): void
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

    public function send(): int
    {
        return QueueMessageManager::add($this);
    }

    public function delete(bool|null $forceDelete = null): int
    {
        return QueueMessageManager::deleteById($this->getId(), $forceDelete);
    }
}
