<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Uploaded files are served from the journal origin: only PDF and raster images may be
 * displayed inline, everything else is downloaded.
 */
final class FileControllerInlineContentTypeTest extends TestCase
{
    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/FileController.php';
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function contentTypeProvider(): array
    {
        return [
            'pdf' => ['application/pdf; charset=binary', true],
            'png' => ['image/png; charset=binary', true],
            'jpeg' => ['image/jpeg', true],
            'gif' => ['image/gif; charset=binary', true],
            'webp' => ['image/webp; charset=binary', true],
            'upper case' => ['APPLICATION/PDF', true],
            'svg' => ['image/svg+xml; charset=us-ascii', false],
            'xml' => ['text/xml; charset=us-ascii', false],
            'plain text' => ['text/plain; charset=us-ascii', false],
            'html' => ['text/html; charset=us-ascii', false],
            'octet stream' => ['application/octet-stream; charset=binary', false],
            'empty' => ['', false],
        ];
    }

    /**
     * @dataProvider contentTypeProvider
     */
    public function testIsInlineContentType(string $contentType, bool $expected): void
    {
        self::assertSame($expected, \FileController::isInlineContentType($contentType));
    }

    public function testOpenFileSendsNosniffAndNoLongerTrustsAnyContentType(): void
    {
        $source = (string) file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/FileController.php');

        self::assertStringContainsString("header('X-Content-Type-Options: nosniff');", $source);
        self::assertStringContainsString('!self::isInlineContentType($contentType)', $source);
    }
}
