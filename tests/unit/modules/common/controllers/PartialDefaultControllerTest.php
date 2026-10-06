<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * The modal structure is reachable without authentication: the request must not be
 * able to choose the markup or the style rendered by the modal views, and the error
 * views must escape the messages they print (source-pattern analysis).
 */
final class PartialDefaultControllerTest extends TestCase
{
    private function read(string $relativePath): string
    {
        return (string) file_get_contents(APPLICATION_PATH . '/modules/' . $relativePath);
    }

    public function testModalActionDoesNotCopyTheWholeRequestIntoTheView(): void
    {
        $source = $this->read('common/controllers/PartialDefaultController.php');

        self::assertStringNotContainsString('getParams()', $source);
        self::assertStringContainsString("MODAL_BOOLEAN_PARAMS = ['buttons', 'hideSubmit']", $source);
        self::assertStringContainsString('FILTER_VALIDATE_BOOLEAN', $source);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function modalViewProvider(): array
    {
        return [
            'journal modal' => ['journal/views/scripts/partials/modal.phtml'],
            'common modal' => ['common/views/scripts/partials/modal.phtml'],
        ];
    }

    /**
     * @dataProvider modalViewProvider
     */
    public function testModalViewsEscapeTheStyleAttribute(string $view): void
    {
        $source = $this->read($view);

        self::assertStringContainsString("echo \$this->escape(\$property . ':' . \$value . ';');", $source);
        self::assertStringNotContainsString("echo \$property . ':'", $source);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function errorViewProvider(): array
    {
        return [
            'error' => ['journal/views/scripts/error/error.phtml'],
            'deny' => ['journal/views/scripts/error/deny.phtml'],
            'http_error' => ['journal/views/scripts/error/http_error.phtml'],
        ];
    }

    /**
     * @dataProvider errorViewProvider
     */
    public function testErrorViewsEscapeTheirMessages(string $view): void
    {
        $source = $this->read($view);

        self::assertDoesNotMatchRegularExpression('/echo \$this->translate\(\$this->/', $source);
        self::assertStringContainsString('$this->escape($this->translate(', $source);
    }
}
