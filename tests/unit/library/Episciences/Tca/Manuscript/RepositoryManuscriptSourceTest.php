<?php

declare(strict_types=1);

namespace unit\library\Episciences\Tca\Manuscript;

use Episciences\Files\File;
use Episciences\Http\PublicAddressGuard;
use Episciences\Paper\DataDescriptor;
use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use Episciences\Tca\Contract\ManuscriptTooLargeException;
use Episciences\Tca\Manuscript\RepositoryManuscriptSource;
use Episciences_Paper;
use Episciences_Paper_File;
use Episciences_Repositories;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
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
    private const string PUBLIC_IP = '93.184.216.34';
    private const int DATAVERSE_REPO_ID = 990;
    private const string ZIP = "PK\x03\x04\x14\x00\x00\x00\x08\x00some compressed bytes";

    /** @var array<int, array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    private bool $hadMetadataSources;

    private mixed $originalMetadataSources = null;

    private ?string $tempDir = null;

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
            self::DATAVERSE_REPO_ID => [Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_DATAVERSE] + $source('Dataverse', ''),
        ]);
        $this->resetRepositoriesCache();
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== null) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }

        if ($this->hadMetadataSources) {
            Zend_Registry::set('metadataSources', $this->originalMetadataSources);
        } else {
            Zend_Registry::getInstance()->offsetUnset('metadataSources');
        }
        $this->resetRepositoriesCache();
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
    public function testReturnsNullForDatasetsAndSoftwareWithoutDataDescriptor(string $type): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => $type]);

        self::assertNull($this->makeSource([], $paper, dataDescriptorPath: null)->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    /**
     * @dataProvider nonArticleTypeProvider
     */
    public function testReadsTheDataDescriptorOfDatasetsAndSoftwareInsteadOfTheRepository(string $type): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => $type]);
        $path = $this->writeTempFile('Data descriptor', self::PDF);

        $file = $this->makeSource([], $paper, dataDescriptorPath: $path)->fetch(self::DOCID);

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame([], $this->history, 'the repository is never requested');
        self::assertSame('Data descriptor.pdf', $file->fileName);
        self::assertSame('application/pdf', $file->mimeType);
        self::assertSame(strlen(self::PDF), $file->size);
        self::assertSame(self::PDF, $file->stream->getContents());
    }

    public function testReadsTheDataDescriptorOfADataverseDataset(): void
    {
        $paper = $this->makePaper(self::DATAVERSE_REPO_ID);
        self::assertTrue($paper->isDataset(), 'every Dataverse record is a dataset');
        $path = $this->writeTempFile('descriptor.pdf', self::PDF);

        $file = $this->makeSource([], $paper, dataDescriptorPath: $path)->fetch(self::DOCID);

        self::assertSame('descriptor.pdf', $file?->fileName);
        self::assertSame([], $this->history);
    }

    /**
     * @dataProvider missingDataDescriptorFileProvider
     */
    public function testReturnsNullWhenTheDataDescriptorFileIsMissingOrEmpty(string $content): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        $path = $content === 'missing' ? $this->tempDir() . '/gone.pdf' : $this->writeTempFile('empty.pdf', '');

        self::assertNull($this->makeSource([], $paper, dataDescriptorPath: $path)->fetch(self::DOCID));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingDataDescriptorFileProvider(): array
    {
        return ['file missing' => ['missing'], 'empty file' => ['empty']];
    }

    /**
     * @dataProvider unsafeDataDescriptorNameProvider
     */
    public function testTheDefaultLoaderRefusesAStoredNameWithADirectoryPart(string $name): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        $paper->setDataDescriptors([1 => (new DataDescriptor())->setFile(new File(['name' => $name]))]);
        $source = new RepositoryManuscriptSource(new Client(), new NullLogger(), static fn(int $docId): Episciences_Paper => $paper);

        self::assertNull($source->fetch(self::DOCID));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeDataDescriptorNameProvider(): array
    {
        return [
            'parent directory' => ['../../config/pwd.json'],
            'absolute path' => ['/etc/passwd'],
            'backslash' => ['..\\secret.pdf'],
        ];
    }

    public function testRefusesADataDescriptorLargerThanTheLimit(): void
    {
        $paper = $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        $path = $this->writeTempFile('big.pdf', self::PDF);

        try {
            $this->makeSource([], $paper, maxBytes: 10, dataDescriptorPath: $path)->fetch(self::DOCID);
            self::fail('An exception was expected');
        } catch (ManuscriptTooLargeException $e) {
            self::assertSame(strlen(self::PDF), $e->getObservedSize());
        }
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
        self::assertGreaterThan(RepositoryManuscriptSource::TIMEOUT - 5, $options['timeout']);
        self::assertLessThanOrEqual(RepositoryManuscriptSource::TIMEOUT, $options['timeout']);
        self::assertSame(RepositoryManuscriptSource::LOW_SPEED_LIMIT, $options['curl'][CURLOPT_LOW_SPEED_LIMIT]);
        self::assertSame(RepositoryManuscriptSource::LOW_SPEED_TIME, $options['curl'][CURLOPT_LOW_SPEED_TIME]);
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

    public function testDecodesAnIsoLatin1NameFromContentDisposition(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => "attachment; filename=\"memoire.pdf\"; filename*=ISO-8859-1''m%E9moire.pdf"], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        self::assertSame('mémoire.pdf', $source->fetch(self::DOCID)?->fileName);
    }

    public function testFallsBackToThePlainNameWhenTheExtendedNameCannotBeRead(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => "attachment; filename=\"memoire.pdf\"; filename*=KOI8-R''%E9%E9.pdf"], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        self::assertSame('memoire.pdf', $source->fetch(self::DOCID)?->fileName);
    }

    /**
     * @dataProvider plainFileNameProvider
     */
    public function testReadsThePlainNameWithEqualSignsCommasAndEscapes(string $contentDisposition, string $expected): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => $contentDisposition], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID, 'hal-01234567')
        );

        self::assertSame($expected, $source->fetch(self::DOCID)?->fileName);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function plainFileNameProvider(): array
    {
        return [
            'equal sign in a quoted name' => ['attachment; filename="a=b final.pdf"', 'a=b final.pdf'],
            'comma in an unquoted name' => ['attachment; filename=Smith, J. paper.pdf', 'Smith, J. paper.pdf'],
            'semicolon in a quoted name' => ['attachment; filename="part1;part2.pdf"; size=12', 'part1;part2.pdf'],
            'escaped quote' => ['attachment; filename="the \\"best\\" paper.pdf"', 'the "best" paper.pdf'],
            'case-insensitive parameter name' => ['attachment; FileName="paper.pdf"', 'paper.pdf'],
        ];
    }

    public function testEncodesTheSpacesAndAccentsOfAStoredFileLink(): void
    {
        $mainFile = $this->makeMainFile('https://zenodo.org/api/records/1/files/Mémoire final.pdf/content', 'Mémoire final.pdf');
        $source = $this->makeSource(
            [new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $mainFile
        );

        $file = $source->fetch(self::DOCID);

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame(
            'https://zenodo.org/api/records/1/files/M%C3%A9moire%20final.pdf/content',
            (string) $this->history[0]['request']->getUri()
        );
    }

    public function testEncodesTheQuotesAndAngleBracketsOfAStoredFileLink(): void
    {
        $mainFile = $this->makeMainFile('https://zenodo.org/api/records/1/files/l\'article "final" <v2>.pdf/content', 'l\'article.pdf');
        $source = $this->makeSource(
            [new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $mainFile
        );

        self::assertInstanceOf(ManuscriptFile::class, $source->fetch(self::DOCID));
        self::assertSame(
            'https://zenodo.org/api/records/1/files/l%27article%20%22final%22%20%3Cv2%3E.pdf/content',
            (string) $this->history[0]['request']->getUri()
        );
    }

    /**
     * @dataProvider unusableContentDispositionProvider
     */
    public function testFallsBackToTheMainFileNameWhenTheAnnouncedNameIsUnusable(string $contentDisposition): void
    {
        $mainFile = $this->makeMainFile('https://zenodo.org/api/records/1/files/x/content', 'Mémoire.pdf');
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => $contentDisposition], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $mainFile
        );

        self::assertSame('Mémoire.pdf', $source->fetch(self::DOCID)?->fileName);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableContentDispositionProvider(): array
    {
        return [
            'raw ISO-8859-1 bytes' => ["attachment; filename=\"M\xE9moire.pdf\""],
            'dots only' => ['attachment; filename="..."'],
            'spaces only' => ['attachment; filename="   "'],
        ];
    }

    public function testFallsBackToTheIdentifierWhenNoNameIsUsable(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Disposition' => 'attachment; filename="..."'], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID)
        );

        self::assertSame('2401.01234v2.pdf', $source->fetch(self::DOCID)?->fileName);
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

        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame('manuscript.txt.pdf', $file->fileName);
        self::assertSame('application/pdf', $file->mimeType);
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
        self::assertInstanceOf(ManuscriptFile::class, $file);
        self::assertSame(self::PDF, $file->stream->getContents());
        self::assertSame(strlen(self::PDF), $file->size);
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
     * @dataProvider malformedLocationProvider
     */
    public function testReturnsNullOnAMalformedRedirect(string $location): void
    {
        $source = $this->makeSource(
            [new Response(302, ['Location' => $location])],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertCount(1, $this->history);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedLocationProvider(): array
    {
        return [
            'no host' => ['http://'],
            'port out of range' => ['http://hal.science:70000/document'],
        ];
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
     * @return array<int, array{int}> keyed by status: PHP casts the numeric string keys to integers
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

    /**
     * @dataProvider metadataInsteadOfAFileProvider
     */
    public function testReturnsNullWhenTheRepositoryAnswersWithMetadataOrAnErrorPage(string $contentType, string $body): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Type' => $contentType], $body)],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $this->makeMainFile('https://baobab.example.org/api/records/1/files/paper.pdf', 'paper.pdf')
        );

        self::assertNull($source->fetch(self::DOCID));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function metadataInsteadOfAFileProvider(): array
    {
        return [
            'file metadata (InvenioRDM links.self)' => ['application/json', '{"key": "paper.pdf", "size": 1234, "links": {}}'],
            'error page sniffed as plain text' => ['text/html; charset=utf-8', 'Service temporarily closed, come back later.'],
        ];
    }

    public function testKeepsAPlainTextManuscript(): void
    {
        $source = $this->makeSource(
            [new Response(200, ['Content-Type' => 'text/plain'], 'Abstract. We prove that...')],
            $this->makePaper((int) Episciences_Repositories::ZENODO_REPO_ID),
            $this->makeMainFile('https://zenodo.org/api/records/1/files/paper.txt/content', 'paper.txt', 'txt')
        );

        self::assertSame('text/plain', $source->fetch(self::DOCID)?->mimeType);
    }

    /**
     * @dataProvider permanentTransferErrorProvider
     */
    public function testReturnsNullOnAPermanentTransferError(int $errno): void
    {
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            handler: static fn(RequestInterface $request): never => throw new RequestException(
                'cURL error ' . $errno,
                $request,
                null,
                null,
                ['errno' => $errno]
            )
        );

        self::assertNull($source->fetch(self::DOCID));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function permanentTransferErrorProvider(): array
    {
        return ['certificate not verified' => [60], 'unsupported protocol' => [1]];
    }

    public function testReturnsNullAfterTooManyRedirects(): void
    {
        $redirects = array_fill(0, RepositoryManuscriptSource::MAX_REDIRECTS + 1, new Response(302, ['Location' => 'https://hal.science/loop']));
        $source = $this->makeSource($redirects, $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID));

        self::assertNull($source->fetch(self::DOCID));
        self::assertCount(RepositoryManuscriptSource::MAX_REDIRECTS + 1, $this->history);
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
     * @return array<int, array{int}> keyed by status: PHP casts the numeric string keys to integers
     */
    public static function transientStatusProvider(): array
    {
        return ['408' => [408], '425' => [425], '429' => [429], '500' => [500], '503' => [503]];
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

    public function testTheSinkRefusesTheWriteCrossingTheLimitSoThatCurlAbortsTheTransfer(): void
    {
        $writes = [];
        // Stands for the cURL handler: a short write makes cURL abort the transfer with CURLE_WRITE_ERROR (23)
        $handler = static function (RequestInterface $request, array $options) use (&$writes): never {
            foreach (['0123456789', 'abcdefghij', 'klmnopqrst'] as $chunk) {
                $written = $options['sink']->write($chunk);
                $writes[] = $written;

                if ($written !== strlen($chunk)) {
                    throw new RequestException('cURL error 23: Failure writing output to destination', $request, null, null, ['errno' => 23]);
                }
            }

            self::fail('The transfer should have been aborted');
        };
        $source = $this->makeSource([], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID), maxBytes: 15, handler: $handler);

        try {
            $source->fetch(self::DOCID);
            self::fail('An exception was expected');
        } catch (ManuscriptTooLargeException $e) {
            self::assertSame([10, 0], $writes, 'the write crossing the limit is refused, nothing after it is downloaded');
            self::assertSame(20, $e->getObservedSize());
        }
    }

    public function testEveryRedirectHopGetsOnlyTheTimeLeft(): void
    {
        $source = $this->makeSource(
            [new Response(302, ['Location' => 'https://hal.science/final.pdf']), new Response(200, [], self::PDF)],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        $source->fetch(self::DOCID);

        self::assertCount(2, $this->history);
        self::assertLessThanOrEqual(RepositoryManuscriptSource::TIMEOUT, $this->history[0]['options']['timeout']);
        self::assertLessThanOrEqual($this->history[0]['options']['timeout'], $this->history[1]['options']['timeout']);
    }

    // -------------------------------------------------------------------------
    // SSRF guard
    // -------------------------------------------------------------------------

    public function testPinsTheRequestToTheCheckedAddress(): void
    {
        $source = $this->makeSource([new Response(200, [], self::PDF)], $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID));

        $source->fetch(self::DOCID);

        $options = $this->history[0]['options'];
        self::assertSame(['arxiv.org:443:' . self::PUBLIC_IP], $options['curl'][CURLOPT_RESOLVE]);
        self::assertSame('v4', $options['force_ip_resolve']);
    }

    /**
     * @dataProvider nonPublicAddressProvider
     */
    public function testRefusesAHostResolvingToANonPublicAddress(string $address): void
    {
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            hostResolver: static fn(string $host): array => [$address]
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonPublicAddressProvider(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private' => ['10.0.0.5'],
            'link-local metadata' => ['169.254.169.254'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'IPv6 loopback' => ['::1'],
        ];
    }

    public function testRefusesAHostWhenOneOfItsAddressesIsNotPublic(): void
    {
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            hostResolver: static fn(string $host): array => [self::PUBLIC_IP, '192.168.1.1']
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
    }

    public function testRefusesARedirectToAnInternalAddressLiteral(): void
    {
        $source = $this->makeSource(
            [new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertCount(1, $this->history);
    }

    public function testRefusesARedirectToAnIpv6Literal(): void
    {
        $source = $this->makeSource(
            [new Response(302, ['Location' => 'http://[::1]:8080/admin'])],
            $this->makePaper((int) Episciences_Repositories::HAL_REPO_ID)
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertCount(1, $this->history);
    }

    public function testThrowsARetryableExceptionWhenTheHostLookupFails(): void
    {
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            hostResolver: static fn(string $host): ?array => null
        );

        $this->expectException(ManuscriptTemporarilyUnavailableException::class);
        $source->fetch(self::DOCID);
    }

    public function testReturnsNullWhenTheHostHasNoIpv4Address(): void
    {
        $source = $this->makeSource(
            [],
            $this->makePaper((int) Episciences_Repositories::ARXIV_REPO_ID),
            hostResolver: static fn(string $host): array => []
        );

        self::assertNull($source->fetch(self::DOCID));
        self::assertSame([], $this->history);
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
        int $maxBytes = ManuscriptFile::MAX_SIZE,
        string $userAgent = '',
        ?callable $handler = null,
        ?\Closure $hostResolver = null,
        ?string $dataDescriptorPath = null
    ): RepositoryManuscriptSource {
        $stack = HandlerStack::create($handler ?? new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new RepositoryManuscriptSource(
            new Client(['handler' => $stack]),
            new NullLogger(),
            static fn(int $docId): ?Episciences_Paper => $paper,
            static fn(Episciences_Paper $p): ?Episciences_Paper_File => $mainFile,
            $maxBytes,
            $userAgent,
            new PublicAddressGuard($hostResolver ?? static fn(string $host): array => [self::PUBLIC_IP]),
            static fn(Episciences_Paper $p): ?string => $dataDescriptorPath
        );
    }

    private function tempDir(): string
    {
        if ($this->tempDir === null) {
            $this->tempDir = sys_get_temp_dir() . '/manuscript-source-test-' . bin2hex(random_bytes(6));
            mkdir($this->tempDir);
        }

        return $this->tempDir;
    }

    private function writeTempFile(string $name, string $content): string
    {
        $path = $this->tempDir() . '/' . $name;
        file_put_contents($path, $content);

        return $path;
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
