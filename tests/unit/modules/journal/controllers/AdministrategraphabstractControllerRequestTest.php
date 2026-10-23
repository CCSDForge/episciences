<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the request handling in AdministrategraphabstractController.
 *
 * Source-analysis tests (the actions exit() and cannot be dispatched in a test):
 * they assert the request token is checked before the POST parameters are used and that
 * the document id is cast to int before being used to build filesystem paths.
 * The authorization itself is covered by AdministrategraphabstractControllerAccessTest.
 *
 */
final class AdministrategraphabstractControllerRequestTest extends TestCase
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
     * $docId must be cast to int before being used to build filesystem paths
     * (move_uploaded_file / unlink).
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
     * The deleted file name must be passed through basename() to drop any
     * directory component.
     */
    public function testDeleteUsesBasenameOnFileName(): void
    {
        $method = $this->extractMethod('deletegraphabsAction');
        self::assertStringContainsString('basename(', $method,
            'deletegraphabsAction must strip any directory component from the file name');
    }

    /**
     * Both mutating actions must validate the per-session request token inside
     * the request guard, before any POST parameter is used.
     */
    public function testActionsValidateTheRequestToken(): void
    {
        foreach (['addgraphabsAction', 'deletegraphabsAction'] as $action) {
            $method = $this->extractMethod($action);

            $tokenPos = strpos($method, 'Episciences_Csrf_Helper::validateRequestToken(');
            self::assertNotFalse($tokenPos,
                "$action must validate the per-session request token");

            $postPos = strpos($method, "getPost('docId')");
            self::assertNotFalse($postPos, "$action must read docId from POST");
            self::assertLessThan($postPos, $tokenPos,
                "$action must validate the token before reading POST parameters");
        }
    }
}
