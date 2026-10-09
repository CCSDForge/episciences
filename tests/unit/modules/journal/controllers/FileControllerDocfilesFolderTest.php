<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Behaviour of FileController::docfilesAction(): only the sub-directories meant to be linked from the
 * pages (comments, copy-editing sources, data descriptor) can be reached. The action is dispatched for
 * real; only the final file delivery (loadFile) is replaced by a recorder.
 */
final class FileControllerDocfilesFolderTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
    }

    /**
     * @param array<string, mixed> $params
     * @return array{int, list<array{string, string}>} HTTP response code, files delivered
     */
    private function runDocfilesAction(array $params): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams($params + ['docId' => '12', 'filename' => 'report', 'extension' => 'xml']);
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new class ($request, $response) extends \FileController {
            /** @var list<array{string, string}> */
            public array $delivered = [];

            protected function loadFile(string $path, string $file, bool $forceDownload = false): void
            {
                $this->delivered[] = [$path, $file];
            }
        };

        $controller->docfilesAction();

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
    }

    public function testCopyEditingFileOfACommentIsServed(): void
    {
        [$code, $delivered] = $this->runDocfilesAction(['folder' => 'ce', 'parentCommentId' => '34']);

        self::assertSame(200, $code);
        self::assertSame('copy_editing_sources/34/report.xml', $delivered[0][1] ?? null);
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
    }
}
