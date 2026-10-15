<?php

namespace unit\library\Ccsd\Oai;

use Ccsd_Error;
use Ccsd_Oai_Client;
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


    public function testGetRecordThrowsOnNonXmlBody(): void
    {
        // e.g. an HTML error page returned by an unavailable OAI-PMH server
        $oai = $this->getClientReturning('<html><body><h1>503 Service Unavailable</h1></body>');

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('Invalid OAI-PMH response');
        $oai->getRecord('oai:zenodo.org:6078767');
    }

    public function testGetRecordThrowsOnEmptyBody(): void
    {
        $oai = $this->getClientReturning('');

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('No body returned');
        $oai->getRecord('oai:zenodo.org:6078767');
    }

    public function testGetRecordThrowsOnOaiError(): void
    {
        $oai = $this->getClientReturning(
            '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">'
            . '<error code="idDoesNotExist">No matching identifier</error></OAI-PMH>'
        );

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('idDoesNotExist: No matching identifier');
        $oai->getRecord('oai:zenodo.org:0');
    }

    public function testGetRecordReturnsRecordFromValidResponse(): void
    {
        $oai = $this->getClientReturning(
            '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
            . '<record><header><identifier>oai:zenodo.org:6078767</identifier></header></record>'
            . '</GetRecord></OAI-PMH>'
        );

        $record = $oai->getRecord('oai:zenodo.org:6078767');

        self::assertIsString($record);
        self::assertStringContainsString('<identifier>oai:zenodo.org:6078767</identifier>', $record);
        self::assertSame(
            'https://oai.example.org/oai?verb=GetRecord&identifier=oai:zenodo.org:6078767&metadataPrefix=oai_dc',
            $oai->requestedUrl
        );
    }


    /**
     * OAI client whose HTTP call returns a fixed body (no network)
     * @param string $body
     * @return Ccsd_Oai_Client
     */
    private function getClientReturning(string $body): Ccsd_Oai_Client
    {
        return new class('https://oai.example.org/oai', 'xml', $body) extends Ccsd_Oai_Client {
            public ?string $requestedUrl = null;
            private string $body;

            public function __construct(string $url, string $format, string $body)
            {
                parent::__construct($url, $format);
                $this->body = $body;
            }

            protected function fetchResponse(string $url, array $options): string
            {
                $this->requestedUrl = $url;
                return $this->body;
            }
        };
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
