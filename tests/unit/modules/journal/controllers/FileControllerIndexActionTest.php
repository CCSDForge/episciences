<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Behaviour of FileController::indexAction(): only flat file names of the journal "files"
 * directory may be served. The action is dispatched for real; only the final file delivery
 * (loadFile) is replaced by a recorder.
 */
final class FileControllerIndexActionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
    }

    /**
     * @param array<string, mixed> $params
     * @return array{int, list<array{string, string}>} HTTP response code, files delivered
     */
    private function runIndexAction(array $params): array
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParams($params);
        $response = new Zend_Controller_Response_HttpTestCase();

        $controller = new class ($request, $response) extends \FileController {
            /** @var list<array{string, string}> */
            public array $delivered = [];

            protected function loadFile(string $path, string $file, bool $forceDownload = false): void
            {
                $this->delivered[] = [$path, $file];
            }
        };

        $controller->indexAction();

        return [$response->getHttpResponseCode(), $controller->delivered];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedNamesProvider(): array
    {
        return [
            'parent directory' => ['../config/pwd', 'json'],
            'encoded parent directory' => ['..%2Fconfig', 'json'],
            'sub directory' => ['sub/file', 'pdf'],
            'backslash' => ['sub\\file', 'pdf'],
            'absolute path' => ['/etc/passwd', 'txt'],
            'arXiv crypto key' => ['paper-crypto', 'pdf'],
            'hyphen' => ['crypto-paper', 'pdf'],
            'null byte' => ["file\0", 'pdf'],
            'trailing newline' => ["file\n", 'pdf'],
            'empty name' => ['', 'pdf'],
            'empty extension' => ['file', ''],
            'dotted extension' => ['file', 'tar.gz'],
            'extension with slash' => ['file', 'p/df'],
            'extension with trailing newline' => ['file', "pdf\n"],
        ];
    }

    /**
     * @dataProvider refusedNamesProvider
     */
    public function testUnsafeOrReservedNamesAreRefused(string $filename, string $extension): void
    {
        [$code, $delivered] = $this->runIndexAction(['filename' => $filename, 'extension' => $extension]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
    }

    public function testMissingParametersAreRefused(): void
    {
        [$code, $delivered] = $this->runIndexAction([]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function acceptedNamesProvider(): array
    {
        return [
            'simple' => ['report', 'pdf'],
            'with space' => ['my report', 'pdf'],
            'underscore and digits' => ['file_01', 'png'],
            'crypto without separator' => ['cryptopaper', 'pdf'],
        ];
    }

    /**
     * @dataProvider acceptedNamesProvider
     */
    public function testFlatNamesAreDelivered(string $filename, string $extension): void
    {
        [$code, $delivered] = $this->runIndexAction(['filename' => $filename, 'extension' => $extension]);

        self::assertSame(200, $code);
        $expected = [[(string)constant('REVIEW_FILES_PATH'), $filename . '.' . $extension]];
        self::assertSame($expected, $delivered);
    }
}
