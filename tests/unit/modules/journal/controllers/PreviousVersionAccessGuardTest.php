<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the access to the previous versions of a paper.
 *
 * Editors and copy editors are unassigned from a version when a new one is
 * submitted: on a previous version, the assignment must be checked on the
 * latest version, and the paper management page must check the permissions
 * of obsolete versions too. Source-analysis tests (ZF1 controllers are not
 * instantiable in isolation) assert that these checks stay in place.
 */
final class PreviousVersionAccessGuardTest extends TestCase
{
    private function extractMethod(string $controller, string $methodName): string
    {
        $source = (string)file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/' . $controller . '.php');

        $start = strpos($source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in $controller");

        $candidates = array_filter([
            strpos($source, "\n    public function ", $start + 1),
            strpos($source, "\n    protected function ", $start + 1),
            strpos($source, "\n    private function ", $start + 1),
        ], static fn($position) => $position !== false);
        $stop = $candidates ? min($candidates) : strlen($source);

        return substr($source, $start, $stop - $start);
    }

    /**
     * Extract a statement from its first token to its ending semicolon.
     */
    private function extractStatement(string $method, string $firstToken): string
    {
        $start = strpos($method, $firstToken);
        self::assertNotFalse($start, "Statement $firstToken not found");

        $end = strpos($method, ';', $start);
        self::assertNotFalse($end);

        return substr($method, $start, $end - $start);
    }

    public function testPaperManagementPageChecksThePermissionsOfObsoleteVersions(): void
    {
        $method = $this->extractMethod('AdministratepaperController', 'viewAction');

        $permissionsCheck = strpos($method, '$this->checkPermissions($review, $paper)');
        $obsoleteBlock = strpos($method, 'if ($paper->isObsolete())');

        self::assertNotFalse($permissionsCheck, 'viewAction must check the permissions');
        self::assertNotFalse($obsoleteBlock);
        self::assertLessThan($obsoleteBlock, $permissionsCheck,
            'the permissions must be checked before the obsolete version handling, for every version');
        self::assertSame(1, substr_count($method, 'checkPermissions('),
            'the permissions check must not depend on the paper status');
    }

    /**
     * @dataProvider paperPageAssignmentChecks
     */
    public function testPaperPageResolvesTheAssignmentOnTheLatestVersion(string $statement, string $expectedCheck): void
    {
        $checks = $this->extractStatement($this->extractMethod('PaperController', 'viewAction'), $statement);

        self::assertStringContainsString($expectedCheck, $checks,
            "$statement must also accept the users assigned to the latest version");
        self::assertStringNotContainsString('->getEditor(', $checks);
        self::assertStringNotContainsString('->getCopyEditor(', $checks);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paperPageAssignmentChecks(): iterable
    {
        yield 'ratings visibility' => ['$isVisibleRatings =', 'isAssignedEditorOrCopyEditor('];
        yield 'report details' => ['$this->view->isAllowedToSeeReportDetails =', 'isAssignedEditor('];
        yield 'non-public details' => ['$commonTest =', 'isAssignedEditorOrCopyEditor('];
    }
}
