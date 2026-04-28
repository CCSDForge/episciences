<?php

namespace Episciences\Files;

use Ccsd_Tools;
use Episciences_Tools;
use Monolog\Logger;
use Zend_Exception;
use Zend_File_Transfer_Adapter_Http;
use Zend_File_Transfer_Exception;
use Zend_Registry;

class Uploader
{
    public const ERRORS_KEY = 'errors';
    public const UPLOADED_FILES_KEY = 'uploaded';

    private array|string $uploadDir;
    private array $info = [];
    private ?Logger $logger;


    public function __construct(array|string $uploadDirs)
    {
        $this->uploadDir = $uploadDirs;

        try {
            $this->logger = Zend_Registry::get('appLogger');
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }

    }

    /**
     * @param bool $debug : to check file info
     * @return $this
     * @throws Zend_File_Transfer_Exception
     */
    public function upload(bool $debug = false): self
    {
        $upload = new Zend_File_Transfer_Adapter_Http();

        $files = $upload->getFileInfo();

        if (!count($files)) {
            return $this;
        }

        $arrayPath = (array)$this->getUploadDir();
        $result = [];

        foreach ($files as $file => $info) {

            if (!$info['size']) {
                continue;
            }

            $tmp = [];
            $path = $arrayPath[$file] ?? array_shift($arrayPath);

            if (!$path) {
                $log = sprintf('No path specified for the file: %s', $file);
                $result[$file][self::ERRORS_KEY] = $log;
                $this->logger?->warning($log);
                continue;
            }

            if (!$debug && !is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
                $log = sprintf('Failed to create directory: %s', $path);
                $this->logger?->critical($log);
                $result[$file][self::ERRORS_KEY] = $log;
                continue;
            }

            if (!$info['error']) {
                $filename = Ccsd_Tools::cleanFileName($info['name']);
                $filename = Episciences_Tools::filenameRotate($path, $filename);
                // save file
                $upload->addFilter('Rename', $path . $filename, $file);

                $tmp['name'] = $filename;
                $tmp['size'] = $info['size'];
                $tmp['type_mime'] = $info['type'];
                $tmp['md5'] = md5_file($info['tmp_name']);
                $tmp['extension'] = str_replace('application/', '', $info['type']);
                $tmp['uploaded_date'] = null;
                $tmp['path'] = $path;

                if (!$debug && !$upload->receive($file)) {
                    $result[$file][self::ERRORS_KEY] = $upload->getMessages();
                } else {
                    $result[self::UPLOADED_FILES_KEY][$file] = new File($tmp);

                }
            }

            if (empty($arrayPath)) {
                $arrayPath[] = $path;
            }
        }

        $this->info = $result;
        return $this;

    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function getLogger(): ?Logger
    {
        return $this->logger;
    }


    public function getUploadDir(): array|string
    {
        return $this->uploadDir;
    }

    public function setUploadDir(array|string $uploadDir): self
    {
        $this->uploadDir = $uploadDir;
        return $this;

    }

}