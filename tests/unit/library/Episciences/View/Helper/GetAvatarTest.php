<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_GetAvatar;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_View_Helper_GetAvatar
 *
 * @covers Episciences_View_Helper_GetAvatar
 */
class GetAvatarTest extends TestCase
{
    /**
     * Test that getPaperStatusColors() returns an array
     */
    public function testGetPaperStatusColorsReturnsArray(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        $this->assertIsArray($colors);
    }

    /**
     * Test that getPaperStatusColors() contains the expected number of entries
     */
    public function testGetPaperStatusColorsCount(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        // Indices 0 to 33 inclusive = 34 entries
        $this->assertCount(34, $colors);
    }

    /**
     * Test that all color values are non-empty strings
     */
    public function testGetPaperStatusColorsAllNonEmpty(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        foreach ($colors as $index => $color) {
            $this->assertIsString($color, "Color at index $index should be a string");
            $this->assertNotEmpty($color, "Color at index $index should not be empty");
        }
    }

    /**
     * Test specific color values for well-known statuses
     */
    public function testGetPaperStatusColorsKnownValues(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();

        $this->assertSame('#aaa', $colors[0]);   // status 0
        $this->assertSame('#666', $colors[1]);   // status 1
        $this->assertSame('#333', $colors[2]);   // status 2
        $this->assertSame('#004876', $colors[3]); // status 3
        $this->assertSame('#9d0', $colors[4]);   // status 4 (accepted)
        $this->assertSame('#FE1B00', $colors[5]); // status 5
        $this->assertSame('#d22', $colors[6]);   // status 6
        $this->assertSame('#f8e806', $colors[7]); // status 7 (yellow fg)
        $this->assertSame('#f89406', $colors[8]); // status 8
        $this->assertSame('#ca6d00', $colors[9]); // status 9
        $this->assertSame('#FF4500', $colors[10]); // status 10
    }

    /**
     * Test that statuses 25-31 share the same color as status 4
     */
    public function testGetPaperStatusColorsSharedWithStatus4(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        $colorStatus4 = $colors[4];

        foreach (range(25, 31) as $status) {
            $this->assertSame(
                $colorStatus4,
                $colors[$status],
                "Status $status should share color with status 4"
            );
        }
    }

    /**
     * Test that status 32 shares the same color as status 20
     */
    public function testGetPaperStatusColor32SharesWithStatus20(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        $this->assertSame($colors[20], $colors[32]);
    }

    /**
     * Test that status 33 shares the same color as status 23
     */
    public function testGetPaperStatusColor33SharesWithStatus23(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        $this->assertSame($colors[23], $colors[33]);
    }

    /**
     * Test that all color values look like valid CSS color strings
     */
    public function testGetPaperStatusColorsLookLikeCssColors(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();

        foreach ($colors as $index => $color) {
            // CSS colors start with '#'
            $this->assertStringStartsWith('#', $color, "Color at index $index should start with '#'");
        }
    }

    /**
     * Test exact color values for statuses 11-24 (previously uncovered)
     */
    public function testGetPaperStatusColorsKnownValuesIndices11To24(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();

        $this->assertSame('#ff7f50', $colors[11]);
        $this->assertSame('#D90115', $colors[12]);
        $this->assertSame('#F7230C', $colors[13]);
        $this->assertSame('#1E7FCB', $colors[14]);
        $this->assertSame('#f89406', $colors[15]);
        $this->assertSame('#009527', $colors[16]);
        $this->assertSame('#ff6347', $colors[17]);
        $this->assertSame('#DD985C', $colors[18]);
        $this->assertSame('#3A8EBA', $colors[19]);
        $this->assertSame('#175732', $colors[20]);
        $this->assertSame('#c50',    $colors[21]);
        $this->assertSame('#708D23', $colors[22]);
        $this->assertSame('#689D71', $colors[23]);
        $this->assertSame('#048B9A', $colors[24]);
    }

    /**
     * Test that asSvg() returns a non-empty SVG string
     */
    public function testAsSvgReturnsValidSvgString(): void
    {
        $result = Episciences_View_Helper_GetAvatar::asSvg('John Doe');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsStringIgnoringCase('<svg', $result);
    }

    /**
     * Test that asPaperStatusSvg() returns '404.svg' for an unknown status
     */
    public function testAsPaperStatusSvgInvalidStatusReturns404(): void
    {
        $result = Episciences_View_Helper_GetAvatar::asPaperStatusSvg('ACC', 999);
        $this->assertSame('404.svg', $result);
    }

    /**
     * Test that asPaperStatusSvg() returns '404.svg' for a crafted string status
     * that would pass an int-cast check but contain path traversal characters.
     */
    public function testAsPaperStatusSvgPathTraversalStatusIsRejected(): void
    {
        // (int)"4/../../../evil" === 4, which is valid — the fix must use (int) in the filename too
        $paperStatusDir = REVIEW_PUBLIC_PATH . 'paper-status';
        if (!is_dir($paperStatusDir)) {
            mkdir($paperStatusDir, 0755, true);
        }

        $result = Episciences_View_Helper_GetAvatar::asPaperStatusSvg('ACC', '4/../../../evil');

        // The returned path must not escape the paper-status directory
        $this->assertStringNotContainsString('..', $result);
        $this->assertStringStartsWith('/public/paper-status/4.', $result);

        // Cleanup any written file
        $writtenFile = $paperStatusDir . '/4.en.svg';
        if (file_exists($writtenFile)) {
            unlink($writtenFile);
        }
    }

    /**
     * Test that asPaperStatusSvg() returns the expected URL path for a valid status
     * and that the SVG file is actually written to disk.
     */
    public function testAsPaperStatusSvgValidStatusCreatesFile(): void
    {
        $paperStatusDir = REVIEW_PUBLIC_PATH . 'paper-status';
        if (!is_dir($paperStatusDir)) {
            mkdir($paperStatusDir, 0755, true);
        }

        $writtenFile = $paperStatusDir . '/4.en.svg';
        // Ensure the file does not already exist so asPaperStatusSvg creates it
        if (file_exists($writtenFile)) {
            unlink($writtenFile);
        }

        $result = Episciences_View_Helper_GetAvatar::asPaperStatusSvg('ACC', 4);

        $this->assertSame('/public/paper-status/4.en.svg', $result);

        $this->assertFileExists($writtenFile);
        $this->assertStringContainsStringIgnoringCase('<svg', file_get_contents($writtenFile));

        // Cleanup
        unlink($writtenFile);
    }
}
