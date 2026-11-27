<?php

declare(strict_types=1);

namespace unit\library\Episciences\Hal;

use Episciences\Hal\HalTeiSource;
use Episciences_Paper;
use Episciences_Repositories;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \Episciences\Hal\HalTeiSource
 */
final class HalTeiSourceTest extends TestCase
{
    private const REPO_ID = 9011;

    /** @var array<mixed> */
    private array $previousRepositories = [];

    protected function setUp(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $this->previousRepositories = (array)$repositories->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Episciences_Repositories::class, '_repositories'))->setValue(null, $this->previousRepositories);
    }

    public function testRefusedPaperRequestsTheHalIdentifierOfItsVersion(): void
    {
        $this->givenRepository('https://api.hal.test/oai/hal/', 'oai:HAL:%%IDv%%VERSION');

        $source = HalTeiSource::ofPaper(new Episciences_Paper([
            'repoid' => self::REPO_ID,
            'identifier' => 'hal-01234567' . Episciences_Paper::REFUSED_IDENTIFIER_SUFFIX,
            'version' => 3,
        ]));

        self::assertSame(self::REPO_ID, $source->repoId);
        self::assertSame('hal-01234567', $source->identifier);
        self::assertSame(3, $source->version);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableRepositoryProvider(): iterable
    {
        yield 'no OAI server' => ['', 'oai:HAL:%%IDv%%VERSION'];
        yield 'no OAI identifier' => ['https://api.hal.test/oai/hal/', ''];
        yield 'not HTTP' => ['file:///etc/passwd', 'oai:HAL:%%IDv%%VERSION'];
        yield 'not a URL' => ['https://', 'oai:HAL:%%IDv%%VERSION'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableRepositoryProvider')]
    public function testRepositoryWithoutUsableOaiServerHasNoRecordUrl(string $baseUrl, string $identifierTemplate): void
    {
        $this->givenRepository($baseUrl, $identifierTemplate);

        self::assertNull((new HalTeiSource(self::REPO_ID, 'hal-01234567', 1))->getRecordUrl());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankIdentifierProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['  '];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blankIdentifierProvider')]
    public function testDocumentWithoutIdentifierHasNoRecordUrl(string $identifier): void
    {
        // The template alone would otherwise yield the OAI identifier "oai:HAL:v1"
        $this->givenRepository('https://api.hal.test/oai/hal/', 'oai:HAL:%%IDv%%VERSION');

        self::assertNull((new HalTeiSource(self::REPO_ID, $identifier, 1))->getRecordUrl());
    }

    public function testDocumentVersionHasRecordUrl(): void
    {
        $this->givenRepository('https://api.hal.test/oai/hal/', 'oai:HAL:%%IDv%%VERSION');

        self::assertSame(
            'https://api.hal.test/oai/hal?verb=GetRecord&metadataPrefix=xml-tei&identifier=oai%3AHAL%3Ahal-01234567v1',
            (new HalTeiSource(self::REPO_ID, 'hal-01234567', 1))->getRecordUrl()
        );
    }

    private function givenRepository(string $baseUrl, string $identifierTemplate): void
    {
        (new ReflectionProperty(Episciences_Repositories::class, '_repositories'))->setValue(null, [
            self::REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'HAL',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_BASEURL => $baseUrl,
                Episciences_Repositories::REPO_IDENTIFIER => $identifierTemplate,
            ],
        ]);
    }
}
