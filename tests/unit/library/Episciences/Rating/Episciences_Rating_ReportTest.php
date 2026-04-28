<?php

namespace unit\library\Episciences\Rating;

use Episciences_Rating_Criterion;
use Episciences_Rating_Report;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Rating_Report.
 *
 * All tests are DB-free. Report::__construct() with no args is safe:
 * generatePath() returns false when docid+uid are unset, so no filesystem access.
 *
 * Bugs documented/fixed:
 *   Rp1 — DB column 'ID' maps to setId() (Grid's field), not setRid(); _rid never
 *          populated from a DB row. Documented only — changing would risk breaking
 *          callers that rely on the current ON DUPLICATE KEY UPDATE behaviour.
 *   Rp2 — calculateScore(): if ($score != 0 && $coefs != 0) treats an all-zero
 *          rating as null instead of 0. Fixed: check only $coefs != 0.
 *
 * @covers Episciences_Rating_Report
 */
final class Episciences_Rating_ReportTest extends TestCase
{
    // =========================================================================
    // calculateScore() — pure logic (no DB, no filesystem)
    // =========================================================================

    public function testCalculateScoreReturnsFalseWhenNoCriteria(): void
    {
        $report = new Episciences_Rating_Report();
        // _criteria is null → is_array(null) = false → returns false

        self::assertFalse($report->calculateScore());
    }

    public function testCalculateScoreReturnsNullWhenAllCriteriaHaveNoCoefficient(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 3, maxNote: 5, coefficient: null),
        ]);

        $report->calculateScore();

        // $coefs = 0 (coefficient is null → += null = 0) → returns null
        self::assertNull($report->getScore());
    }

    /**
     * Fix Rp2: a reviewer who rated everything at 0 (the minimum) must get
     * a computed score of 0, not null.
     *
     * Before the fix: if ($score != 0 && $coefs != 0) → score=0 fails the
     * first condition → null was returned even when coefficients exist.
     */
    public function testCalculateScoreReturnsZeroWhenAllNotesAreZero(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 0, maxNote: 4, coefficient: 1),
            $this->makeCriterion(note: 0, maxNote: 4, coefficient: 2),
        ]);

        $report->calculateScore(0);

        // score = (0/4*1 + 0/4*2) / 3 * 10 = 0 — not null
        self::assertSame(0.0, $report->getScore(), 'Fix Rp2: all-zero rating must produce score 0, not null');
    }

    public function testCalculateScoreWithSingleCriterion(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 3, maxNote: 4, coefficient: 1),
        ]);

        $report->calculateScore(1);

        // (3/4 * 1) / 1 * 10 = 7.5
        self::assertSame(7.5, $report->getScore());
    }

    public function testCalculateScoreWithMultipleCriteria(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 4, maxNote: 4, coefficient: 2),  // 1.0 weighted
            $this->makeCriterion(note: 2, maxNote: 4, coefficient: 1),  // 0.5 weighted
        ]);

        $report->calculateScore(1);

        // score = (4/4*2 + 2/4*1) / 3 * 10 = (2 + 0.5) / 3 * 10 = 8.333...
        self::assertSame(8.3, $report->getScore());
    }

    public function testCalculateScoreIgnoresCriteriaWithNoCoefficient(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 4, maxNote: 4, coefficient: 2),
            $this->makeCriterion(note: 0, maxNote: 4, coefficient: null), // no coefficient: ignored
        ]);

        $report->calculateScore(0);

        // Only first criterion counts: (4/4*2) / 2 * 10 = 10
        self::assertSame(10.0, $report->getScore());
    }

    public function testCalculateScoreWithMaxScoreOverride(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setMax_score(5);
        $report->setCriteria([
            $this->makeCriterion(note: 2, maxNote: 4, coefficient: 1),
        ]);

        $report->calculateScore(1);

        // (2/4 * 1) / 1 * 5 = 2.5
        self::assertSame(2.5, $report->getScore());
    }

    public function testCalculateScorePrecisionParameter(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 1, maxNote: 3, coefficient: 1),
        ]);

        $report->calculateScore(2);

        // (1/3 * 1) / 1 * 10 = 3.333... rounded to 2 decimals = 3.33
        self::assertSame(3.33, $report->getScore());
    }

    public function testGetScoreLazilyCallsCalculateScore(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 4, maxNote: 4, coefficient: 1),
        ]);

        // _score not set yet — getScore() must trigger calculateScore()
        $score = $report->getScore();

        self::assertNotNull($score);
        self::assertSame(10.0, $score);
    }

    public function testSetScoreOverridesCalculation(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([
            $this->makeCriterion(note: 0, maxNote: 4, coefficient: 1),
        ]);
        $report->setScore(7.5);

        // _score is already set → calculateScore() not called
        self::assertSame(7.5, $report->getScore());
    }

    // =========================================================================
    // populate() / getAttachments()
    // =========================================================================

    public function testGetAttachmentsReturnsEmptyArrayWhenNoCriteria(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setCriteria([]);

        self::assertSame([], $report->getAttachments());
    }

    public function testGetAttachmentsReturnsOnlyCriteriaWithAttachments(): void
    {
        $withAttachment    = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]);
        $withAttachment->setId('item_1');
        $withAttachment->setAttachment_setting(true);
        $withAttachment->setAttachment('review.pdf');

        $withoutAttachment = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]);
        $withoutAttachment->setId('item_2');
        $withoutAttachment->setAttachment_setting(true);
        // no attachment file set

        $report = new Episciences_Rating_Report();
        $report->setCriteria([$withAttachment, $withoutAttachment]);

        $attachments = $report->getAttachments();

        self::assertCount(1, $attachments);
        self::assertArrayHasKey('file_item_1', $attachments);
        self::assertSame('review.pdf', $attachments['file_item_1']);
    }

    // =========================================================================
    // exists()
    // =========================================================================

    public function testExistsReturnsFalseWhenNoPathSet(): void
    {
        $report = new Episciences_Rating_Report();
        // _path is null → getPath() returns null → file_exists('report.xml') = false

        self::assertFalse($report->exists());
    }

    // =========================================================================
    // Bug Rp1 — DB column 'ID' maps to setId() (Grid's field), not setRid()
    // =========================================================================

    /**
     * Design issue Rp1 (documented, not fixed): Episciences_Rating_Report extends
     * Episciences_Rating_Grid. The constructor's magic `set{Ucfirst(Key)}` dispatch
     * maps the DB column 'ID' to Grid::setId(), not Report::setRid().
     *
     * As a result, getRid() always returns null when a Report is constructed from
     * a DB row. The save() method still works because ON DUPLICATE KEY UPDATE fires
     * on the (UID, DOCID) unique constraint, not on the primary key.
     *
     * This test documents the current (unexpected) behaviour without fixing it,
     * to guard against accidental changes that might break callers relying on it.
     */
    public function testRidIsNullWhenConstructedWithIdKey(): void
    {
        // Simulating what happens when the DB row ['ID' => 7, 'UID' => 5, 'DOCID' => 3]
        // is passed to the constructor. No filesystem access occurs because uid+docid
        // result in a path that doesn't exist.
        $report = new Episciences_Rating_Report(['id' => 7, 'uid' => 5, 'docid' => 3]);

        self::assertNull(
            $report->getRid(),
            'Design issue Rp1: DB column ID maps to Grid::setId(), not Report::setRid() — _rid stays null'
        );

        // The grid _id IS set (to the DB row ID) — confirming the mapping
        self::assertSame(7, $report->getId());
    }

    /**
     * setRid() works correctly when called explicitly (e.g. from other code).
     */
    public function testSetRidAndGetRid(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setRid(42);

        self::assertSame(42, $report->getRid());
    }

    public function testSetRidWithNullResetsRid(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setRid(10);
        $report->setRid(null);

        self::assertNull($report->getRid());
    }

    // =========================================================================
    // getMax_score / setMax_score
    // =========================================================================

    public function testDefaultMaxScoreIsTen(): void
    {
        $report = new Episciences_Rating_Report();

        self::assertSame(10, $report->getMax_score());
    }

    public function testSetMaxScoreOverridesDefault(): void
    {
        $report = new Episciences_Rating_Report();
        $report->setMax_score(5);

        self::assertSame(5, $report->getMax_score());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeCriterion(int $note, int $maxNote, int|float|null $coefficient): Episciences_Rating_Criterion
    {
        $options = [];
        for ($i = 0; $i <= $maxNote; $i++) {
            $options[$i] = ['value' => $i, 'label' => null];
        }

        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setOptions($options);
        $c->setNote($note);
        $c->setCoefficient($coefficient);

        return $c;
    }
}
