<?php

class Episciences_Paper_DocumentBackup
{

    const DEFAULT_FILE_EXTENSION = 'pdf';
    /**
     * @var int
     */
    protected $_docid;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $pathFileName;

    /**
     * @var string
     */
    protected $extension = self::DEFAULT_FILE_EXTENSION;

    /**
     * Episciences_Paper_DocumentBackup constructor.
     * @param int $docid
     */
    public function __construct(int $docid)
    {
        $this->setDocid($docid);
        $this->setPath();
        $this->setPathFileName();

    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     */
    public function setExtension(string $extension = self::DEFAULT_FILE_EXTENSION)
    {
        $this->extension = $extension;
    }

    /**
     * @param string $mainFileContent
     * @return bool
     */
    public function saveDocumentBackupFile(string $mainFileContent): bool
    {
        try {
            $this->makeDocumentBackupPath();
        } catch (RuntimeException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
        if (file_put_contents($this->getPathFileName(), $mainFileContent)) {
            return true;
        }
        return false;
    }

    /**
     *
     */
    protected function makeDocumentBackupPath()
    {
        $backupPath = $this->getPath();
        if (!file_exists($backupPath) && !mkdir($backupPath, 0777, true) && !is_dir($backupPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $backupPath));
        }
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     *
     */
    public function setPath()
    {
        $this->path = REVIEW_FILES_PATH . $this->getDocid() . '/' . REVIEW_DOCUMENT_DIR_NAME . '/';
    }

    /**
     * @return string
     */
    public function getPathFileName(): string
    {
        return $this->pathFileName;
    }

    /**
     * @param string $fileExtension
     */
    public function setPathFileName()
    {
        $this->pathFileName = sprintf("%s%s.%s", $this->getPath(), $this->getDocid(), $this->getExtension());
    }

    /**
     * @return int
     */
    public function getDocid(): int
    {
        return $this->_docid;
    }

    /**
     * @param int $docid
     */
    public function setDocid(int $docid)
    {
        $this->_docid = $docid;
    }

    /**
     * @return string
     */
    public function getDocumentBackupFile(): string
    {
        if (!$this->hasDocumentBackupFile()) {
            $mainFileContent = '';
        } else {
            $mainFileContent = file_get_contents($this->getPathFileName());
        }


        if (!$mainFileContent) {
            $mainFileContent = '';
        }

        return $mainFileContent;
    }

    /**
     * @return bool
     */
    public function hasDocumentBackupFile(): bool
    {
        $mainFilePathName = $this->getPathFileName();
        return (file_exists($mainFilePathName)) && (is_readable($mainFilePathName));
    }


}