<?php

namespace unit\library\Episciences\submit;

use Ccsd_Error;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for Episciences_Submit private methods.
 *
 * GROUP A — Private helpers of getDoc():
 *   assertDateTimeVersion(), assertVersion(), assertHalSubmissionAllowed(),
 *   buildCcsdErrorResult(), buildGenericErrorResult(), assertNewVersionConsistency()
 *
 * GROUP C — Private helpers of saveDoc():
 *   parseEnrichment(), errorResult(), canAutoAssign(), getSuggestedEditorsFromPost()
 *
 * All tests are DB-free where possible. Methods that require an Episciences_Submit
 * instance use new Episciences_Submit() which sets $this->_db from the default adapter.
 * Tests for instance methods only exercise logic that does not reach $this->_db.
 *
 * @covers Episciences_Submit
 */
final class Episciences_Submit_PrivateMethodsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function invoke(string $method, array $args = [], bool $static = true): mixed
    {
        $rm = new ReflectionMethod(Episciences_Submit::class, $method);
        $rm->setAccessible(true);
        return $static ? $rm->invokeArgs(null, $args) : $rm->invokeArgs(new Episciences_Submit(), $args);
    }

    private const FAKE_DSPACE_REPOID = 9999;

    /** Create a mock Episciences_Paper with getVersion(), getIdentifier(), and getRepoid() stubs. */
    private function mockPaper(float $version, string $identifier = '', int $repoid = 0): Episciences_Paper
    {
        $mock = $this->createMock(Episciences_Paper::class);
        $mock->method('getVersion')->willReturn($version);
        $mock->method('getIdentifier')->willReturn($identifier);
        $mock->method('getRepoid')->willReturn($repoid);
        return $mock;
    }

    /** Inject a fake DSpace repository into the static Repositories cache. */
    private function setUpFakeDspaceRepo(): void
    {
        $rp = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $rp->setAccessible(true);
        $rp->setValue(null, [
            self::FAKE_DSPACE_REPOID => [
                Episciences_Repositories::REPO_LABEL => 'Fake DSpace',
                Episciences_Repositories::REPO_TYPE  => Episciences_Repositories::TYPE_DSPACE,
            ],
        ]);
    }

    /** Reset the static Repositories cache after DSpace tests. */
    private function tearDownFakeDspaceRepo(): void
    {
        $rp = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $rp->setAccessible(true);
        $rp->setValue(null, []);
    }

    // =========================================================================
    // GROUP A — assertDateTimeVersion()
    // =========================================================================

    /**
     * If $docId is falsy (null / 0 / ''), the method returns immediately.
     */
    public function testAssertDateTimeVersionEarlyReturnWhenDocIdFalsy(): void
    {
        $docId = null;
        $result = ['update' => '20240315:120000'];
        $this->invoke('assertDateTimeVersion', [&$docId, null, &$result, false]);
        self::assertNull($docId); // unchanged
    }

    /**
     * If result has no UPDATE_DATETIME key, the method returns immediately.
     */
    public function testAssertDateTimeVersionEarlyReturnWhenNoUpdateDatetime(): void
    {
        $docId = 42;
        $result = [];
        $paper = $this->mockPaper(1.0, 'some-id-no-datetime');
        $paper->hasHook = true;
        $this->invoke('assertDateTimeVersion', [&$docId, $paper, &$result, false]);
        self::assertSame(42, $docId); // unchanged
    }

    /**
     * previousPaper identifier has no datetime pattern → previousDatetime = ''.
     * '' < '20240315:120000' → new version: docId reset to null, hookVersion incremented.
     */
    public function testAssertDateTimeVersionResetsDocIdWhenCurrentIsNewer(): void
    {
        $docId = 42;
        $result = ['update' => '20240315:120000', 'hookVersion' => 1.0];
        // identifier has no YYYYMMDD:HHMMSS pattern → getDateTimePattern returns ''
        $paper = $this->mockPaper(1.0, 'some-id-without-datetime');
        $paper->hasHook = true;
        $this->invoke('assertDateTimeVersion', [&$docId, $paper, &$result, true]);
        self::assertNull($docId);
        self::assertSame(2.0, $result['hookVersion']);
    }

    /**
     * previousPaper identifier has the same datetime as current → no update.
     */
    public function testAssertDateTimeVersionNoChangeWhenDatetimesEqual(): void
    {
        $docId = 42;
        $datetime = '20240315:120000';
        $result = ['update' => $datetime, 'hookVersion' => 1.0];
        $paper = $this->mockPaper(1.0, 'some-id/' . $datetime);
        $paper->hasHook = true;
        $this->invoke('assertDateTimeVersion', [&$docId, $paper, &$result, true]);
        // same datetime → previousDatetime is NOT < current → no change
        self::assertSame(42, $docId);
        self::assertSame(1.0, $result['hookVersion']);
    }

    /**
     * previousPaper has older datetime → treated as new version.
     */
    public function testAssertDateTimeVersionResetsDocIdWhenPreviousIsOlder(): void
    {
        $docId = 99;
        $result = ['update' => '20250101:000000', 'hookVersion' => 3.0];
        $paper = $this->mockPaper(3.0, 'paper/20240101:000000'); // older
        $paper->hasHook = true;
        $this->invoke('assertDateTimeVersion', [&$docId, $paper, &$result, true]);
        self::assertNull($docId);
        self::assertSame(4.0, $result['hookVersion']);
    }

    /**
     * previousPaper has newer datetime than current → no update.
     */
    public function testAssertDateTimeVersionNoChangeWhenPreviousIsNewer(): void
    {
        $docId = 77;
        $result = ['update' => '20230101:000000', 'hookVersion' => 2.0]; // current is older
        $paper = $this->mockPaper(2.0, 'paper/20250101:000000'); // previous is newer
        $paper->hasHook = true;
        $this->invoke('assertDateTimeVersion', [&$docId, $paper, &$result, true]);
        self::assertSame(77, $docId); // unchanged — previous > current, NOT a new version
    }

    // =========================================================================
    // GROUP A — assertVersion()
    // =========================================================================

    /**
     * No previousPaper → returns immediately, docId unchanged.
     */
    public function testAssertDspaceVersionEarlyReturnWhenNoPreviousPaper(): void
    {
        $docId = 42;
        $result = ['hookVersion' => 2.0];
        $this->invoke('assertDspaceVersion', [&$docId, null, $result]);
        self::assertSame(42, $docId);
    }

    /**
     * previousPaper version (1.0) < hookVersion (2.0) → docId set to null, return.
     */
    public function testAssertDspaceVersionResetsDocIdWhenNewVersionIsHigher(): void
    {
        $this->setUpFakeDspaceRepo();
        try {
            $docId = 42;
            $result = ['hookVersion' => 2.0];
            $paper = $this->mockPaper(1.0, '', self::FAKE_DSPACE_REPOID);
            $this->invoke('assertDspaceVersion', [&$docId, $paper, $result]);
            self::assertNull($docId);
        } finally {
            $this->tearDownFakeDspaceRepo();
        }
    }

    /**
     * previousPaper version (2.0) equal to hookVersion (2.0) → throws Ccsd_Error.
     */
    public function testAssertDspaceVersionThrowsWhenVersionNotHigherThanPrevious(): void
    {
        $this->setUpFakeDspaceRepo();
        try {
            $this->expectException(Ccsd_Error::class);
            $docId = 42;
            $result = ['hookVersion' => 2.0];
            $paper = $this->mockPaper(2.0, '', self::FAKE_DSPACE_REPOID);
            $this->invoke('assertDspaceVersion', [&$docId, $paper, $result]);
        } finally {
            $this->tearDownFakeDspaceRepo();
        }
    }

    /**
     * previousPaper version (3.0) > hookVersion (2.0) → throws Ccsd_Error.
     */
    public function testAssertDspaceVersionThrowsWhenVersionLowerThanPrevious(): void
    {
        $this->setUpFakeDspaceRepo();
        try {
            $this->expectException(Ccsd_Error::class);
            $docId = 42;
            $result = ['hookVersion' => 2.0];
            $paper = $this->mockPaper(3.0, '', self::FAKE_DSPACE_REPOID);
            $this->invoke('assertDspaceVersion', [&$docId, $paper, $result]);
        } finally {
            $this->tearDownFakeDspaceRepo();
        }
    }

    // =========================================================================
    // GROUP A — assertHalSubmissionAllowed()
    // =========================================================================

    /**
     * Record contains the HAL document URL → not a notice → oai=null → no throw.
     */
    public function testAssertHalSubmissionAllowedDoesNotThrowForValidDoc(): void
    {
        $record = '<record><dc:identifier>https://hal.science/hal-01234567/document</dc:identifier></record>';
        // No exception expected
        $this->invoke('assertHalSubmissionAllowed', [$record, 'hal-01234567', 'oai:HAL:hal-01234567v1', null]);
        $this->addToAssertionCount(1);
    }

    /**
     * Record does NOT contain the HAL document URL → is a notice → throws 'docIsNotice:'.
     */
    public function testAssertHalSubmissionAllowedThrowsForNotice(): void
    {
        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessageMatches('/docIsNotice/');
        $record = '<record><dc:title>Just a notice, no file</dc:title></record>';
        $this->invoke('assertHalSubmissionAllowed', [$record, 'hal-01234567', 'oai:HAL:hal-01234567v1', null]);
    }

    /**
     * Valid HAL record with versioned document URL → not a notice, oai=null → no throw.
     */
    public function testAssertHalSubmissionAllowedAcceptsVersionedUrl(): void
    {
        $record = '<record><dc:identifier>https://hal.science/hal-01234567v2/document</dc:identifier></record>';
        $this->invoke('assertHalSubmissionAllowed', [$record, 'hal-01234567', 'oai:HAL:hal-01234567v2', null]);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // GROUP A — buildGenericErrorResult()
    // =========================================================================

    public function testBuildGenericErrorResultReturnsStatusZero(): void
    {
        $e = new \Exception('Something went wrong');
        $result = $this->invoke('buildGenericErrorResult', [$e]);
        self::assertSame(0, $result['status']);
        self::assertArrayHasKey('error', $result);
    }

    public function testBuildGenericErrorResultContainsExceptionMessage(): void
    {
        $e = new \Exception('Network timeout');
        $result = $this->invoke('buildGenericErrorResult', [$e]);
        // Without translator (CLI mode), error = raw getMessage()
        self::assertStringContainsString('Network timeout', $result['error']);
    }

    public function testBuildGenericErrorResultWithEmptyMessage(): void
    {
        $e = new \Exception('');
        $result = $this->invoke('buildGenericErrorResult', [$e]);
        self::assertSame(0, $result['status']);
    }

    // =========================================================================
    // GROUP A — buildCcsdErrorResult()
    // =========================================================================

    public function testBuildCcsdErrorResultReturnsStatusZero(): void
    {
        $e = new Ccsd_Error('docIsNotice:');
        $result = $this->invoke('buildCcsdErrorResult', [$e, 9999]);
        self::assertSame(0, $result['status']);
        self::assertArrayHasKey('error', $result);
    }

    public function testBuildCcsdErrorResultWithIdDoesNotExistCode(): void
    {
        $e = new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE . ':');
        $result = $this->invoke('buildCcsdErrorResult', [$e, 9999]);
        self::assertSame(0, $result['status']);
        self::assertArrayHasKey('error', $result);
    }

    public function testBuildCcsdErrorResultWithArxivVersionCode(): void
    {
        $e = new Ccsd_Error(Ccsd_Error::ARXIV_VERSION_DOES_NOT_EXIST_CODE . ':');
        $result = $this->invoke('buildCcsdErrorResult', [$e, 9999]);
        self::assertSame(0, $result['status']);
        self::assertArrayHasKey('error', $result);
    }

    public function testBuildCcsdErrorResultWithGenericCode(): void
    {
        $e = new Ccsd_Error('some random error message');
        $result = $this->invoke('buildCcsdErrorResult', [$e, 9999]);
        self::assertSame(0, $result['status']);
        self::assertArrayHasKey('error', $result);
        // Generic errors contain DEFAULT_PREFIX_CODE pattern
        self::assertStringContainsString('operation ended', $result['error']);
    }

    // =========================================================================
    // GROUP A — assertNewVersionConsistency()
    // =========================================================================

    /**
     * No old paper → returns immediately, no throw.
     */
    public function testAssertNewVersionConsistencyNoThrowWithNullOldPaper(): void
    {
        $submission = $this->mockPaper(2.0);
        $this->invoke('assertNewVersionConsistency', [null, $submission, []]);
        $this->addToAssertionCount(1);
    }

    /**
     * Old paper and submission have the same concept identifier → no throw.
     */
    public function testAssertNewVersionConsistencyNoThrowWhenConceptIdentifierMatches(): void
    {
        $oldPaper = $this->createMock(Episciences_Paper::class);
        $oldPaper->method('getRepoid')->willReturn(9999); // no hook class
        $oldPaper->method('getConcept_identifier')->willReturn('doi:10.5281/zenodo.123456');
        $oldPaper->method('getIdentifier')->willReturn('123456');

        $submission = $this->createMock(Episciences_Paper::class);
        $submission->method('getConcept_identifier')->willReturn('doi:10.5281/zenodo.123456');
        $submission->method('getIdentifier')->willReturn('123456');

        $result = ['record' => ''];
        $this->invoke('assertNewVersionConsistency', [$oldPaper, $submission, $result]);
        $this->addToAssertionCount(1);
    }

    /**
     * Concept identifiers differ → throws Ccsd_Error.
     */
    public function testAssertNewVersionConsistencyThrowsWhenConceptIdentifiersDiffer(): void
    {
        $this->expectException(Ccsd_Error::class);

        $oldPaper = $this->createMock(Episciences_Paper::class);
        $oldPaper->method('getRepoid')->willReturn(9999);
        $oldPaper->method('getConcept_identifier')->willReturn('doi:10.5281/zenodo.AAAAAA');
        $oldPaper->method('getIdentifier')->willReturn('AAAAAA');

        $submission = $this->createMock(Episciences_Paper::class);
        $submission->method('getConcept_identifier')->willReturn('doi:10.5281/zenodo.BBBBBB'); // different
        $submission->method('getIdentifier')->willReturn('BBBBBB');

        $result = ['record' => ''];
        $this->invoke('assertNewVersionConsistency', [$oldPaper, $submission, $result]);
    }

    /**
     * No concept identifier, but identifiers differ → throws Ccsd_Error.
     */
    public function testAssertNewVersionConsistencyThrowsWhenNoConceptButIdentifiersDiffer(): void
    {
        $this->expectException(Ccsd_Error::class);

        $oldPaper = $this->createMock(Episciences_Paper::class);
        $oldPaper->method('getRepoid')->willReturn(9999);
        $oldPaper->method('getConcept_identifier')->willReturn(null); // no concept
        $oldPaper->method('getIdentifier')->willReturn('old-id');

        $submission = $this->createMock(Episciences_Paper::class);
        $submission->method('getConcept_identifier')->willReturn(null);
        $submission->method('getIdentifier')->willReturn('different-id'); // different

        $result = ['record' => ''];
        $this->invoke('assertNewVersionConsistency', [$oldPaper, $submission, $result]);
    }

    /**
     * No concept identifier, same identifier → no throw.
     */
    public function testAssertNewVersionConsistencyNoThrowWhenNoConceptButSameIdentifier(): void
    {
        $oldPaper = $this->createMock(Episciences_Paper::class);
        $oldPaper->method('getRepoid')->willReturn(9999);
        $oldPaper->method('getConcept_identifier')->willReturn(null);
        $oldPaper->method('getIdentifier')->willReturn('same-id');

        $submission = $this->createMock(Episciences_Paper::class);
        $submission->method('getConcept_identifier')->willReturn(null);
        $submission->method('getIdentifier')->willReturn('same-id');

        $result = ['record' => ''];
        $this->invoke('assertNewVersionConsistency', [$oldPaper, $submission, $result]);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // GROUP C — parseEnrichment()
    // =========================================================================

    public function testParseEnrichmentWithEmptyData(): void
    {
        $result = $this->invoke('parseEnrichment', [[]], false);
        self::assertSame([], $result);
    }

    public function testParseEnrichmentWithEmptyHEnrichment(): void
    {
        $result = $this->invoke('parseEnrichment', [['h_enrichment' => '']], false);
        self::assertSame([], $result);
    }

    public function testParseEnrichmentWithValidJson(): void
    {
        $enrichment = ['authors' => ['Alice', 'Bob'], 'keywords' => ['PHP']];
        $result = $this->invoke('parseEnrichment', [['h_enrichment' => json_encode($enrichment)]], false);
        self::assertSame($enrichment, $result);
    }

    public function testParseEnrichmentConvertsTextTypeToDefault(): void
    {
        $data = ['h_enrichment' => json_encode(['type' => Episciences_Paper::TEXT_TYPE_TITLE])];
        $result = $this->invoke('parseEnrichment', [$data], false);
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $result['type']);
    }

    public function testParseEnrichmentWithInvalidJsonReturnsEmpty(): void
    {
        // trigger_error() inside catch block emits E_USER_NOTICE — suppress it
        $result = @$this->invoke('parseEnrichment', [['h_enrichment' => 'not-valid-json']], false);
        self::assertSame([], $result);
    }

    public function testParseEnrichmentPreservesOtherTypes(): void
    {
        $data = ['h_enrichment' => json_encode(['type' => 'article'])];
        $result = $this->invoke('parseEnrichment', [$data], false);
        self::assertSame('article', $result['type']);
    }

    // =========================================================================
    // GROUP C — errorResult()
    // =========================================================================

    public function testErrorResultReturnsCorrectStructure(): void
    {
        $result = $this->invoke('errorResult', [], false);
        self::assertSame(['code' => 0, 'message' => ''], $result);
    }

    // =========================================================================
    // GROUP C — canAutoAssign()
    // =========================================================================

    public function testCanAutoAssignReturnsTrueWhenSettingPresent(): void
    {
        $auto = ['canAssignEditors', 'canAssignChiefEditors'];
        $result = $this->invoke('canAutoAssign', [$auto, 'canAssignEditors'], false);
        self::assertTrue($result);
    }

    public function testCanAutoAssignReturnsFalseWhenSettingAbsent(): void
    {
        $auto = ['canAssignEditors'];
        $result = $this->invoke('canAutoAssign', [$auto, 'canAssignChiefEditors'], false);
        self::assertFalse($result);
    }

    public function testCanAutoAssignReturnsFalseForEmptyArray(): void
    {
        $result = $this->invoke('canAutoAssign', [[], 'canAssignEditors'], false);
        self::assertFalse($result);
    }

    public function testCanAutoAssignIsStrictComparison(): void
    {
        // Uses in_array strict=true → '1' !== 1
        $auto = [1, 2];
        $result = $this->invoke('canAutoAssign', [$auto, '1'], false);
        self::assertFalse($result);
    }

    // =========================================================================
    // GROUP C — getSuggestedEditorsFromPost()
    // =========================================================================

    public function testGetSuggestedEditorsFromPostReturnsEditors(): void
    {
        $post = ['suggestEditors' => [5, 12]];
        $result = $this->invoke('getSuggestedEditorsFromPost', [$post], false);
        self::assertSame([5, 12], array_values($result));
    }

    public function testGetSuggestedEditorsFromPostFiltersEmptyValues(): void
    {
        $post = ['suggestEditors' => [5, '', 0, 12]];
        $result = $this->invoke('getSuggestedEditorsFromPost', [$post], false);
        // array_filter removes falsy values (0, '')
        self::assertNotContains('', $result);
        self::assertNotContains(0, $result);
        self::assertContains(5, $result);
        self::assertContains(12, $result);
    }

    public function testGetSuggestedEditorsFromPostReturnsEmptyWhenCanReplace(): void
    {
        $post = ['suggestEditors' => [5, 12], 'can_replace' => true];
        $result = $this->invoke('getSuggestedEditorsFromPost', [$post], false);
        self::assertSame([], $result);
    }

    public function testGetSuggestedEditorsFromPostReturnsEmptyWhenNoKey(): void
    {
        $post = ['some_other_key' => 'value'];
        $result = $this->invoke('getSuggestedEditorsFromPost', [$post], false);
        self::assertSame([], $result);
    }

    public function testGetSuggestedEditorsFromPostWithSingleEditor(): void
    {
        $post = ['suggestEditors' => 42]; // scalar, not array
        $result = $this->invoke('getSuggestedEditorsFromPost', [$post], false);
        // (array)42 = [42] → filter → [42]
        self::assertContains(42, $result);
    }
}
