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

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier, $version);

        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->expectExceptionObject($e);
        }

    }



    public function testGetHalRecord(): void {

        $repoId = 1;
        $identifier = 'hal-02525803';
        $version = 3;

        $oai = $this->getOai($repoId);

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier, $version);



        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->expectExceptionObject($e);
        }

    }


    public function testGetZenodoRecord(): void {

        $repoId = 4;
        $identifier = '6078767';

        $oai = $this->getOai($repoId);

        $id = Episciences_Repositories::getIdentifier($repoId, $identifier);


        try {
            $record = $oai->getRecord($id);
            self::assertIsString($record);
        } catch (Exception $e) {
            $this->expectExceptionObject($e);
        }


    }


    /**
     * @param int $repoId
     * @return Episciences_Oai_Client
     */
    private function getOai(int $repoId): Episciences_Oai_Client
    {
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);
        return new Episciences_Oai_Client($baseUrl, 'xml');
    }

}
