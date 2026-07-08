<?php

class Episciences_Paper_File
{
    public const DEFAULT_SELF_LINK_VALUE = '#';

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

    protected bool $_isMain = false;

    /** @var DateTime */
    protected $_timeModified = 'CURRENT_TIMESTAMP';

    protected ?string $_downloadLike = null;

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
            'fileType' => $this->getFileType(),
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
    public function setId(int $id): self
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
    public function setFileName(string $fileName): self
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
    public function setFileSize(int $fileSize): self
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
    public function setFileType(string $fileType = null): self
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
    public function setChecksum(string $checksum): self
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
    public function setChecksumType(string $checksumType = null): self
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
    public function setSelfLink(string $link = null): self
    {
        $this->_selfLink = $link ?? self::DEFAULT_SELF_LINK_VALUE;
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
    public function setTimeModified(string $timeModified): self
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


    public function setDownloadLike(): self
    {

        if (Episciences_Repositories::isDataverse($this->getSource())) {

            $dUrl = Episciences_Repositories::getApiUrl($this->getSource());
            $dUrl .= 'access/datafile/:persistentId?persistentId=';
            $dUrl .= Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX;
            $dUrl .= Episciences_DoiTools::cleanDoi($this->getSelfLink());
            $this->_downloadLike = $dUrl;

        } else {
            $this->_downloadLike = $this->getSelfLink();

        }

        return $this;
    }

    public function getDownloadLike(): ?string
    {
        return $this->_downloadLike;
    }

    public function isIsMain(): bool
    {
        return $this->_isMain;
    }

    public function setIsMain(bool $isMain = false): self
    {
        $this->_isMain = $isMain;
        return $this;
    }



    public function save(): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $affectedRows = 0;
        $values[] = '(' . $db->quote($this->getDocId()) . ',' . $db->quote($this->getSource()) . ',' . $db->quote($this->getFileName()) . ',' . $db->quote($this->getChecksum()) . ',' . $db->quote($this->getChecksumType()) . ',' . $db->quote($this->getSelfLink()) . ',' . $db->quote($this->getFileSize()) . ',' . $db->quote($this->getFileType()) . ',' .  $db->quote($this->isIsMain()) . ')';
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_FILES) . ' (`doc_id`, `source`, `file_name`, `checksum`, `checksum_type`, `self_link`, `file_size`, `file_type`,`is_main`) VALUES ';
        $sql .= implode(', ', $values);
        $sql .= ' AS new_file ON DUPLICATE KEY UPDATE `file_size` = new_file.file_size, `checksum` = new_file.checksum, `checksum_type` = new_file.checksum_type, `file_type` = new_file.file_type, `file_name` = new_file.file_name, is_main = new_file.is_main';
        try {
            //Prepares and executes an SQL
            /** @var Zend_Db_Statement_Interface $result */
            $result = $db->query($sql);
            $affectedRows = $result->rowCount();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return $affectedRows;

    }

}