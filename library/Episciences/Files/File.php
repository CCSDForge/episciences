<?php

namespace Episciences\Files;

use DateTimeImmutable;
use Episciences_Tools;
use Zend_Db_Adapter_Exception;
use Zend_Db_Table_Abstract;

class File
{
    private ?int $id = null;
    private int $docid;
    private string $name;
    private string $extension;
    private string $typeMime;
    private int $size;
    private string $md5;
    private string $source;
    private ?string $uploadedDate = null;



    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * set paper options
     * @param array $options
     */
    public function setOptions(array $options): void
    {

        if (empty($options)) {
            return;
        }

        $classMethods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = Episciences_Tools::convertToCamelCase($key, '_', true);
            $method = 'set' . $key;
            if (in_array($method, $classMethods, true)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'docid' => $this->getDocId(),
            'name' => $this->getName(),
            'extension' => $this->getExtension(),
            'type_mime' => $this->getTypeMime(),
            'size' => $this->getSize(),
            'md5' => $this->getMd5(),
            'source' => $this->getSource(),
            'uploaded_date' => $this->getUploadedDate()
        ];

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id = null): self
    {
        $this->id = $id;
        return $this;
    }

    public function getDocid(): int
    {
        return $this->docid;
    }

    public function setDocid(int $docId): self
    {
        $this->docid = $docId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function getTypeMime(): string
    {
        return $this->typeMime;
    }

    public function setTypeMime(string $typeMime): self
    {
        $this->typeMime = $typeMime;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getMd5(): string
    {
        return $this->md5;
    }

    public function setMd5(string $md5): self
    {
        $this->md5 = $md5;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source = FileManager::DD_SOURCE): self
    {
        $this->source = $source;
        return $this;
    }

    public function getUploadedDate(): ?string
    {
        return $this->uploadedDate;
    }

    public function setUploadedDate(string $uploadedDate = null): self
    {
        $this->uploadedDate = empty($uploadedDate)  ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : $uploadedDate;
        return $this;

    }

    /**
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): self
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db?->insert(FileManager::TABLE, $this->toArray());
        $this->setId($db?->lastInsertId());
        return $this;

    }
}