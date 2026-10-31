<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Ccsd_Error;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Episciences_Submit;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Repository processing of an initial submission, after the paper is saved and the notifications are sent:
 * the repository hooks (Episciences_Submit::processRepositoryHooks()) and the HAL datasets request
 * (Episciences_Submit::datasetsProcessing()).
 */
final class RepositoryHooksTest extends TestCase
{
    private const SPY_REPO_ID = 9998;
    /** Fake HAL repository: the label contains "HAL" */
    private const HAL_REPO_ID = 9997;
    private const HAL_API_URL = 'https://api.hal.test';
    private const DOCID = 1234;

    /** @var array<mixed> */
    private array $previousRepositories;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/modules/support/NewVersionSpyHooks.php';
    }

    protected function setUp(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $this->previousRepositories = $repositories->getValue();
        $repositories->setValue(null, [
            self::SPY_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'New Version Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            ],
            self::HAL_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'HAL Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_API_URL => self::HAL_API_URL,
            ],
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
    }

    public function testFilesFetchFailureDoesNotSkipTheLinkedData(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('files unavailable');

        $errors = $this->collectErrors(fn() => $this->processRepositoryHooks());

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the linked data hook still runs');
        self::assertSame(['files unavailable'], $errors, 'the failure is reported, not thrown');
    }

    public function testLinkedDataFetchFailureIsReportedNotThrown(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $errors = $this->collectErrors(fn() => $this->processRepositoryHooks());

        self::assertArrayHasKey('hookFilesProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertSame(['linked data unavailable'], $errors);
    }

    public function testHalRequestHasNoVersionAndAnEncodedIdentifier(): void
    {
        $requests = [];
        $client = $this->halClient($requests, new Response(200, [], '{"response":{"numFound":0,"docs":[]}}'));

        $affectedRows = Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567&rows=1000'), $client);

        self::assertSame(0, $affectedRows);
        self::assertCount(1, $requests);
        $uri = $requests[0]['request']->getUri();
        self::assertSame('api.hal.test', $uri->getHost());
        self::assertSame('/search/', $uri->getPath());
        parse_str($uri->getQuery(), $query);
        self::assertSame('halId_s:hal-01234567&rows=1000', $query['q'], 'the identifier is encoded');
        self::assertArrayNotHasKey('rows', $query);
        self::assertSame('swhidId_s,researchData_s', $query['fl']);
        self::assertStringNotContainsString('version_i', $uri->getQuery(), 'the HAL index only holds the latest version');
    }

    public function testHalResponseWithoutDocsDoesNotFail(): void
    {
        $requests = [];
        $client = $this->halClient($requests, new Response(200, [], '{"response":{"numFound":0}}'));

        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567'), $client))
        );

        self::assertSame([], $errors);
    }

    public function testHalApiFailureIsReported(): void
    {
        $requests = [];
        $client = $this->halClient($requests, new Response(503, [], 'Service Unavailable'));

        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567'), $client))
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('503', $errors[0]);
    }

    public function testNonHalRepositoryDoesNotQueryTheApi(): void
    {
        $requests = [];
        $client = $this->halClient($requests);
        $paper = new Episciences_Paper(['docid' => self::DOCID, 'repoid' => self::SPY_REPO_ID, 'identifier' => '42']);

        self::assertSame(0, Episciences_Submit::datasetsProcessing($paper, $client));
        self::assertSame([], $requests);
    }

    /**
     * @param array<mixed> $requests receives the Guzzle history of the requests sent
     * @param-out array<mixed>|\ArrayAccess<int, array<mixed>> $requests
     */
    private function halClient(array &$requests, Response ...$responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($requests));

        return new Client(['handler' => $stack]);
    }

    private function halPaper(string $identifier): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'repoid' => self::HAL_REPO_ID,
            'identifier' => $identifier,
            'version' => 1,
        ]);
    }

    private function processRepositoryHooks(): void
    {
        $paper = new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => self::SPY_REPO_ID,
            'identifier' => '42',
        ]);

        $submit = (new ReflectionClass(Episciences_Submit::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Episciences_Submit::class, 'processRepositoryHooks');
        $method->setAccessible(true);
        $method->invoke($submit, $paper, []);
    }

    /**
     * @return list<string> messages of the errors triggered by $run
     */
    private function collectErrors(callable $run): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;
            return true;
        });

        try {
            $run();
        } finally {
            restore_error_handler();
        }

        return $errors;
    }
}
