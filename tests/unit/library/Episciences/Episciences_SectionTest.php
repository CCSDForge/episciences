<?php

namespace unit\library\Episciences;

use Episciences_Section;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Section
 *
 * Only pure-logic methods that need no DB, no session, no constants are tested here.
 *
 * @covers Episciences_Section
 */
class Episciences_SectionTest extends TestCase
{
    // =========================================================================
    // getName()
    // =========================================================================

    public function testGetNameReturnsFallbackWhenNoTitles(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(null); // initialize typed property before access
        $this->assertSame(Episciences_Section::UNLABELED_SECTION, $s->getName('en'));
    }

    public function testGetNameReturnsRequestedLanguage(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(['en' => 'My section', 'fr' => 'Ma rubrique']);
        $this->assertSame('My section', $s->getName('en'));
        $this->assertSame('Ma rubrique', $s->getName('fr'));
    }

    public function testGetNameFallsBackToFirstTitleWhenLangMissing(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(['en' => 'My section']);
        // 'de' does not exist → fallback to first entry
        $this->assertSame('My section', $s->getName('de'));
    }

    // =========================================================================
    // getNameKey()
    // =========================================================================

    public function testGetNameKeyWithForceTrueAndNoTitles(): void
    {
        $s = new Episciences_Section();
        $s->setSid(12);
        $s->setTitles(null); // initialize typed property before access
        $key = $s->getNameKey('en', true);
        $this->assertSame('section_12_title', $key);
    }

    public function testGetNameKeyWithForceFalseAndNoTitles(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(null); // initialize typed property before access
        $this->assertSame('', $s->getNameKey('en', false));
    }

    public function testGetNameKeyReturnsMatchingTitle(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(['en' => 'Section EN', 'fr' => 'Rubrique FR']);
        $this->assertSame('Section EN', $s->getNameKey('en'));
        $this->assertSame('Rubrique FR', $s->getNameKey('fr'));
    }

    // =========================================================================
    // getDescriptionKey()
    // =========================================================================

    public function testGetDescriptionKeyWithForceFalseAndNoDescriptions(): void
    {
        $s = new Episciences_Section();
        $s->setDescriptions(null); // initialize typed property before access
        $this->assertSame('', $s->getDescriptionKey(false));
    }

    public function testGetDescriptionKeyWithForceTrueAndNoDescriptions(): void
    {
        $s = new Episciences_Section();
        $s->setSid(5);
        $s->setDescriptions(null); // initialize typed property before access
        $key = $s->getDescriptionKey(true);
        $this->assertSame('section_5_description', $key);
    }

    // =========================================================================
    // getStatus()
    // =========================================================================

    public function testGetStatusDefaultsToZeroWhenSettingMissing(): void
    {
        $s = new Episciences_Section();
        // getSetting() returns false when key is absent; (int)false === 0
        $this->assertSame(0, $s->getStatus());
    }

    public function testGetStatusReturnsOpenStatus(): void
    {
        $s = new Episciences_Section();
        $s->setSetting(Episciences_Section::SETTING_STATUS, Episciences_Section::SECTION_OPEN_STATUS);
        $this->assertSame(Episciences_Section::SECTION_OPEN_STATUS, $s->getStatus());
    }

    public function testGetStatusReturnsClosedStatus(): void
    {
        $s = new Episciences_Section();
        $s->setSetting(Episciences_Section::SETTING_STATUS, Episciences_Section::SECTION_CLOSED_STATUS);
        $this->assertSame(Episciences_Section::SECTION_CLOSED_STATUS, $s->getStatus());
    }

    // =========================================================================
    // getSetting() / setSetting()
    // =========================================================================

    public function testGetSettingReturnsFalseForMissingSetting(): void
    {
        $s = new Episciences_Section();
        $this->assertFalse($s->getSetting('nonexistent'));
    }

    public function testSetAndGetSetting(): void
    {
        $s = new Episciences_Section();
        $s->setSetting('custom_key', 'custom_value');
        $this->assertSame('custom_value', $s->getSetting('custom_key'));
    }

    // =========================================================================
    // setPosition() / getPosition()
    // =========================================================================

    public function testSetPositionDefaultsToZero(): void
    {
        $s = new Episciences_Section();
        $this->assertSame(0, $s->getPosition());
    }

    public function testSetPositionStoresValue(): void
    {
        $s = new Episciences_Section();
        $s->setPosition(3);
        $this->assertSame(3, $s->getPosition());
    }

    // =========================================================================
    // toArray()
    // =========================================================================

    public function testToArrayContainsExpectedKeys(): void
    {
        $s = new Episciences_Section();
        $s->setSid(10);
        $s->setRvid(2);
        $s->setTitles(['en' => 'Title']);
        $s->setDescriptions(['en' => 'Desc']);

        $arr = $s->toArray();

        $this->assertArrayHasKey('sid', $arr);
        $this->assertArrayHasKey('rvid', $arr);
        $this->assertArrayHasKey('position', $arr);
        $this->assertArrayHasKey('titles', $arr);
        $this->assertArrayHasKey('descriptions', $arr);
        $this->assertSame(10, $arr['sid']);
        $this->assertSame(2, $arr['rvid']);
        $this->assertSame(['en' => 'Title'], $arr['titles']);
    }

    // =========================================================================
    // setTitles() / getTitles() / setDescriptions() / getDescriptions()
    // =========================================================================

    public function testSetAndGetTitles(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(['en' => 'T1', 'fr' => 'T2']);
        $this->assertSame(['en' => 'T1', 'fr' => 'T2'], $s->getTitles());
    }

    public function testSetTitlesNullReturnsNull(): void
    {
        $s = new Episciences_Section();
        $s->setTitles(null);
        $this->assertNull($s->getTitles());
    }

    public function testSetAndGetDescriptions(): void
    {
        $s = new Episciences_Section();
        $s->setDescriptions(['en' => 'D1']);
        $this->assertSame(['en' => 'D1'], $s->getDescriptions());
    }
}
