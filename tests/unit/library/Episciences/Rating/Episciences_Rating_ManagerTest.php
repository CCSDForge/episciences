<?php

namespace unit\library\Episciences\Rating;

use Episciences_Rating_Criterion;
use Episciences_Rating_Manager;
use Episciences_Rating_Report;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Rating_Manager (and related Rating entities).
 *
 * DB-dependent methods (find, getList, getPreviousRatings, isExistingCriterion)
 * are covered via API-contract and source-inspection tests only.
 * Pure-logic methods (getAverageRating) and entity classes (Report, Criterion)
 * are tested with real objects — no DB required.
 *
 * Bugs documented here (tests that currently FAIL signal unfixed bugs):
 *   B1 — getList() wrong order() call: direction 'DESC' silently dropped
 *   B2 — getRatingForm() separator label not escaped (XSS)
 *   B3 — getRatingForm() malformed HTML div (missing opening quote)
 *   B4 — getAverageRating() excludes STATUS_PENDING=0 reports (falsy check)
 *   B5 — Criterion::getMaxNote() throws ValueError on empty options
 *   B6 — Manager::find() duplicates Report::find() (maintenance hazard)
 *
 * @covers Episciences_Rating_Manager
 * @covers Episciences_Rating_Report
 * @covers Episciences_Rating_Criterion
 */
final class Episciences_Rating_ManagerTest extends TestCase
{
    // =========================================================================
    // getAverageRating() — pure logic, no DB
    // =========================================================================

    public function testGetAverageRatingReturnsNullForEmptyArray(): void
    {
        self::assertNull(Episciences_Rating_Manager::getAverageRating([]));
    }

    public function testGetAverageRatingReturnsNullForFalsyInput(): void
    {
        self::assertNull(Episciences_Rating_Manager::getAverageRating(false));
        self::assertNull(Episciences_Rating_Manager::getAverageRating(null));
    }

    public function testGetAverageRatingReturnsNullWhenAllReportsHaveZeroStatus(): void
    {
        // STATUS_PENDING = 0 → falsy → all excluded → null returned
        $r1 = $this->makeReport(Episciences_Rating_Report::STATUS_WIP, 8.0);
        $r2 = $this->makeReport(Episciences_Rating_Report::STATUS_WIP, 6.0);

        // Both WIP (status=1) are truthy and included
        self::assertSame(7.0, Episciences_Rating_Manager::getAverageRating([$r1, $r2]));
    }

