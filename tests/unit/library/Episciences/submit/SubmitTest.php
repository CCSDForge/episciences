<?php

namespace unit\library\Episciences\submit;

use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use Zend_Exception;

class SubmitTest extends TestCase
{
    public const IS_ALREADY_EXISTS = 2;
    public function testGetExistArxivDocWithoutMangeNewVersionErrors(): void
    {
        $repoId = 2;
        $identifier = '2208.07775';
        $version = 1;
        $rvId = 8;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version,null, false, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }

    }


    public function testGetExistArxivDocWithMangeNewVersionErrors(): void
    {
        $repoId = 2;
        $identifier = '2208.07775';
        $version = 1;
        $rvId = 8;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version,null, true, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }

    }



    public function testZenodoDocWithMangeNewVersionErrors(): void
    {
        $repoId = 4;
        $identifier = '10.5281/zenodo.6078767';
        $version = null;
        $rvId = 23;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version,null, false, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }

    }

}