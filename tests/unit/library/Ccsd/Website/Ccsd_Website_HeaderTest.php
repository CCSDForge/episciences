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

    private function renderText(string $text): string
    {
        $html = $this->render([
            'type' => 'text',
            'align' => 'left',
            'text' => ['en' => $text],
            'text_class' => '',
            'text_style' => '',
        ]);
        preg_match('#<span>(.*)</span>#s', $html, $matches);

        return $matches[1] ?? '';
    }

    public function testInlineTagsInLabelsAreKept(): void
    {
        self::assertSame(
            '<b>Revue</b> <strong>d&apos;<i>études</i></strong> <em>maths</em>',
            $this->renderText('<B>Revue</b> <STRONG>d&apos;<i>études</I></strong> <em>maths</em>')
        );
    }

    public function testInlineTagsWithAttributesAreEscaped(): void
    {
        self::assertSame(
            // The closing tag has no kept opening tag left to close, so it is dropped
            '&lt;b onclick=&quot;x&quot;&gt;Revue &lt;strong style=&quot;x&quot;&gt;',
            $this->renderText('<b onclick="x">Revue</b> <strong style="x">')
        );
    }

    public function testInlineTagsAreBalanced(): void
    {
        self::assertSame('<b>open</b>', $this->renderText('<b>open'));
        self::assertSame('stray', $this->renderText('stray</i>'));
        self::assertSame('<b><i>x</i></b>y', $this->renderText('<b><i>x</b>y</i>'));
        self::assertSame('<strong>a<br>b</strong>', $this->renderText('<strong>a<br />b</strong>'));
    }

    public function testOtherTagsInLabelsAreEscaped(): void
    {
        self::assertSame('&lt;u&gt;x&lt;/u&gt; &lt;bold&gt;', $this->renderText('<u>x</u> <bold>'));
    }

    private function renderSpan(string $class, string $style): string
    {
        $html = $this->render([
            'type' => 'text',
            'align' => 'left',
            'text' => ['en' => 'x'],
            'text_class' => $class,
            'text_style' => $style,
        ]);
        preg_match('#<span[^>]*>#', $html, $matches);

        return $matches[0] ?? '';
    }

    public function testTypographicStylesAreKept(): void
    {
        self::assertSame(
            '<span style="font-size:40px;font-weight:bold;color:#21472e;color:rgb(2, 125, 186);font-family:&quot;Open Sans&quot;, serif;">',
            $this->renderSpan('', 'font-size:40px; Font-Weight: bold ;color:#21472e;color: rgb(2, 125, 186);font-family:"Open Sans", serif')
        );
    }

    public function testLayoutAndRemoteStylesAreDropped(): void
    {
        self::assertSame(
            '<span style="color:red;">',
            $this->renderSpan('', 'position:fixed;top:0;width:100%;background:url(https://evil.example/x.png);color:red;'
                . 'font-family:url(x);color:expression(alert(1));color:var(--x);color:\72 ed;color:red/**/')
        );
        self::assertSame('<span>', $this->renderSpan('', 'position:fixed;z-index:9999'));
    }

    public function testInvalidClassNamesAreDropped(): void
    {
        self::assertSame(
            '<span class="rv-header _x -title">',
            $this->renderSpan(' rv-header  a" 2col _x -title {}', '')
        );
        self::assertSame('<span>', $this->renderSpan('"><script>', ''));
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

    public function testRelativeMailtoAndTelLinksAreKept(): void
    {
        foreach (['/page/about', 'mailto:contact@example.org', 'tel:+33123456789', '//example.org/'] as $href) {
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
        foreach (["java\tscript:alert(1)", "java\nscript:alert(1)", " \x01javascript:alert(1)", "data:text/html,x", "ftp://example.org/"] as $href) {
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
