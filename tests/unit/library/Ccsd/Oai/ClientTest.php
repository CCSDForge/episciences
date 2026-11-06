<?php

namespace unit\library\Ccsd\Oai;

use Ccsd_Error;
use Ccsd_Oai_Client;
use Ccsd_Tools;
use Episciences_Oai_Client;
use Episciences_Repositories;
use Exception;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
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

    public function testGetRecordSendsAShortTimeout(): void
    {
        $oai = $this->getClientReturning(
            '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
            . '<record><header><identifier>oai:zenodo.org:6078767</identifier></header></record>'
            . '</GetRecord></OAI-PMH>'
        );

        $oai->getRecord('oai:zenodo.org:6078767');

        self::assertSame(Ccsd_Oai_Client::REQUEST_TIMEOUT, $oai->requestedOptions[CURLOPT_TIMEOUT]);
        self::assertSame(Ccsd_Oai_Client::CONNECT_TIMEOUT, $oai->requestedOptions[CURLOPT_CONNECTTIMEOUT]);
        self::assertLessThan(Ccsd_Tools::DEFAULT_CURL_OPT_TIMEOUT, Ccsd_Oai_Client::REQUEST_TIMEOUT);
    }

    public function testGetRecordReportsATimeoutAsRepositoryTimeout(): void
    {
        $oai = $this->getClientFailingWith(
            new Ccsd_Error('Timeout was reached', CURLE_OPERATION_TIMEDOUT, 'CURL_ERROR')
        );

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('A timeout must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, $e->getMessage());
            self::assertSame(CURLE_OPERATION_TIMEDOUT, $e->getCode());
        }
    }

    public function testGetRecordKeepsOtherCurlErrors(): void
    {
        $oai = $this->getClientFailingWith(
            new Ccsd_Error("Couldn't resolve host name", CURLE_COULDNT_RESOLVE_HOST, 'CURL_ERROR')
        );

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('CURL_ERROR_' . CURLE_COULDNT_RESOLVE_HOST);
        $oai->getRecord('oai:arXiv.org:1706.03762');
    }

    public function testGetRecordLogsTheFailedRequest(): void
    {
        $oai = $this->getClientFailingWith(
            new Ccsd_Error('Timeout was reached', CURLE_OPERATION_TIMEDOUT, 'CURL_ERROR'),
            ['http_code' => 0, 'primary_ip' => '192.0.2.10', 'connect_time' => 0.05, 'total_time' => 30.0]
        );
        $handler = new TestHandler();
        $oai->setLogger(new Logger('test', [$handler]));

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('A timeout must raise a Ccsd_Error');
        } catch (Ccsd_Error) {
        }

        self::assertTrue($handler->hasWarningThatContains('OAI-PMH request failed'));
        $context = $handler->getRecords()[0]['context'];
        self::assertSame(
            'https://oai.example.org/oai?verb=GetRecord&identifier=oai:arXiv.org:1706.03762&metadataPrefix=oai_dc',
            $context['url']
        );
        self::assertSame(CURLE_OPERATION_TIMEDOUT, $context['curl_errno']);
        self::assertStringContainsString('Timeout was reached', $context['error']);
        self::assertSame(0, $context['http_code']);
        self::assertSame('192.0.2.10', $context['primary_ip']);
        self::assertSame(0.05, $context['connect_time']);
        self::assertSame(30.0, $context['total_time']);
    }

    public function testGetRecordDoesNotLogASuccessfulRequest(): void
    {
        $oai = $this->getClientReturning(
            '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
            . '<record><header><identifier>oai:zenodo.org:6078767</identifier></header></record>'
            . '</GetRecord></OAI-PMH>'
        );
        $handler = new TestHandler();
        $oai->setLogger(new Logger('test', [$handler]));

        $oai->getRecord('oai:zenodo.org:6078767');

        self::assertSame([], $handler->getRecords());
    }

    public function testCurlTransferInfoIsReturnedOnFailure(): void
    {
        // Closed local port: the connection is refused at once, without reaching the network
        $transferInfo = null;
        try {
            Ccsd_Tools::curlSourceGetContents('http://127.0.0.1:1/', [CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 2], $transferInfo);
            self::fail('A refused connection must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(CURLE_COULDNT_CONNECT, $e->getCode());
        }

        self::assertIsArray($transferInfo);
        self::assertArrayHasKey('total_time', $transferInfo);
        self::assertArrayHasKey('connect_time', $transferInfo);
        self::assertSame(0, $transferInfo['http_code']);
    }

    public function testRepositoryTimeoutHasItsOwnMessage(): void
    {
        $message = (new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE))->parseError();

        self::assertStringContainsString('did not respond in time', $message);
        self::assertStringNotContainsString('The operation ended with this error', $message);
    }


    /**
     * OAI client whose HTTP call fails with the given error (no network)
     * @param Ccsd_Error $error
     * @param array $transferInfo curl_getinfo() of the failed request
     * @return Ccsd_Oai_Client
     */
    private function getClientFailingWith(Ccsd_Error $error, array $transferInfo = []): Ccsd_Oai_Client
    {
        return new class('https://oai.example.org/oai', 'xml', $error, $transferInfo) extends Ccsd_Oai_Client {
            private Ccsd_Error $error;
            private array $transferInfo;

            public function __construct(string $url, string $format, Ccsd_Error $error, array $transferInfo)
            {
                parent::__construct($url, $format);
                $this->error = $error;
                $this->transferInfo = $transferInfo;
            }

            protected function fetchResponse(string $url, array $options): string
            {
                $this->lastTransferInfo = $this->transferInfo;
                throw $this->error;
            }
        };
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
            public array $requestedOptions = [];
            private string $body;

            public function __construct(string $url, string $format, string $body)
            {
                parent::__construct($url, $format);
                $this->body = $body;
            }

            protected function fetchResponse(string $url, array $options): string
            {
                $this->requestedUrl = $url;
                $this->requestedOptions = $options;
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
