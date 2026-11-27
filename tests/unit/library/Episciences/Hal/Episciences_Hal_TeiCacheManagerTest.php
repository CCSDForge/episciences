<?php

declare(strict_types=1);

namespace unit\library\Episciences\Hal;

use Episciences\Hal\HalTeiSource;
use Episciences_Hal_TeiCacheManager;
use Episciences_Repositories;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @covers Episciences_Hal_TeiCacheManager
 */
final class Episciences_Hal_TeiCacheManagerTest extends TestCase
{
    private const HAL = 9001;
    private const HAL_PREPROD = 9002;
    private const NO_OAI = 9003;
    private const IDENTIFIER = 'hal-01234567';

    private ArrayAdapter $cache;

    /** @var array<mixed> */
    private array $previousRepositories = [];

    protected function setUp(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $this->previousRepositories = (array)$repositories->getValue();
        $repositories->setValue(null, [
            self::HAL => self::repository('HAL', 'https://api.hal.test/oai/hal/', 'oai:HAL:%%IDv%%VERSION'),
            self::HAL_PREPROD => self::repository('HAL-PREPROD', 'https://api-preprod.hal.test/oai/hal/', 'oai-preprod:HAL:%%IDv%%VERSION'),
            self::NO_OAI => self::repository('HAL without OAI', '', ''),
        ]);

        $this->cache = new ArrayAdapter(0, false);
        Episciences_Hal_TeiCacheManager::setCachePool($this->cache);
    }

    protected function tearDown(): void
    {
        Episciences_Hal_TeiCacheManager::setCachePool(null);
        Episciences_Hal_TeiCacheManager::setHttpClient(null);
        (new ReflectionProperty(Episciences_Repositories::class, '_repositories'))->setValue(null, $this->previousRepositories);
    }

    public function testTeiIsReadFromTheOaiServerOfTheRepositoryForTheRequestedVersion(): void
    {
        self::assertSame(
            ['https://api.hal.test/oai/hal?verb=GetRecord&metadataPrefix=xml-tei&identifier=oai%3AHAL%3Ahal-01234567v2'],
            $this->requestedUrls(new HalTeiSource(self::HAL, self::IDENTIFIER, 2))
        );
    }

    public function testTeiWithoutVersionIsTheLatestVersion(): void
    {
        self::assertSame(
            ['https://api.hal.test/oai/hal?verb=GetRecord&metadataPrefix=xml-tei&identifier=oai%3AHAL%3Ahal-01234567'],
            $this->requestedUrls(new HalTeiSource(self::HAL, self::IDENTIFIER))
        );
    }

    public function testEachHalInstanceIsAskedForItsOwnDocuments(): void
    {
        self::assertSame(
            ['https://api-preprod.hal.test/oai/hal?verb=GetRecord&metadataPrefix=xml-tei&identifier=oai-preprod%3AHAL%3Ahal-01234567v1'],
            $this->requestedUrls(new HalTeiSource(self::HAL_PREPROD, self::IDENTIFIER, 1))
        );
    }

    public function testSameIdentifierOnTwoHalInstancesIsCachedTwice(): void
    {
        $this->halResponds(new Response(200, [], self::oaiRecord('production')), new Response(200, [], self::oaiRecord('preprod')));

        self::assertStringContainsString('production', Episciences_Hal_TeiCacheManager::fetchAndGet(new HalTeiSource(self::HAL, self::IDENTIFIER, 1)));
        self::assertStringContainsString('preprod', Episciences_Hal_TeiCacheManager::fetchAndGet(new HalTeiSource(self::HAL_PREPROD, self::IDENTIFIER, 1)));
    }

    /**
     * The TEI readers walk the TEI from a <TEI> root in the default namespace (SimpleXML)
     */
    public function testTeiIsCachedOutOfTheOaiEnvelopeWithItsRootInTheDefaultNamespace(): void
    {
        $this->halResponds(new Response(200, [], self::oaiRecord('v2')));

        $tei = Episciences_Hal_TeiCacheManager::fetchAndGet($this->source(2));
        $document = simplexml_load_string($tei);

        self::assertNotFalse($document);
        self::assertSame('TEI', $document->getName());
        self::assertSame('v2', (string)$document->text->body->listBibl->biblFull->titleStmt->title);
    }

