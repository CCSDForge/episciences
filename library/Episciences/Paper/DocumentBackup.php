<?php

class Episciences_Paper_DocumentBackup
{

    public const DEFAULT_FILE_EXTENSION = 'pdf';
    public const REVIEW_DOCUMENT_DIR_NAME = 'documents';
    /**
     * @var int
     */
    protected int $_docid;

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var string
     */
    protected string $pathFileName;

    /**
     * @var string
     */
    protected string $extension = self::DEFAULT_FILE_EXTENSION;

    /**
     * Episciences_Paper_DocumentBackup constructor.
     * @param int $docid
     */
    public function __construct(int $docid, string $rvcode)
    {
        $this
            ->setDocid($docid)
            ->setPath($rvcode)
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
        if (!file_exists($backupPath) && !mkdir($backupPath, 0775, true) && !is_dir($backupPath)) {
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
    public function setPath(string $rvcode, string $path = null): \Episciences_Paper_DocumentBackup
    {
        if (!$path) {
            $this->path = APPLICATION_PATH . '/../data/' . $rvcode . '/' . 'files/' . $this->getDocid() . '/' . self::REVIEW_DOCUMENT_DIR_NAME . '/';
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

    /**
     * Removes the backup copy, which is keyed on the DOCID only: to be called when the document
     * of a DOCID changes (paper replaced in place by a new version).
     *
     * @return bool true when there is no backup copy left
     */
    public function deleteDocumentBackupFile(): bool
    {
        $mainFilePathName = $this->getPathFileName();

        // The failure is logged below with its reason, not raised as a PHP warning as well
        if (!file_exists($mainFilePathName) || @unlink($mainFilePathName)) {
            return true;
        }

        \Episciences\AppRegistry::getMonoLogger()?->warning(
            'The backup copy of the document could not be deleted.',
            [
                'docid' => $this->getDocid(),
                'path' => $mainFilePathName,
                'reason' => error_get_last()['message'] ?? 'unknown',
            ]
        );

        return false;
    }

    /**
     * Removes the backup copy of a DOCID whose document changed (new version saved on the same DOCID):
     * submission replaced in place, version posted by the editorial team, COAR Notify in-place update.
     *
     * @return bool true when there is no backup copy left
     */
    public static function deleteForPaper(int $docId, int $rvId): bool
    {
        $journal = \Episciences_ReviewsManager::findByRvid($rvId);

        if (!$journal) {
            \Episciences\AppRegistry::getMonoLogger()?->warning(
                'The backup copy of the document was not deleted: the journal was not found.',
                ['docid' => $docId, 'rvid' => $rvId]
            );

            return false;
        }

        return (new self($docId, (string)$journal->getCode()))->deleteDocumentBackupFile();
    }


}