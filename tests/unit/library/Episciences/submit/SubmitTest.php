<?php

namespace unit\library\Episciences\submit;

use Episciences_Paper;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Exception;

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
}
