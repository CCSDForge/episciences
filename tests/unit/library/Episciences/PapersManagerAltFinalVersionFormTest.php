<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

final class PapersManagerAltFinalVersionFormTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string)file_get_contents(LIBRARY_PATH . '/Episciences/PapersManager.php');
    }

    public function testFormDefaultsToCurrentArxivVersion(): void
    {
        self::assertStringContainsString("'value' => (string)\$paper->getVersion()", $this->source);
        self::assertStringContainsString("'label' => 'Version arXiv'", $this->source);
    }

    public function testStoredPasswordMakesPasswordFieldOptional(): void
    {
        self::assertStringContainsString(
            'addPaperArxivPwdElement($form, empty($paper->getPassword()))',
            $this->source
        );
    }
}
