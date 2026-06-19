<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Source-level check for RobotsDefaultController.
 *
 * @covers RobotsDefaultController
 */
final class RobotsDefaultControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/RobotsDefaultController.php'
        );
    }

    public function testSitemapUrlUsesApplicationBase(): void
    {
        self::assertStringContainsString('rtrim(APPLICATION_URL', $this->source,
            'the sitemap URL must be built from APPLICATION_URL');
        self::assertStringNotContainsString("\$_SERVER['HTTP_HOST']", $this->source,
            'the sitemap URL must not be built from the request host');
    }
}
