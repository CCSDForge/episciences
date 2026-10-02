<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the request handling in AdministrategraphabstractController.
 *
 * Source-analysis tests (ZF1 controllers are not instantiable in isolation):
 * they assert that both actions go through the common request guard, that the
 * guard checks the request token before reading POST parameters and checks the
 * user's rights on the paper itself, and that the file handling stays safe.
 * The validation rules themselves are unit-tested in GraphicalAbstractValidatorTest.
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
        $end = preg_match('/\n    (?:public|private|protected) function /', $this->source, $matches, PREG_OFFSET_CAPTURE, (int) $start + 1)
            ? $matches[0][1]
            : false;
        return $end === false
            ? substr($this->source, (int) $start)
            : substr($this->source, (int) $start, (int) $end - (int) $start);
    }

    public function testBothActionsGoThroughTheRequestGuardFirst(): void
    {
        foreach (['addgraphabsAction', 'deletegraphabsAction'] as $action) {
            $method = $this->extractMethod($action);

            $guardPos = strpos($method, '$this->loadAuthorizedPaper()');
            self::assertNotFalse($guardPos, "$action must call the request guard");
            self::assertStringContainsString('if ($paper === null) {', $method,
                "$action must stop when the guard rejects the request");

            $postPos = strpos($method, 'getPost(');
            self::assertNotFalse($postPos, "$action must read POST parameters");
            self::assertLessThan($postPos, $guardPos,
                "$action must run the guard before reading POST parameters");
        }
    }

    /**
     * The guard must validate the per-session request token before any POST parameter is used.
     */
    public function testGuardValidatesTheRequestTokenFirst(): void
    {
        $guard = $this->extractMethod('loadAuthorizedPaper');

        $tokenPos = strpos($guard, 'Episciences_Csrf_Helper::validateRequestToken(');
        self::assertNotFalse($tokenPos, 'the guard must validate the per-session request token');
        self::assertStringContainsString('!$request->isXmlHttpRequest() || !$request->isPost()', $guard);

        $postPos = strpos($guard, "getPost('docId')");
        self::assertNotFalse($postPos, 'the guard must read docId from POST');
        self::assertLessThan($postPos, $tokenPos,
            'the guard must validate the token before reading POST parameters');
    }

    /**
     * The guard must check the user's rights on the paper itself.
     */
    public function testGuardChecksTheRightsOnThePaper(): void
    {
        $guard = $this->extractMethod('loadAuthorizedPaper');

        self::assertStringContainsString(
            '!GraphicalAbstractPolicy::canEdit($paper)',
            $guard,
            'the guard must apply the version and role rules to the paper'
        );
        self::assertStringNotContainsString('isAuthor()', $this->source,
            'the rights must be checked on the paper');
        self::assertStringContainsString("(int)\$request->getPost('docId')", $guard,
            'docId must be cast to int before being used to build paths');
        self::assertStringContainsString('Episciences_PapersManager::get($docId, false, RVID)', $guard,
            'the paper must be loaded from the current journal');
    }

    /**
     * A body larger than post_max_size empties $_POST: it must not be reported as a forbidden request.
     */
    public function testPostMaxSizeOverflowIsReportedAsTooLarge(): void
    {
        $guard = $this->extractMethod('loadAuthorizedPaper');

        $overflowPos = strpos($guard, 'exceedsPostMaxSize(');
        self::assertNotFalse($overflowPos);
        self::assertStringContainsString('sendErrors(413,', $guard);
        self::assertLessThan(strpos($guard, 'Episciences_Csrf_Helper::validateRequestToken('), $overflowPos);
    }

    /**
     * The database is saved before the previous file is removed, and a failed save drops only the new file.
     */
    public function testPreviousFileIsRemovedOnlyAfterTheSave(): void
    {
        $add = $this->extractMethod('addgraphabsAction');

        $savePos = strpos($add, 'GraphicalAbstractRepository::save(');
        $removePreviousPos = strpos($add, 'removeFile($docId, $current->file)');
        self::assertNotFalse($savePos);
        self::assertNotFalse($removePreviousPos);
        self::assertLessThan($removePreviousPos, $savePos);
        self::assertStringNotContainsString('unlink(', $this->extractMethod('storeUploadedFile'));
    }

    public function testDeleteUsesBasenameOnFileName(): void
    {
        $method = $this->extractMethod('deletegraphabsAction');
        self::assertStringContainsString('basename(', $method,
            'deletegraphabsAction must strip any directory component from the file name');
    }

    public function testUploadIsValidatedAndItsMoveChecked(): void
    {
        $validate = $this->extractMethod('validateUploadedFile');
        self::assertStringContainsString('is_uploaded_file(', $validate);
        self::assertStringContainsString('GraphicalAbstractValidator::validateFile(', $validate);

        $store = $this->extractMethod('storeUploadedFile');
        self::assertStringContainsString('if (!move_uploaded_file(', $store,
            'the result of move_uploaded_file() must be checked');
        self::assertStringContainsString('GraphicalAbstractValidator::extensionFor(', $store,
            'the stored extension must come from the actual MIME type');
        self::assertStringNotContainsString('pathinfo(', $this->source,
            'the original file name must not be trusted');
    }

    public function testEveryErrorPathSendsAResponse(): void
    {
        $add = $this->extractMethod('addgraphabsAction');
        self::assertStringContainsString('$isTooLarge ? 413 : 400', $add);
        self::assertStringContainsString('$this->sendErrors(500,', $add);
        self::assertStringNotContainsString('exit(', $this->source,
            'responses must go through the response object');
    }
}
