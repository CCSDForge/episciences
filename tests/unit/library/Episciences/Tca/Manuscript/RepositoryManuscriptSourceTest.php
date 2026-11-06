<?php

declare(strict_types=1);

namespace unit\library\Episciences\Tca\Manuscript;

use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use Episciences\Tca\Contract\ManuscriptTooLargeException;
use Episciences\Tca\Manuscript\RepositoryManuscriptSource;
use Episciences_Paper;
use Episciences_Paper_File;
use Episciences_Repositories;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Zend_Registry;

/**
 * Behavioural tests of the manuscript acquisition: every test calls fetch() against
 * a mocked HTTP client, an in-memory paper and fake repository definitions.
 *
 * @covers \Episciences\Tca\Manuscript\RepositoryManuscriptSource
 * @covers \Episciences\Tca\Manuscript\SizeLimitedStream
 * @covers \Episciences\Tca\Manuscript\UnavailableManuscript
 */
final class RepositoryManuscriptSourceTest extends TestCase
{
    private const int DOCID = 4242;
    private const string PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    private const string ZIP = "PK\x03\x04\x14\x00\x00\x00\x08\x00some compressed bytes";

    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    private ?string $backupFile = null;

    private bool $hadMetadataSources;

    private mixed $originalMetadataSources = null;

    protected function setUp(): void
    {
        $source = static fn(string $name, string $paperUrl): array => [
            'name' => $name,
            'type' => 'repository',
            'identifier' => null,
            'doc_url' => '',
            'paper_url' => $paperUrl,
            'base_url' => '',
            'api_url' => '',
            'doi_prefix' => '',
        ];

        $this->hadMetadataSources = Zend_Registry::isRegistered('metadataSources');
        if ($this->hadMetadataSources) {
            $this->originalMetadataSources = Zend_Registry::get('metadataSources');
        }

        Zend_Registry::set('metadataSources', [
            (int) Episciences_Repositories::HAL_REPO_ID => $source('HAL', 'https://hal.science/%%IDv%%VERSION/document'),
            (int) Episciences_Repositories::ARXIV_REPO_ID => $source('arXiv', 'https://arxiv.org/pdf/%%IDv%%VERSION'),
            (int) Episciences_Repositories::ZENODO_REPO_ID => $source('Zenodo', ''),
            (int) Episciences_Repositories::ARCHE_ID => $source('ARCHE', ''),
        ]);
        $this->resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        if ($this->hadMetadataSources) {
            Zend_Registry::set('metadataSources', $this->originalMetadataSources);
        } else {
            Zend_Registry::getInstance()->offsetUnset('metadataSources');
        }
        $this->resetRepositoriesCache();

        if ($this->backupFile !== null && is_file($this->backupFile)) {
            unlink($this->backupFile);
        }
    }

    // -------------------------------------------------------------------------
    // Papers without analysable file
    // -------------------------------------------------------------------------

    public function testReturnsNullWhenThePaperDoesNotExist(): void
    {
        $source = $this->makeSource([], paper: null);

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    public function testReturnsNullForATemporaryVersion(): void
    {
        $source = $this->makeSource([], $this->makePaper(0));

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    /**
     * @dataProvider nonArticleTypeProvider
     */
    public function testReturnsNullForDatasetsAndSoftware(string $type): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => $type]);

