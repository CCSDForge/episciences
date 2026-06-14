<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use PHPUnit\Framework\TestCase;

/**
 * Source-level regression guards for FilesManager.
 *
 * insert()/update() require a database adapter, so — like the controller
 * source-analysis tests in this suite — these assertions check that the
 * corrected handling stays in place rather than executing the queries.
 *
 * @covers Episciences_Paper_FilesManager
 */
final class Episciences_Paper_FilesManagerGuardTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/../library/Episciences/Paper/FilesManager.php'
        );
    }

    public function testInsertChecksTheLoopElementNotTheArray(): void
    {
        // The instanceof check must target the loop element ($file), not the
        // whole collection ($files) — otherwise it is always true.
        self::assertStringContainsString('!($file instanceof Episciences_Paper_File)', $this->source);
        self::assertStringNotContainsString('!($files instanceof Episciences_Paper_File)', $this->source);
    }

    public function testUpdateMapsFileTypeFromGetFileType(): void
    {
        self::assertMatchesRegularExpression(
            "/'fileType'\s*=>\s*\\\$file->getFileType\(\)/",
            $this->source
        );
        self::assertDoesNotMatchRegularExpression(
            "/'fileType'\s*=>\s*\\\$file->getFileSize\(\)/",
            $this->source
        );
    }
}
