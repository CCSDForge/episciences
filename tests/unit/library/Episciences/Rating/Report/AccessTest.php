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

            // Public: PUBLIC only
            'public + public' => [
                Episciences_Rating_Report_Access::ROLE_PUBLIC,
                Episciences_Rating_Criterion::VISIBILITY_PUBLIC,
                true,
            ],
            'public + contributor' => [
                Episciences_Rating_Report_Access::ROLE_PUBLIC,
                Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR,
                false,
            ],
            'public + editors' => [
                Episciences_Rating_Report_Access::ROLE_PUBLIC,
                Episciences_Rating_Criterion::VISIBILITY_EDITORS,
                false,
            ],
            'public + null' => [
                Episciences_Rating_Report_Access::ROLE_PUBLIC,
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
            123, // uid
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
            123,
            $review,
            false, // isSecretary
            true,  // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    public function testResolveRoleReturnsReportAuthorForReviewer(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(456);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            456, // uid matches report author
            $review,
            false,
            false,
            false,
            false
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRoleReturnsReportAuthorForOnbehalfReviewer(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(456);
        $report->method('getOnbehalf_uid')->willReturn(789);

        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            789, // uid matches onbehalf_uid
            $review,
            false,
            false,
            false,
            false
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRoleReturnsPaperAuthorForOwner(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(111);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(456);
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            111, // uid matches paper author
            $review,
            false,
            false,
            false,
            false
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR, $result);
    }

    public function testResolveRoleReturnsPublicForAnonymous(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $report = $this->createMock(Episciences_Rating_Report::class);
        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            null, // not logged in
            $review,
            false,
            false,
            false,
            false
        );

        self::assertSame(Episciences_Rating_Report_Access::ROLE_PUBLIC, $result);
    }

    public function testResolveRolePrecedenceReportAuthorOverPaperAuthor(): void
    {
        // A reviewer who is also the paper author should be REPORT_AUTHOR
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(123); // same as uid
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(123); // same as uid
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturn(false);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            123, // is both paper author and report author
            $review,
            false,
            false,
            false,
            false
        );

        // Report author has higher precedence
        self::assertSame(Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR, $result);
    }

    public function testResolveRolePrecedenceEditorialStaffOverReportAuthor(): void
    {
        // A secretary who is also the report author should be EDITORIAL_STAFF
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(456); // same as uid
        $report->method('getOnbehalf_uid')->willReturn(null);

        $review = $this->createMock(Episciences_Review::class);

        $result = Episciences_Rating_Report_Access::resolveRole(
            $paper,
            $report,
            456, // is both secretary and report author
            $review,
            true,  // isSecretary
            false,
            false,
            false
        );

        // Editorial staff has higher precedence
        self::assertSame(Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF, $result);
    }

    // -------------------------------------------------------------------------
    // mayDownloadAttachment() - open peer review scenarios
    // -------------------------------------------------------------------------

    /**
     * Anonymous visitor can download public attachment
     * when SETTING_SHOW_RATINGS is enabled and paper is published.
     */
    public function testAnonymousCanDownloadPublicAttachmentWhenOpenPeerReview(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(999);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(888);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_SHOW_RATINGS, true],
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            null, // anonymous user
            $review,
            false, // isSecretary
            false, // isGuestEditor
            false, // isEditor
            false  // isCopyEditor
        );

        self::assertTrue($result);
    }

    /**
     * Anonymous visitor cannot download contributor attachment
     * even when SETTING_SHOW_RATINGS is enabled.
     */
    public function testAnonymousCannotDownloadContributorAttachment(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('contributor_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(999);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(888);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_SHOW_RATINGS, true],
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'contributor_file.pdf',
            null, // anonymous user
            $review,
            false,
            false,
            false,
            false
        );

        self::assertFalse($result);
    }

    /**
     * Anonymous visitor cannot download anything when SETTING_SHOW_RATINGS is disabled.
     */
    public function testAnonymousCannotDownloadWhenShowRatingsDisabled(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(999);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(888);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_SHOW_RATINGS, false], // disabled
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            null,
            $review,
            false,
            false,
            false,
            false
        );

        self::assertFalse($result);
    }

    /**
     * Anonymous visitor cannot download when paper is not published.
     */
    public function testAnonymousCannotDownloadWhenPaperNotPublished(): void
    {
        $criterion = $this->createMock(Episciences_Rating_Criterion::class);
        $criterion->method('getAttachment')->willReturn('public_file.pdf');
        $criterion->method('getVisibility')->willReturn(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);

        $report = $this->createMock(Episciences_Rating_Report::class);
        $report->method('getUid')->willReturn(999);
        $report->method('getOnbehalf_uid')->willReturn(null);
        $report->method('getCriteria')->willReturn([$criterion]);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getUid')->willReturn(888);
        $paper->method('isPublished')->willReturn(false); // not published
        $paper->method('getEditor')->willReturn(false);
        $paper->method('getCopyEditor')->willReturn(false);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnMap([
            [Episciences_Review::SETTING_SHOW_RATINGS, true],
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS, false],
            [Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS, false],
        ]);

        $result = Episciences_Rating_Report_Access::mayDownloadAttachment(
            $paper,
            $report,
            'public_file.pdf',
            null,
            $review,
            false,
            false,
            false,
            false
        );

        self::assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Role constants
    // -------------------------------------------------------------------------

    public function testRoleConstantsAreDefined(): void
    {
        self::assertSame('editorial_staff', Episciences_Rating_Report_Access::ROLE_EDITORIAL_STAFF);
        self::assertSame('report_author', Episciences_Rating_Report_Access::ROLE_REPORT_AUTHOR);
        self::assertSame('paper_author', Episciences_Rating_Report_Access::ROLE_PAPER_AUTHOR);
        self::assertSame('public', Episciences_Rating_Report_Access::ROLE_PUBLIC);
    }
}
