<?php

declare(strict_types=1);

namespace unit\library\Episciences\Rating;

use Episciences_GridsManager;
use Episciences_Rating_Grid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Grid of a review request type (#319): key "t<type id>", file grid_t<type id>.xml, kept apart from the volume grids.
 *
 * @covers Episciences_GridsManager::gridKey
 * @covers Episciences_GridsManager::requestTypeGridKey
 * @covers Episciences_GridsManager::requestTypeIdOfKey
 * @covers Episciences_GridsManager::filenameOf
 * @covers Episciences_Rating_Grid::loadXML
 */
final class Episciences_GridsManager_RequestTypeGridTest extends TestCase
{
    private const DEFAULT_GRID = APPLICATION_PATH . '/../data/default/files/rating_grids/grid_0.xml';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        foreach (array_unique(array_map('dirname', $this->files)) as $dir) {
            @rmdir($dir);
        }
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function keys(): array
    {
        return [
            'default grid' => ['0', '0'],
            'volume grid' => ['12', '12'],
            'volume grid as int' => [12, '12'],
            'request type grid' => ['t5', 't5'],
            'path' => ['../5', null],
            'negative' => ['-5', null],
            'other prefix' => ['x5', null],
            'empty' => ['', null],
            'array' => [['5'], null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('keys')]
    public function testOnlyAVolumeIdOrARequestTypeKeyIsAGridKey(mixed $raw, ?string $expected): void
    {
        self::assertSame($expected, Episciences_GridsManager::gridKey($raw));
    }

    public function testTheKeyOfARequestTypeGridGivesBackTheType(): void
    {
        $key = Episciences_GridsManager::requestTypeGridKey(5);

        self::assertSame('t5', $key);
        self::assertSame(5, Episciences_GridsManager::requestTypeIdOfKey($key));
        self::assertNull(Episciences_GridsManager::requestTypeIdOfKey('5'));
        self::assertNull(Episciences_GridsManager::requestTypeIdOfKey('0'));
        self::assertSame('grid_t5.xml', Episciences_GridsManager::filenameOf($key));
    }

    private function gridFile(string $filename): string
    {
        $dir = sys_get_temp_dir() . '/grids-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . '/' . $filename;
        copy(self::DEFAULT_GRID, $file);
        $this->files[] = $file;

        return $file;
    }

    public function testARequestTypeGridKeepsItsKeyInsteadOfReadingAVolumeId(): void
    {
        $typeGrid = new Episciences_Rating_Grid();
        self::assertTrue($typeGrid->loadXML($this->gridFile('grid_t5.xml')));

        $volumeGrid = new Episciences_Rating_Grid();
        self::assertTrue($volumeGrid->loadXML($this->gridFile('grid_5.xml')));

        self::assertSame('t5', (string)$typeGrid->getId());
        self::assertSame('5', (string)$volumeGrid->getId());
    }
}
