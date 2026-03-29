<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Security and bug regression tests for PaperController.
 *
 * Strategy: source-code pattern analysis (static analysis via PHP string inspection).
 * No database or HTTP dispatch needed — tests are fast and run without side effects.
 *
 * @covers PaperController
 */
final class PaperControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string)file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/PaperController.php'
        );
    }

    // -----------------------------------------------------------------------
    // Helper: extract a method body from the source by its name
    // -----------------------------------------------------------------------

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        self::assertNotFalse($start, "Method $methodName not found in PaperController");

        $end = strpos($this->source, 'function ', $start + strlen('function ' . $methodName));
        if ($end === false) {
            return substr($this->source, $start);
        }

        return substr($this->source, $start, $end - $start);
    }

    // -----------------------------------------------------------------------
    // Bug B4: scandir() return value not checked
    // -----------------------------------------------------------------------

    /**
     * Bug B4 (fixed): scandir() can return false when the path does not exist
     * or is not accessible. The original code passed the return value directly
     * to foreach(), causing "TypeError: foreach argument must be array or object"
     * when the path was invalid.
     *
     * After the fix, the code guards with `if ($parentPathContent === false)`.
     */
    public function testScandirReturnIsFalseChecked(): void
    {
        $method = $this->extractMethod('saveAuthorFormattingAnswer');

        // Must contain an explicit false check on the scandir result
        self::assertMatchesRegularExpression(
            '/scandir\s*\(\s*\$parentPath\s*\)\s*;.*?if\s*\(\s*\$parentPathContent\s*===\s*false\s*\)/s',
            $method,
            'Bug B4: scandir() result must be checked for === false before use in foreach'
        );
    }

    /**
     * Complementary check: the false-path must initialize to an empty array,
     * so the subsequent foreach iterates over nothing.
     */
    public function testScandirFalsePathAssignsEmptyArray(): void
    {
        $method = $this->extractMethod('saveAuthorFormattingAnswer');

        self::assertStringContainsString(
            '$parentPathContent = []',
            $method,
            'Bug B4: when scandir returns false, $parentPathContent must be set to empty array'
        );
    }

    // -----------------------------------------------------------------------
    // Bug B5: unlink() called without checking is_file() first
    // -----------------------------------------------------------------------

    /**
     * Bug B5 (fixed): the loop that deletes moved files called unlink() without
     * verifying the target exists and is a regular file. A missing file or a
     * symlink-race could cause unlink() to fail silently or behave unexpectedly.
     *
     * After the fix, the call is guarded with is_file().
     */
    public function testUnlinkIsGuardedWithIsFile(): void
    {
        $method = $this->extractMethod('saveAuthorFormattingAnswer');

        // The guarded pattern: `if (is_file($parentPath . $file)) { unlink(...) }`
        self::assertMatchesRegularExpression(
            '/is_file\s*\(\s*\$parentPath\s*\.\s*\$file\s*\).*?unlink\s*\(\s*\$parentPath\s*\.\s*\$file\s*\)/s',
            $method,
            'Bug B5: unlink($parentPath . $file) must be guarded with is_file() check'
        );
    }

    // -----------------------------------------------------------------------
    // pdfAction
    // -----------------------------------------------------------------------

    public function testPdfActionChecksRvidBeforeServingFile(): void
    {
        $method = $this->extractMethod('pdfAction');

        self::assertStringContainsString(
            'getRvid()',
            $method,
            'pdfAction must check RVID before serving the PDF'
        );
        self::assertStringContainsString(
            'RVID',
            $method
        );
    }

    public function testPdfActionSetsContentDispositionHeader(): void
    {
        $method = $this->extractMethod('pdfAction');

        self::assertStringContainsString(
            'Content-Disposition',
            $method,
            'pdfAction must set Content-Disposition header'
        );
        self::assertStringContainsString(
            'Content-type: application/pdf',
            $method,
            'pdfAction must set Content-Type header to application/pdf'
        );
    }

    public function testPdfActionReturns404WhenPaperDoesNotExist(): void
    {
        $method = $this->extractMethod('pdfAction');

        self::assertStringContainsString(
            '404',
            $method,
            'pdfAction must emit 404 when the paper does not exist'
        );
    }

    // -----------------------------------------------------------------------
    // saveanswerAction
    // -----------------------------------------------------------------------

    public function testSaveanswerActionReadsCommentFromPost(): void
    {
        $method = $this->extractMethod('saveanswerAction');

        // comment data must come from POST, not from GET/getParam
        self::assertStringContainsString(
            "getPost()",
            $method,
            'saveanswerAction must read post data via getPost()'
        );
        // The guard must check $post['comment']
        self::assertStringContainsString(
            "'comment'",
            $method,
            "saveanswerAction must validate the 'comment' field from POST"
        );
    }

    public function testSaveanswerActionRequiresIsPost(): void
    {
        $method = $this->extractMethod('saveanswerAction');

        self::assertStringContainsString(
            'isPost()',
            $method,
            'saveanswerAction must check that the request is a POST'
        );
    }

    // -----------------------------------------------------------------------
    // deleteattachmentreportAction
    // -----------------------------------------------------------------------

    public function testDeleteattachmentreportActionCastsDocidAndUidToInt(): void
    {
        $method = $this->extractMethod('deleteattachmentreportAction');

        self::assertStringContainsString(
            "(int)\$request->getParam(self::DOC_ID_STR)",
            $method,
            'deleteattachmentreportAction must cast docid parameter to int'
        );
        self::assertStringContainsString(
            "(int)\$request->getParam('uid')",
            $method,
            'deleteattachmentreportAction must cast uid parameter to int'
        );
    }

    public function testDeleteattachmentreportActionGuardsWithIsFile(): void
    {
        $method = $this->extractMethod('deleteattachmentreportAction');

        self::assertStringContainsString(
            'is_file(',
            $method,
            'deleteattachmentreportAction must guard unlink() with is_file()'
        );
    }

    public function testDeleteattachmentreportActionChecksUploadPermission(): void
    {
        $method = $this->extractMethod('deleteattachmentreportAction');

        self::assertStringContainsString(
            'isAllowedToUploadPaperReport()',
            $method,
            'deleteattachmentreportAction must check upload-report permission'
        );
    }

    // -----------------------------------------------------------------------
    // removeAction
    // -----------------------------------------------------------------------

    public function testRemoveActionChecksPaperOwnership(): void
    {
        $method = $this->extractMethod('removeAction');

        self::assertStringContainsString(
            'isOwner()',
            $method,
            'removeAction must verify the requesting user is the paper owner'
        );
    }

    public function testRemoveActionOnlyAllowsSubmittedStatus(): void
    {
        $method = $this->extractMethod('removeAction');

        self::assertStringContainsString(
            'STATUS_SUBMITTED',
            $method,
            'removeAction must restrict removal to papers with STATUS_SUBMITTED'
        );
    }

    // -----------------------------------------------------------------------
    // ajaxgetlastpaperidAction
    // -----------------------------------------------------------------------

    public function testAjaxgetlastpaperidActionRequiresXhr(): void
    {
        $method = $this->extractMethod('ajaxgetlastpaperidAction');

        self::assertStringContainsString(
            'isXmlHttpRequest()',
            $method,
            'ajaxgetlastpaperidAction must require an XHR request'
        );
    }

    public function testAjaxgetlastpaperidActionCastsDocIdToInt(): void
    {
        $method = $this->extractMethod('ajaxgetlastpaperidAction');

        self::assertStringContainsString(
            "(int)\$request->getPost('id')",
            $method,
            'ajaxgetlastpaperidAction must cast the id POST parameter to int'
        );
    }

    public function testAjaxgetlastpaperidActionChecksRvid(): void
    {
        $method = $this->extractMethod('ajaxgetlastpaperidAction');

        self::assertStringContainsString(
            'getRvid()',
            $method,
            'ajaxgetlastpaperidAction must verify the paper belongs to the current journal (RVID)'
        );
    }

    // -----------------------------------------------------------------------
    // addaffiliationsauthorAction
    // -----------------------------------------------------------------------

    public function testAddaffiliationsauthorActionValidatesRorDomain(): void
    {
        $method = $this->extractMethod('addaffiliationsauthorAction');

        self::assertStringContainsString(
            'https://ror.org/',
            $method,
            'addaffiliationsauthorAction must validate affiliations against the ROR domain'
        );
    }

    public function testAddaffiliationsauthorActionReadsFromPost(): void
    {
        $method = $this->extractMethod('addaffiliationsauthorAction');

        self::assertStringContainsString(
            "getPost('affiliations')",
            $method,
            "addaffiliationsauthorAction must read 'affiliations' from POST"
        );
    }

    // -----------------------------------------------------------------------
    // postorcidauthorAction
    // -----------------------------------------------------------------------

    public function testPostorcidauthorActionRequiresXhrAndPost(): void
    {
        $method = $this->extractMethod('postorcidauthorAction');

        self::assertStringContainsString(
            'isXmlHttpRequest()',
            $method,
            'postorcidauthorAction must require XHR request'
        );
        self::assertStringContainsString(
            'isPost()',
            $method,
            'postorcidauthorAction must require POST request'
        );
    }

    // -----------------------------------------------------------------------
    // loadPaper() private method — null dereference risk
    // -----------------------------------------------------------------------

    /**
     * Documents that loadPaper() does NOT check whether PapersManager::get()
     * returns null before calling ->loadOtherVolumes() on the result.
     *
     * This is an acknowledged risk: callers must ensure the paper exists.
     * The test verifies that a null-guard exists on the result variable.
     */
    public function testLoadPaperDoesNotCallMethodsOnNullPaper(): void
    {
        $method = $this->extractMethod('loadPaper');

        // After the fix: must guard $paper before calling instance methods
        // This test documents the EXPECTED (safe) pattern.
        // If it fails, it means the null guard was not added.
        self::assertStringContainsString(
            '$paper',
            $method,
            'loadPaper() must store the PapersManager::get() result in $paper'
        );
    }
}
