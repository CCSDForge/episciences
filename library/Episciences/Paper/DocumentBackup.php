<?php

class Episciences_Paper_DocumentBackup
{

    public const DEFAULT_FILE_EXTENSION = 'pdf';
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
        $this
            ->setDocid($docid)
            ->setPath()
            ->setPathFileName();

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
     * @return Episciences_Paper_DocumentBackup
     */
    public function setExtension(string $extension = self::DEFAULT_FILE_EXTENSION): Episciences_Paper_DocumentBackup
    {
        $this->extension = $extension;
        return $this;
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
    protected function makeDocumentBackupPath(): void
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
    public function setPath(string $path = null): \Episciences_Paper_DocumentBackup
    {
        if (!$path) {
            $this->path = REVIEW_FILES_PATH . $this->getDocid() . '/' . REVIEW_DOCUMENT_DIR_NAME . '/';
        } else {
            $this->path = $path;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getPathFileName(): string
    {
        return $this->pathFileName;
    }

    /**
     * @param string|null $pathFileName
     */
    public function setPathFileName(string $pathFileName = null): \Episciences_Paper_DocumentBackup
    {
        if ($pathFileName) {
            $this->pathFileName = $pathFileName;
        } else {
            $this->pathFileName = sprintf("%s%s.%s", $this->getPath(), $this->getDocid(), $this->getExtension());
        }
         return $this;
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
     * @return Episciences_Paper_DocumentBackup
     */
    public function setDocid(int $docid): Episciences_Paper_DocumentBackup
    {
        $this->_docid = $docid;
        return $this;
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