        self::assertNull($this->makeSource([], $paper)->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonArticleTypeProvider(): array
    {
        return [
            'dataset' => [Episciences_Paper::DATASET_TYPE_TITLE],
            'software' => [Episciences_Paper::SOFTWARE_TYPE_TITLE],
        ];
    }

    public function testReturnsNullWhenTheRepositoryHasNoFileUrl(): void
    {
        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ARCHE_ID));

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    public function testReturnsNullWhenAFilesEnrichedRecordHasNoMainFile(): void
    {
        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID), mainFile: null);

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    public function testReturnsNullForANonHttpFileLink(): void
    {
        $mainFile = $this->makeMainFile('file:///etc/passwd', 'paper.pdf');
        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID), $mainFile);

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    // -------------------------------------------------------------------------
    // Local backup
    // -------------------------------------------------------------------------

    public function testUsesTheLocalBackupWithoutAnyHttpCall(): void
    {
        $this->backupFile = (string) tempnam(sys_get_temp_dir(), 'tca_backup_');
        file_put_contents($this->backupFile, self::PDF);

        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID), backupPath: $this->backupFile);
        $file = $source->fetch(self::DOCID);

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame([], $this->history);
        self::assertSame('2401.01234v2.pdf', $file->fileName);
        self::assertSame(strlen(self::PDF), $file->size);
        self::assertSame('application/pdf', $file->mimeType);
        self::assertSame(self::PDF, $file->stream->getContents());
    }

    public function testRefusesALocalBackupLargerThanTheLimit(): void
    {
        $this->backupFile = (string) tempnam(sys_get_temp_dir(), 'tca_backup_');
        file_put_contents($this->backupFile, self::PDF);

        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID), backupPath: $this->backupFile, maxBytes: 10);

        $this->expectException(ManuscriptTooLargeException::class);
        $source->fetch(self::DOCID);
    }

    // -------------------------------------------------------------------------
    // Successful downloads
    // -------------------------------------------------------------------------

    public function testDownloadsAnArxivPdfFromTheComputedUrl(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Type' => 'application/pdf'], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID)
        );

        $file = $source->fetch(self::DOCID);

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertCount(1, $this->history);
        self::assertSame('https://arxiv.org/pdf/2401.01234v2', (string) $this->history[0]['request']->getUri());
        self::assertSame('2401.01234v2.pdf', $file->fileName);
        self::assertSame(strlen(self::PDF), $file->size);
        self::assertSame('application/pdf', $file->mimeType);
        self::assertSame(self::PDF, $file->stream->getContents(), 'the stream starts at the beginning of the file');
    }

    public function testSendsRealTransferOptionsAndTheUserAgent(): void
    {
        $source = $this->makeSource(
            [new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            userAgent: 'Episciences test agent'
        );

        $source->fetch(self::DOCID);

        $options = $this->history[0]['options'];
        self::assertSame(RepositoryManuscriptSource::CONNECT_TIMEOUT, $options['connect_timeout']);
        self::assertSame(RepositoryManuscriptSource::TIMEOUT, $options['timeout']);
        self::assertFalse($options['allow_redirects']);
        self::assertSame('Episciences test agent', $this->history[0]['request']->getHeaderLine('User-Agent'));
    }

    public function testKeepsTheAccentedNameAndTheDocxTypeOfAZenodoFile(): void
    {
        $mainFile = $this->makeMainFile('https://zenodo.org/api/records/1/files/x/content', 'Étude complète.docx', 'docx');
        $source = $this->makeSource(
            [new Response(200, ['Content-Type' => 'application/octet-stream'], self::ZIP)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $mainFile
        );

        $file = $source->fetch(self::DOCID);

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame('https://zenodo.org/api/records/1/files/x/content', (string) $this->history[0]['request']->getUri());
        self::assertSame('Étude complète.docx', $file->fileName);
        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $file->mimeType);
    }

    public function testPrefersTheNameFromContentDisposition(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => "attachment; filename=\"fallback.pdf\"; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf"], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        self::assertSame('résumé.pdf', $source->fetch(self::DOCID)?->fileName);
    }

    public function testStripsPathSeparatorsFromTheFileName(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => 'attachment; filename="../../etc/paper.pdf"'], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        self::assertSame('paper.pdf', $source->fetch(self::DOCID)?->fileName);
    }

    public function testAppendsTheExtensionMatchingTheDetectedType(): void
    {
        $mainFile = $this->makeMainFile('https://zenodo.org/api/records/1/files/x/content', 'manuscript.txt', 'txt');
        $source = $this->makeSource(
            [new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $mainFile
        );

        $file = $source->fetch(self::DOCID);

        self::assertSame('manuscript.txt.pdf', $file?->fileName);
        self::assertSame('application/pdf', $file?->mimeType);
    }

    public function testFollowsRedirectsAndKeepsOnlyTheFinalBody(): void
    {
        $source = $this->makeSource(
            [
                new Response(302, ['Location' => '/document/final.pdf'], '<html>Moved</html>'),
                new Response(200, [], self::PDF),
            ],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        $file = $source->fetch(self::DOCID);

        self::assertCount(2, $this->history);
        self::assertSame('https://hal.science/document/final.pdf', (string) $this->history[1]['request']->getUri());
        self::assertSame(self::PDF, $file?->stream->getContents());
        self::assertSame(strlen(self::PDF), $file?->size);
    }

    // -------------------------------------------------------------------------
    // Final failures: null
    // -------------------------------------------------------------------------

    public function testReturnsNullOnARedirectToANonHttpUrl(): void
    {
        $source = $this->makeSource(
            [new Response(302, ['Location' => 'file:///etc/passwd'])],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertCount(1, $this->history);
    }

    /**
     * @dataProvider unavailableStatusProvider
     */
    public function testReturnsNullWhenTheFileIsGoneOrRestricted(int $status): void
    {
        $source = $this->makeSource([new Response($status)], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID));

        self::assertNull($source->fetch(self::DOCID));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function unavailableStatusProvider(): array
    {
        return ['401' => [401], '403' => [403], '404' => [404], '410' => [410], '400' => [400]];
    }

    public function testReturnsNullWhenTheRepositoryAnswersWithAWebPage(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Type' => 'text/html'], '<!DOCTYPE html><html><body>Embargoed until 2030</body></html>')],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        self::assertNull($source->fetch(self::DOCID));
    }

    public function testReturnsNullForAnEmptyFile(): void
    {
        $source = $this->makeSource([new Response(200, [], '')], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID));

        self::assertNull($source->fetch(self::DOCID));
    }

    // -------------------------------------------------------------------------
    // Exceptions
    // -------------------------------------------------------------------------

    /**
     * @dataProvider transientStatusProvider
     */
    public function testThrowsARetryableExceptionOnTransientErrors(int $status): void
    {
        $source = $this->makeSource([new Response($status)], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID));

        $this->expectException(ManuscriptTemporarilyUnavailableException::class);
        $source->fetch(self::DOCID);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function transientStatusProvider(): array
    {
        return ['429' => [429], '500' => [500], '503' => [503]];
    }

    public function testThrowsARetryableExceptionWhenTheRepositoryIsUnreachable(): void
    {
        // A handler of its own: MockHandler would hand the queued exception to on_headers as if it were a response
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            handler: static fn(RequestInterface $request): never => throw new ConnectException('Connection timed out', $request)
        );

        try {
            $source->fetch(self::DOCID);
            self::fail('An exception was expected');
        } catch (ManuscriptTemporarilyUnavailableException $e) {
            self::assertTrue($e->isRetryable());
            self::assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function testThrowsARetryableExceptionAfterTooManyRedirects(): void
    {
        $redirects = array_fill(0, RepositoryManuscriptSource::MAX_REDIRECTS + 1, new Response(302, ['Location' => 'https://hal.science/loop']));
        $source = $this->makeSource($redirects, $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID));

        $this->expectException(ManuscriptTemporarilyUnavailableException::class);
        $source->fetch(self::DOCID);
    }

    public function testStopsWhenTheAnnouncedSizeExceedsTheLimit(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Length' => '1000'], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            maxBytes: 50
        );

        try {
            $source->fetch(self::DOCID);
            self::fail('An exception was expected');
        } catch (ManuscriptTooLargeException $e) {
            self::assertSame(1000, $e->getObservedSize());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testStopsWhenAnUnannouncedBodyExceedsTheLimit(): void
    {
        $source = $this->makeSource(
            [new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            maxBytes: 10
        );

        $this->expectException(ManuscriptTooLargeException::class);
        $source->fetch(self::DOCID);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, mixed> $responses
     */
    private function makeSource(
        array $responses,
        ?Episciences_Paper $paper,
        ?Episciences_Paper_File $mainFile = null,
        ?string $backupPath = null,
        int $maxBytes = ManuscriptFile::MAX_SIZE,
        string $userAgent = '',
        ?callable $handler = null
    ): RepositoryManuscriptSource {
        $stack = HandlerStack::create($handler ?? new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new RepositoryManuscriptSource(
            new Client(['handler' => $stack]),
            new NullLogger(),
            static fn(int $docId): ?Episciences_Paper => $paper,
            static fn(Episciences_Paper $p): ?Episciences_Paper_File => $mainFile,
            static fn(Episciences_Paper $p): ?string => $backupPath,
            $maxBytes,
            $userAgent
        );
    }

    private function makePaper(int $repoId, string $identifier = '2401.01234', float $version = 2): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setDocid(self::DOCID);
        $paper->setRepoid($repoId);
        $paper->setIdentifier($identifier);
        $paper->setVersion($version);

        return $paper;
    }

    private function makeMainFile(string $selfLink, string $fileName, string $fileType = 'pdf'): Episciences_Paper_File
    {
        return new Episciences_Paper_File([
            'doc_id' => self::DOCID,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'self_link' => $selfLink,
            'file_size' => 0,
        ]);
    }

    private function resetRepositoriesCache(): void
    {
        $property = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $property->setValue(null, []);
    }
}
