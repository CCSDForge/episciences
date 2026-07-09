<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Source-level regression guards for PaperController::getmasterfileformAction()
 * and PaperController::savemasterfileAction().
 *
 * ZF1 controllers are not instantiable in isolation, so — like the other
 * controller source-analysis tests in this suite — these assertions check
 * that the corrected guard ordering stays in place rather than executing
 * the actions against a real request/DB.
 *
 * Regressions specifically guarded against:
 *   - getmasterfileformAction() must reject callers who fail
 *     isAllowedToEditMasterFile() instead of only checking the paper exists
 *     (an earlier version disclosed the file list to any authenticated role
 *     with access to the action, regardless of paper ownership).
 *   - savemasterfileAction() must persist the previous main file's cleared
 *     is_main flag (an earlier version mutated it in memory only, leaving
 *     two files flagged is_main=1 in the database after a single switch).
 *
 * @covers PaperController::getmasterfileformAction
 * @covers PaperController::savemasterfileAction
 */
final class PaperControllerMasterFileGuardTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/PaperController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in PaperController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    // =========================================================================
    // getmasterfileformAction()
    // =========================================================================

    public function testGetFormChecksIsAllowedToEditMasterFile(): void
    {
        $method = $this->extractMethod('getmasterfileformAction');

        self::assertStringContainsString(
            '$paper->isAllowedToEditMasterFile()',
            $method,
            'getmasterfileformAction() must reject callers who are not allowed to edit the master file.'
        );
    }

    public function testGetFormPermissionCheckRunsAfterThePaperIsLoadedButBeforeRendering(): void
    {
        $method = $this->extractMethod('getmasterfileformAction');

        $paperLoadedPos = strpos($method, 'Episciences_PapersManager::get(');
        $permissionCheckPos = strpos($method, 'isAllowedToEditMasterFile()');
        $renderPos = strpos($method, 'renderScript(');

        self::assertNotFalse($paperLoadedPos);
        self::assertNotFalse($permissionCheckPos);
        self::assertNotFalse($renderPos);
        self::assertLessThan($permissionCheckPos, $paperLoadedPos, 'The paper must be loaded before the permission check.');
        self::assertLessThan($renderPos, $permissionCheckPos, 'The permission check must run before the form is rendered.');
    }

    public function testGetFormReturnsEarlyWhenDocIdIsMissing(): void
    {
        $method = $this->extractMethod('getmasterfileformAction');

        self::assertMatchesRegularExpression(
            '/if\s*\(\s*!\s*\$docId\s*\)\s*\{\s*return;/',
            $method,
            'A missing docid must short-circuit the action.'
        );
    }

    public function testGetFormReturns404WhenPaperDoesNotExist(): void
    {
        $method = $this->extractMethod('getmasterfileformAction');

        $instanceofPos = strpos($method, '!$paper instanceof Episciences_Paper');
        $notFoundPos = strpos($method, 'setHttpResponseCode(404)');

        self::assertNotFalse($instanceofPos);
        self::assertNotFalse($notFoundPos);
        self::assertGreaterThan($instanceofPos, $notFoundPos, '404 must be set inside the "paper not found" guard.');
    }

    // =========================================================================
    // savemasterfileAction()
    // =========================================================================

    public function testSaveFormOnlyAcceptsPostAjaxRequests(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString('$request->isPost()', $method);
        self::assertStringContainsString('$request->isXmlHttpRequest()', $method);
    }

    public function testSaveFormChecksIsAllowedToEditMasterFile(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString(
            '$paper->isAllowedToEditMasterFile()',
            $method,
            'savemasterfileAction() must reject callers who are not allowed to edit the master file.'
        );
    }

    public function testSaveFormValidatesTargetFileBelongsToThePaper(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString(
            '$targetFile->getDocId() !== $paper->getDocid()',
            $method,
            'The requested master file must belong to the paper being edited.'
        );
    }

    public function testPreviousMasterFileIsPersistedBeforeTargetFileIsSaved(): void
    {
        // Regression guard for the "old main file never desactivated" bug:
        // $previousMasterFile->save() must be called, and it must happen
        // before $targetFile->save() so a crash midway can't leave two
        // files flagged is_main=1.
        $method = $this->extractMethod('savemasterfileAction');

        $previousSetIsMainPos = strpos($method, '$previousMasterFile->setIsMain()');
        $previousSavePos = strpos($method, '$previousMasterFile->save()');
        $targetSavePos = strpos($method, '$targetFile->save()');

        self::assertNotFalse($previousSetIsMainPos, '$previousMasterFile->setIsMain() must be called.');
        self::assertNotFalse($previousSavePos, '$previousMasterFile->save() must be called — mutating the in-memory flag alone is not enough.');
        self::assertNotFalse($targetSavePos);

        self::assertLessThan($previousSavePos, $previousSetIsMainPos);
        self::assertLessThan($targetSavePos, $previousSavePos, 'The previous main file must be saved before the new one is.');
    }

    public function testTargetFileIsFlaggedMainBeforeBeingSaved(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        $setIsMainTruePos = strpos($method, '$targetFile->setIsMain(true)');
        $targetSavePos = strpos($method, '$targetFile->save()');

        self::assertNotFalse($setIsMainTruePos);
        self::assertNotFalse($targetSavePos);
        self::assertLessThan($targetSavePos, $setIsMainTruePos);
    }

    public function testSaveFormShortCircuitsWhenTargetIsAlreadyTheMainFile(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString(
            '$previousMasterFile->getId() === $targetFile->getId()',
            $method,
            'Switching to the already-active main file must be a no-op.'
        );
    }

    public function testSaveFormSetsJsonContentType(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString("header('Content-Type: application/json", $method);
    }

    public function testSaveFormResultIncludesTargetId(): void
    {
        $method = $this->extractMethod('savemasterfileAction');

        self::assertStringContainsString("\$result['targetId'] = \$targetFile->getId();", $method);
    }
}