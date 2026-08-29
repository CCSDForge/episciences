<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper type predicates, type accessors and
 * DOI eligibility check.
 *
 * Tested methods:
 *   - isPreprint()
 *   - isDataset()
 *   - isSoftware()
 *   - isDataSetOrSoftware()
 *   - getTypeWithKey()
 *   - canBeAssignedDOI()
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_TypeTest extends TestCase
{
    // -----------------------------------------------------------------------
    // isPreprint()
    // -----------------------------------------------------------------------

    public function testIsPreprintReturnsTrueForDefaultPreprintType(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        self::assertTrue($paper->isPreprint());
    }

    public function testIsPreprintReturnsTrueForTextType(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::TEXT_TYPE_TITLE]);
        self::assertTrue($paper->isPreprint());
    }

    public function testIsPreprintReturnsFalseForSoftwareType(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        self::assertFalse($paper->isPreprint());
    }

    public function testIsPreprintReturnsFalseForDatasetType(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        self::assertFalse($paper->isPreprint());
    }

    public function testIsPreprintReturnsFalseForArticleType(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::ARTICLE_TYPE_TITLE]);
        self::assertFalse($paper->isPreprint());
    }

    // -----------------------------------------------------------------------
    // isSoftware()
    // -----------------------------------------------------------------------

    public function testIsSoftwareReturnsTrueWhenTypeTitleIsSoftware(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        self::assertTrue($paper->isSoftware());
    }

    public function testIsSoftwareReturnsFalseForPreprint(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        self::assertFalse($paper->isSoftware());
    }

    public function testIsSoftwareReturnsFalseForDataset(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        self::assertFalse($paper->isSoftware());
    }

    // -----------------------------------------------------------------------
    // isDataset() — type-title branch (repoId=0 is not a Dataverse repository)
    // -----------------------------------------------------------------------

    public function testIsDatasetReturnsTrueWhenTypeTitleIsDataset(): void
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid(0); // not a Dataverse repo → tests type-title branch
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        self::assertTrue($paper->isDataset());
    }

    public function testIsDatasetReturnsFalseForSoftware(): void
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid(0);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        self::assertFalse($paper->isDataset());
    }

    public function testIsDatasetReturnsFalseForPreprint(): void
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid(0);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        self::assertFalse($paper->isDataset());
    }

    // -----------------------------------------------------------------------
    // isDataSetOrSoftware()
    // -----------------------------------------------------------------------

    public function testIsDataSetOrSoftwareReturnsTrueForDataset(): void
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid(0);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DATASET_TYPE_TITLE]);
        self::assertTrue($paper->isDataSetOrSoftware());
    }

    public function testIsDataSetOrSoftwareReturnsTrueForSoftware(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE]);
        self::assertTrue($paper->isDataSetOrSoftware());
    }

    public function testIsDataSetOrSoftwareReturnsFalseForPreprint(): void
    {
        $paper = new Episciences_Paper();
        $paper->setRepoid(0);
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        self::assertFalse($paper->isDataSetOrSoftware());
    }

    // -----------------------------------------------------------------------
    // getTypeWithKey()
    // -----------------------------------------------------------------------

    public function testGetTypeWithKeyReturnsTitleTypeField(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([
            Episciences_Paper::TITLE_TYPE => Episciences_Paper::SOFTWARE_TYPE_TITLE,
            Episciences_Paper::TYPE_TYPE  => 'software',
        ]);
        self::assertSame(Episciences_Paper::SOFTWARE_TYPE_TITLE, $paper->getTypeWithKey(Episciences_Paper::TITLE_TYPE));
    }

    public function testGetTypeWithKeyReturnsEmptyStringForUnknownKey(): void
    {
        $paper = new Episciences_Paper();
        self::assertSame('', $paper->getTypeWithKey('nonexistent_key'));
    }

    public function testGetTypeWithKeyWithNullKeyReturnsTitleTypeValue(): void
    {
        $paper = new Episciences_Paper();
        $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::DEFAULT_TYPE_TITLE]);
        // When no key is given, getTypeWithKey() falls through to return TITLE_TYPE value
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $paper->getTypeWithKey());
    }

    // -----------------------------------------------------------------------
    // canBeAssignedDOI()
    // -----------------------------------------------------------------------

    public function testCanBeAssignedDOIReturnsTrueForAcceptedStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setStatus(Episciences_Paper::STATUS_ACCEPTED);
        self::assertTrue($paper->canBeAssignedDOI());
    }

    public function testCanBeAssignedDOIReturnsTrueForPublishedStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);
        self::assertTrue($paper->canBeAssignedDOI());
    }

    public function testCanBeAssignedDOIReturnsFalseForSubmittedStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);
        self::assertFalse($paper->canBeAssignedDOI());
    }

    public function testCanBeAssignedDOIReturnsFalseForRefusedStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setStatus(Episciences_Paper::STATUS_REFUSED);
        self::assertFalse($paper->canBeAssignedDOI());
    }

    public function testCanBeAssignedDOIReturnsFalseForObsoleteStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setStatus(Episciences_Paper::STATUS_OBSOLETE);
        self::assertFalse($paper->canBeAssignedDOI());
    }

    /**
     * All statuses in $_canBeAssignedDOI should return true.
     */
    public function testCanBeAssignedDOICoversAllEligibleStatuses(): void
    {
        $paper = new Episciences_Paper();
        foreach (Episciences_Paper::$_canBeAssignedDOI as $status) {
            $paper->setStatus($status);
            self::assertTrue(
                $paper->canBeAssignedDOI(),
                "Status $status should be eligible for DOI assignment"
            );
        }
    }
}
