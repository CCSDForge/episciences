<?php

namespace unit\library\Episciences;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;


class Episciences_ToolsTest extends TestCase
{
    public static function testIsInUppercase(){

        self::assertEquals(true, Episciences_Tools::isInUppercase('SCREEN_NAME'));
        self::assertEquals(false, Episciences_Tools::isInUppercase('SCREEN_name'));

    }

    public function testCheckValueType()
    {
        // Test HAL identifier
        self::assertEquals('hal', Episciences_Tools::checkValueType('hal_12345678'));
        self::assertEquals('hal', Episciences_Tools::checkValueType('hal-12345678v1'));
        
        // Test DOI
        self::assertEquals('doi', Episciences_Tools::checkValueType('10.1000/123456'));
        self::assertEquals('doi', Episciences_Tools::checkValueType('10.1038/nature.2022.12345'));
        
        // Test Software Heritage ID  
        self::assertEquals('software', Episciences_Tools::checkValueType('swh:1:dir:0123456789abcdef0123456789abcdef01234567'));
        self::assertEquals('software', Episciences_Tools::checkValueType('swh:1:cnt:0123456789abcdef0123456789abcdef01234567;origin=https://example.com'));
        
        // Test URL
        self::assertEquals('url', Episciences_Tools::checkValueType('https://example.com'));
        self::assertEquals('url', Episciences_Tools::checkValueType('http://example.org/page'));
        
        // Test Handle
        self::assertEquals('handle', Episciences_Tools::checkValueType('123456789/12345'));
        self::assertEquals('handle', Episciences_Tools::checkValueType('hdl.handle.net/123456789/12345'));
        
        // Test ArXiv
        self::assertEquals('arxiv', Episciences_Tools::checkValueType('2023.12345'));
        self::assertEquals('arxiv', Episciences_Tools::checkValueType('math.AG/0123456'));
        
        // Test unrecognized value
        self::assertFalse(Episciences_Tools::checkValueType('invalid-value'));
        self::assertFalse(Episciences_Tools::checkValueType(''));
        self::assertFalse(Episciences_Tools::checkValueType('just some text'));
    }

    // -------------------------------------------------------------------------
    // Security S5 — XSS in getTmpFilesLinks() (source inspection)
    // -------------------------------------------------------------------------

    /**
     * Regression S5: $fileName from JSON payload injected into href and link text
     * without encoding — allows XSS and path traversal.
     * The fix uses urlencode() for the href and htmlspecialchars() for the display text.
     */
    public function testBuildHtmlTmpDocUrlsEscapesFileName(): void
    {
        $method = new ReflectionMethod(Episciences_Tools::class, 'buildHtmlTmpDocUrls');
        $lines = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString(
            'urlencode($fileName)',
            $source,
            'Security S5: $fileName must be urlencode()d in the href attribute'
        );
        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$href/',
            $source,
            'Security S5: href must be escaped with htmlspecialchars() before HTML injection'
        );
        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$fileName/',
            $source,
            'Security S5: $fileName must be escaped with htmlspecialchars() in the link text'
        );
    }

}

