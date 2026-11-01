<?php

namespace unit\library\Episciences\Repositories;

use Ccsd_Error;
use Episciences_Repositories_Common;
use Episciences_Repositories_Dspace_Hooks;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Unit tests for Episciences_Repositories_Dspace_Hooks.
 *
 * All tests are DB-free: only pure static methods tested via direct calls or ReflectionMethod.
 *
 * @covers Episciences_Repositories_Dspace_Hooks
 */
final class Episciences_Repositories_Dspace_HooksTest extends TestCase
{
    // =========================================================================
    // hookCleanIdentifiers()
    // =========================================================================

    /**
     * When the identifier contains the HDL URL prefix, the prefix is stripped
     * and the bare handle is returned under META_IDENTIFIER.
     */
    public function testHookCleanIdentifiersRemovesHdlUrl(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookCleanIdentifiers(
            ['id' => 'https://hdl.handle.net/1822/79894']
        );

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894'],
            $result
        );
    }

    /**
     * When the identifier does not contain the HDL URL prefix, the raw value
     * is returned unchanged under META_IDENTIFIER.
     */
    public function testHookCleanIdentifiersRawId(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookCleanIdentifiers(
            ['id' => '1822/79894']
        );

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894'],
            $result
        );
    }

    // =========================================================================
    // hookIsRequiredVersion()
    // =========================================================================

    /**
     * hookIsRequiredVersion() must return ['result' => false].
     */
    public function testHookIsRequiredVersionReturnsFalse(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookIsRequiredVersion();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    /**
     * hookIsIdentifierCommonToAllVersions() must return ['result' => false].
     */
    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookIsIdentifierCommonToAllVersions();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    /**
     * When no META_IDENTIFIER key is present in params, hookVersion() returns [].
     */
    public function testHookVersionNoIdentifier(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion([]);

        self::assertSame([], $result);
    }

    /**
     * When the identifier does not contain a dotted version segment (e.g. '1822/79894'),
     * getVersionFromIdentifier() finds no match and falls back to '1'.
     */
    public function testHookVersionWithIdentifierNoVersion(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894']
        );

        self::assertSame(['version' => '1'], $result);
    }

    /**
     * When the identifier contains a dotted version segment (e.g. '1822/79894.3'),
     * getVersionFromIdentifier() extracts the part after the dot ('3').
     */
    public function testHookVersionWithIdentifierDottedVersion(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894.3']
        );

        self::assertSame(['version' => '3'], $result);
    }

    // =========================================================================
    // firstXPathValue() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * firstXPathValue() returns the string value of the first matching node.
     */
    public function testFirstXPathValueMatch(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'firstXPathValue');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?><root><child>hello</child></root>'
        );

        $result = $method->invoke(null, $xml, '//child');

        self::assertSame('hello', $result);
    }

    /**
     * firstXPathValue() returns an empty string when xpath finds no match.
     */
    public function testFirstXPathValueNoMatch(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'firstXPathValue');
        $method->setAccessible(true);

        $xml = simplexml_load_string('<?xml version="1.0"?><root></root>');

        $result = $method->invoke(null, $xml, '//nonexistent');

        self::assertSame('', $result);
    }

    // =========================================================================
    // extractLanguage() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractLanguage() returns a known 2-letter language code when a valid
     * dc:language element is present.
     */
    public function testExtractLanguage2Letter(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLanguage');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:language>en</dc:language>'
            . '</root>'
        );
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $result = $method->invoke(null, $xml);

        self::assertSame('en', $result);
    }

    /**
     * extractLanguage() falls back to 'en' when the language name is unknown
     * (convertTo2LetterCode() returns null for unrecognised language names longer than 3 chars,
     * which triggers Translations::findLanguageCodeByLanguageName() returning null).
     */
    public function testExtractLanguageUnknownFallbacksToEn(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLanguage');
        $method->setAccessible(true);

        // Use a 4+ char unknown name — triggers Translations::findLanguageCodeByLanguageName()
        // which returns null for unknown names, causing convertTo2LetterCode() to return null,
        // and extractLanguage() to fall back to 'en' via ?? 'en'.
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:language>unknownlanguage</dc:language>'
            . '</root>'
        );
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $result = $method->invoke(null, $xml);

        self::assertSame('en', $result);
    }

    // =========================================================================
    // extractLicense() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractLicense() returns the rightsURI attribute value when present.
     */
    public function testExtractLicenseWithUri(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLicense');
        $method->setAccessible(true);

        $xmlString = '<?xml version="1.0"?>'
            . '<root xmlns:datacite="http://datacite.org/schema/kernel-4">'
            . '<datacite:rightsList>'
            . '<datacite:rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</datacite:rights>'
            . '</datacite:rightsList>'
            . '</root>';

        $xml = simplexml_load_string($xmlString);
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $xml);

        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $result);
    }

    /**
     * extractLicense() returns an empty string when there is no datacite:rights node.
     */
    public function testExtractLicenseEmpty(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLicense');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:datacite="http://datacite.org/schema/kernel-4"></root>'
        );
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $xml);

        self::assertSame('', $result);
    }

    // =========================================================================
    // buildDataForOaiDc() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * buildDataForOaiDc() returns an array containing the expected keys.
     */
    public function testBuildDataForOaiDc(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'buildDataForOaiDc');
        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            [['value' => 'Test Title', 'language' => 'en']],  // $titles
            ['Doe, John'],                                       // $creatorsDc
            [['value' => 'subject1', 'language' => 'en']],      // $subjects
            [['value' => 'A description', 'language' => 'en']], // $descriptions
            'en',                                                // $language
            'article',                                           // $dcType
            '2024-01-15',                                        // $datestamp
            ['oai:repositorium.uminho.pt:1822/79894'],           // $identifiers
            'https://creativecommons.org/licenses/by/4.0/',      // $license
            'Test Publisher'                                     // $publisher
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('title', $result);
        self::assertArrayHasKey('creator', $result);
        self::assertArrayHasKey('subject', $result);
        self::assertArrayHasKey('description', $result);
        self::assertArrayHasKey('language', $result);
        self::assertArrayHasKey('type', $result);
        self::assertArrayHasKey('date', $result);
        self::assertArrayHasKey('identifier', $result);
        self::assertArrayHasKey('rights', $result);
        self::assertArrayHasKey('publisher', $result);
    }

    // =========================================================================
    // extractMetadata() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractMetadata() returns [] when the XML string is invalid.
     */
    public function testExtractMetadataInvalidXml(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        // Suppress libxml warnings emitted by simplexml_load_string() on invalid input
        set_error_handler(static function () {
            return true;
        });

        try {
            $result = $method->invoke(null, 'not xml');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $result);
    }

    /**
     * extractMetadata() returns a non-empty array containing TO_COMPILE_OAI_DC
     * when fed a minimal but valid OAI-PMH record with DataCite metadata.
     * @noinspection SqlNoDataSourceInspection
     */
    public function testExtractMetadataValidXml(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repositorium.uminho.pt:1822/79894</identifier>
    <datestamp>2024-01-15</datestamp>
  </header>
  <metadata>
    <datacite:titles>
      <datacite:title xml:lang="en">Test Title</datacite:title>
    </datacite:titles>
    <datacite:creators>
      <datacite:creator>
        <datacite:creatorName>Doe, John</datacite:creatorName>
      </datacite:creator>
    </datacite:creators>
    <dc:language>en</dc:language>
    <datacite:rightsList>
      <datacite:rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</datacite:rights>
    </datacite:rightsList>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertArrayHasKey(Episciences_Repositories_Common::TO_COMPILE_OAI_DC, $result);
    }

    // =========================================================================
    // extractMetadata() — datestamp fallback and enrichment sections
    // =========================================================================

    /**
     * When the header datestamp is empty, extractMetadata() falls back to
     * the datacite:date[@dateType="Issued"] element.
     */
    public function testExtractMetadataDatestampFallback(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repo:1822/99999</identifier>
    <datestamp></datestamp>
  </header>
  <metadata>
    <datacite:dates>
      <datacite:date dateType="Issued">2023-05-20</datacite:date>
    </datacite:dates>
    <dc:language>en</dc:language>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        $body = $result[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'] ?? [];
        self::assertSame('2023-05-20', $body['date'] ?? '');
    }

    /**
     * extractMetadata() exposes oaire:file nodes under ENRICHMENT > FILES.
     */
    public function testExtractMetadataWithFiles(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repo:1822/11111</identifier>
    <datestamp>2024-01-01</datestamp>
  </header>
  <metadata>
    <dc:language>en</dc:language>
    <oaire:file mimeType="application/pdf" objectType="fulltext" accessRightsURI="http://purl.org/coar/access_right/c_abf2">https://example.com/paper.pdf</oaire:file>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        $enrichment = $result[Episciences_Repositories_Common::ENRICHMENT] ?? [];
        self::assertArrayHasKey(Episciences_Repositories_Common::FILES, $enrichment);
        self::assertNotEmpty($enrichment[Episciences_Repositories_Common::FILES]);
        self::assertSame('application/pdf', $enrichment[Episciences_Repositories_Common::FILES][0]['mimeType']);
    }

    /**
     * extractMetadata() exposes related identifiers under ENRICHMENT > RELATED_IDENTIFIERS.
     */
    public function testExtractMetadataWithRelatedIdentifiers(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repo:1822/22222</identifier>
    <datestamp>2024-02-01</datestamp>
  </header>
  <metadata>
    <dc:language>en</dc:language>
    <datacite:relatedIdentifiers>
      <datacite:relatedIdentifier relationType="IsVersionOf" relatedIdentifierType="DOI">10.5281/zenodo.99999</datacite:relatedIdentifier>
    </datacite:relatedIdentifiers>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        $enrichment = $result[Episciences_Repositories_Common::ENRICHMENT] ?? [];
        self::assertArrayHasKey(Episciences_Repositories_Common::RELATED_IDENTIFIERS, $enrichment);
        self::assertNotEmpty($enrichment[Episciences_Repositories_Common::RELATED_IDENTIFIERS]);
        self::assertSame('10.5281/zenodo.99999', $enrichment[Episciences_Repositories_Common::RELATED_IDENTIFIERS][0]['identifier']);
    }

    /**
     * extractMetadata() populates the 'creator' field in the OAI-DC body
     * when datacite:creators are present.
     */
    public function testExtractMetadataPopulatesCreator(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repo:1822/33333</identifier>
    <datestamp>2024-03-01</datestamp>
  </header>
  <metadata>
    <datacite:creators>
      <datacite:creator>
        <datacite:creatorName>Dupont, Marie</datacite:creatorName>
      </datacite:creator>
    </datacite:creators>
    <dc:language>fr</dc:language>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        $body = $result[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'] ?? [];
        self::assertContains('Dupont, Marie', $body['creator'] ?? []);
    }

    // =========================================================================
    // hookFilesProcessing() — record already loaded by the caller
    // =========================================================================

    /**
     * A record already loaded (Episciences_PapersManager::updateRecordData()) is used, not requested again:
     * the unknown repository id would fail if hookApiRecords() were called.
     */
    public function testHookFilesProcessingReusesTheLoadedRecord(): void
    {
        // A warning (undefined repository) fails the test instead of letting the request go out
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \ErrorException($errstr, 0, $errno);
        });

        try {
            $result = Episciences_Repositories_Dspace_Hooks::hookFilesProcessing([
                'docId' => 1,
                'repoId' => -1,
                'identifier' => '1822/79894',
                'response' => [Episciences_Repositories_Common::ENRICHMENT => [Episciences_Repositories_Common::FILES => []]],
            ]);
        } finally {
            restore_error_handler();
        }

        self::assertSame(0, $result['affectedRows']);
    }

    // =========================================================================
    // hookFilesProcessing() — a file that cannot be loaded
    // =========================================================================

    /**
     * A paper with stored files (metadata update): a partial list would delete the others, they are kept
     */
    public function testStoredFilesAreKeptWhenAFileCannotBeLoaded(): void
    {
        $queries = $this->withDatabase(storedFiles: true, run: function (): void {
            try {
                $this->processFiles([new Response(200, [], self::BITSTREAM_JSON), new Response(503)]);
                self::fail('the failure is thrown');
            } catch (Ccsd_Error $e) {
                self::assertStringContainsString('Files not updated: ' . self::OTHER_BITSTREAM_URL, $e->getMessage());
                self::assertStringContainsString('ServerException', $e->getMessage());
            }
        });

        self::assertSame([], $this->writes($queries), 'nothing written: the stored files are kept');
    }

    /**
     * A paper without stored files (initial submission): the files available are stored, the failure is reported
     */
    public function testAvailableFilesAreStoredWhenNoneAreAndAFileCannotBeLoaded(): void
    {
        $queries = $this->withDatabase(storedFiles: false, run: function (): void {
            try {
                $this->processFiles([new Response(200, [], self::BITSTREAM_JSON), new Response(429)]);
                self::fail('the failure is thrown');
            } catch (Ccsd_Error $e) {
                self::assertStringContainsString('Files not stored: ' . self::OTHER_BITSTREAM_URL, $e->getMessage());
            }
        });

        $writes = $this->writes($queries);
        self::assertCount(1, $writes);
        self::assertStringContainsString('article.pdf', $writes[0]);
    }

    /**
     * One client for the files of the record: both requests go through the injected handler
     */
    public function testFilesAreStoredWhenEveryFileIsLoaded(): void
    {
        $queries = $this->withDatabase(storedFiles: true, run: function (): void {
            $result = $this->processFiles([
                new Response(200, [], self::BITSTREAM_JSON),
                new Response(200, [], '{"name": "data.csv", "sizeBytes": 3, "type": "csv", "checkSum": {"checkSumAlgorithm": "MD5", "value": "def"}}'),
            ]);
            self::assertSame(1, $result['affectedRows']);
        });

        $writes = $this->writes($queries);
        self::assertCount(1, $writes);
        self::assertStringContainsString('article.pdf', $writes[0]);
        self::assertStringContainsString('data.csv', $writes[0]);
    }

    private const BITSTREAM_JSON = '{"name": "article.pdf", "sizeBytes": 12, "type": "pdf", "checkSum": {"checkSumAlgorithm": "MD5", "value": "abc"}}';
    private const OTHER_BITSTREAM_URL = 'https://repositorium.test/bitstreams/71ba5e92-bab4-4901-aa31-9c68d2560511/download';

    /**
     * @param list<Response> $responses one per file
     * @return array<string, mixed>
     */
    private function processFiles(array $responses): array
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        // The REST API failure is logged: not displayed by the test
        $previousLevel = error_reporting(0);
        try {
            return Episciences_Repositories_Dspace_Hooks::hookFilesProcessing([
                'docId' => 1234,
                'repoId' => 21,
                'response' => [Episciences_Repositories_Common::ENRICHMENT => [Episciences_Repositories_Common::FILES => [
                    ['url' => self::BITSTREAM_URL, 'mimeType' => 'application/pdf'],
                    ['url' => self::OTHER_BITSTREAM_URL, 'mimeType' => 'text/csv'],
                ]]],
            ], $client);
        } finally {
            error_reporting($previousLevel);
        }
    }

    /**
     * Runs $run with a database that has (or not) files stored for the paper
     *
     * @return list<string> the statements sent to the database
     */
    private function withDatabase(bool $storedFiles, callable $run): array
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $queries = [];
        $db = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['query', 'fetchAssoc', 'beginTransaction', 'commit', 'rollBack'])
            ->getMock();
        $db->method('fetchAssoc')->willReturn([]);
        $db->method('query')->willReturnCallback(function (mixed $sql) use (&$queries, $storedFiles): Zend_Db_Statement_Interface {
            $queries[] = (string)$sql;
            $statement = $this->createMock(Zend_Db_Statement_Interface::class);
            $statement->method('fetchColumn')->willReturn($storedFiles ? '1' : false);
            $statement->method('rowCount')->willReturn(1);
            return $statement;
        });

        Zend_Db_Table_Abstract::setDefaultAdapter($db);
        try {
            $run();
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
        }

        return $queries;
    }

    /**
     * @param list<string> $queries
     * @return list<string>
     */
    private function writes(array $queries): array
    {
        return array_values(array_filter($queries, static fn(string $sql): bool => !str_starts_with($sql, 'SELECT')));
    }

    // =========================================================================
    // getFileInfoFromApi()
    // =========================================================================

    private const BITSTREAM_URL = 'https://repositorium.test/bitstreams/61ba5e92-bab4-4901-aa31-9c68d2560510/download';

    public function testFileInfoIsTheBitstream(): void
    {
        $info = $this->fileInfo(new Response(200, [], '{"name": "article.pdf", "sizeBytes": 12}'));

        self::assertSame(['name' => 'article.pdf', 'sizeBytes' => 12], $info);
    }

    /**
     * A restricted (under embargo), removed or refused file is not stored, as before: the other files are.
     * The same request would fail again: thrown, it would prevent any update of the stored files
     *
     * @dataProvider skippedClientErrorProvider
     */
    public function testClientErrorFileIsSkipped(Response $response): void
    {
        self::assertNull($this->fileInfo($response));
    }

    /**
     * @return array<string, array{Response}>
     */
    public static function skippedClientErrorProvider(): array
    {
        return [
            'bad request' => [new Response(400)],
            'unauthorized' => [new Response(401, [], '{"status": 401}')],
            'forbidden' => [new Response(403)],
            'not found' => [new Response(404)],
            'method not allowed' => [new Response(405)],
            'gone' => [new Response(410)],
        ];
    }

    /**
     * A throttled or timed out request says nothing about the file: thrown, as a server error
     */
    public function testThrottledRequestIsThrown(): void
    {
        $this->expectException(ClientException::class);

        $this->fileInfo(new Response(429));
    }

    /**
     * Thrown, so that the files step fails instead of storing a partial list of files (which deletes the others)
     */
    public function testServerErrorIsThrown(): void
    {
        $this->expectException(ServerException::class);

        $this->fileInfo(new Response(503));
    }

    public function testNetworkFailureIsThrown(): void
    {
        $this->expectException(ConnectException::class);

        $this->fileInfo(new ConnectException('timeout', new Request('GET', self::BITSTREAM_URL)));
    }

    public function testUnexpectedPayloadIsThrown(): void
    {
        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('Unexpected bitstream response');

        $this->fileInfo(new Response(200, [], 'not json'));
    }

    private function fileInfo(Response|\Throwable $response): ?array
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'getFileInfoFromApi');
        $method->setAccessible(true);

        // The REST API failure is logged: not displayed by the test
        $previousLevel = error_reporting(0);
        try {
            return $method->invoke(null, self::BITSTREAM_URL, $client);
        } finally {
            error_reporting($previousLevel);
        }
    }
}
