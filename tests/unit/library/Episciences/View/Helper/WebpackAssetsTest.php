<?php

declare(strict_types=1);

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_WebpackAssets;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_View_Helper_WebpackAssets
 */
class WebpackAssetsTest extends TestCase
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        Episciences_View_Helper_WebpackAssets::setEntrypointsPath('');
    }

    private function writeEntrypoints(mixed $data): string
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'ep_webpack_');
        file_put_contents($this->tmpFile, json_encode($data));
        Episciences_View_Helper_WebpackAssets::setEntrypointsPath($this->tmpFile);
        return $this->tmpFile;
    }

    private function helper(): Episciences_View_Helper_WebpackAssets
    {
        return new Episciences_View_Helper_WebpackAssets();
    }

    public function testRendersScriptTagsForKnownEntry(): void
    {
        $this->writeEntrypoints([
            'entrypoints' => [
                'altcha' => [
                    'js' => ['/build/runtime.js', '/build/vendors-altcha.js', '/build/altcha.js'],
                ],
            ],
        ]);

        $result = $this->helper()->webpackAssets('altcha');

        $this->assertStringContainsString('<script src="/build/runtime.js"></script>', $result);
        $this->assertStringContainsString('<script src="/build/vendors-altcha.js"></script>', $result);
        $this->assertStringContainsString('<script src="/build/altcha.js"></script>', $result);
        $this->assertSame(3, substr_count($result, '<script'));
    }

    public function testReturnsEmptyStringForUnknownEntry(): void
    {
        $this->writeEntrypoints([
            'entrypoints' => [
                'app' => ['js' => ['/build/app.js']],
            ],
        ]);

        $this->assertSame('', $this->helper()->webpackAssets('unknown'));
    }

    public function testReturnsEmptyStringWhenEntrypointsFileMissing(): void
    {
        Episciences_View_Helper_WebpackAssets::setEntrypointsPath('/nonexistent/path/entrypoints.json');

        $this->assertSame('', $this->helper()->webpackAssets('altcha'));
    }

    public function testReturnsEmptyStringWhenJsonIsMalformed(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'ep_webpack_');
        file_put_contents($this->tmpFile, 'not-valid-json{{{');
        Episciences_View_Helper_WebpackAssets::setEntrypointsPath($this->tmpFile);

        $this->assertSame('', $this->helper()->webpackAssets('altcha'));
    }

    public function testEntrypointsAreCachedAcrossInstances(): void
    {
        $this->writeEntrypoints([
            'entrypoints' => ['altcha' => ['js' => ['/build/altcha.js']]],
        ]);

        $first  = $this->helper()->webpackAssets('altcha');
        // Replace file with empty content — cached result must still be returned
        file_put_contents($this->tmpFile, json_encode([]));
        $second = $this->helper()->webpackAssets('altcha');

        $this->assertSame($first, $second);
        $this->assertStringContainsString('/build/altcha.js', $first);
    }

    public function testSrcAttributesAreEscaped(): void
    {
        $this->writeEntrypoints([
            'entrypoints' => [
                'test' => ['js' => ['/build/file.js?v=1&foo=bar']],
            ],
        ]);

        $result = $this->helper()->webpackAssets('test');

        $this->assertStringContainsString('v=1&amp;foo=bar', $result);
        $this->assertStringNotContainsString('v=1&foo=bar', $result);
    }

    public function testEntryWithNoCssKeyStillWorks(): void
    {
        $this->writeEntrypoints([
            'entrypoints' => [
                'altcha' => ['js' => ['/build/altcha.js']],
            ],
        ]);

        $result = $this->helper()->webpackAssets('altcha');

        $this->assertStringContainsString('<script src="/build/altcha.js"></script>', $result);
    }
}
