<?php

namespace unit\library\Episciences;

use Episciences_SectionsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_SectionsManager
 *
 * Only pure-logic static methods (no DB, no session, no constants) are tested.
 *
 * @covers Episciences_SectionsManager
 */
class Episciences_SectionsManagerTest extends TestCase
{
    // =========================================================================
    // revertSectionTitleToTextArray()
    // =========================================================================

    public function testRevertSectionTitleToTextArrayReturnsNullOnNull(): void
    {
        $this->assertNull(Episciences_SectionsManager::revertSectionTitleToTextArray(null));
    }

    public function testRevertSectionTitleToTextArrayReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(Episciences_SectionsManager::revertSectionTitleToTextArray([]));
    }

    public function testRevertSectionTitleToTextArrayExtractsLangs(): void
    {
        $input = [
            'title_en' => 'English section',
            'title_fr' => 'Rubrique française',
        ];
        $result = Episciences_SectionsManager::revertSectionTitleToTextArray($input);
        $this->assertSame(['en' => 'English section', 'fr' => 'Rubrique française'], $result);
    }

    public function testRevertSectionTitleToTextArrayIgnoresNonTitleKeys(): void
    {
        $input = [
            'title_en'       => 'Title EN',
            'description_en' => 'Should be ignored',
            'status'         => '1',
        ];
        $result = Episciences_SectionsManager::revertSectionTitleToTextArray($input);
        $this->assertArrayHasKey('en', $result);
        $this->assertCount(1, $result);
    }

    public function testRevertSectionTitleToTextArrayReturnsEmptyWhenNoTitleKeys(): void
    {
        $input = ['status' => '1', 'description_en' => 'Desc'];
        $result = Episciences_SectionsManager::revertSectionTitleToTextArray($input);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // revertSectionDescriptionToTextareaArray()
    // =========================================================================

    public function testRevertSectionDescriptionToTextareaArrayReturnsNullOnNull(): void
    {
        $this->assertNull(Episciences_SectionsManager::revertSectionDescriptionToTextareaArray(null));
    }

    public function testRevertSectionDescriptionToTextareaArrayReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(Episciences_SectionsManager::revertSectionDescriptionToTextareaArray([]));
    }

    public function testRevertSectionDescriptionToTextareaArrayExtractsLangs(): void
    {
        $input = [
            'description_en' => 'English description',
            'description_fr' => 'Description française',
        ];
        $result = Episciences_SectionsManager::revertSectionDescriptionToTextareaArray($input);
        $this->assertSame(['en' => 'English description', 'fr' => 'Description française'], $result);
    }

    public function testRevertSectionDescriptionToTextareaArrayIgnoresNonDescriptionKeys(): void
    {
        $input = [
            'description_en' => 'Desc',
            'title_en'       => 'Should be ignored',
        ];
        $result = Episciences_SectionsManager::revertSectionDescriptionToTextareaArray($input);
        $this->assertArrayHasKey('en', $result);
        $this->assertCount(1, $result);
    }

    // =========================================================================
    // translateSectionKey() — non-numeric guard (no DB required for this path)
    // =========================================================================

    public function testTranslateSectionKeyReturnsEmptyStringForNonNumericKey(): void
    {
        $this->assertSame('', Episciences_SectionsManager::translateSectionKey('abc'));
    }

    public function testTranslateSectionKeyReturnsEmptyStringForEmptyString(): void
    {
        $this->assertSame('', Episciences_SectionsManager::translateSectionKey(''));
    }

    public function testTranslateSectionKeyReturnsEmptyStringForZeroString(): void
    {
        $this->assertSame('', Episciences_SectionsManager::translateSectionKey('0'));
    }

    public function testTranslateSectionKeyReturnsEmptyStringForAlphanumericString(): void
    {
        // filter_var('abc123', FILTER_SANITIZE_NUMBER_INT) → '123' → (int)'123' = 123
        // Then self::find(123) hits DB → cannot test here.
        // 'xyz' → '' → 0 → return ''
        $this->assertSame('', Episciences_SectionsManager::translateSectionKey('xyz'));
    }
}
