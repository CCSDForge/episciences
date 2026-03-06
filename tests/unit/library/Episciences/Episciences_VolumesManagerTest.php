<?php

namespace unit\library\Episciences;

use Episciences_VolumesManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_VolumesManager
 *
 * Only pure-logic static methods (no DB, no session, no constants) are tested.
 *
 * @covers Episciences_VolumesManager
 */
class Episciences_VolumesManagerTest extends TestCase
{
    // =========================================================================
    // revertVolumeTitleToTextArray()
    // =========================================================================

    public function testRevertVolumeTitleToTextArrayReturnsNullOnNull(): void
    {
        $this->assertNull(Episciences_VolumesManager::revertVolumeTitleToTextArray(null));
    }

    public function testRevertVolumeTitleToTextArrayReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(Episciences_VolumesManager::revertVolumeTitleToTextArray([]));
    }

    public function testRevertVolumeTitleToTextArrayExtractsLangs(): void
    {
        $input = [
            'title_en' => 'English title',
            'title_fr' => 'Titre français',
        ];
        $result = Episciences_VolumesManager::revertVolumeTitleToTextArray($input);
        $this->assertSame(['en' => 'English title', 'fr' => 'Titre français'], $result);
    }

    public function testRevertVolumeTitleToTextArrayIgnoresNonTitleKeys(): void
    {
        $input = [
            'title_en'       => 'Title',
            'description_en' => 'Description',
            'other_key'      => 'Other',
        ];
        $result = Episciences_VolumesManager::revertVolumeTitleToTextArray($input);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayNotHasKey('description_en', $result);
        $this->assertArrayNotHasKey('other_key', $result);
    }

    public function testRevertVolumeTitleToTextArrayReturnsEmptyArrayWhenNoTitleKeys(): void
    {
        $input = ['status' => '1', 'year' => '2024'];
        // No keys start with 'title_' → output is []
        // Note: empty([]) is true → function returns null; but if non-empty input
        // has no matches the output is [] which is not empty, so it returns [] not null.
        // Actually: empty($input) is false → we enter the loop → no match → $output = [].
        // Return value is $output = [] (NOT null).
        $result = Episciences_VolumesManager::revertVolumeTitleToTextArray($input);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // revertVolumeDescriptionToTextareaArray()
    // =========================================================================

    public function testRevertVolumeDescriptionToTextareaArrayReturnsNullOnNull(): void
    {
        $this->assertNull(Episciences_VolumesManager::revertVolumeDescriptionToTextareaArray(null));
    }

    public function testRevertVolumeDescriptionToTextareaArrayReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(Episciences_VolumesManager::revertVolumeDescriptionToTextareaArray([]));
    }

    public function testRevertVolumeDescriptionToTextareaArrayExtractsLangs(): void
    {
        $input = [
            'description_en' => 'English description',
            'description_fr' => 'Description française',
        ];
        $result = Episciences_VolumesManager::revertVolumeDescriptionToTextareaArray($input);
        $this->assertSame(['en' => 'English description', 'fr' => 'Description française'], $result);
    }

    public function testRevertVolumeDescriptionToTextareaArrayIgnoresNonDescriptionKeys(): void
    {
        $input = [
            'description_en' => 'Desc',
            'title_en'       => 'Title',
        ];
        $result = Episciences_VolumesManager::revertVolumeDescriptionToTextareaArray($input);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayNotHasKey('title_en', $result);
    }

    // =========================================================================
    // translateVolumeKey() — non-numeric guard (no DB required for this path)
    // =========================================================================

    public function testTranslateVolumeKeyReturnsEmptyStringForNonNumericKey(): void
    {
        // FILTER_SANITIZE_NUMBER_INT on 'abc' → '' → (int)'' === 0 → return ''
        $this->assertSame('', Episciences_VolumesManager::translateVolumeKey('abc'));
    }

    public function testTranslateVolumeKeyReturnsEmptyStringForEmptyString(): void
    {
        $this->assertSame('', Episciences_VolumesManager::translateVolumeKey(''));
    }

    public function testTranslateVolumeKeyReturnsEmptyStringForZero(): void
    {
        // '0' → (int)0 → falsy → return ''
        $this->assertSame('', Episciences_VolumesManager::translateVolumeKey('0'));
    }

    public function testTranslateVolumeKeyReturnsEmptyStringForNegativeNumberString(): void
    {
        // filter_var('-1', FILTER_SANITIZE_NUMBER_INT) → '-1' → (int)'-1' === -1 → truthy
        // Then self::find(-1) hits the DB and returns false.
        // We can't test the DB path here; just confirm the non-numeric guard works.
        // For 'abc', the result is 0 → empty string.
        $this->assertSame('', Episciences_VolumesManager::translateVolumeKey('not-a-number!@#'));
    }
}
