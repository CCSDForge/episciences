<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the security fixes in FileController (audit findings
 * F-1, F-2, F-4).
 *
 * ZF1 module controllers are not Composer-autoloaded and need the full request
 * stack to instantiate, so — like the other controller tests in this suite — we
 * analyse the source to assert the security guards remain wired. The actual
 * path-confinement behaviour is covered behaviourally by
 * DefaultControllerResolveSafePathTest.
 *
 * @covers FileController
 */
final class FileControllerSecurityTest extends TestCase
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
    // F-1 — path confinement wired through loadFile()
    // -----------------------------------------------------------------------

    public function testLoadFileConfinesViaResolveSafePath(): void
    {
        $method = $this->extractMethod('loadFile');
        self::assertStringContainsString('resolveSafePath', $method,
            'loadFile() must confine the path through resolveSafePath()');
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
    // F-2 — deleteAction: authenticated POST + sanitisation + confinement
    // -----------------------------------------------------------------------

    public function testDeleteActionRequiresPostAndAuthentication(): void
    {
        $method = $this->extractMethod('deleteAction');

        self::assertStringContainsString('isPost()', $method,
            'deleteAction() must require a POST request');
        self::assertStringContainsString('Episciences_Auth::isLogged()', $method,
            'deleteAction() must require an authenticated user (it was reachable anonymously via XHR)');
        self::assertStringContainsString('403', $method,
            'deleteAction() must reject unauthorised access with HTTP 403');
    }

    public function testDeleteActionSanitisesFilename(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('basename(', $method,
            'deleteAction() must strip any directory component from the file name');
    }

    public function testDeleteActionConfinesTargetBeforeUnlink(): void
    {
        $method = $this->extractMethod('deleteAction');
        self::assertStringContainsString('resolveSafePath', $method,
            'deleteAction() must confine the deletion target via resolveSafePath()');

        // The unlink must happen on the confined path, not the raw concatenation.
        $resolvePos = strpos($method, 'resolveSafePath');
        $unlinkPos  = strpos($method, 'unlink(');
        self::assertNotFalse($unlinkPos, 'deleteAction() must call unlink()');
        self::assertLessThan($unlinkPos, $resolvePos,
            'The path must be confined before unlink() is called');
    }

    // -----------------------------------------------------------------------
    // F-4 — reportAction: access control on confidential rating reports
    // -----------------------------------------------------------------------

    public function testReportActionEnforcesAccessControl(): void
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

    public function testReportActionHidesExistenceWith404(): void
    {
        $method = $this->extractMethod('reportAction');
        self::assertStringContainsString('404', $method,
            'reportAction() must return 404 (not 403) so it does not disclose the report existence');
    }
}
