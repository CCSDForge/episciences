<?php

namespace unit\library\Episciences\submit;

use Ccsd_Error;
use Ccsd_Oai_ServerErrorException;
use Episciences_Oai_Client;
use Episciences_Repositories_ArXiv_DataciteFallback;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Zend_Exception;
use Zend_Registry;

/**
 * Tests for Episciences_Submit.
 *
 * Two groups:
 *
 * GROUP A — Integration tests (network, DB): testGetExist*, testZenodo*
 *   These hit real APIs and are skipped gracefully on Zend_Exception.
 *
 * GROUP B — Pure unit tests (no DB, no network):
 *   extractVersionsFromArXivRaw(), processAndPrepareType(), addIfNotExists(),
 *   normalizeSubmissionParameters(), isHalNotice(), extractEmbargoDate().
 *
 * Bugs documented:
 *   S1 — normalizeSubmissionParameters(): $post['h_doc'] accessed without isset (lines 2860, 2872)
 *   S2 — normalizeSubmissionParameters(): $post['h_repoId']/$post['h_version'] without isset (lines 2856-2857)
 *   S3 — extractVersionsFromArXivRaw(): $rawRecord['metadata']['arXivRaw']['version'] accessed directly
 *   S4 — saveTmpVersion(): simplexml_load_string() without libxml error buffer (line 488)
 *
 * @covers Episciences_Submit
 */
class SubmitTest extends TestCase
{
    public const IS_ALREADY_EXISTS = 2;

    // =========================================================================
    // GROUP A — Integration tests
    // =========================================================================

