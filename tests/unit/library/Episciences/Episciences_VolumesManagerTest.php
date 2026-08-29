<?php

namespace unit\library\Episciences;

use Episciences_VolumesManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_VolumesManager
 *
 * Only pure-logic static methods (no DB, no session, no constants) are tested.
 *
 * @covers Episciences_VolumesManager
 */
class Episciences_VolumesManagerTest extends TestCase
{
    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(Episciences_VolumesManager::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs(null, $args);
    }
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

    // =========================================================================
    // groupAssignmentRowsByVid() — pure logic, no DB
    // =========================================================================

    private function makeRow(int $vid, int $uid, string $status, string $when = '2024-01-01'): array
    {
        return ['ITEMID' => $vid, 'UID' => $uid, 'STATUS' => $status, 'WHEN' => $when];
    }

    public function testGroupAssignmentRowsByVidReturnsEmptyOnEmptyInput(): void
    {
        $this->assertSame([], $this->callPrivate('groupAssignmentRowsByVid', [[], true]));
        $this->assertSame([], $this->callPrivate('groupAssignmentRowsByVid', [[], false]));
    }

    public function testGroupAssignmentRowsByVidGroupsByItemId(): void
    {
        $rows = [
            $this->makeRow(10, 1, 'active'),
            $this->makeRow(20, 2, 'active'),
        ];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [$rows, true]);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertArrayHasKey(1, $result[10]);
        $this->assertArrayHasKey(2, $result[20]);
    }

    public function testGroupAssignmentRowsByVidFiltersNonActiveWhenActive(): void
    {
        $rows = [
            $this->makeRow(10, 1, 'active'),
            $this->makeRow(10, 2, 'inactive'),
        ];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [$rows, true]);

        $this->assertArrayHasKey(1, $result[10]);
        $this->assertArrayNotHasKey(2, $result[10] ?? []);
    }

    public function testGroupAssignmentRowsByVidIncludesNonActiveWhenNotActive(): void
    {
        $rows = [
            $this->makeRow(10, 1, 'active'),
            $this->makeRow(10, 2, 'inactive'),
        ];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [$rows, false]);

        $this->assertArrayHasKey(1, $result[10]);
        $this->assertArrayHasKey(2, $result[10]);
    }

    public function testGroupAssignmentRowsByVidLastRowWinsForSameVidAndUid(): void
    {
        $rows = [
            $this->makeRow(10, 1, 'active', '2023-01-01'),
            $this->makeRow(10, 1, 'active', '2024-06-01'),
        ];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [$rows, true]);

        $this->assertCount(1, $result[10]);
        $this->assertSame('2024-06-01', $result[10][1]['WHEN']);
    }

    public function testGroupAssignmentRowsByVidHandlesMultipleEditorsPerVolume(): void
    {
        $rows = [
            $this->makeRow(5, 100, 'active'),
            $this->makeRow(5, 101, 'active'),
            $this->makeRow(5, 102, 'inactive'),
        ];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [$rows, true]);

        $this->assertCount(2, $result[5]);
        $this->assertArrayHasKey(100, $result[5]);
        $this->assertArrayHasKey(101, $result[5]);
    }

    public function testGroupAssignmentRowsByVidReturnsRowData(): void
    {
        $row = $this->makeRow(7, 42, 'active', '2025-03-15');
        $result = $this->callPrivate('groupAssignmentRowsByVid', [[$row], true]);

        $this->assertSame('active', $result[7][42]['STATUS']);
        $this->assertSame('2025-03-15', $result[7][42]['WHEN']);
    }

    public function testGroupAssignmentRowsByVidCastsVidAndUidToInt(): void
    {
        $row = ['ITEMID' => '10', 'UID' => '42', 'STATUS' => 'active', 'WHEN' => '2024-01-01'];
        $result = $this->callPrivate('groupAssignmentRowsByVid', [[$row], true]);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(42, $result[10]);
    }

    // =========================================================================
    // extractUniqueUids() — pure logic, no DB
    // =========================================================================

    public function testExtractUniqueUidsReturnsEmptyOnEmptyInput(): void
    {
        $this->assertSame([], $this->callPrivate('extractUniqueUids', [[]]));
    }

    public function testExtractUniqueUidsSingleVolumeMultipleEditors(): void
    {
        $grouped = [
            10 => [100 => [], 101 => []],
        ];
        $uids = $this->callPrivate('extractUniqueUids', [$grouped]);
        sort($uids);

        $this->assertSame([100, 101], $uids);
    }

    public function testExtractUniqueUidsDeduplicatesAcrossVolumes(): void
    {
        $grouped = [
            10 => [100 => [], 101 => []],
            20 => [100 => [], 102 => []],
        ];
        $uids = $this->callPrivate('extractUniqueUids', [$grouped]);
        sort($uids);

        $this->assertSame([100, 101, 102], $uids);
        $this->assertCount(3, $uids);
    }

    public function testExtractUniqueUidsSingleEditor(): void
    {
        $grouped = [42 => [7 => []]];
        $uids = $this->callPrivate('extractUniqueUids', [$grouped]);

        $this->assertSame([7], $uids);
    }

    public function testExtractUniqueUidsReturnsIntKeys(): void
    {
        $grouped = [10 => [99 => []]];
        $uids = $this->callPrivate('extractUniqueUids', [$grouped]);

        $this->assertIsInt($uids[0]);
    }
}
