<?php

declare(strict_types=1);

namespace unit\library\Episciences\Review;

use Episciences_Review;
use PHPUnit\Framework\TestCase;
use unit\modules\support\RecordingDbAdapter;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_Review::getSettings() loads the stored settings when they were not loaded yet.
 */
final class ReviewSettingsLoadingTest extends TestCase
{
    private const RVID = 8;

    private RecordingDbAdapter $db;

    private ?Zend_Db_Adapter_Abstract $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->db = new RecordingDbAdapter();
        $this->db->respondTo('/FROM `REVIEW_SETTING` WHERE \\(RVID = ' . self::RVID . '\\)/', [
            ['RVID' => self::RVID, 'SETTING' => Episciences_Review::SETTING_ISSN, 'VALUE' => '1234-5678'],
        ]);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testSettingsAreLoadedWhenNotLoadedYet(): void
    {
        $review = new Episciences_Review(['rvid' => self::RVID]);

        self::assertSame('1234-5678', $review->getSettings()[Episciences_Review::SETTING_ISSN] ?? null);
    }

    public function testSettingsAreLoadedOnce(): void
    {
        $review = new Episciences_Review(['rvid' => self::RVID]);

        $review->getSettings();
        $review->getSettings();
        $review->getSetting(Episciences_Review::SETTING_ISSN);

        self::assertCount(1, $this->db->selects());
    }

    public function testSettingsSetInMemoryAreKeptOnTopOfTheStoredOnes(): void
    {
        $review = new Episciences_Review(['rvid' => self::RVID]);
        $review->setSetting(Episciences_Review::SETTING_ISSN_PRINT, '8765-4321');

        self::assertSame([
            Episciences_Review::SETTING_ISSN => '1234-5678',
            Episciences_Review::SETTING_ISSN_PRINT => '8765-4321',
        ], $review->getSettings());
    }

    public function testSettingsSetInMemoryAreNotReplacedByTheStoredOnes(): void
    {
        $review = new Episciences_Review(['rvid' => self::RVID]);
        $review->setSetting(Episciences_Review::SETTING_ISSN, '8765-4321');

        self::assertSame('8765-4321', $review->getSettings()[Episciences_Review::SETTING_ISSN] ?? null);
        self::assertSame('8765-4321', $review->getSetting(Episciences_Review::SETTING_ISSN));
    }

    public function testForcedReloadDropsTheSettingsSetInMemory(): void
    {
        $review = new Episciences_Review(['rvid' => self::RVID]);
        $review->setSetting(Episciences_Review::SETTING_ISSN, '8765-4321');

        $review->loadSettings(true);

        self::assertSame([Episciences_Review::SETTING_ISSN => '1234-5678'], $review->getSettings());
    }
}
