<?php

declare(strict_types=1);

namespace unit\library\Episciences\Rating\Report;

use Episciences_Paper;
use Episciences_Rating_Criterion;
use Episciences_Rating_Report;
use Episciences_Rating_Report_Access;
use Episciences_Review;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Rating_Report_Access.
 *
 * Tests the access control logic for report attachments, including role
 * resolution and criterion visibility filtering.
 *
 * @covers Episciences_Rating_Report_Access
 */
class AccessTest extends TestCase
{
    // Test UIDs - arbitrary values, only the relationships matter
    private const UID_PAPER_AUTHOR = 123;
    private const UID_REPORT_AUTHOR = 456;
    private const UID_ONBEHALF = 789;
    private const UID_OTHER_PAPER_AUTHOR = 888;
    private const UID_UNRELATED = 999;
    private const UID_INVALID_ZERO = 0;  // boundary case: uid = 0 should be rejected

    // -------------------------------------------------------------------------
    // mayReadCriterion() - visibility matrix
    // -------------------------------------------------------------------------

    /**
     * @dataProvider criterionVisibilityMatrixProvider
     */
    public function testMayReadCriterionMatrix(string $role, ?string $visibility, bool $expected): void
    {
        $result = Episciences_Rating_Report_Access::mayReadCriterion($role, $visibility);
        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string, 1: string|null, 2: bool}>
     */
    public static function criterionVisibilityMatrixProvider(): array
    {
        return [
            // Editorial staff: full access
            'editorial_staff + public' => [
                Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF,
                Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                true,
            ],
            'editorial_staff + contributor' => [
                Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF,
                Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR,
                true,
            ],
            'editorial_staff + editors' => [
                Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF,
                Episciences_Rating_Criterion::VISIBILITY_EDITORS,
                true,
            ],
            'editorial_staff + null' => [
                Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF,
                null,
                true,
            ],

            // Report author: full access
            'report_author + public' => [
                Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                true,
            ],
            'report_author + contributor' => [
                Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR,
                true,
            ],
            'report_author + editors' => [
                Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_EDITORS,
                true,
            ],
            'report_author + null' => [
                Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR,
                null,
                true,
            ],

            // Paper author: PUBLIC and CONTRIBUTOR only
            'paper_author + public' => [
                Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                true,
            ],
            'paper_author + contributor' => [
                Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR,
                true,
            ],
            'paper_author + editors' => [
                Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR,
                Episciences_Rating_Criterion::VISIBILITY_EDITORS,
                false,
            ],
            'paper_author + null' => [
                Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR,
                null,
                false,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // findAttachmentVisibility()
    // -------------------------------------------------------------------------

    public function testFindAttachmentVisibilityReturnsVisibilityWhenFound(): void
    {
        $criterion1 = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion1->method('getAttachment')->willReturn('other_file.pdf');
        $criterion1->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_EDITORS);

        $criterion2 = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion2->method('getAttachment')->willReturn('target_file.pdf');
        $criterion2->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getCriteria')->willReturn([$criterion1, $criterion2]);

        $result = Episciences_Rating_Report_Access::findAttachmentVisibility($report, 'target_file.pdf');

        self::assertSame(Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR, $result);
    }

    public function testFindAttachmentVisibilityReturnsNullWhenNotFound(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('other_file.pdf');

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getCriteria')->willReturn([$criterion]);

        $result = Episciences_Rating_Report_Access::findAttachmentVisibility($report, 'missing_file.pdf');

        self::assertNull($result);
    }

    public function testFindAttachmentVisibilityReturnsNullWhenNoCriteria(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getCriteria')->willReturn([]);

        $result = Episciences_Rating_Report_Access::findAttachmentVisibility($report, 'any_file.pdf');

        self::assertNull($result);
    }

    public function testFindAttachmentVisibilityReturnsNullWhenCriteriaIsNull(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getCriteria')->willReturn(null);

        $result = Episciences_Rating_Report_Access::findAttachmentVisibility($report, 'any_file.pdf');

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // resolveRole() - precedence tests
    // -------------------------------------------------------------------------

    public function testResolveRoleReturnsEditorialStaffForSecretary(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $report = $this->createMock(Episciences_Rating_Report::class);
        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            true,  // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    public function testResolveRoleReturnsEditorialStaffForGuestEditor(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $report = $this->createMock(Episciences_Rating_Report::class);
        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            true,  // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    // -------------------------------------------------------------------------
    // resolveRole() - editor encapsulation tests
    // -------------------------------------------------------------------------

    /**
     * Global editor with encapsulation disabled is editorial staff.
     */
    public function testGlobalEditorWithoutEncapsulationIsEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            true,  // isEditor - global editor role
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    /**
     * Global editor with encapsulation enabled but not assigned to paper is NOT editorial staff.
     */
    public function testGlobalEditorWithEncapsulationNotAssignedIsNotEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);  // not assigned to this paper
        $paper->method('getCopyEditor')->willReturn(false);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, true],  // encapsulation ON
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,  // different from paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            true,  // isEditor - global editor role
            false  // isCopyEditor
        );

        // Not editorial staff, not report author, not paper author → null
        self::assertNull($result);
    }

