<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Files\UploadedFileToken;
use Episciences\Security\AccessLog;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

/**
 * Files of an answer to a copy-editing request (PaperController::saveAuthorFormattingAnswer()): uploaded in the
 * directory of the request, which also holds the request's own files. Only the files the user uploaded for the
 * answer (see UploadedFileToken) are attached to it and moved to its directory; the other files stay in place.
 * Real directories are used; the token secret is the controller's only stub.
 */
final class PaperControllerCopyEditingAnswerFilesTest extends TestCase
{
    use SecurityLogCaptureTrait;

    public const UID = 4242;
    public const SECRET = 'copy-editing-answer-test-secret';

    private string $root;
    private string $requestDir;
    private string $answerDir;
    private string $mailDir;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';
        Zend_Session::$_unitTestEnabled = true;

        $this->root = sys_get_temp_dir() . '/ce-answer-test-' . bin2hex(random_bytes(4)) . '/';
        $this->requestDir = $this->root . 'request/';
        $this->answerDir = $this->root . 'answer/';
        $this->mailDir = $this->root . 'mail/';
        mkdir($this->requestDir, 0777, true);
        mkdir($this->mailDir, 0777, true);
        $_POST = [];
    }

    protected function tearDown(): void
    {
        foreach ([$this->requestDir, $this->answerDir, $this->mailDir] as $dir) {
            foreach (glob($dir . '*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        rmdir($this->root);
        $_POST = [];
    }

    private function sign(string $file): string
    {
        return (new UploadedFileToken(self::UID, self::SECRET))->sign($this->requestDir, $file);
    }

    /**
     * @param array<mixed> $attachments attachments[] of the form
     * @param array<mixed> $tokens attachment_tokens[] of the form
     * @return list<string> names attached to the answer
     */
    private function saveAnswerFiles(array $attachments, array $tokens, bool $notified = false): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost([
            'attachments' => $attachments,
            UploadedFileToken::POST_KEY => $tokens,
        ]);

        $controller = new class ($request, new Zend_Controller_Response_HttpTestCase()) extends \PaperController {
            /**
             * The steps of saveAuthorFormattingAnswer() on the files
             * @param array<mixed> $attachments
             * @return list<string>
             */
            public function saveFiles(string $parentPath, string $path, array $attachments, ?string $mailPath): array
            {
                $attachments = array_values($this->uploadedAttachments($parentPath, $attachments));
                $this->moveCopyEditingAnswerFiles($parentPath, $path, $attachments, $mailPath);

                return $attachments;
            }

            protected function uploadedFileToken(): UploadedFileToken
            {
                return new UploadedFileToken(
                    PaperControllerCopyEditingAnswerFilesTest::UID,
                    PaperControllerCopyEditingAnswerFilesTest::SECRET
                );
            }
        };

        return $controller->saveFiles($this->requestDir, $this->answerDir, $attachments, $notified ? $this->mailDir : null);
    }

    private function store(string $dir, string $file, string $content = 'content'): void
    {
        file_put_contents($dir . $file, $content);
    }

    public function testFileUploadedForTheAnswerIsMovedToTheAnswerDirectory(): void
    {
        $this->store($this->requestDir, 'sources.zip');

        $attached = $this->saveAnswerFiles(['sources.zip'], [$this->sign('sources.zip')]);

        self::assertSame(['sources.zip'], $attached);
        self::assertFileExists($this->answerDir . 'sources.zip');
        self::assertFileDoesNotExist($this->requestDir . 'sources.zip');
        self::assertSame([], $this->loggedEvents());
    }

    public function testFileOfTheRequestNamedInTheFormStaysWithTheRequest(): void
    {
        $this->store($this->requestDir, 'template.docx');
        $this->store($this->requestDir, 'sources.zip');

        $attached = $this->saveAnswerFiles(
            ['sources.zip', 'template.docx'],
            [$this->sign('sources.zip'), '']
        );

        self::assertSame(['sources.zip'], $attached);
        self::assertFileExists($this->requestDir . 'template.docx');
        self::assertFileDoesNotExist($this->answerDir . 'template.docx');
        self::assertSame([[AccessLog::TOKEN_INVALID, 'uploaded_file_token']], $this->loggedEvents());
    }

    public function testFailedUploadIsDroppedSilently(): void
    {
        // An empty name is sent back by es.fileupload.js when the upload failed
        $attached = $this->saveAnswerFiles([''], ['']);

        self::assertSame([], $attached);
        self::assertSame([], $this->loggedEvents());
    }

    public function testMissingRequestDirectoryMovesNothing(): void
    {
        rmdir($this->requestDir);

        // scandir() warns about the missing directory: not an error of the answer
        $attached = @$this->saveAnswerFiles(['sources.zip'], [$this->sign('sources.zip')]);

        self::assertSame(['sources.zip'], $attached);
        self::assertDirectoryDoesNotExist($this->answerDir);
    }

    public function testCopyForTheMailUnderAnotherNameDoesNotRemoveAnotherFileOfTheRequest(): void
    {
        // A file of the same name is already in the mail attachments: the copy is renamed "figure_1.png", the name
        // of a file of the request
        $this->store($this->mailDir, 'figure.png');
        $this->store($this->requestDir, 'figure.png', 'uploaded for the answer');
        $this->store($this->requestDir, 'figure_1.png', 'file of the request');

        $this->saveAnswerFiles(['figure.png'], [$this->sign('figure.png')], true);

        self::assertFileExists($this->answerDir . 'figure.png');
        self::assertFileDoesNotExist($this->requestDir . 'figure.png');
        self::assertStringEqualsFile($this->requestDir . 'figure_1.png', 'file of the request');
    }
}
