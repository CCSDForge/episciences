<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the access-control fix in AdministrategraphabstractController
 * (audit finding F-3): the request guard logic was inverted, letting an
 * unauthorised user through a well-formed AJAX POST, and $docId was used unsanitised
 * in filesystem paths.
 *
 * Source-analysis tests (ZF1 controllers are not instantiable in isolation).
 *
 * @covers AdministrategraphabstractController
 */
final class AdministrategraphabstractControllerSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/AdministrategraphabstractController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found");
        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        return $end === false
            ? substr($this->source, (int) $start)
            : substr($this->source, (int) $start, (int) $end - (int) $start);
    }

    /**
     * The corrected guard must DENY when the user is not authorised, i.e. the
     * negated-and form: (... ) || (!isAllowedToManagePaper() && !isAuthor()).
     * The previous (buggy) form used a non-negated AND that let unauthorised
     * users pass.
     */
    public function testRejectionGuardUsesNegatedAuthorisation(): void
    {
        foreach (['addgraphabsAction', 'deletegraphabsAction'] as $action) {
            $method = $this->extractMethod($action);

            self::assertStringContainsString('!Episciences_Auth::isAllowedToManagePaper()', $method,
                "$action must deny when the user cannot manage the paper");
            self::assertStringContainsString('!Episciences_Auth::isAuthor()', $method,
                "$action must deny when the user is not an author");

            // Must NOT use the old positive form inside the guard.
            self::assertStringNotContainsString(
                '&& (Episciences_Auth::isAllowedToManagePaper() || Episciences_Auth::isAuthor())',
                $method,
                "$action must not use the inverted (buggy) authorisation condition"
            );
        }
    }

    /**
     * $docId must be cast to int before being used in filesystem paths
     * (move_uploaded_file / unlink), preventing path traversal via the docId.
     */
    public function testDocIdIsCastToInt(): void
    {
        foreach (['addgraphabsAction', 'deletegraphabsAction'] as $action) {
            $method = $this->extractMethod($action);
            self::assertStringContainsString("(int)\$request->getPost('docId')", $method,
                "$action must cast docId to int before using it in a path");
        }
    }

    /**
     * The deleted file name must be passed through basename() to neutralise any
     * directory component.
     */
    public function testDeleteSanitisesFileName(): void
    {
        $method = $this->extractMethod('deletegraphabsAction');
        self::assertStringContainsString('basename(', $method,
            'deletegraphabsAction must strip any directory component from the file name');
    }
}
