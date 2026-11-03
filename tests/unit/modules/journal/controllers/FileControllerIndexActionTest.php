<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Security\AccessLog;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

/**
 * Behaviour of FileController::indexAction(): only flat file names of the journal "files"
 * directory may be served. The action is dispatched for real; only the final file delivery
 * (loadFile) is replaced by a recorder.
 */
final class FileControllerIndexActionTest extends TestCase
{
    use SecurityLogCaptureTrait;

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
     * @return array<string, array{string, string, string}> file name, extension, reason logged ('' for none)
     */
    public static function refusedNamesProvider(): array
    {
        return [
            'parent directory' => ['../config/pwd', 'json', 'unsafe_file_name'],
            'encoded parent directory' => ['..%2Fconfig', 'json', ''],
            'sub directory' => ['sub/file', 'pdf', 'unsafe_file_name'],
            'backslash' => ['sub\\file', 'pdf', 'unsafe_file_name'],
            'absolute path' => ['/etc/passwd', 'txt', 'unsafe_file_name'],
            'arXiv crypto key' => ['paper-crypto', 'pdf', 'reserved_file_name'],
            'hyphen' => ['crypto-paper', 'pdf', ''],
            'null byte' => ["file\0", 'pdf', 'unsafe_file_name'],
            'trailing newline' => ["file\n", 'pdf', 'unsafe_file_name'],
            'empty name' => ['', 'pdf', ''],
            'empty extension' => ['file', '', ''],
            'dotted extension' => ['file', 'tar.gz', ''],
            'extension with slash' => ['file', 'p/df', 'unsafe_file_name'],
            'extension with trailing newline' => ['file', "pdf\n", 'unsafe_file_name'],
        ];
    }

    /**
     * Only the names trying to reach another file are logged: the others are mere mistakes.
     *
     * @dataProvider refusedNamesProvider
     */
    public function testUnsafeOrReservedNamesAreRefused(string $filename, string $extension, string $loggedReason): void
    {
        [$code, $delivered] = $this->runIndexAction(['filename' => $filename, 'extension' => $extension]);

        self::assertSame(404, $code);
        self::assertSame([], $delivered, 'no file must be delivered');
        self::assertSame(
            $loggedReason === '' ? [] : [[AccessLog::UNSAFE_PATH, $loggedReason]],
            $this->loggedEvents()
        );
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
