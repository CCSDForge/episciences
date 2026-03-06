<?php

namespace unit\library\Episciences;

use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Volume
 *
 * Only pure-logic methods that need no DB, no session, no constants are tested here.
 *
 * @covers Episciences_Volume
 */
class Episciences_VolumeTest extends TestCase
{
    // =========================================================================
    // findGapsInPaperOrders() — static, pure logic
    // =========================================================================

    public function testFindGapsReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], Episciences_Volume::findGapsInPaperOrders([]));
    }

    public function testFindGapsReturnsEmptyWhenNoGaps(): void
    {
        // positions 0,1,2 — contiguous, no gaps
        $papers = [0 => 'a', 1 => 'b', 2 => 'c'];
        $this->assertSame([], Episciences_Volume::findGapsInPaperOrders($papers));
    }

    public function testFindGapsSingleEntry(): void
    {
        // Only position 0 — max is 0, loop runs 0 times → no gaps
        $this->assertSame([], Episciences_Volume::findGapsInPaperOrders([0 => 'only']));
    }

    public function testFindGapsDetectsMissingMiddlePosition(): void
    {
        // positions 0, 2 — 1 is missing
        $papers = [0 => 'a', 2 => 'c'];
        $gaps = Episciences_Volume::findGapsInPaperOrders($papers);
        $this->assertContains(1, $gaps);
    }

    public function testFindGapsDetectsMultipleGaps(): void
    {
        // positions 0, 3 — gaps at 1 and 2
        $papers = [0 => 'a', 3 => 'd'];
        $gaps = Episciences_Volume::findGapsInPaperOrders($papers);
        $this->assertContains(1, $gaps);
        $this->assertContains(2, $gaps);
        $this->assertCount(2, $gaps);
    }

    public function testFindGapsDoesNotIncludeMaxAsGap(): void
    {
        // Loop goes up to (but not including) max — so max itself is never reported
        $papers = [0 => 'a', 5 => 'f'];
        $gaps = Episciences_Volume::findGapsInPaperOrders($papers);
        $this->assertNotContains(5, $gaps);
    }

    public function testFindGapsWithNonZeroBaseKey(): void
    {
        // positions 1, 3 — gaps at 0 and 2
        $papers = [1 => 'b', 3 => 'd'];
        $gaps = Episciences_Volume::findGapsInPaperOrders($papers);
        $this->assertContains(0, $gaps);
        $this->assertContains(2, $gaps);
    }

    // =========================================================================
    // getName() — no lang registry (lang=null path)
    // =========================================================================

    /**
     * When no titles exist, getName() always returns UNLABELED_VOLUME regardless of lang.
     */
    public function testGetNameReturnsFallbackWhenNoTitles(): void
    {
        $v = new Episciences_Volume();
        $v->setTitles(null); // initialize typed property before access
        $this->assertSame(Episciences_Volume::UNLABELED_VOLUME, $v->getName('en'));
    }

    public function testGetNameReturnsRequestedLanguage(): void
    {
        $v = new Episciences_Volume();
        $v->setTitles(['en' => 'English title', 'fr' => 'Titre français']);
        $this->assertSame('English title', $v->getName('en'));
        $this->assertSame('Titre français', $v->getName('fr'));
    }

    public function testGetNameFallsBackToFirstTitleWhenLangMissing(): void
    {
        $v = new Episciences_Volume();
        $v->setTitles(['en' => 'English title']);
        // 'de' does not exist → falls back to first title
        $this->assertSame('English title', $v->getName('de'));
    }

    // =========================================================================
    // getNameKey()
    // =========================================================================

    public function testGetNameKeyWithEmptyTitlesAndForceTrue(): void
    {
        $v = new Episciences_Volume();
        $v->setVid(42);
        $v->setTitles(null);
        $key = $v->getNameKey('en', true);
        $this->assertSame('volume_42_title', $key);
    }

    public function testGetNameKeyWithEmptyTitlesAndForceFalse(): void
    {
        $v = new Episciences_Volume();
        $v->setTitles(null);
        $this->assertSame('', $v->getNameKey('en', false));
    }

    public function testGetNameKeyReturnsMatchingTitle(): void
    {
        $v = new Episciences_Volume();
        $v->setTitles(['en' => 'My volume', 'fr' => 'Mon volume']);
        $this->assertSame('My volume', $v->getNameKey('en'));
        $this->assertSame('Mon volume', $v->getNameKey('fr'));
    }

    // =========================================================================
    // getDescriptionKey()
    // =========================================================================

    public function testGetDescriptionKeyWithForceFalseAndNoDescriptions(): void
    {
        $v = new Episciences_Volume();
        $v->setDescriptions(null);
        $this->assertSame('', $v->getDescriptionKey(false));
    }

    public function testGetDescriptionKeyWithForceTrueAndNoDescriptions(): void
    {
        $v = new Episciences_Volume();
        $v->setVid(7);
        $v->setDescriptions(null);
        $key = $v->getDescriptionKey(true);
        $this->assertSame('volume_7_description', $key);
    }

    // =========================================================================
    // getSetting() / setSetting()
    // =========================================================================

    public function testGetSettingReturnsFalseForMissingSetting(): void
    {
        $v = new Episciences_Volume();
        $this->assertFalse($v->getSetting('nonexistent'));
    }

    public function testSetAndGetSetting(): void
    {
        $v = new Episciences_Volume();
        $v->setSetting('status', '1');
        $this->assertSame('1', $v->getSetting('status'));
    }

    public function testSetSettingCastsValueToString(): void
    {
        $v = new Episciences_Volume();
        $v->setSetting('num', 42);
        $this->assertSame('42', $v->getSetting('num'));
    }

    // =========================================================================
    // setVid() / getVid()
    // =========================================================================

    public function testSetVidCastsToInt(): void
    {
        $v = new Episciences_Volume();
        $v->setVid('99');
        $this->assertSame(99, $v->getVid());
    }

    public function testSetVidWithZero(): void
    {
        $v = new Episciences_Volume();
        $v->setVid(0);
        $this->assertSame(0, $v->getVid());
    }

    // =========================================================================
    // setVol_type() / getVol_type()
    // =========================================================================

    public function testSetVolTypeAndGet(): void
    {
        $v = new Episciences_Volume();
        $v->setVol_type('special_issue');
        $this->assertSame('special_issue', $v->getVol_type());
    }

    public function testSetVolTypeNull(): void
    {
        $v = new Episciences_Volume();
        $v->setVol_type(null);
        $this->assertNull($v->getVol_type());
    }

    // =========================================================================
    // setVol_num() / getVol_num()
    // =========================================================================

    public function testSetVolNumAndGet(): void
    {
        $v = new Episciences_Volume();
        $v->setVol_num('3');
        // setVol_num() casts to int internally
        $this->assertSame(3, $v->getVol_num());
    }

    // =========================================================================
    // toArray()
    // =========================================================================

    public function testToArrayContainsExpectedKeys(): void
    {
        $v = new Episciences_Volume();
        $v->setVid(10);
        $v->setRvid(5);
        $v->setTitles(['en' => 'T']);
        $v->setDescriptions(['en' => 'D']);

        $arr = $v->toArray();

        $this->assertArrayHasKey('vid', $arr);
        $this->assertArrayHasKey('rvid', $arr);
        $this->assertArrayHasKey('position', $arr);
        $this->assertArrayHasKey('titles', $arr);
        $this->assertArrayHasKey('descriptions', $arr);
        $this->assertArrayHasKey('settings', $arr);
        $this->assertArrayHasKey('metadatas', $arr);
        $this->assertSame(10, $arr['vid']);
        $this->assertSame(5, $arr['rvid']);
        $this->assertSame(['en' => 'T'], $arr['titles']);
    }

    // =========================================================================
    // sanitizeMetadataValues() — private, tested via reflection
    // =========================================================================

    public function testSanitizeMetadataValuesEscapesTitleHtml(): void
    {
        $v = new Episciences_Volume();
        $method = new ReflectionMethod(Episciences_Volume::class, 'sanitizeMetadataValues');
        $method->setAccessible(true);

        $input = [
            'title' => ['en' => '<script>alert("xss")</script>'],
        ];

        $result = $method->invoke($v, $input);
        $this->assertStringNotContainsString('<script>', $result['title']['en']);
        $this->assertStringContainsString('&lt;script&gt;', $result['title']['en']);
    }

    public function testSanitizeMetadataValuesEscapesContentHtml(): void
    {
        $v = new Episciences_Volume();
        $method = new ReflectionMethod(Episciences_Volume::class, 'sanitizeMetadataValues');
        $method->setAccessible(true);

        $input = [
            'content' => ['fr' => '<b onmouseover="evil()">text</b>'],
        ];

        $result = $method->invoke($v, $input);
        $this->assertStringNotContainsString('<b ', $result['content']['fr']);
    }

    public function testSanitizeMetadataValuesPassesThroughSafeFields(): void
    {
        $v = new Episciences_Volume();
        $method = new ReflectionMethod(Episciences_Volume::class, 'sanitizeMetadataValues');
        $method->setAccessible(true);

        $input = [
            'id'         => 99,
            'file'       => 'document.pdf',
            'deletelist' => [1, 2],
        ];

        $result = $method->invoke($v, $input);
        $this->assertSame(99, $result['id']);
        $this->assertSame('document.pdf', $result['file']);
        $this->assertSame([1, 2], $result['deletelist']);
    }

    public function testSanitizeMetadataValuesIgnoresNonStringTitleEntries(): void
    {
        $v = new Episciences_Volume();
        $method = new ReflectionMethod(Episciences_Volume::class, 'sanitizeMetadataValues');
        $method->setAccessible(true);

        // Non-string values in the title array must be silently ignored
        $input = [
            'title' => ['en' => 'Good title', 'fr' => null],
        ];

        $result = $method->invoke($v, $input);
        $this->assertArrayHasKey('en', $result['title']);
        $this->assertArrayNotHasKey('fr', $result['title']);
    }

    // =========================================================================
    // savePaperPositionsInVolume() (instance method) — string parsing logic
    // =========================================================================

    /**
     * Tests that the instance-level savePaperPositionsInVolume() correctly parses
     * the comma-separated "paper-N" string and skips non-numeric entries.
     * We stub out the static VolumesManager call by verifying the parsing only.
     */
    public function testSavePaperPositionsSkipsNonNumericPaperIds(): void
    {
        // The method calls Episciences_VolumesManager::savePaperPositionsInVolume(),
        // which requires DB. We cannot run that part here without a real DB.
        // We test the string-parsing logic indirectly by checking it does not
        // throw for malformed entries.
        $this->expectNotToPerformAssertions();

        $v = new Episciences_Volume();
        // "paper-abc" → not numeric → skipped. "paper-5" → valid.
        // We can't assert the DB write, but we confirm no exception is thrown.
        // (Actual DB call is skipped because $paper_positions ends up empty)
        $v->savePaperPositionsInVolume(1, 'paper-abc,paper-xyz');
    }
}