    public function testGetExistArxivDocWithoutMangeNewVersionErrors(): void
    {
        $repoId = 2;
        $identifier = '2208.07775';
        $version = 1;
        $rvId = 8;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version, null, false, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }
    }

    public function testGetExistArxivDocWithMangeNewVersionErrors(): void
    {
        $repoId = 2;
        $identifier = '2208.07775';
        $version = 1;
        $rvId = 8;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version, null, true, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }
    }

    public function testZenodoDocWithMangeNewVersionErrors(): void
    {
        $repoId = 4;
        $identifier = '10.5281/zenodo.6078767';
        $version = null;
        $rvId = 23;

        try {
            $result = Episciences_Submit::getDoc($repoId, $identifier, $version, null, false, $rvId);
            self::assertIsArray($result);
            if (isset($result['record'])) {
                self::assertIsString($result['record']);
            }
            self::assertIsInt($result['status']);
            // Accept either successful retrieval (1) or already exists (2) or not found (0)
            self::assertContains($result['status'], [0, 1, self::IS_ALREADY_EXISTS]);
        } catch (Zend_Exception $e) {
            $this->expectExceptionObject($e);
        }
    }

    // =========================================================================
    // GROUP B — Pure unit tests
    // =========================================================================

    // -------------------------------------------------------------------------
    // extractVersionsFromArXivRaw()
    // -------------------------------------------------------------------------

    /**
     * Single version stored as a scalar under key 'version' (arXiv compact format).
     */
    public function testExtractVersionsFromArXivRawSingleScalarVersion(): void
    {
        $raw = ['metadata' => ['arXivRaw' => ['version' => ['version' => 'v3']]]];
        $result = Episciences_Submit::extractVersionsFromArXivRaw($raw);
        self::assertSame(['3'], $result);
    }

    /**
     * Multiple versions stored as an indexed array of arrays.
     */
    public function testExtractVersionsFromArXivRawMultipleArrayVersions(): void
    {
        $raw = [
            'metadata' => [
                'arXivRaw' => [
                    'version' => [
                        ['version' => 'v1'],
                        ['version' => 'v2'],
                        ['version' => 'v3'],
                    ],
                ],
            ],
        ];
        $result = Episciences_Submit::extractVersionsFromArXivRaw($raw);
        self::assertSame(['1', '2', '3'], $result);
    }

    /**
     * Empty version list returns an empty array.
     */
    public function testExtractVersionsFromArXivRawEmptyVersionList(): void
    {
        $raw = ['metadata' => ['arXivRaw' => ['version' => []]]];
        $result = Episciences_Submit::extractVersionsFromArXivRaw($raw);
        self::assertSame([], $result);
    }

    /**
     * Fix S3: isset guard added before accessing nested structure.
     * Missing keys now return [] cleanly without any warning.
     */
    public function testExtractVersionsFromArXivRawMissingKeyReturnsEmpty(): void
    {
        self::assertSame([], Episciences_Submit::extractVersionsFromArXivRaw([]));
        self::assertSame([], Episciences_Submit::extractVersionsFromArXivRaw(['metadata' => []]));
        self::assertSame([], Episciences_Submit::extractVersionsFromArXivRaw(['metadata' => ['arXivRaw' => []]]));
    }

    // -------------------------------------------------------------------------
    // processAndPrepareType()
    // -------------------------------------------------------------------------

    public function testProcessAndPrepareTypeWithNull(): void
    {
        self::assertSame([], Episciences_Submit::processAndPrepareType(null));
    }

    public function testProcessAndPrepareTypeWithEmptyString(): void
    {
        self::assertSame([], Episciences_Submit::processAndPrepareType(''));
    }

    public function testProcessAndPrepareTypeWithEmptyArray(): void
    {
        self::assertSame([], Episciences_Submit::processAndPrepareType([]));
    }

    public function testProcessAndPrepareTypeWithPlainArticle(): void
    {
        $result = Episciences_Submit::processAndPrepareType('article');
        self::assertSame([Episciences_Paper::TITLE_TYPE => 'article'], $result);
    }

    public function testProcessAndPrepareTypeStripsInfoEuRepoPrefix(): void
    {
        $result = Episciences_Submit::processAndPrepareType('info:eu-repo/semantics/article');
        self::assertSame([Episciences_Paper::TITLE_TYPE => 'article'], $result);
    }

    public function testProcessAndPrepareTypeHandlesJournalArticleTitle(): void
    {
        // 'Journal Article' → lower → 'journal article' → strip spaces → 'journalarticle' → map to 'article'
        $result = Episciences_Submit::processAndPrepareType('Journal Article');
        self::assertSame([Episciences_Paper::TITLE_TYPE => Episciences_Paper::ARTICLE_TYPE_TITLE], $result);
    }

    public function testProcessAndPrepareTypeHandlesWorkingPaper(): void
    {
        $result = Episciences_Submit::processAndPrepareType('working paper');
        self::assertSame([Episciences_Paper::TITLE_TYPE => Episciences_Paper::ARTICLE_TYPE_TITLE], $result);
    }

    public function testProcessAndPrepareTypeNormalizesConferencePaper(): void
    {
        $result = Episciences_Submit::processAndPrepareType('conference paper');
        self::assertSame([Episciences_Paper::TITLE_TYPE => Episciences_Paper::CONFERENCE_TYPE], $result);
    }

    public function testProcessAndPrepareTypeMapsPreprints(): void
    {
        // 'preprint' is in PREPRINT_TYPES → DEFAULT_TYPE_TITLE = 'preprint'
        $result = Episciences_Submit::processAndPrepareType('preprint');
        self::assertSame([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE], $result);
    }

    public function testProcessAndPrepareTypePassesThroughUnknownType(): void
    {
        $result = Episciences_Submit::processAndPrepareType('software');
        self::assertSame([Episciences_Paper::TITLE_TYPE => 'software'], $result);
    }

    public function testProcessAndPrepareTypeWithArrayFallsBackToFirstElement(): void
    {
        // Array with non-OTHER first element → uses first element
        $result = Episciences_Submit::processAndPrepareType(['article', 'ignored']);
        self::assertSame([Episciences_Paper::TITLE_TYPE => 'article'], $result);
    }

    public function testProcessAndPrepareTypeWithOtherTypeFallsBackToLast(): void
    {
        // ['other', 'dataset'] → first is OTHER_TYPE → use last element
        $result = Episciences_Submit::processAndPrepareType([Episciences_Paper::OTHER_TYPE, 'dataset']);
        self::assertSame([Episciences_Paper::TITLE_TYPE => 'dataset'], $result);
    }

    // -------------------------------------------------------------------------
    // addIfNotExists()
    // -------------------------------------------------------------------------

    public function testAddIfNotExistsWithEmptyInput(): void
    {
        $output = ['uid1' => 'existing'];
        Episciences_Submit::addIfNotExists([], $output);
        self::assertSame(['uid1' => 'existing'], $output);
    }

    public function testAddIfNotExistsAddsNewKey(): void
    {
        $output = ['uid1' => 'existing'];
        Episciences_Submit::addIfNotExists(['uid2' => 'new'], $output);
        self::assertArrayHasKey('uid2', $output);
        self::assertSame('new', $output['uid2']);
    }

    public function testAddIfNotExistsDoesNotOverwriteExistingKey(): void
    {
        $output = ['uid1' => 'existing'];
        Episciences_Submit::addIfNotExists(['uid1' => 'override'], $output);
        self::assertSame('existing', $output['uid1']);
    }

    public function testAddIfNotExistsCallsSetTagWhenTagIsNonEmpty(): void
    {
        $mock = new class {
            public string $tag = '';
            public function setTag(string $t): void
            {
                $this->tag = $t;
            }
        };

        $output = [];
        Episciences_Submit::addIfNotExists(['uid1' => $mock], $output, 'myTag');
        self::assertSame('myTag', $mock->tag);
        self::assertSame($mock, $output['uid1']);
    }

    public function testAddIfNotExistsDoesNotCallSetTagWhenTagIsEmpty(): void
    {
        $mock = new class {
            public bool $called = false;
            public function setTag(string $t): void
            {
                $this->called = true;
            }
        };

        $output = [];
        Episciences_Submit::addIfNotExists(['uid1' => $mock], $output, '');
        self::assertFalse($mock->called);
    }

    public function testAddIfNotExistsDoesNotErrorOnObjectWithoutSetTag(): void
    {
        $obj = new \stdClass();
        $output = [];
        Episciences_Submit::addIfNotExists(['uid1' => $obj], $output, 'someTag');
        self::assertSame($obj, $output['uid1']);
    }

    // -------------------------------------------------------------------------
    // normalizeSubmissionParameters()
    // -------------------------------------------------------------------------

    public function testNormalizeSubmissionParametersHappyPath(): void
    {
        $post = [
            'h_repoId'  => '2',
            'h_version' => '1.5',
            'h_doc'     => 'hal-01234567',
            'search_doc' => [
                'docId'   => 'old-id',
                'repoId'  => '1',
                'version' => '1.0',
            ],
        ];

        Episciences_Submit::normalizeSubmissionParameters($post);

        self::assertSame(2, $post['h_repoId']);
        self::assertSame(1.5, $post['h_version']);
        // search_doc keys replaced from hidden fields
        self::assertSame('hal-01234567', $post['search_doc']['docId']);
        self::assertSame(2, $post['search_doc']['repoId']);
        self::assertSame(1.5, $post['search_doc']['version']);
        // h_* copies injected into search_doc
        self::assertSame('hal-01234567', $post['search_doc']['h_docId']);
        self::assertSame(1.5, $post['search_doc']['h_version']);
        self::assertSame(2, $post['search_doc']['h_repoId']);
    }

    public function testNormalizeSubmissionParametersWithNoSearchDocSubkeys(): void
    {
        // If search_doc sub-keys are absent, lines inside if-blocks are skipped
        $post = [
            'h_repoId'  => '3',
            'h_version' => '2.0',
            'h_doc'     => 'arxiv-12345',
            'search_doc' => [],
        ];

        Episciences_Submit::normalizeSubmissionParameters($post);

        self::assertSame(3, $post['h_repoId']);
        self::assertSame(2.0, $post['h_version']);
        self::assertArrayNotHasKey('docId', $post['search_doc']);
        self::assertSame('arxiv-12345', $post['search_doc']['h_docId']);
    }

    public function testNormalizeSubmissionParametersWithMissingSearchDoc(): void
    {
        // search_doc key absent but h_* keys present — the h_* copy lines at bottom
        // still reference $post[$key] creating it if missing
        $post = [
            'h_repoId'  => '1',
            'h_version' => '0.0',
            'h_doc'     => 'test',
        ];

        Episciences_Submit::normalizeSubmissionParameters($post);

        // h_* injected into search_doc even though search_doc was not in the original post
        self::assertSame('test', $post['search_doc']['h_docId']);
        self::assertSame(0.0, $post['search_doc']['h_version']);
        self::assertSame(1, $post['search_doc']['h_repoId']);
    }

    /**
     * Fix S1: $post['h_doc'] now uses ?? '' guard — missing key returns '' cleanly.
     */
    public function testNormalizeSubmissionParametersMissingHDocReturnsEmptyString(): void
    {
        $post = [
            'h_repoId'   => '1',
            'h_version'  => '1.0',
            // 'h_doc' intentionally missing
            'search_doc' => ['docId' => 'original'],
        ];

        Episciences_Submit::normalizeSubmissionParameters($post);
        self::assertSame('', $post['search_doc']['h_docId']);
        self::assertSame('', $post['search_doc']['docId']);
    }

    /**
     * Fix S2: $post['h_repoId'] and $post['h_version'] now use ?? 0 guard.
     * Missing keys default to 0 / 0.0 without emitting any warning.
     */
    public function testNormalizeSubmissionParametersMissingHRepoIdDefaultsToZero(): void
    {
        $post = [
            // 'h_repoId' intentionally missing
            'h_version'  => '1.0',
            'h_doc'      => 'test',
            'search_doc' => [],
        ];

        Episciences_Submit::normalizeSubmissionParameters($post);
        self::assertSame(0, $post['h_repoId']);
    }

    // -------------------------------------------------------------------------
    // buildCcsdErrorResult() — private, tested via ReflectionMethod
    // -------------------------------------------------------------------------

    public function testRepositoryTimeoutGivesAClearMessage(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'buildCcsdErrorResult');
        $method->setAccessible(true);

        $repoId = 2;
        $result = $method->invoke(null, new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, CURLE_OPERATION_TIMEDOUT), $repoId);

        self::assertSame(0, $result['status']);
        self::assertStringContainsString(
            sprintf('The open archive %s did not respond in time', Episciences_Repositories::getLabel($repoId)),
            $result['error']
        );
        self::assertStringContainsString('mailto:' . EPISCIENCES_SUPPORT, $result['error']);
        self::assertStringNotContainsString('%s', $result['error']);
    }

    public function testRepositoryTimeoutMessageIsFormattedWhenTheTranslatorIsMissing(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'buildCcsdErrorResult');
        $method->setAccessible(true);

        // Web context (not CLI) without a registered translator: the registry lookup throws
        $hadServerSoftware = array_key_exists('SERVER_SOFTWARE', $_SERVER);
        $previousServerSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        $hadTranslator = Zend_Registry::isRegistered('Zend_Translate');
        $previousTranslator = $hadTranslator ? Zend_Registry::get('Zend_Translate') : null;

        $_SERVER['SERVER_SOFTWARE'] = 'Apache';
        if ($hadTranslator) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }

        try {
            $repoId = 2;
            $result = $method->invoke(null, new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, CURLE_OPERATION_TIMEDOUT), $repoId);
        } finally {
            if ($hadServerSoftware) {
                $_SERVER['SERVER_SOFTWARE'] = $previousServerSoftware;
            } else {
                unset($_SERVER['SERVER_SOFTWARE']);
            }
            if ($hadTranslator) {
                Zend_Registry::set('Zend_Translate', $previousTranslator);
            }
        }

        self::assertStringContainsString(
            sprintf('The open archive %s did not respond in time', Episciences_Repositories::getLabel($repoId)),
            $result['error']
        );
        self::assertStringNotContainsString('%s', $result['error']);
    }

    // -------------------------------------------------------------------------
    // isHalNotice() — private, tested via ReflectionMethod
    // -------------------------------------------------------------------------

    public function testIsHalNoticeFalseWhenDocumentLinkPresent(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'isHalNotice');
        $method->setAccessible(true);

        $record = '<record><dcterms:identifier>https://hal.science/hal-01234567/document</dcterms:identifier></record>';
        // Pattern found → NOT a notice → returns false
        $result = $method->invoke(null, $record, 'hal-01234567');
        self::assertFalse($result);
    }

    public function testIsHalNoticeTrueWhenDocumentLinkAbsent(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'isHalNotice');
        $method->setAccessible(true);

        $record = '<record><dcterms:title>Some paper</dcterms:title></record>';
        // Pattern not found → IS a notice (no file) → returns true
        $result = $method->invoke(null, $record, 'hal-01234567');
        self::assertTrue($result);
    }

    public function testIsHalNoticeFalseWhenVersionedDocumentLinkPresent(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'isHalNotice');
        $method->setAccessible(true);

        $record = '<record><dcterms:identifier>https://hal.science/hal-01234567v2/document</dcterms:identifier></record>';
        $result = $method->invoke(null, $record, 'hal-01234567');
        self::assertFalse($result);
    }

    public function testIsHalNoticeUsesCustomFormat(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'isHalNotice');
        $method->setAccessible(true);

        $record = '<record><oai_dc:identifier>https://hal.science/hal-99999/document</oai_dc:identifier></record>';
        // Default format is 'dcterms' — record uses 'oai_dc', won't match → returns true (is a notice)
        $result = $method->invoke(null, $record, 'hal-99999', 'dcterms');
        self::assertTrue($result);

        // With correct format 'oai_dc' → matches → returns false (not a notice)
        $result = $method->invoke(null, $record, 'hal-99999', 'oai_dc');
        self::assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // extractEmbargoDate() — private, tested via ReflectionMethod
    // -------------------------------------------------------------------------

    public function testExtractEmbargoDateWithValidDate(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'extractEmbargoDate');
        $method->setAccessible(true);

        $record = '<record><dcterms:available>2025-06-15</dcterms:available></record>';
        $result = $method->invoke(null, $record);
        self::assertSame('2025-06-15', $result);
    }

    public function testExtractEmbargoDateWithoutAvailableTagReturnsToday(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'extractEmbargoDate');
        $method->setAccessible(true);

        $record = '<record><dcterms:title>No date here</dcterms:title></record>';
        $result = $method->invoke(null, $record);
        // No <dcterms:available> → falls back to today's date
        self::assertSame(date('Y-m-d'), $result);
    }

    public function testExtractEmbargoDateWithEmptyAvailableTagReturnsToday(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'extractEmbargoDate');
        $method->setAccessible(true);

        $record = '<record><dcterms:available></dcterms:available></record>';
        $result = $method->invoke(null, $record);
        self::assertSame(date('Y-m-d'), $result);
    }

    public function testExtractEmbargoDateReturnsFutureDateCorrectly(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'extractEmbargoDate');
        $method->setAccessible(true);

        $record = '<record><dcterms:available>2099-12-31</dcterms:available></record>';
        $result = $method->invoke(null, $record);
        self::assertSame('2099-12-31', $result);
    }

    // =========================================================================
    // extractVersionSuffix() — private, tested via ReflectionMethod
    // =========================================================================

    /**
     * @dataProvider versionSuffixProvider
     */
    public function testExtractVersionSuffix(string $id, ?float $version, string $expectedId, ?float $expectedVersion): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'extractVersionSuffix');
        $method->setAccessible(true);

        $method->invokeArgs(null, [&$id, &$version]);

        self::assertSame($expectedId, $id);
        self::assertSame($expectedVersion, $version);
    }

    public static function versionSuffixProvider(): array
    {
        return [
            'HAL with suffix, no version' => ['hal-01234567v4', null, 'hal-01234567', 4.0],
            'HAL with suffix overrides version field' => ['hal-01234567v4', 1.0, 'hal-01234567', 4.0],
            'HAL without suffix keeps version' => ['hal-01234567', 2.0, 'hal-01234567', 2.0],
            'arXiv new format with suffix' => ['2310.02192v12', 1.0, '2310.02192', 12.0],
            'arXiv old format with suffix' => ['hep-th/9704188v2', null, 'hep-th/9704188', 2.0],
            'arXiv without suffix' => ['2310.02192', null, '2310.02192', null],
            'bare "v4" is not an identifier' => ['v4', 1.0, 'v4', 1.0],
            'v without digits is kept' => ['hal-01234567v', 1.0, 'hal-01234567v', 1.0],
            'v0 is not a version' => ['hal-01234567v0', 1.0, 'hal-01234567v0', 1.0],
        ];
    }

    // =========================================================================
    // isVersionSuffixedRepository() — private, tested via ReflectionMethod
    // =========================================================================

    /**
     * @dataProvider versionSuffixedRepositoryProvider
     */
    public function testIsVersionSuffixedRepository(int $repoId, bool $expected): void
    {
        $sources = [
            1 => ['name' => 'HAL', 'type' => 'repository'],
            2 => ['name' => 'arXiv', 'type' => 'repository'],
            4 => ['name' => 'Zenodo', 'type' => 'repository'],
            98 => ['name' => 'ADspace', 'type' => 'dspace'],
            99 => ['name' => 'ADataverse', 'type' => 'dataverse'],
        ];

        $hadSources = Zend_Registry::isRegistered('metadataSources');
        $previousSources = $hadSources ? Zend_Registry::get('metadataSources') : null;
        $cache = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $cache->setAccessible(true);
        $previousCache = $cache->getValue();

        try {
            Zend_Registry::set('metadataSources', $sources);
            $cache->setValue(null, []);

            $method = new ReflectionMethod(Episciences_Submit::class, 'isVersionSuffixedRepository');
            $method->setAccessible(true);

            self::assertSame($expected, $method->invoke(null, $repoId));
        } finally {
            if ($hadSources) {
                Zend_Registry::set('metadataSources', $previousSources);
            } else {
                Zend_Registry::getInstance()->offsetUnset('metadataSources');
            }
            $cache->setValue(null, $previousCache);
        }
    }

    public static function versionSuffixedRepositoryProvider(): array
    {
        return [
            'HAL' => [1, true],
            'arXiv' => [2, true],
            'Zenodo' => [4, false],
            'DSpace' => [98, false],
            'Dataverse' => [99, false],
            'unknown repository' => [9999, false],
        ];
    }

    // =========================================================================
    // assertArxivVersionExists() — private, tested via ReflectionMethod
    // =========================================================================

    public function testAssertArxivVersionExistsSkipsWithoutOaiIdentifier(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'assertArxivVersionExists');
        $method->setAccessible(true);

        $oai = $this->createMock(Episciences_Oai_Client::class);
        $oai->expects(self::never())->method('getArXivRawRecord');

        // No OAI identifier template configured: nothing to check, no TypeError
        self::assertNull($method->invoke(null, $oai, null, 1));
    }

    // =========================================================================
    // arXiv: two OAI-PMH attempts, then the DataCite fallback (no network)
    // =========================================================================

    private const ARXIV_ID = '2602.07473';
    private const OAI_RECORD = '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
        . '<record><header><identifier>oai:arXiv.org:2602.07473</identifier></header><metadata>'
        . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title>From arXiv</dc:title></oai_dc:dc></metadata></record></GetRecord></OAI-PMH>';

    /** @var array<int, array<string, mixed>> */
    private array $dataciteHistory = [];
    private bool $hadArxivSources = false;
    private mixed $previousArxivSources = null;
    private mixed $previousRepositoriesCache = null;

    public function testLoadRecordAsksArxivTwiceWithShortTimeouts(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiTimeout(), ['body' => self::OAI_RECORD, 'http_code' => 200]]);

        [$record] = $this->loadArxivRecord();

        self::assertSame(2, $oai->calls);
        self::assertSame(15, $oai->requestedOptions[CURLOPT_TIMEOUT]);
        self::assertSame(5, $oai->requestedOptions[CURLOPT_CONNECTTIMEOUT]);
        self::assertStringContainsString('From arXiv', $record);
    }

    public function testLoadRecordTriesArxivTwiceThenReadsDatacite(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiTimeout(), $this->oaiTimeout()]);
        $this->dataciteResponds(new Response(200, [], $this->dataciteBody()));

        [$record, $result, $oaiAfter] = $this->loadArxivRecord();

        self::assertSame(2, $oai->calls, 'two attempts on the arXiv OAI-PMH server');
        self::assertSame(15, $oai->requestedOptions[CURLOPT_TIMEOUT]);
        self::assertSame(5, $oai->requestedOptions[CURLOPT_CONNECTTIMEOUT]);
        self::assertCount(1, $this->dataciteHistory);
        self::assertStringContainsString('oai_dc:dc', $record);
        self::assertStringContainsString('<identifier>oai:arXiv.org:' . self::ARXIV_ID . '</identifier>', $record);
        self::assertSame(Episciences_Repositories_ArXiv_DataciteFallback::SOURCE, $result[Episciences_Submit::RECORD_SOURCE]);
        self::assertNull($oaiAfter, 'no other request to the unavailable OAI-PMH server');
    }

    public function testLoadRecordReadsDataciteAfterTwoHttpServerErrors(): void
    {
        $oai = $this->useArxivAnswering([['body' => '', 'http_code' => 503], ['body' => '', 'http_code' => 502]]);
        $this->dataciteResponds(new Response(200, [], $this->dataciteBody()));

        [$record, $result] = $this->loadArxivRecord();

        self::assertSame(2, $oai->calls);
        self::assertStringContainsString('oai_dc:dc', $record);
        self::assertSame(Episciences_Repositories_ArXiv_DataciteFallback::SOURCE, $result[Episciences_Submit::RECORD_SOURCE]);
    }

    public function testLoadRecordDoesNotReadDataciteWhenArxivAnswersTheSecondTime(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiTimeout(), ['body' => self::OAI_RECORD, 'http_code' => 200]]);
        $this->dataciteResponds();

        [$record, $result, $oaiAfter] = $this->loadArxivRecord();

        self::assertSame(2, $oai->calls);
        self::assertStringContainsString('From arXiv', $record);
        self::assertArrayNotHasKey(Episciences_Submit::RECORD_SOURCE, $result);
        self::assertSame([], $this->dataciteHistory);
        self::assertNotNull($oaiAfter);
    }

    public function testLoadRecordReportsAnUnknownDocumentFromDatacite(): void
    {
        $this->useArxivAnswering([$this->oaiTimeout(), $this->oaiTimeout()]);
        $this->dataciteResponds(new Response(404, [], '{}'));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        $this->loadArxivRecord();
    }

    public function testLoadRecordKeepsTheArxivTimeoutWhenDataciteFailsToo(): void
    {
        $this->useArxivAnswering([$this->oaiTimeout(), $this->oaiTimeout()]);
        $this->dataciteResponds(new Response(503, [], 'Service Unavailable'));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::REPOSITORY_TIMEOUT_CODE);
        $this->loadArxivRecord();
    }

    public function testLoadRecordReadsDataciteAfterTwoRefusedConnections(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiConnectionRefused(), $this->oaiConnectionRefused()]);
        $this->dataciteResponds(new Response(200, [], $this->dataciteBody()));

        [$record, $result] = $this->loadArxivRecord();

        self::assertSame(2, $oai->calls);
        self::assertStringContainsString('oai_dc:dc', $record);
        self::assertSame(Episciences_Repositories_ArXiv_DataciteFallback::SOURCE, $result[Episciences_Submit::RECORD_SOURCE]);
    }

    public function testLoadRecordReportsArxivAsUnavailableWhenTheConnectionIsRefusedAndDataciteFailsToo(): void
    {
        $this->useArxivAnswering([$this->oaiConnectionRefused(), $this->oaiConnectionRefused()]);
        $this->dataciteResponds(new Response(503, [], 'Service Unavailable'));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE);
        $this->loadArxivRecord();
    }

    public function testLoadRecordKeepsTheArxivServerErrorWhenDataciteFailsToo(): void
    {
        $this->useArxivAnswering([['body' => '', 'http_code' => 503], ['body' => '', 'http_code' => 503]]);
        $this->dataciteResponds(new Response(500, [], ''));

        $this->expectException(Ccsd_Oai_ServerErrorException::class);
        $this->loadArxivRecord();
    }

    public function testLoadRecordDoesNotReadDataciteAfterAnOaiError(): void
    {
        $oai = $this->useArxivAnswering([[
            'body' => '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">'
                . '<error code="idDoesNotExist">No matching identifier</error></OAI-PMH>',
            'http_code' => 200,
        ]]);
        $this->dataciteResponds();

        try {
            $this->loadArxivRecord();
            self::fail('An OAI error must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertStringStartsWith('idDoesNotExist', $e->getMessage());
        }

        self::assertSame(1, $oai->calls);
        self::assertSame([], $this->dataciteHistory);
    }

    public function testLoadRecordDoesNotReadDataciteWhenSwitchedOff(): void
    {
        $this->useArxivAnswering([$this->oaiTimeout(), $this->oaiTimeout()]);
        $this->dataciteResponds();
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(false);

        try {
            $this->loadArxivRecord();
            self::fail('Two timeouts must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, $e->getMessage());
        }

        self::assertSame([], $this->dataciteHistory);
    }

    // =========================================================================
    // arXiv: version check (arXivRaw) after a record served by arXiv
    // =========================================================================

    public function testVersionCheckAsksArxivOnce(): void
    {
        $oai = $this->useArxivAnswering([['body' => $this->arXivRawBody(), 'http_code' => 200]]);
        $this->dataciteResponds();

        $result = $this->assertArxivRequestedVersion($oai, 2);

        self::assertSame(1, $oai->calls);
        self::assertSame([], $this->dataciteHistory);
        self::assertArrayNotHasKey('fallbackVersions', $result);
    }

    public function testVersionCheckReadsDataciteAfterAnArxivServerError(): void
    {
        $oai = $this->useArxivAnswering([['body' => '', 'http_code' => 503]]);
        $this->dataciteResponds(new Response(200, [], $this->dataciteBody()));

        $result = $this->assertArxivRequestedVersion($oai, 2);

        self::assertSame(1, $oai->calls, 'a single attempt: DataCite answers when arXivRaw fails');
        self::assertCount(1, $this->dataciteHistory);
        self::assertSame([1, 2], array_map('intval', $result['fallbackVersions']));
        self::assertArrayNotHasKey(Episciences_Submit::RECORD_SOURCE, $result, 'the record still comes from arXiv');
    }

    public function testVersionCheckRefusesAVersionUnknownToDatacite(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiTimeout()]);
        $this->dataciteResponds(new Response(200, [], $this->dataciteBody()));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::ARXIV_VERSION_DOES_NOT_EXIST_CODE);
        $this->assertArxivRequestedVersion($oai, 5);
    }

    public function testVersionCheckKeepsTheArxivErrorWhenDataciteDoesNotKnowTheDocument(): void
    {
        $oai = $this->useArxivAnswering([['body' => '', 'http_code' => 503]]);
        $this->dataciteResponds(new Response(404, [], '{}'));

        try {
            $this->assertArxivRequestedVersion($oai, 2);
            self::fail('The arXiv error must be raised');
        } catch (Ccsd_Error $e) {
            self::assertInstanceOf(Ccsd_Oai_ServerErrorException::class, $e);
            self::assertNotSame(Ccsd_Error::ID_DOES_NOT_EXIST_CODE, $e->getMessage());
        }

        self::assertCount(1, $this->dataciteHistory);
    }

    public function testVersionCheckDoesNotReadDataciteForAnUnknownArxivVersion(): void
    {
        $oai = $this->useArxivAnswering([['body' => $this->arXivRawBody(), 'http_code' => 200]]);
        $this->dataciteResponds();

        try {
            $this->assertArxivRequestedVersion($oai, 5);
            self::fail('An unknown version must be refused');
        } catch (Ccsd_Error $e) {
            self::assertStringStartsWith(Ccsd_Error::ARXIV_VERSION_DOES_NOT_EXIST_CODE, $e->getMessage());
        }

        self::assertSame([], $this->dataciteHistory);
    }

    public function testVersionCheckKeepsTheArxivErrorWhenTheFallbackIsSwitchedOff(): void
    {
        $oai = $this->useArxivAnswering([$this->oaiTimeout()]);
        $this->dataciteResponds();
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(false);

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::REPOSITORY_TIMEOUT_CODE);
        $this->assertArxivRequestedVersion($oai, 2);
    }

    /**
     * @dataProvider fallbackVersionProvider
     */
    public function testAssertFallbackVersionExists(?int $requestedVersion, ?string $expectedError): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'assertFallbackVersionExists');
        $method->setAccessible(true);
        $result = ['fallbackVersions' => [1, 2, 3], 'fallbackLatestVersion' => 3];

        try {
            $method->invoke(null, $result, $requestedVersion);
            self::assertNull($expectedError, 'the version must be refused');
        } catch (Ccsd_Error $e) {
            self::assertNotNull($expectedError, 'the version must be accepted: ' . $e->getMessage());
            self::assertStringStartsWith($expectedError, $e->getMessage());
        }
    }

    public static function fallbackVersionProvider(): array
    {
        return [
            'no version requested' => [null, null],
            'first version' => [1, null],
            'latest version' => [3, null],
            'next version, maybe not yet in DataCite' => [4, Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE],
            'unknown version' => [5, Ccsd_Error::ARXIV_VERSION_DOES_NOT_EXIST_CODE],
        ];
    }

    public function testRepositoryUnavailableGivesAClearMessage(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'buildCcsdErrorResult');
        $method->setAccessible(true);

        $this->seedArxivRepository();
        $result = $method->invoke(null, new Ccsd_Oai_ServerErrorException(503, 'https://oai.example.org/oai'), 2);

        self::assertSame(0, $result['status']);
        self::assertStringContainsString('arXiv', $result['error']);
        self::assertStringContainsString('mailto:' . EPISCIENCES_SUPPORT, $result['error']);
        self::assertStringNotContainsString('%s', $result['error']);
        self::assertStringNotContainsString('The operation ended with this error', $result['error']);
    }

    public function testRefusedConnectionGivesTheUnavailableMessage(): void
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'buildCcsdErrorResult');
        $method->setAccessible(true);

        $this->seedArxivRepository();
        $result = $method->invoke(null, new Ccsd_Error(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, CURLE_COULDNT_CONNECT), 2);

        self::assertSame(0, $result['status']);
        self::assertStringContainsString('arXiv', $result['error']);
        self::assertStringContainsString('temporarily unavailable', $result['error']);
        self::assertStringNotContainsString('CURL_ERROR', $result['error']);
        self::assertStringNotContainsString('%s', $result['error']);
    }

    protected function tearDown(): void
    {
        Episciences_Submit::setOaiClientFactory(null);
        Episciences_Repositories_ArXiv_DataciteFallback::setHttpClient(null);
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(null);

        if ($this->previousRepositoriesCache !== null) {
            if ($this->hadArxivSources) {
                Zend_Registry::set('metadataSources', $this->previousArxivSources);
            } else {
                Zend_Registry::getInstance()->offsetUnset('metadataSources');
            }
            $cache = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
            $cache->setAccessible(true);
            $cache->setValue(null, $this->previousRepositoriesCache);
            $this->previousRepositoriesCache = null;
        }
    }

    /**
     * arXiv as the only configured repository, with its OAI-PMH settings
     */
    private function seedArxivRepository(): void
    {
        if ($this->previousRepositoriesCache === null) {
            $this->hadArxivSources = Zend_Registry::isRegistered('metadataSources');
            $this->previousArxivSources = $this->hadArxivSources ? Zend_Registry::get('metadataSources') : null;
            $cache = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
            $cache->setAccessible(true);
            $this->previousRepositoriesCache = $cache->getValue();
            $cache->setValue(null, []);
        }

        Zend_Registry::set('metadataSources', [
            2 => [
                'name' => 'arXiv',
                'type' => 'repository',
                'identifier' => 'oai:arXiv.org:%%ID',
                'base_url' => 'https://oai.example.org/oai',
                'api_url' => '',
            ],
        ]);
    }

    /**
     * Configures arXiv and makes its OAI-PMH client answer with $script (see getScriptedOaiClient())
     */
    private function useArxivAnswering(array $script): Episciences_Oai_Client
    {
        $this->seedArxivRepository();
        $oai = $this->getScriptedOaiClient($script);
        Episciences_Submit::setOaiClientFactory(static fn(string $baseUrl): Episciences_Oai_Client => $oai);

        return $oai;
    }

    /**
     * @return array{0: mixed, 1: array, 2: ?Episciences_Oai_Client} record, result, OAI client after the call
     */
    private function loadArxivRecord(): array
    {
        $method = new ReflectionMethod(Episciences_Submit::class, 'loadRecord');
        $method->setAccessible(true);

        $result = [];
        $oai = null;
        $args = [[], '2', 'oai:arXiv.org:' . self::ARXIV_ID, &$result, &$oai, self::ARXIV_ID];
        $record = $method->invokeArgs(null, $args);

        return [$record, $result, $oai];
    }

    /**
     * Version check of an arXiv record served by arXiv (no RECORD_SOURCE), with the client set up
     * for the record request (two attempts)
     * @return array the submission result, with the fallback versions when DataCite was read
     */
    private function assertArxivRequestedVersion(Episciences_Oai_Client $oai, ?int $version): array
    {
        $oai->setMaxAttempts(2);
        $method = new ReflectionMethod(Episciences_Submit::class, 'assertArxivRequestedVersion');
        $method->setAccessible(true);

        $result = [];
        $args = [&$result, $oai, 'oai:arXiv.org:' . self::ARXIV_ID, $version, '2', self::ARXIV_ID];
        $method->invokeArgs(null, $args);

        return $result;
    }

    /**
     * arXivRaw record listing versions v1 and v2
     */
    private function arXivRawBody(): string
    {
        return '<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord>'
            . '<record><header><identifier>oai:arXiv.org:' . self::ARXIV_ID . '</identifier></header><metadata>'
            . '<arXivRaw xmlns="http://arxiv.org/OAI/arXivRaw/"><id>' . self::ARXIV_ID . '</id>'
            . '<version version="v1"><date>Sat, 7 Feb 2026 10:09:41 GMT</date></version>'
            . '<version version="v2"><date>Fri, 6 Nov 2026 14:35:09 GMT</date></version>'
            . '</arXivRaw></metadata></record></GetRecord></OAI-PMH>';
    }

    private function dataciteResponds(Response ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->dataciteHistory = [];
        $stack->push(Middleware::history($this->dataciteHistory));
        Episciences_Repositories_ArXiv_DataciteFallback::setHttpClient(new Client(['handler' => $stack]));
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(true);
    }

    private function dataciteBody(): string
    {
        return (string)json_encode(['data' => ['attributes' => [
            'titles' => [['title' => 'From DataCite']],
            'creators' => [['name' => 'Doe, Jane', 'givenName' => 'Jane', 'familyName' => 'Doe']],
            'descriptions' => [['description' => 'An abstract.', 'descriptionType' => 'Abstract']],
            'dates' => [
                ['date' => '2026-02-07T10:09:41Z', 'dateType' => 'Submitted', 'dateInformation' => 'v1'],
                ['date' => '2026-11-06T14:35:09Z', 'dateType' => 'Submitted', 'dateInformation' => 'v2'],
            ],
            'version' => '2',
            'updated' => '2026-11-09T05:24:31.000Z',
        ]]]);
    }

    private function oaiTimeout(): Ccsd_Error
    {
        return new Ccsd_Error('Timeout was reached', CURLE_OPERATION_TIMEDOUT, Ccsd_Error::CURL_ERROR_TYPE);
    }

    private function oaiConnectionRefused(): Ccsd_Error
    {
        return new Ccsd_Error("Couldn't connect to server", CURLE_COULDNT_CONNECT, Ccsd_Error::CURL_ERROR_TYPE);
    }

    /**
     * arXiv OAI-PMH client answering each request with the next item of $script (no network, no pause):
     * a Ccsd_Error to throw, or ['body' => string, 'http_code' => int]
     */
    private function getScriptedOaiClient(array $script): Episciences_Oai_Client
    {
        return new class('https://oai.example.org/oai', $script) extends Episciences_Oai_Client {
            public int $calls = 0;
            public array $requestedOptions = [];
            private array $script;

            public function __construct(string $url, array $script)
            {
                parent::__construct($url, 'xml', 'episciences-test');
                $this->setLogger(null);
                $this->script = $script;
            }

            protected function fetchResponse(string $url, array $options): string
            {
                $this->requestedOptions = $options;
                $step = $this->script[$this->calls++];
                if ($step instanceof Ccsd_Error) {
                    $this->lastTransferInfo = ['http_code' => 0];
                    throw $step;
                }
                $this->lastTransferInfo = ['http_code' => $step['http_code']];
                return $step['body'];
            }

            protected function pause(int $seconds): void
            {
            }
        };
    }
}