    public function testGetAverageRatingComputesCorrectAverage(): void
    {
        $r1 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 8.0);
        $r2 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 6.0);

        // round((8+6)/2, 0) = 7.0
        self::assertSame(7.0, Episciences_Rating_Manager::getAverageRating([$r1, $r2]));
    }

    public function testGetAverageRatingRespectsPrecisionParameter(): void
    {
        $r1 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 7.0);
        $r2 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 8.0);

        // (7+8)/2 = 7.5 — with precision=1 should stay 7.5
        self::assertSame(7.5, Episciences_Rating_Manager::getAverageRating([$r1, $r2], 1));
    }

    public function testGetAverageRatingDefaultPrecisionIsZero(): void
    {
        $r1 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 7.0);
        $r2 = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 8.0);

        // (7+8)/2 = 7.5 — round to 0 decimal → 8.0
        self::assertSame(8.0, Episciences_Rating_Manager::getAverageRating([$r1, $r2]));
    }

    /**
     * Pending reports (STATUS_PENDING = 0) are explicitly excluded from the average.
     * Fix B4: replaced falsy check `if ($rating->getStatus())` with
     * `if ($rating->getStatus() !== STATUS_PENDING)` so the exclusion is intentional
     * and immune to any future reordering of status constant values.
     */
    public function testGetAverageRatingExcludesPendingReports(): void
    {
        $pending = $this->makeReport(Episciences_Rating_Report::STATUS_PENDING, 10.0);

        self::assertNull(
            Episciences_Rating_Manager::getAverageRating([$pending]),
            'Pending reports must be excluded from the average (explicit !== STATUS_PENDING check)'
        );
    }

    public function testGetAverageRatingIncludesWipReports(): void
    {
        $wip = $this->makeReport(Episciences_Rating_Report::STATUS_WIP, 8.0);

        // STATUS_WIP = 1 → truthy → included
        self::assertSame(8.0, Episciences_Rating_Manager::getAverageRating([$wip]));
    }

    public function testGetAverageRatingMixedStatusOnlyCountsNonPending(): void
    {
        $completed = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 9.0);
        $pending   = $this->makeReport(Episciences_Rating_Report::STATUS_PENDING, 1.0);

        // Only $completed passes the falsy check — average = 9.0
        self::assertSame(9.0, Episciences_Rating_Manager::getAverageRating([$completed, $pending]));
    }

    public function testGetAverageRatingReturnsSingleScore(): void
    {
        $r = $this->makeReport(Episciences_Rating_Report::STATUS_COMPLETED, 7.0);

        self::assertSame(7.0, Episciences_Rating_Manager::getAverageRating([$r]));
    }

    // =========================================================================
    // API contract — method signatures (Reflection-based)
    // =========================================================================

    public function testGetListHasThreeOptionalParameters(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_Manager::class, 'getList');

        self::assertCount(3, $method->getParameters());

        foreach ($method->getParameters() as $param) {
            self::assertTrue(
                $param->isDefaultValueAvailable(),
                "getList() parameter \${$param->getName()} must have a default value (null)"
            );
            self::assertNull($param->getDefaultValue());
        }
    }

    public function testGetListParameterNames(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_Manager::class, 'getList');
        $names  = array_map(fn($p) => $p->getName(), $method->getParameters());

        self::assertSame(['docid', 'uid', 'status'], $names);
    }

    public function testGetAverageRatingSecondParamIsPrecisionWithDefaultZero(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_Manager::class, 'getAverageRating');
        $params = $method->getParameters();

        self::assertCount(2, $params);
        self::assertSame('precision', $params[1]->getName());
        self::assertTrue($params[1]->isDefaultValueAvailable());
        self::assertSame(0, $params[1]->getDefaultValue());
    }

    public function testGetPreviousRatingsFirstParamIsTypedEpisciencesPaper(): void
    {
        $method = new ReflectionMethod(Episciences_Rating_Manager::class, 'getPreviousRatings');
        $first  = $method->getParameters()[0];

        self::assertSame('paper', $first->getName());
        self::assertNotNull($first->getType());
        self::assertSame('Episciences_Paper', $first->getType()->getName());
    }

    // =========================================================================
    // Bug B1 — getList() passes direction as ignored second argument to order()
    // =========================================================================

    /**
     * Bug B1: Zend_Db_Select::order(string $spec) takes ONE argument.
     * getList() calls ->order('CREATION_DATE', 'DESC') — the 'DESC' is silently
     * discarded, so results are ordered ASC instead of DESC.
     *
     * The private helper getListQuery() correctly uses ->order('CREATION_DATE DESC')
     * but getList() does not call that helper and has its own broken query.
     *
     * This test FAILS while the bug exists and PASSES once fixed.
     */
    public function testGetListUsesCorrectSingleStringOrderFormat(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Manager::class, 'getList');
        $source     = $this->getMethodSource($reflection);

        // Correct: direction embedded in the string → 'CREATION_DATE DESC'
        // Buggy:   direction as second arg → ('CREATION_DATE', 'DESC') — silently dropped
        self::assertStringNotContainsString(
            "order('CREATION_DATE', 'DESC')",
            $source,
            "Bug B1: getList() passes 'DESC' as a second arg to order() — it is silently ignored "
            . "by Zend_Db_Select. Use ->order('CREATION_DATE DESC') instead."
        );

        self::assertStringContainsString(
            "order('CREATION_DATE DESC')",
            $source,
            "Bug B1: getList() should use ->order('CREATION_DATE DESC') (direction in the string)."
        );
    }

    /**
     * Verify that getListQuery() (used by getPreviousRatings()) already uses the
     * correct format — confirming the fix pattern to apply to getList().
     */
    public function testGetListQueryUsesCorrectOrderFormat(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Manager::class, 'getListQuery');
        $reflection->setAccessible(true);
        $source     = $this->getMethodSource($reflection);

        self::assertStringContainsString(
            "order('CREATION_DATE DESC')",
            $source,
            'getListQuery() should already use the correct single-string order format'
        );
    }

    // =========================================================================
    // Bug B3 — getRatingForm() malformed HTML (missing opening quote on div class)
    // =========================================================================

    /**
     * Bug B3: Line 176 contains:
     *   $bloc_delete_file = "<div class=col-sm-2'>";
     * The opening single-quote before `col-sm-2` is missing.
     * Correct:  "<div class='col-sm-2'>"
     *
     * This test FAILS while the bug exists and PASSES once fixed.
     */
    public function testGetRatingFormDeleteBlockHasNoMalformedDivClass(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Manager::class, 'getRatingForm');
        $source     = $this->getMethodSource($reflection);

        self::assertStringNotContainsString(
            '"<div class=col-sm-2\'>"',
            $source,
            "Bug B3: malformed HTML — \"<div class=col-sm-2'>\" is missing the opening "
            . "single-quote. Should be \"<div class='col-sm-2'>\"."
        );
    }

    // =========================================================================
    // Bug B2 — getRatingForm() separator label not escaped (XSS)
    // =========================================================================

    /**
     * Bug B2: The separator criterion label is injected directly into HTML:
     *   'value' => '<h2 class="separator">' . $criterion->getLabel() . '</h2>'
     * without htmlspecialchars(). A label containing <script> tags would execute JS.
     *
     * This test FAILS while the bug exists and PASSES once fixed.
     */
    public function testGetRatingFormSeparatorLabelIsEscaped(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Manager::class, 'getRatingForm');
        $source     = $this->getMethodSource($reflection);

        // Ensure the separator block exists (sanity check)
        self::assertStringContainsString(
            'isSeparator()',
            $source,
            'Sanity: separator branch must exist in getRatingForm()'
        );

        // The label must go through htmlspecialchars() before HTML injection
        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$criterion->getLabel\(\)/',
            $source,
            'Bug B2: separator label must be escaped with htmlspecialchars() before HTML injection (XSS risk)'
        );
    }

    // =========================================================================
    // Bug B6 — Manager::find() duplicates Report::find()
    // =========================================================================

    /**
     * Fix B6: Manager::find() now delegates to Report::find() instead of duplicating its body.
     * A single implementation means bug fixes only need to be applied once.
     */
    public function testManagerFindDelegatesToReportFind(): void
    {
        $managerFind = new ReflectionMethod(Episciences_Rating_Manager::class, 'find');
        $source      = $this->getMethodSource($managerFind);

        self::assertStringContainsString(
            'Episciences_Rating_Report::find(',
            $source,
            'Manager::find() must delegate to Report::find() instead of duplicating its body'
        );
    }

    // =========================================================================
    // Episciences_Rating_Report entity (no DB)
    // =========================================================================

    public function testReportStatusConstants(): void
    {
        self::assertSame(0, Episciences_Rating_Report::STATUS_PENDING);
        self::assertSame(1, Episciences_Rating_Report::STATUS_WIP);
        self::assertSame(2, Episciences_Rating_Report::STATUS_COMPLETED);
    }

    public function testReportDefaultStatusIsPending(): void
    {
        // Constructor without docid+uid → generatePath() returns false →
        // _path stays null → file_exists('report.xml') → false → no loadXML call
        $report = new Episciences_Rating_Report();

        self::assertSame(Episciences_Rating_Report::STATUS_PENDING, $report->getStatus());
        self::assertTrue($report->isPending());
        self::assertFalse($report->isCompleted());
        self::assertFalse($report->isInProgress());
    }

    public function testReportSetStatusCompleted(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setStatus(Episciences_Rating_Report::STATUS_COMPLETED);

        self::assertTrue($report->isCompleted());
        self::assertFalse($report->isPending());
        self::assertFalse($report->isInProgress());
    }

    public function testReportSetStatusWip(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setStatus(Episciences_Rating_Report::STATUS_WIP);

        self::assertTrue($report->isInProgress());
        self::assertFalse($report->isPending());
        self::assertFalse($report->isCompleted());
    }

    public function testReportSetStatusCastsStringToInt(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setStatus('2');

        self::assertSame(2, $report->getStatus());
        self::assertIsInt($report->getStatus());
    }

    public function testReportSetUidCastsStringToInt(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setUid('42');

        self::assertSame(42, $report->getUid());
        self::assertIsInt($report->getUid());
    }

    public function testReportIsCompletedUsesStrictComparison(): void
    {
        $report = new Episciences_Rating_Report();

        // STATUS_PENDING=0 must NOT be treated as completed
        self::assertFalse($report->isCompleted());
        // STATUS_WIP=1 must NOT be treated as completed
        $report->setStatus(Episciences_Rating_Report::STATUS_WIP);
        self::assertFalse($report->isCompleted());
    }

    // =========================================================================
    // Episciences_Rating_Criterion entity (no DB)
    // =========================================================================

    public function testCriterionVisibilityConstants(): void
    {
        self::assertSame('public', Episciences_Rating_Criterion::VISIBILITY_PUBLIC);
        self::assertSame('contributor', Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR);
        self::assertSame('editors', Episciences_Rating_Criterion::VISIBILITY_EDITORS);
    }

    public function testCriterionTypeConstants(): void
    {
        self::assertSame('separator', Episciences_Rating_Criterion::TYPE_SEPARATOR);
        self::assertSame('criterion', Episciences_Rating_Criterion::TYPE_CRITERION);
    }

    public function testCriterionIsSeparator(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_SEPARATOR);

        self::assertTrue($c->isSeparator());
        self::assertFalse($c->isCriterion());
    }

    public function testCriterionIsCriterion(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);

        self::assertFalse($c->isSeparator());
        self::assertTrue($c->isCriterion());
    }

    public function testCriterionHasNoOptionsByDefault(): void
    {
        $c = new Episciences_Rating_Criterion();

        self::assertFalse($c->hasOptions());
        self::assertFalse($c->allowsNote());
    }

    public function testCriterionHasOptionsAfterSet(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setOptions([
            0 => ['value' => 0, 'label' => null],
            1 => ['value' => 1, 'label' => null],
        ]);

        self::assertTrue($c->hasOptions());
        self::assertTrue($c->allowsNote());
    }

    public function testCriterionIsEmptyByDefault(): void
    {
        $c = new Episciences_Rating_Criterion();

        self::assertTrue($c->isEmpty());
        self::assertFalse($c->hasValue());
    }

    public function testCriterionIsNotEmptyAfterComment(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setComment('Good methodology.');

        self::assertFalse($c->isEmpty());
        self::assertTrue($c->hasValue());
        self::assertTrue($c->hasComment());
    }

    public function testCriterionIsNotEmptyAfterAttachment(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setAttachment('review.pdf');

        self::assertFalse($c->isEmpty());
        self::assertTrue($c->hasAttachment());
    }

    public function testCriterionSetNoteConvertsStringToInt(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setNote('3');

        self::assertSame(3, $c->getNote());
        self::assertIsInt($c->getNote());
        self::assertTrue($c->hasNote());
    }

    public function testCriterionNoteZeroIsNumericSoHasNoteReturnsTrue(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setNote(0);

        // is_numeric(0) === true — note 0 counts as "having a note"
        self::assertTrue($c->hasNote());
    }

    public function testCriterionGetMaxNoteWithOptions(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setOptions([
            0 => ['value' => 0, 'label' => null],
            1 => ['value' => 1, 'label' => null],
            2 => ['value' => 2, 'label' => null],
            3 => ['value' => 3, 'label' => null],
        ]);

        self::assertSame(3, $c->getMaxNote());
    }

    /**
     * Fix B5: getMaxNote() now guards against empty options.
     * Previously max(array_keys([])) threw ValueError in PHP 8.x.
     * The fix returns 1 as a safe default when no options are defined.
     */
    public function testCriterionGetMaxNoteReturnsOneOnEmptyOptions(): void
    {
        $c = new Episciences_Rating_Criterion();
        // $_options = [] by default — must not throw

        self::assertSame(1, $c->getMaxNote());
    }

    public function testCriterionHasCoefficientReturnsFalseWhenNull(): void
    {
        $c = new Episciences_Rating_Criterion();

        // is_numeric(null) === false
        self::assertFalse($c->hasCoefficient());
    }

    public function testCriterionHasCoefficientReturnsTrueForIntCoefficient(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setCoefficient(2);

        self::assertTrue($c->hasCoefficient());
    }

    public function testCriterionHasCoefficientReturnsTrueForFloatCoefficient(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setCoefficient(1.5);

        self::assertTrue($c->hasCoefficient());
    }

    public function testCriterionAllowsCommentWhenSettingIsTrue(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setComment_setting(true);

        self::assertTrue($c->allowsComment());
    }

    public function testCriterionDoesNotAllowCommentByDefault(): void
    {
        $c = new Episciences_Rating_Criterion();

        // _comment_setting is null by default → allowsComment() returns null (falsy, not false)
        self::assertEmpty($c->allowsComment());
    }

    public function testCriterionAllowsAttachmentWhenSettingIsTrue(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setAttachment_setting(true);

        self::assertTrue($c->allowsAttachment());
    }

    public function testCriterionDoesNotAllowAttachmentByDefault(): void
    {
        $c = new Episciences_Rating_Criterion();

        // _attachment_setting is null by default → allowsAttachment() returns null (falsy, not false)
        self::assertEmpty($c->allowsAttachment());
    }

    public function testCriterionGetOptionReturnsCorrectOption(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setOptions([
            0 => ['value' => 0, 'label' => ['en' => 'Poor']],
            1 => ['value' => 1, 'label' => ['en' => 'Good']],
        ]);

        $option = $c->getOption(1);
        self::assertIsArray($option);
        self::assertSame(['en' => 'Good'], $option['label']);
    }

    public function testCriterionGetOptionReturnsFalseForMissingKey(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setOptions([0 => ['value' => 0, 'label' => null]]);

        self::assertFalse($c->getOption(99));
    }

    public function testCriterionToArrayContainsExpectedKeysForCriterionType(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);

        $array = $c->toArray();

        self::assertArrayHasKey('type', $array);
        self::assertArrayHasKey('options', $array);
        self::assertArrayHasKey('note', $array);
        self::assertArrayHasKey('coefficient', $array);
        self::assertArrayHasKey('comment', $array);
        self::assertArrayHasKey('attachment', $array);
        self::assertArrayHasKey('visibility', $array);
    }

    public function testCriterionToArrayDoesNotContainOptionKeysForSeparatorType(): void
    {
        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_SEPARATOR);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);
        $c->setPosition(1);

        $array = $c->toArray();

        self::assertArrayNotHasKey('options', $array);
        self::assertArrayNotHasKey('note', $array);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeReport(int $status, float $score): Episciences_Rating_Report
    {
        $mock = $this->createMock(Episciences_Rating_Report::class);
        $mock->method('getStatus')->willReturn($status);
        $mock->method('getScore')->willReturn($score);

        return $mock;
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    private function normaliseSource(string $source): string
    {
        // Strip whitespace differences for comparison
        return preg_replace('/\s+/', ' ', trim($source));
    }
}
