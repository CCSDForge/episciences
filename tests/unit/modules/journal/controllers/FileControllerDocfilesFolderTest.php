<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Security\AccessLog;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Behaviour of FileController::docfilesAction(): only the sub-directories meant to be linked from the
 * pages (comments, copy-editing sources, data descriptor) can be reached, and only by the users allowed
 * to read the files of the paper. The action is dispatched for real; the per-paper access check (see
 * DocumentFilePolicyTest for its rules) and the final file delivery (loadFile) are replaced by recorders.
 */
final class FileControllerDocfilesFolderTest extends TestCase
{
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
        $this->logHandler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->logHandler]));
    }

    protected function tearDown(): void
    {
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
    }

    /**
     * @return list<array{string, string}> event and reason of each security log record
     */
    private function loggedEvents(): array
    {
        return array_map(
            static fn(LogRecord $record): array => [$record->message, $record->context['reason'] ?? ''],
            $this->logHandler->getRecords()
        );
    }

    /** @var list<array{int, string, string}> access checks asked: docId, folder, file */
    private array $accessChecks = [];

    /**
     * @param array<string, mixed> $params
     * @return array{int, list<array{string, string}>} HTTP response code, files delivered
     */
    private function runDocfilesAction(array $params, bool $isAllowed = true): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams($params + ['docId' => '12', 'filename' => 'report', 'extension' => 'xml']);
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new class ($request, $response) extends \FileController {
            /** @var list<array{string, string}> */
            public array $delivered = [];
            /** @var list<array{int, string, string}> */
            public array $accessChecks = [];
            public bool $isAllowed = true;

            protected function isAllowedToReadDocumentFile(int $docId, string $folder, string $file): bool
            {
                $this->accessChecks[] = [$docId, $folder, $file];
                return $this->isAllowed;
            }

            protected function loadFile(string $path, string $file, bool $forceDownload = false): void
            {
                $this->delivered[] = [$path, $file];
            }
        };
        $controller->isAllowed = $isAllowed;

        $controller->docfilesAction();
        $this->accessChecks = $controller->accessChecks;

        return [$response->getHttpResponseCode(), $controller->delivered];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedFoldersProvider(): array
    {
        return [
            'reviewer reports' => ['reports'],
            'ratings' => ['ratings'],
            'temporary versions' => ['tmp'],
            'e-mail attachments' => ['attachments'],
            'parent directory' => ['..'],
            'empty' => [''],
        ];
    }

    /**
     * @dataProvider refusedFoldersProvider
     */
    public function testOtherFoldersAreRefused(string $folder): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => $folder]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
        self::assertSame(
            $folder === '' ? [] : [[AccessLog::UNSAFE_PATH, 'unexpected_folder']],
            $this->loggedEvents(),
            'a missing folder is not an attempt'
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function servedFoldersProvider(): array
    {
        return [
            'comments' => ['comments', 'comments/report.xml'],
            'copy-editing alias' => ['ce', 'copy_editing_sources/report.xml'],
            'copy-editing' => ['copy_editing_sources', 'copy_editing_sources/report.xml'],
            'data descriptor' => ['dd', 'dd/report.xml'],
        ];
    }

    /**
     * @dataProvider servedFoldersProvider
     */
    public function testExpectedFoldersAreServed(string $folder, string $relativePath): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => $folder]);

        self::assertSame(200, $code);
        self::assertCount(1, $delivered);
        self::assertSame($relativePath, $delivered[0][1]);
        self::assertSame([], $this->loggedEvents());
    }

    public function testCopyEditingFileOfACommentIsServed(): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => 'ce', 'parentCommentId' => '34']);

        self::assertSame(200, $code);
        self::assertSame('copy_editing_sources/34/report.xml', $delivered[0][1] ?? null);
    }

    public function testCopyEditingCommentIdMustBeANumber(): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => 'ce', 'parentCommentId' => '../comments']);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
    }

    public function testAccessIsCheckedOnThePaperFolderAndFile(): void
    {
        $this->runDocfilesAction(['folder' => 'ce', 'parentCommentId' => '34']);

        self::assertSame([[12, 'copy_editing_sources', 'report.xml']], $this->accessChecks);
    }

    /**
     * @dataProvider servedFoldersProvider
     */
    public function testUserNotAllowedOnThePaperGetsNotFound(string $folder): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => $folder], false);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
    }

    public function testMissingFolderIsRefused(): void
    {
        [$code, $delivered] = $this->runDocfilesAction([]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function climbingSegmentsProvider(): array
    {
        return [
            'file name climbs out of the folder' => [['filename' => '../reports/7/report', 'extension' => 'xml']],
            'file name is a parent directory' => [['filename' => '..', 'extension' => '']],
            'comment id climbs out of the folder' => [['parentCommentId' => '../reports/7']],
            'comment id is not a number' => [['parentCommentId' => 'abc']],
            'comment id outside the copy-editing folder' => [['parentCommentId' => '34']],
        ];
    }

    /**
     * @param array<string, string> $params
     * @dataProvider climbingSegmentsProvider
     */
    public function testSegmentsCannotLeaveTheFolder(array $params): void
    {
        [$code, $delivered] = $this->runDocfilesAction($params + ['folder' => 'comments']);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
        self::assertSame([[AccessLog::UNSAFE_PATH, 'unsafe_file_name']], $this->loggedEvents());
    }
}
