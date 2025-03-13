<?php

/**
 * @property string $_downloadLike // @see Episciences_Paper_FilesManager::findByDocId
 */
class Episciences_Paper_File
{

    /**
     * @var int
     */
    protected $_id;
    /**
     * @var int
     */
    protected $_docId;
    /**
     * @var string
     */
    protected $_fileName;
    /**
     * @var int
     */
    protected $_fileSize;
    /**
     * @var string
     */
    protected $_fileType;

    /** @var string */
    protected $_checksum;

    /** @var string */
    protected $_checksumType;

    /** @var string */
    protected $_selfLink;
    protected int $_source;

    /** @var DateTime */
    protected $_timeModified = 'CURRENT_TIMESTAMP';

    /**
     * Episciences_Paper_File constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * set paper options
     * @param array $options
     */
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


    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'docId' => $this->getDocId(),
            'fileName' => $this->getFileName(),
            'checksum' => $this->getChecksum(),
            'checksumType' => $this->getChecksumType(),
            'selfLink' => $this->getSelfLink(),
            'fileSize' => $this->getFileSize(),
            'fileType' => $this->getFileSize(),
            'timeModified' => $this->getTimeModified()
        ];
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * @param int $id
     * @return Episciences_Paper_File
     */
    public function setId(int $id): Episciences_Paper_File
    {
        $this->_id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getDocId(): int
    {
        return $this->_docId;
    }

    /**
     * @param int $docId
     */
    public function setDocId(int $docId): void
    {
        $this->_docId = $docId;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->_fileName;
    }

    /**
     * @param string $fileName
     * @return Episciences_Paper_File
     */
    public function setFileName(string $fileName): Episciences_Paper_File
    {
        $this->_fileName = $fileName;
        return $this;
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->_fileSize;
    }

    /**
     * @param int $fileSize
     * @return Episciences_Paper_File
     */
    public function setFileSize(int $fileSize): Episciences_Paper_File
    {
        $this->_fileSize = $fileSize;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileType(): string
    {
        return $this->_fileType;
    }

    /**
     * @param string|null $fileType
     * @return Episciences_Paper_File
     */
    public function setFileType(string $fileType = null): Episciences_Paper_File
    {
        $this->_fileType = $fileType ?? 'pdf';
        return $this;
    }

    /**
     * @return string
     */
    public function getChecksum(): string
    {
        return $this->_checksum;
    }

    /**
     * @param string $checksum
     * @return Episciences_Paper_File
     */
    public function setChecksum(string $checksum): Episciences_Paper_File
    {
        $this->_checksum = $checksum;
        return $this;
    }

    /**
     * @return string
     */
    public function getChecksumType(): string
    {
        return $this->_checksumType;
    }

    /**
     * @param string|null $checksumType
     * @return Episciences_Paper_File
     */
    public function setChecksumType(string $checksumType = null): Episciences_Paper_File
    {
        $this->_checksumType = $checksumType ?? 'MD5';
        return $this;
    }

    /**
     * @return string
     */
    public function getSelfLink(): string
    {
        return $this->_selfLink;
    }

    /**
     * @param string|null $link
     * @return Episciences_Paper_File
     */
    public function setSelfLink(string $link = null): Episciences_Paper_File
    {
        $this->_selfLink = $link ?? '#';
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTimeModified()
    {
        return $this->_timeModified;
    }

    /**
     * @param string $timeModified
     * @return Episciences_Paper_File
     * @throws Exception
     */
    public function setTimeModified(string $timeModified): Episciences_Paper_File
    {
        $this->_timeModified = new DateTime($timeModified);
        return $this;
    }

    public function getSource(): int
    {
        return $this->_source;
    }

    public function setSource(int $source): self
    {
        $this->_source = $source;
        return $this;
    }

    public function getName(): string
    {
        return $this->getFileName();
    }

}