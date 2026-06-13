<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for path handling and request handling in FileController.
 *
 * ZF1 module controllers are not Composer-autoloaded and need the full request
 * stack to instantiate, so — like the other controller tests in this suite — we
 * analyse the source to assert the expected handling stays in place. The actual
 * path-confinement behaviour is covered by DefaultControllerResolveSafePathTest.
 *
 * @covers FileController
 */
final class FileControllerPathHandlingTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/FileController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in FileController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    // -----------------------------------------------------------------------
    // loadFile() resolves paths under a trusted base
    // -----------------------------------------------------------------------

    public function testLoadFileResolvesPathThroughResolveSafePath(): void
    {
        $method = $this->extractMethod('loadFile');
        self::assertStringContainsString('resolveSafePath', $method,
            'loadFile() must resolve the path through resolveSafePath()');
    }

    public function testReadActionsPassATrustedBaseToLoadFile(): void
    {
        // Each read action must call loadFile() with a base directory argument
        // (REVIEW_FILES_PATH or a document directory), i.e. the 2-arg form.
        foreach (['indexAction', 'tmpAction', 'paperAction'] as $action) {
            $method = $this->extractMethod($action);
            self::assertMatchesRegularExpression(
                '/loadFile\(\s*(REVIEW_FILES_PATH|Episciences_PapersManager::buildDocumentPath)/',
                $method,
                "$action must pass a trusted base directory to loadFile()"
            );
        }
    }

    // -----------------------------------------------------------------------
    // deleteAction(): authenticated POST + name sanitisation + confinement
    // -----------------------------------------------------------------------

    public function testDeleteActionRequiresAuthenticatedPost(): void
    {
        $method = $this->extractMethod('deleteAction');

        self::assertStringContainsString('isPost()', $method,
            'deleteAction() must require a POST request');
        self::assertStringContainsString('Episciences_Auth::isLogged()', $method,
            'deleteAction() must require an authenticated user');
        self::assertStringContainsString('403', $method,
            'deleteAction() must answer 403 when the request is not an authenticated POST');
    }

    public function testDeleteActionSanitisesFileName(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('basename(', $method,
            'deleteAction() must strip any directory component from the file name');
    }

    public function testDeleteActionResolvesTargetBeforeUnlink(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('resolveSafePath', $method,
            'deleteAction() must resolve the target through resolveSafePath()');

        // The unlink must happen on the resolved path, not the raw concatenation.
        $resolvePos = strpos($method, 'resolveSafePath');
        $unlinkPos  = strpos($method, 'unlink(');
        self::assertNotFalse($unlinkPos, 'deleteAction() must call unlink()');
        self::assertLessThan($unlinkPos, $resolvePos,
            'The path must be resolved before unlink() is called');
    }

    // -----------------------------------------------------------------------
    // reportAction(): permission check before serving a report attachment
    // -----------------------------------------------------------------------

    public function testReportActionChecksPermissions(): void
    {
        $method = $this->extractMethod('reportAction');

        self::assertStringContainsString('Episciences_Auth::isLogged()', $method,
            'reportAction() must require authentication');
        self::assertStringContainsString('isAllowedToUploadPaperReport()', $method,
            'reportAction() must allow editorial staff');
        self::assertStringContainsString('getEditor(', $method,
            "reportAction() must allow the paper's editor");
        self::assertStringContainsString('$report->getUid()', $method,
            'reportAction() must allow the reviewer who authored the report');
    }

    public function testReportActionUses404WhenNotPermitted(): void
    {
        $method = $this->extractMethod('reportAction');
        self::assertStringContainsString('404', $method,
            'reportAction() must answer 404 when the report is missing or not permitted');
    }

    // -----------------------------------------------------------------------
    // upload/delete: request token + relation to the targeted document
    // -----------------------------------------------------------------------

    public function testUploadAndDeleteValidateTheRequestToken(): void
    {
        foreach (['uploadAction', 'deleteAction'] as $action) {
            $method = $this->extractMethod($action);
            self::assertStringContainsString(
                'Episciences_Csrf_Helper::validateRequestToken(',
                $method,
                "$action() must validate the per-session request token"
            );
        }
    }

    public function testUploadAndDeleteCheckTheRelationToTheDocument(): void
    {
        foreach (['uploadAction', 'deleteAction'] as $action) {
            $method = $this->extractMethod($action);

            $guardPos = strpos($method, 'isAllowedToHandleDocumentFiles(');
            self::assertNotFalse($guardPos,
                "$action() must check the user's relation to the targeted document");

            // The check must come before the storage folder is built / used.
            $folderPos = strpos($method, 'buildStorageFolder(');
            self::assertNotFalse($folderPos, "$action() must build the storage folder");
            self::assertLessThan($folderPos, $guardPos,
                "$action() must check the relation before resolving the storage folder");
        }
    }

    public function testDocumentRelationGuardCoversTheExpectedRelations(): void
    {
        $method = $this->extractMethod('isAllowedToHandleDocumentFiles');

        self::assertStringContainsString('isOwnerOrCoAuthor()', $method,
            'the guard must accept the contributor (owner or co-author)');
        self::assertStringContainsString('Episciences_Auth::isAllowedToManagePaper()', $method,
            'the guard must accept the users allowed to manage papers');

        foreach (['getEditor(', 'getCopyEditor(', 'getReviewer('] as $relation) {
            self::assertStringContainsString($relation, $method,
                "the guard must accept the paper relation $relation)");
        }

        // No document targeted: the session-scoped directory needs no relation.
        self::assertStringContainsString('if (!$docId && !$paperId)', $method,
            'the guard must keep allowing the session-scoped attachments directory');
    }
}