    public function testOaiErrorIsNeitherCachedNorRequestedAgainInTheSameProcess(): void
    {
        $handler = $this->halResponds(new Response(200, [], self::oaiError('idDoesNotExist')), new Response(200, [], self::oaiRecord('back')));

        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source(9)));
        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source(9)));
        self::assertCount(1, $handler, 'HAL called once for the failed TEI');
    }

    /**
     * A page served with a 200 status instead of the TEI (HAL maintenance) is neither cached nor read
     */
    public function testResponseThatIsNotATeiIsNotCached(): void
    {
        $this->halResponds(new Response(200, ['Content-Type' => 'text/html'], '<!DOCTYPE html><html><body>Maintenance</body></html>'));

        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source()));
        self::assertFalse($this->cache->hasItem('hal-tei-' . self::HAL . '-' . self::IDENTIFIER . '.xml'), 'the page is not cached');
    }

    public function testRepositoryWithoutOaiServerIsNotRequested(): void
    {
        $handler = $this->halResponds(new Response(200, [], self::oaiRecord('never')));

        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet(new HalTeiSource(self::NO_OAI, self::IDENTIFIER, 1)));
        self::assertCount(1, $handler, 'HAL not called');
    }

    public function testRefreshReplacesTheCachedTeiOfThatVersionOnly(): void
    {
        $this->halResponds(
            new Response(200, [], self::oaiRecord('latest')),
            new Response(200, [], self::oaiRecord('v2 before')),
            new Response(200, [], self::oaiRecord('v2 edited on HAL'))
        );
        Episciences_Hal_TeiCacheManager::fetchAndCache($this->source());
        Episciences_Hal_TeiCacheManager::fetchAndCache($this->source(2));

        self::assertTrue(Episciences_Hal_TeiCacheManager::refresh($this->source(2)));

        self::assertStringContainsString('v2 edited on HAL', Episciences_Hal_TeiCacheManager::getFromCache($this->source(2)));
        self::assertStringContainsString('latest', Episciences_Hal_TeiCacheManager::getFromCache($this->source()));
    }

    public function testFailedRefreshKeepsTheCachedTei(): void
    {
        $handler = $this->halResponds(new Response(200, [], self::oaiRecord('cached')), new Response(503));
        Episciences_Hal_TeiCacheManager::fetchAndCache($this->source());

        self::assertFalse(@Episciences_Hal_TeiCacheManager::refresh($this->source()));

        self::assertStringContainsString('cached', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source()));
        self::assertCount(0, $handler, 'HAL called twice');
    }

    public function testCachedTeiIsServedWithoutCallingHal(): void
    {
        $handler = $this->halResponds(new Response(200, [], self::oaiRecord('cached')));
        Episciences_Hal_TeiCacheManager::fetchAndCache($this->source());

        self::assertFalse(Episciences_Hal_TeiCacheManager::fetchAndCache($this->source()), 'already cached');
        self::assertStringContainsString('cached', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source()));
        self::assertCount(0, $handler, 'HAL called once');
    }

    public function testRefreshRequestsAFailedTeiAgain(): void
    {
        $this->halResponds(new Response(200, [], ''), new Response(200, [], self::oaiRecord('back')));
        Episciences_Hal_TeiCacheManager::fetchAndCache($this->source());

        self::assertTrue(Episciences_Hal_TeiCacheManager::refresh($this->source()));

        self::assertStringContainsString('back', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source()));
    }

    public function testFailureOfOneVersionDoesNotBlockAnother(): void
    {
        $this->halResponds(new Response(200, [], self::oaiError('idDoesNotExist')), new Response(200, [], self::oaiRecord('v2')));

        self::assertSame('', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source(9)));
        self::assertStringContainsString('v2', Episciences_Hal_TeiCacheManager::fetchAndGet($this->source(2)));
    }

    private function source(int $version = 0): HalTeiSource
    {
        return new HalTeiSource(self::HAL, self::IDENTIFIER, $version);
    }

    private function halResponds(Response ...$responses): MockHandler
    {
        $handler = new MockHandler($responses);
        Episciences_Hal_TeiCacheManager::setHttpClient(new Client(['handler' => HandlerStack::create($handler)]));

        return $handler;
    }

    /**
     * @return list<string>
     */
    private function requestedUrls(HalTeiSource $source): array
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], self::oaiRecord('any'))]));
        $stack->push(Middleware::history($history));
        Episciences_Hal_TeiCacheManager::setHttpClient(new Client(['handler' => $stack]));

        Episciences_Hal_TeiCacheManager::fetchAndGet($source);

        return array_map(static fn(array $transaction): string => (string)$transaction['request']->getUri(), $history);
    }

    /**
     * @return array<string, string>
     */
    private static function repository(string $label, string $baseUrl, string $identifierTemplate): array
    {
        return [
            Episciences_Repositories::REPO_LABEL => $label,
            Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            Episciences_Repositories::REPO_BASEURL => $baseUrl,
            Episciences_Repositories::REPO_IDENTIFIER => $identifierTemplate,
        ];
    }

    /**
     * GetRecord response of the HAL OAI-PMH server: the TEI inside the envelope, with a prefixed root
     */
    private static function oaiRecord(string $title): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord xmlns:tei="http://www.tei-c.org/ns/1.0"><record>'
            . '<header><identifier>oai:HAL:hal-01234567</identifier></header><metadata>'
            . '<tei:TEI xmlns:hal="http://hal.archives-ouvertes.fr/"><tei:text><tei:body><tei:listBibl><tei:biblFull>'
            . '<tei:titleStmt><tei:title xml:lang="en">' . $title . '</tei:title></tei:titleStmt>'
            . '</tei:biblFull></tei:listBibl></tei:body></tei:text></tei:TEI>'
            . '</metadata></record></GetRecord></OAI-PMH>';
    }

    private static function oaiError(string $code): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="' . $code . '">unknown identifier</error></OAI-PMH>';
    }
}
