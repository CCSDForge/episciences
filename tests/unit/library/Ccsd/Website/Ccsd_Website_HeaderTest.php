<?php

namespace unit\library\Ccsd\Website;

use Ccsd_Website_Header;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Website_Header::createHeader() output escaping.
 */
class Ccsd_Website_HeaderTest extends TestCase
{
    private string $layoutDir;

    protected function setUp(): void
    {
        $this->layoutDir = sys_get_temp_dir() . '/header-test-' . uniqid('', true) . '/';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->layoutDir . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->layoutDir)) {
            rmdir($this->layoutDir);
        }
    }

    private function render(array $logo): string
    {
        $header = new Ccsd_Website_Header(1, '', '/public/', $this->layoutDir);
        $header->setLanguages(['en']);
        $header->_logos = ['logo_0' => $logo];
        $header->createHeader();

        return (string)file_get_contents($this->layoutDir . 'header.en.html');
    }

    public function testTextLogoIsEscaped(): void
    {
        $html = $this->render([
            'type' => 'text',
            'align' => 'left" onmouseover="x',
            'text' => ['en' => '<script>alert(1)</script>'],
            'text_class' => 'a" onclick="x',
            'text_style' => 'color:red" onload="x',
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('" onclick="', $html);
        self::assertStringNotContainsString('" onmouseover="', $html);
        self::assertStringNotContainsString('" onload="', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testExistingEntitiesAndLineBreaksInLabelsAreKept(): void
    {
        $html = $this->render([
            'type' => 'text',
            'align' => 'center',
            'text' => ['en' => 'Revue d&apos;&eacute;tudes<br/>A &amp; B & C'],
            'text_class' => '',
            'text_style' => '',
        ]);

        self::assertStringContainsString('>Revue d&apos;&eacute;tudes<br>A &amp; B &amp; C</span>', $html);
        self::assertStringContainsString('<td align="center">', $html);
    }

    public function testUnknownAlignmentFallsBackToLeft(): void
    {
        $html = $this->render([
            'type' => 'text',
            'align' => 'left" onmouseover="x',
            'text' => ['en' => 'x'],
            'text_class' => '',
            'text_style' => '',
        ]);

        self::assertStringContainsString('<td align="left">', $html);
    }

    public function testRelativeAndMailtoLinksAreKept(): void
    {
        foreach (['/page/about', 'mailto:contact@example.org', '//example.org/'] as $href) {
            $html = $this->render([
                'type' => 'img',
                'align' => 'left',
                'img' => 'logo.png',
                'img_href' => $href,
                'img_width' => '',
                'img_height' => '',
                'img_alt' => '',
            ]);

            self::assertStringContainsString('href="' . $href . '"', $html);
        }
    }

    public function testImageLogoAttributesAreEscaped(): void
    {
        $html = $this->render([
            'type' => 'img',
            'align' => 'left',
            'img' => 'logo.png" onerror="x',
            'img_href' => 'https://example.org/?a="b',
            'img_width' => '10" onload="x',
            'img_height' => '',
            'img_alt' => 'alt" onfocus="x',
        ]);

        self::assertStringNotContainsString('" onerror="', $html);
        self::assertStringNotContainsString('" onload="', $html);
        self::assertStringNotContainsString('" onfocus="', $html);
        self::assertStringNotContainsString('a="b', $html);
    }

    public function testDangerousLinkSchemeIsDropped(): void
    {
        $html = $this->render([
            'type' => 'img',
            'align' => 'left',
            'img' => 'logo.png',
            'img_href' => 'javascript:alert(1)',
            'img_width' => '',
            'img_height' => '',
            'img_alt' => '',
        ]);

        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testObfuscatedLinkSchemeIsDropped(): void
    {
        foreach (["java\tscript:alert(1)", "java\nscript:alert(1)", " \x01javascript:alert(1)", "data:text/html,x"] as $href) {
            $html = $this->render([
                'type' => 'img',
                'align' => 'left',
                'img' => 'logo.png',
                'img_href' => $href,
                'img_width' => '',
                'img_height' => '',
                'img_alt' => '',
            ]);

            self::assertStringContainsString('<a href=""', $html, 'href kept: ' . json_encode($href));
        }
    }

    public function testRegularLinkIsKept(): void
    {
        $html = $this->render([
            'type' => 'img',
            'align' => 'left',
            'img' => 'logo.png',
            'img_href' => 'https://example.org/',
            'img_width' => '',
            'img_height' => '',
            'img_alt' => '',
        ]);

        self::assertStringContainsString('href="https://example.org/"', $html);
    }
}
