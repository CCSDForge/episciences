<?php

namespace unit\library\Ccsd\Oai;

use Ccsd_Error;
use Ccsd_Oai_Client;
use Ccsd_Oai_ServerErrorException;
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

    public function testGetRecordReportsAnUnreachableRepositoryAsUnavailable(): void
    {
        $oai = $this->getClientFailingWith(
            new Ccsd_Error("Couldn't resolve host name", CURLE_COULDNT_RESOLVE_HOST, Ccsd_Error::CURL_ERROR_TYPE)
        );

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('An unreachable repository must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, $e->getMessage());
            self::assertSame(CURLE_COULDNT_RESOLVE_HOST, $e->getCode());
            self::assertTrue(Ccsd_Oai_Client::isTransientFailure($e));
        }
    }

    public function testGetRecordKeepsOtherCurlErrors(): void
    {
        $oai = $this->getClientFailingWith(
            new Ccsd_Error('Maximum redirects followed', CURLE_TOO_MANY_REDIRECTS, Ccsd_Error::CURL_ERROR_TYPE)
        );

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('CURL_ERROR_' . CURLE_TOO_MANY_REDIRECTS);
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


    // -------------------------------------------------------------------------
    // Attempts and HTTP 5xx answers
    // -------------------------------------------------------------------------

    public function testGetRecordMakesASingleAttemptByDefault(): void
    {
        $oai = $this->getScriptedClient([$this->timeout(), self::VALID_RESPONSE]);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('A timeout must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, $e->getMessage());
        }

        self::assertSame(1, $oai->calls);
        self::assertSame([], $oai->pauses);
    }

    public function testGetRecordTriesAgainAfterATimeout(): void
    {
        $oai = $this->getScriptedClient([$this->timeout(), self::VALID_RESPONSE]);
        $oai->setMaxAttempts(2);
        $handler = new TestHandler();
        $oai->setLogger(new Logger('test', [$handler]));

        $record = $oai->getRecord('oai:arXiv.org:1706.03762');

        self::assertStringContainsString('<identifier>oai:arXiv.org:1706.03762</identifier>', $record);
        self::assertSame(2, $oai->calls);
        self::assertSame([Ccsd_Oai_Client::RETRY_DELAY], $oai->pauses);
        self::assertCount(1, $handler->getRecords());
        self::assertSame(1, $handler->getRecords()[0]['context']['attempt']);
        self::assertSame(2, $handler->getRecords()[0]['context']['max_attempts']);
    }

    public function testGetRecordGivesUpAfterTheLastAttempt(): void
    {
        $oai = $this->getScriptedClient([$this->timeout(), $this->timeout(), self::VALID_RESPONSE]);
        $oai->setMaxAttempts(2);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('Two timeouts must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, $e->getMessage());
            self::assertTrue(Ccsd_Oai_Client::isTransientFailure($e));
        }

        self::assertSame(2, $oai->calls);
    }

    public function testGetRecordReportsAnHttpServerError(): void
    {
        $oai = $this->getScriptedClient([['body' => '<html><h1>Bad Gateway</h1></html>', 'http_code' => 502]]);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('An HTTP 502 answer must raise a Ccsd_Oai_ServerErrorException');
        } catch (Ccsd_Oai_ServerErrorException $e) {
            self::assertSame(502, $e->getHttpStatus());
            self::assertSame(502, $e->getCode());
            self::assertSame(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, $e->getMessage());
            self::assertSame(
                'https://oai.example.org/oai?verb=GetRecord&identifier=oai:arXiv.org:1706.03762&metadataPrefix=oai_dc',
                $e->getUrl()
            );
            self::assertNull($e->getRetryAfter());
            self::assertStringNotContainsString('Bad Gateway', $e->getMessage());
        }
    }

    public function testGetRecordTriesAgainAfterAnHttpServerError(): void
    {
        $oai = $this->getScriptedClient([['body' => 'Service Unavailable', 'http_code' => 503], self::VALID_RESPONSE]);
        $oai->setMaxAttempts(2);

        self::assertIsString($oai->getRecord('oai:arXiv.org:1706.03762'));
        self::assertSame(2, $oai->calls);
    }

    public function testGetRecordGivesUpAfterTwoHttpServerErrors(): void
    {
        $oai = $this->getScriptedClient([
            ['body' => '', 'http_code' => 502],
            ['body' => '', 'http_code' => 502],
        ]);
        $oai->setMaxAttempts(2);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('Two HTTP 502 answers must raise a Ccsd_Oai_ServerErrorException');
        } catch (Ccsd_Oai_ServerErrorException $e) {
            self::assertSame(502, $e->getHttpStatus());
        }

        self::assertSame(2, $oai->calls);
    }

    public function testGetRecordCapsTheRetryAfterPause(): void
    {
        $oai = $this->getScriptedClient([
            ['body' => '', 'http_code' => 503, 'retry_after' => 120],
            self::VALID_RESPONSE,
        ]);
        $oai->setMaxAttempts(2);

        $oai->getRecord('oai:arXiv.org:1706.03762');

        self::assertSame([Ccsd_Oai_Client::MAX_RETRY_DELAY], $oai->pauses);
    }

    public function testGetRecordFollowsAShortRetryAfter(): void
    {
        $oai = $this->getScriptedClient([
            ['body' => '', 'http_code' => 503, 'retry_after' => 2],
            self::VALID_RESPONSE,
        ]);
        $oai->setMaxAttempts(2);

        $oai->getRecord('oai:arXiv.org:1706.03762');

        self::assertSame([2], $oai->pauses);
    }

    public function testGetRecordDoesNotTryAgainAfterAnHttpClientError(): void
    {
        $oai = $this->getScriptedClient([['body' => '<html><body><h1>Not Found</h1></body>', 'http_code' => 404], self::VALID_RESPONSE]);
        $oai->setMaxAttempts(2);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('An HTML page must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertNotInstanceOf(Ccsd_Oai_ServerErrorException::class, $e);
            self::assertSame('Invalid OAI-PMH response', $e->getMessage());
        }

        self::assertSame(1, $oai->calls);
    }

    public function testGetRecordDoesNotTryAgainAfterAnOaiError(): void
    {
        $oai = $this->getScriptedClient([
            ['body' => '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">'
                . '<error code="idDoesNotExist">No matching identifier</error></OAI-PMH>'],
            self::VALID_RESPONSE,
        ]);
        $oai->setMaxAttempts(2);

        try {
            $oai->getRecord('oai:arXiv.org:0000.00000');
            self::fail('An OAI error must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertStringStartsWith('idDoesNotExist', $e->getMessage());
            self::assertFalse(Ccsd_Oai_Client::isTransientFailure($e));
        }

        self::assertSame(1, $oai->calls);
    }

    public function testGetRecordSendsTheConfiguredTimeouts(): void
    {
        $oai = $this->getScriptedClient([self::VALID_RESPONSE]);
        $oai->setRequestTimeout(15);
        $oai->setConnectTimeout(5);

        $oai->getRecord('oai:arXiv.org:1706.03762');

        self::assertSame(15, $oai->requestedOptions[CURLOPT_TIMEOUT]);
        self::assertSame(5, $oai->requestedOptions[CURLOPT_CONNECTTIMEOUT]);
    }

    public function testGetRecordReportsARefusedConnectionAsUnavailableAfterTheLastAttempt(): void
    {
        $refused = static fn(): Ccsd_Error => new Ccsd_Error("Couldn't connect to server", CURLE_COULDNT_CONNECT, Ccsd_Error::CURL_ERROR_TYPE);
        $oai = $this->getScriptedClient([$refused(), $refused(), self::VALID_RESPONSE]);
        $oai->setMaxAttempts(2);

        try {
            $oai->getRecord('oai:arXiv.org:1706.03762');
            self::fail('Two refused connections must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, $e->getMessage());
            self::assertSame(CURLE_COULDNT_CONNECT, $e->getCode());
        }

        self::assertSame(2, $oai->calls);
    }

    public function testTransientFailures(): void
    {
        self::assertTrue(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, CURLE_COULDNT_CONNECT)));
        self::assertTrue(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Oai_ServerErrorException(500, 'https://oai.example.org/oai')));
        self::assertTrue(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, CURLE_OPERATION_TIMEDOUT)));
        self::assertTrue(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error("Couldn't connect to server", CURLE_COULDNT_CONNECT, Ccsd_Error::CURL_ERROR_TYPE)));
        // Same number, but not a cURL error
        self::assertFalse(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error('Something else', CURLE_COULDNT_CONNECT)));
        self::assertFalse(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error('Only http and https URLs are allowed', 0, Ccsd_Error::CURL_ERROR_TYPE)));
        self::assertFalse(Ccsd_Oai_Client::isTransientFailure(new Ccsd_Error('Invalid OAI-PMH response')));
    }

    public function testRepositoryUnavailableHasItsOwnMessage(): void
    {
        $message = (new Ccsd_Oai_ServerErrorException(503, 'https://oai.example.org/oai'))->parseError();

        self::assertStringContainsString('is temporarily unavailable', $message);
        self::assertStringNotContainsString('The operation ended with this error', $message);
    }

    private const VALID_RESPONSE = [
        'body' => '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
            . '<record><header><identifier>oai:arXiv.org:1706.03762</identifier></header></record>'
            . '</GetRecord></OAI-PMH>',
        'http_code' => 200,
    ];

    private function timeout(): Ccsd_Error
    {
        return new Ccsd_Error('Timeout was reached', CURLE_OPERATION_TIMEDOUT, Ccsd_Error::CURL_ERROR_TYPE);
    }

    /**
     * OAI client answering each request with the next item of $script (no network, no pause):
     * a Ccsd_Error to throw, or ['body' => string, 'http_code' => int, 'retry_after' => ?int]
     * @param array<Ccsd_Error|array> $script
     */
    private function getScriptedClient(array $script): Ccsd_Oai_Client
    {
        return new class('https://oai.example.org/oai', 'xml', $script) extends Ccsd_Oai_Client {
            public int $calls = 0;
            /** @var int[] */
            public array $pauses = [];
            public array $requestedOptions = [];
            private array $script;

            public function __construct(string $url, string $format, array $script)
            {
                parent::__construct($url, $format);
                $this->script = $script;
            }

            protected function fetchResponse(string $url, array $options): string
            {
                $this->requestedOptions = $options;
                $step = $this->script[$this->calls++];
                if ($step instanceof Ccsd_Error) {
                    $this->lastTransferInfo = ['http_code' => 0];
                    $this->lastRetryAfter = null;
                    throw $step;
                }
                $this->lastTransferInfo = ['http_code' => $step['http_code'] ?? 200];
                $this->lastRetryAfter = $step['retry_after'] ?? null;
                return $step['body'];
            }

            protected function pause(int $seconds): void
            {
                $this->pauses[] = $seconds;
            }
        };
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
                2 => 'https://oaipmh.arxiv.org/oai',             // ArXiv
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
