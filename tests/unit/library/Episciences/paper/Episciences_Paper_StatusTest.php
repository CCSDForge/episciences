<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper status predicates, group-membership
 * predicates, and STATUS_DICTIONARY integrity.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_StatusTest extends TestCase
{
    private function paperWithStatus(int $status): Episciences_Paper
    {
        $paper = new Episciences_Paper();
        $paper->setStatus($status);
        return $paper;
    }

    // -----------------------------------------------------------------------
    // Simple single-status predicates
    // -----------------------------------------------------------------------

    public function testIsPublishedReturnsTrueWhenPublished(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_PUBLISHED)->isPublished());
    }

    public function testIsPublishedReturnsFalseWhenSubmitted(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED)->isPublished());
    }

    public function testIsAcceptedReturnsTrueWhenAccepted(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_ACCEPTED)->isAccepted());
    }

    public function testIsAcceptedReturnsFalseWhenSubmitted(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED)->isAccepted());
    }

    public function testIsObsoleteReturnsTrueWhenObsolete(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_OBSOLETE)->isObsolete());
    }

    public function testIsObsoleteReturnsFalseWhenPublished(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_PUBLISHED)->isObsolete());
    }

    public function testIsRefusedReturnsTrueWhenRefused(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_REFUSED)->isRefused());
    }

    public function testIsRefusedReturnsFalseWhenAccepted(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_ACCEPTED)->isRefused());
    }

    public function testIsRemovedReturnsTrueWhenRemoved(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_REMOVED)->isRemoved());
    }

    public function testIsRemovedReturnsFalseWhenPublished(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_PUBLISHED)->isRemoved());
    }

    public function testIsDeletedReturnsTrueWhenDeleted(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_DELETED)->isDeleted());
    }

    public function testIsDeletedReturnsFalseWhenPublished(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_PUBLISHED)->isDeleted());
    }

    public function testIsAbandonedReturnsTrueWhenAbandoned(): void
    {
        self::assertTrue($this->paperWithStatus(Episciences_Paper::STATUS_ABANDONED)->isAbandoned());
    }

    public function testIsAbandonedReturnsFalseWhenSubmitted(): void
    {
        self::assertFalse($this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED)->isAbandoned());
    }

    public function testIsTmpVersionAcceptedReturnsTrueWhenTmpAccepted(): void
    {
        self::assertTrue(
            $this->paperWithStatus(Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED)->isTmpVersionAccepted()
        );
    }

    public function testIsTmpVersionAcceptedReturnsFalseWhenSubmitted(): void
    {
        self::assertFalse(
            $this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED)->isTmpVersionAccepted()
        );
    }

    // -----------------------------------------------------------------------
    // isRevisionRequested
    // -----------------------------------------------------------------------

    /**
     * @dataProvider isRevisionRequestedProvider
     */
    public function testIsRevisionRequested(int $status, bool $expected): void
    {
        self::assertSame($expected, $this->paperWithStatus($status)->isRevisionRequested());
    }

    public static function isRevisionRequestedProvider(): array
    {
        return [
            'minor revision'              => [Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION, true],
            'major revision'              => [Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION, true],
            'tmp version accepted'        => [Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED, true],
            'accepted minor revision'     => [Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION, true],
            'accepted major revision'     => [Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION, true],
            'submitted (not revision)'    => [Episciences_Paper::STATUS_SUBMITTED, false],
            'published (not revision)'    => [Episciences_Paper::STATUS_PUBLISHED, false],
            'accepted (not revision)'     => [Episciences_Paper::STATUS_ACCEPTED, false],
        ];
    }

    // -----------------------------------------------------------------------
    // isAcceptedSubmission
    // -----------------------------------------------------------------------

    /**
     * @dataProvider isAcceptedSubmissionProvider
     */
    public function testIsAcceptedSubmission(int $status, bool $expected): void
    {
        self::assertSame($expected, $this->paperWithStatus($status)->isAcceptedSubmission());
    }

    public static function isAcceptedSubmissionProvider(): array
    {
        return [
            'accepted'                   => [Episciences_Paper::STATUS_ACCEPTED, true],
            'ce author sources waiting'  => [Episciences_Paper::STATUS_CE_WAITING_FOR_AUTHOR_SOURCES, true],
            'tmp version accepted'       => [Episciences_Paper::STATUS_TMP_VERSION_ACCEPTED, true],
            // STATUS_PUBLISHED is NOT in ACCEPTED_SUBMISSIONS
            'published (not accepted)'   => [Episciences_Paper::STATUS_PUBLISHED, false],
            'submitted (not accepted)'   => [Episciences_Paper::STATUS_SUBMITTED, false],
            'refused (not accepted)'     => [Episciences_Paper::STATUS_REFUSED, false],
        ];
    }

    // -----------------------------------------------------------------------
    // isEditableVersion
    // -----------------------------------------------------------------------

    /**
     * @dataProvider isEditableVersionProvider
     */
    public function testIsEditableVersion(int $status, bool $expected): void
    {
        self::assertSame($expected, $this->paperWithStatus($status)->isEditableVersion());
    }

    public static function isEditableVersionProvider(): array
    {
        return [
            'submitted'        => [Episciences_Paper::STATUS_SUBMITTED, true],
            'accepted'         => [Episciences_Paper::STATUS_ACCEPTED, true],
            'published'        => [Episciences_Paper::STATUS_PUBLISHED, true],
            'refused'          => [Episciences_Paper::STATUS_REFUSED, false],
            'deleted'          => [Episciences_Paper::STATUS_DELETED, false],
        ];
    }

    // -----------------------------------------------------------------------
    // canBeAssignedDOI
    // -----------------------------------------------------------------------

    /**
     * @dataProvider canBeAssignedDoiProvider
     */
    public function testCanBeAssignedDoi(int $status, bool $expected): void
    {
        self::assertSame($expected, $this->paperWithStatus($status)->canBeAssignedDOI());
    }

    public static function canBeAssignedDoiProvider(): array
    {
        return [
            'accepted'   => [Episciences_Paper::STATUS_ACCEPTED, true],
            'published'  => [Episciences_Paper::STATUS_PUBLISHED, true],
            'submitted'  => [Episciences_Paper::STATUS_SUBMITTED, false],
            'refused'    => [Episciences_Paper::STATUS_REFUSED, false],
            'obsolete'   => [Episciences_Paper::STATUS_OBSOLETE, false],
        ];
    }

    // -----------------------------------------------------------------------
    // isEditable — calls Episciences_Auth::getUid() which returns 0 in test env
    // -----------------------------------------------------------------------

    public function testIsEditableReturnsTrueForNonOwnerWithEditableStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(1);  // different from Auth uid (0 in test env)
        $paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);
        self::assertTrue($paper->isEditable());
    }

    public function testIsEditableReturnsFalseForNonOwnerWithNonEditableStatus(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(1);
        $paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);
        self::assertFalse($paper->isEditable());
    }

    public function testIsEditableReturnsFalseWhenAuthorIsCurrentUser(): void
    {
        // Auth uid is 0 in test env; paper uid 0 matches → not editable
        $paper = new Episciences_Paper();
        $paper->setUid(0);
        $paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);
        self::assertFalse($paper->isEditable());
    }

    // -----------------------------------------------------------------------
    // isLatestVersion — mocked getLatestVersionId to avoid DB
    // -----------------------------------------------------------------------

    public function testIsLatestVersionReturnsTrueWhenDocidMatchesLatest(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn('123');
        $paper->setDocid(123);
        self::assertTrue($paper->isLatestVersion());
    }

    public function testIsLatestVersionReturnsFalseWhenDocidDoesNotMatch(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn('456');
        $paper->setDocid(123);
        self::assertFalse($paper->isLatestVersion());
    }

    public function testIsLatestVersionWithFalseFromDb(): void
    {
        // Validates Fix 3: getLatestVersionId() can return false (DB miss)
        // With the fixed return type (string|false|null), this must not throw TypeError
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn(false);
        $paper->setDocid(42);
        // (int)false === 0, which differs from 42 → false
        self::assertFalse($paper->isLatestVersion());
    }

    public function testIsLatestVersionWithNullFromDb(): void
    {
        // getLatestVersionId() can return null → (int)null === 0 !== docid → false
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn(null);
        $paper->setDocid(42);
        self::assertFalse($paper->isLatestVersion());
    }

    // -----------------------------------------------------------------------
    // STATUS_DICTIONARY integrity — validates Fix 1
    // -----------------------------------------------------------------------

    public function testStatusDictionaryHasNoEmbeddedDoubleQuotes(): void
    {
        // Fix 1: STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION had trailing "
        foreach (Episciences_Paper::STATUS_DICTIONARY as $code => $label) {
            self::assertStringNotContainsString(
                '"',
                $label,
                "STATUS_DICTIONARY[$code] contains a double-quote character: '$label'"
            );
        }
    }

    public function testStatusDictionaryHasNoSurroundingSingleQuotes(): void
    {
        // Fix 1: STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION had surrounding ''
        foreach (Episciences_Paper::STATUS_DICTIONARY as $code => $label) {
            self::assertSame(
                $label,
                trim($label, "'"),
                "STATUS_DICTIONARY[$code] has surrounding single-quote characters: $label"
            );
        }
    }

    public function testStatusDictionaryCoversAllStatusCodes(): void
    {
        foreach (Episciences_Paper::STATUS_CODES as $code) {
            self::assertArrayHasKey(
                $code,
                Episciences_Paper::STATUS_DICTIONARY,
                "STATUS_DICTIONARY is missing entry for STATUS_CODES item: $code"
            );
        }
    }
}
