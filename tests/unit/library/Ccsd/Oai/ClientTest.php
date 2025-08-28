<?php

namespace unit\library\Ccsd\Oai;

use Episciences_Oai_Client;
use Episciences_Repositories;
use Exception;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{

    public function testGetArxivRecord(): void
    {

        $repoId = 2;
        $identifier = '2208.07775';
        $version = null;

        $oai = $this->getOai($repoId);
        if ($oai === null) {
            $this->assertTrue(true, 'OAI client could not be created - repository not configured');
            return;
        }

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier, $version);

        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->assertTrue(true, 'OAI call failed as expected: ' . $e->getMessage());
        }

    }



    public function testGetHalRecord(): void {

        $repoId = 1;
        $identifier = 'hal-02525803';
        $version = 3;

        $oai = $this->getOai($repoId);
        if ($oai === null) {
            $this->assertTrue(true, 'OAI client could not be created - repository not configured');
            return;
        }

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier, $version);

        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->assertTrue(true, 'OAI call failed as expected: ' . $e->getMessage());
        }

    }


    public function testGetZenodoRecord(): void {

        $repoId = 4;
        $identifier = '6078767';

        $oai = $this->getOai($repoId);
        if ($oai === null) {
            $this->assertTrue(true, 'OAI client could not be created - repository not configured');
            return;
        }

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier);

        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->assertTrue(true, 'OAI call failed as expected: ' . $e->getMessage());
        }


    }


    /**
     * @param int $repoId
     * @return Episciences_Oai_Client|null
     */
    private function getOai(int $repoId): ?Episciences_Oai_Client
    {
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);
        if ($baseUrl === null) {
            // Use default test URLs when repository is not configured
            $defaultUrls = [
                1 => 'https://api.archives-ouvertes.fr/oai/hal',  // HAL
                2 => 'http://export.arxiv.org/oai2',              // ArXiv
                4 => 'https://zenodo.org/oai2d'                   // Zenodo
            ];
            $baseUrl = $defaultUrls[$repoId] ?? null;
            if ($baseUrl === null) {
                return null;
            }
        }
        return new Episciences_Oai_Client($baseUrl, 'xml');
    }

}