    /**
     * Global editor with encapsulation enabled BUT assigned to paper IS editorial staff.
     * Paper assignment overrides encapsulation setting.
     */
    public function testGlobalEditorWithEncapsulationButAssignedIsEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(true);  // assigned to this paper
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, true],  // encapsulation ON
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            true,  // isEditor - global editor role
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    /**
     * Non-global editor but assigned to paper is editorial staff.
     */
    public function testAssignedEditorWithoutGlobalRoleIsEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(true);  // assigned to this paper
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor - NOT global editor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    // -------------------------------------------------------------------------
    // resolveRole() - copy editor encapsulation tests
    // -------------------------------------------------------------------------

    /**
     * Global copy editor with encapsulation disabled is editorial staff.
     */
    public function testGlobalCopyEditorWithoutEncapsulationIsEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            true   // isCopyEditor - global copy editor role
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    /**
     * Global copy editor with encapsulation enabled but not assigned is NOT editorial staff.
     */
    public function testGlobalCopyEditorWithEncapsulationNotAssignedIsNotEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);  // not assigned
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, true],  // encapsulation ON
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            true   // isCopyEditor - global copy editor role
        );

        self::assertNull($result);
    }

    /**
     * Global copy editor with encapsulation enabled BUT assigned to paper IS editorial staff.
     */
    public function testGlobalCopyEditorWithEncapsulationButAssignedIsEditorialStaff(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(true);  // assigned to this paper

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, true],  // encapsulation ON
        ]);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            true   // isCopyEditor - global copy editor role
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    // -------------------------------------------------------------------------
    // resolveRole() - boundary tests
    // -------------------------------------------------------------------------

    /**
     * User with uid = 0 should not match as report author.
     */
    public function testUidZeroDoesNotMatchReportAuthor(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_INVALID_ZERO);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_INVALID_ZERO,  // uid = 0 should be rejected even if it matches report uid
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        // uid = 0 is not a valid user, should return null
        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // resolveRole() - report author tests
    // -------------------------------------------------------------------------

    public function testResolveRoleReturnsReportAuthorForReviewer(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_REPORT_AUTHOR);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_REPORT_AUTHOR,  // uid matches report author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRoleReturnsReportAuthorForOnbehalfReviewer(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_REPORT_AUTHOR);
        $report->method('getOnbehalf_uid')->willReturn(self::UID_ONBEHALF);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_ONBEHALF,  // uid matches onbehalf_uid
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRoleReturnsPaperAuthorForOwner(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_REPORT_AUTHOR);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR, $result);
    }

    public function testResolveRoleReturnsNullForAnonymous(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            null,  // not logged in
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertNull($result);
    }

    public function testResolveRolePrecedenceReportAuthorOverPaperAuthor(): void
    {
        // A reviewer who is also the paper author should be REPORT_AUTHOR
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);  // same person is report author
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_PAPER_AUTHOR,  // is both paper author and report author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        // Report author has higher precedence
        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRolePrecedenceReportAuthorOverEditorialStaff(): void
    {
        // A secretary who is also the report author should be REPORT_AUTHOR,
        // so they keep full access to their own report (including WIP) even
        // though they also hold an editorial role.
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_REPORT_AUTHOR);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            self::UID_REPORT_AUTHOR,  // is both secretary and report author
            $review,
            true,  // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        // Report author has higher precedence
        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    // -------------------------------------------------------------------------
    // mayDownloadAttachment() - anonymous users denied
    // -------------------------------------------------------------------------

    /**
     * Anonymous users cannot download attachments (requires authentication).
     */
    public function testAnonymousUserCannotDownloadAttachment(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            null,  // anonymous user
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false  // isReportsVisibleToAuthor
        );

        self::assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // mayDownloadAttachment() - paper author scenarios
    // -------------------------------------------------------------------------

    /**
     * Paper author can download public attachment when reports are visible.
     */
    public function testPaperAuthorCanDownloadPublicAttachmentWhenReportsVisible(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR); // paper author
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            true   // isReportsVisibleToAuthor
        );

        self::assertTrue($result);
    }

    /**
     * Paper author can download contributor attachment when reports are visible.
     */
    public function testPaperAuthorCanDownloadContributorAttachmentWhenReportsVisible(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('contributor_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'contributor_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            true   // isReportsVisibleToAuthor
        );

        self::assertTrue($result);
    }

    /**
     * Paper author cannot download editors-only attachment.
     */
    public function testPaperAuthorCannotDownloadEditorsAttachment(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('editors_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_EDITORS);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'editors_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            true   // isReportsVisibleToAuthor
        );

        self::assertFalse($result);
    }

    /**
     * Paper author cannot download when reports are not visible (wrong status).
     */
    public function testPaperAuthorCannotDownloadWhenReportsNotVisible(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false  // isReportsVisibleToAuthor = false (paper in wrong status)
        );

        self::assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // mayDownloadAttachment() - report status filter (STATUS_WIP)
    // -------------------------------------------------------------------------

    /**
     * Paper author cannot download from a reopened (WIP) report.
     */
    public function testPaperAuthorCannotDownloadFromReopenedReport(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_WIP);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            true   // isReportsVisibleToAuthor
        );

        self::assertFalse($result);
    }

    /**
     * Paper author cannot download from a pending report.
     */
    public function testPaperAuthorCannotDownloadFromPendingReport(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_PENDING);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            self::UID_PAPER_AUTHOR,  // uid matches paper author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            true   // isReportsVisibleToAuthor
        );

        self::assertFalse($result);
    }

    /**
     * Editorial staff cannot download from a WIP report.
     * Draft reports are private to the reviewer; editorial staff can only access completed reports.
     */
    public function testEditorialStaffCannotDownloadFromWipReport(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_WIP);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_PAPER_AUTHOR,  // secretary, different from paper author
            $review,
            true,  // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false  // isReportsVisibleToAuthor
        );

        self::assertFalse($result);
    }

    /**
     * Editorial staff can download from a completed report.
     */
    public function testEditorialStaffCanDownloadFromCompletedReport(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_PAPER_AUTHOR,  // secretary, different from paper author
            $review,
            true,  // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false  // isReportsVisibleToAuthor
        );

        self::assertTrue($result);
    }

    /**
     * Report author can download from their own WIP report.
     * The reviewer can access their own draft report while writing it.
     */
    public function testReportAuthorCanDownloadFromOwnWipReport(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_REPORT_AUTHOR);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_WIP);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_REPORT_AUTHOR,  // uid matches report author
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false  // isReportsVisibleToAuthor
        );

        self::assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // mayDownloadAttachment() - COI (Conflict of Interest) scenarios
    // -------------------------------------------------------------------------

    /**
     * Editor with declared COI cannot download report attachment.
     */
    public function testEditorWithConflictCannotDownloadAttachment(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            true,  // isEditor
            false, // isCopyEditor
            false, // isReportsVisibleToAuthor
            true   // hasConflict = true
        );

        self::assertFalse($result);
    }

    /**
     * Secretary with declared COI cannot download report attachment.
     */
    public function testSecretaryWithConflictCannotDownloadAttachment(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_PAPER_AUTHOR,
            $review,
            true,  // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false, // isCopyEditor
            false, // isReportsVisibleToAuthor
            true   // hasConflict = true
        );

        self::assertFalse($result);
    }

    /**
     * Editor without COI can download completed report attachment.
     */
    public function testEditorWithoutConflictCanDownloadAttachment(): void
    {
        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(self::UID_UNRELATED);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getStatus')->willReturn(Episciences_Rating_Report::STATUS_COMPLETED);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(self::UID_OTHER_PAPER_AUTHOR);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'any_file.pdf',
            self::UID_PAPER_AUTHOR,
            $review,
            false, // isSecretary
            false, // isGuestEditor
            true,  // isEditor
            false, // isCopyEditor
            false, // isReportsVisibleToAuthor
            false  // hasConflict = false
        );

        self::assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Role constants
    // -------------------------------------------------------------------------

    public function testRoleConstantsAreDefined(): void
    {
        self::assertSame('editorial_staff', Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF);
        self::assertSame('report_author', Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR);
        self::assertSame('paper_author', Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR);
    }
}
