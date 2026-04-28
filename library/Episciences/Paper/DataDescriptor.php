<?php

namespace Episciences\Paper;

use DateTimeImmutable;
use Episciences\Files\FileManager;
use Episciences_PapersManager;
use Episciences_Tools;
use Zend_Db_Adapter_Exception;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;
use Episciences\Files\File;
use Zend_Registry;

class DataDescriptor
{
    private ?int $id = null;
    private int $uid;
    private int $docid;
    private int $fileid;
    private string $path;
    private float $version = 1;
    private ?string $submissionDate = null;
    private File $file;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id = null): self // data_descriptor
    {
        $this->id = $id;
        return $this;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;
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

    public function getFileid(): int
    {
        return $this->fileid;
    }

    public function setFileid(int $fileId): self
    {
        $this->fileid = $fileId;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path = null): self
    {
        if (!$path) {
            $this->path = REVIEW_PATH . 'files' . DIRECTORY_SEPARATOR . $this->getDocId() . DIRECTORY_SEPARATOR . 'dataDescriptor';
        } else {
            $this->path = $path;
        }
        return $this;
    }

    public function getVersion(): float
    {
        return $this->version;
    }

    public function setVersion(float $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getSubmissionDate(): ?string
    {
        return $this->submissionDate;
    }

    public function setSubmissionDate(string $submissionDate = null): self
    {
        $this->submissionDate = empty($submissionDate) ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : $submissionDate;
        return $this;
    }

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
            'uid' => $this->getUid(),
            'docid' => $this->getDocid(),
            'fileid' => $this->getFileid(),
            'version' => $this->getVersion(),
            'submission_date' => $this->getSubmissionDate()
        ];

    }

    /**
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): self
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db?->insert(DataDescriptorManager::class::TABLE, $this->toArray());
        $this->setId($db?->lastInsertId());
        return $this;

    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function loadFile(): void
    {
        $this->setFile(FileManager::getById($this->getFileid()));
    }

}