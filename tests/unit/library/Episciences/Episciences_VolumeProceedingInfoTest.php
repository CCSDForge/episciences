<?php

namespace unit\library\Episciences;

use Episciences_Volume;
use PHPUnit\Framework\TestCase;

/**
 * Episciences_Volume::getProceedingInfo() used to call loadSettings() on every
 * invocation, re-querying settings that Episciences_VolumesManager::find() had
 * already loaded. It now reads whatever is in memory.
 *
 * Most of these tests error out against the old implementation: the volume built
 * here has no database adapter, so the loadSettings() call raised
 * "Call to a member function select() on null".
 *
 * @covers Episciences_Volume
 */
final class Episciences_VolumeProceedingInfoTest extends TestCase
{
    /**
     * @param array<string, string> $settings
     */
    private function makeVolume(array $settings): Episciences_Volume
    {
        $volume = new Episciences_Volume();
        $volume->setVid(15);
        $volume->setSettings($settings);

        return $volume;
    }

    public function testReturnsTheInMemorySettingsWithoutQuerying(): void
    {
        $volume = $this->makeVolume([
            Episciences_Volume::VOLUME_IS_PROCEEDING => '1',
            Episciences_Volume::VOLUME_CONFERENCE_NAME => 'Some Conference',
            Episciences_Volume::VOLUME_CONFERENCE_ACRONYM => 'SC',
        ]);

        $info = $volume->getProceedingInfo();

        self::assertSame('1', $info[Episciences_Volume::VOLUME_IS_PROCEEDING]);
        self::assertSame('Some Conference', $info[Episciences_Volume::VOLUME_CONFERENCE_NAME]);
        self::assertSame('SC', $info[Episciences_Volume::VOLUME_CONFERENCE_ACRONYM]);
    }

    public function testExposesAllNineConferenceKeys(): void
    {
        self::assertSame([
            Episciences_Volume::VOLUME_IS_PROCEEDING,
            Episciences_Volume::VOLUME_CONFERENCE_NAME,
            Episciences_Volume::VOLUME_CONFERENCE_THEME,
            Episciences_Volume::VOLUME_CONFERENCE_ACRONYM,
            Episciences_Volume::VOLUME_CONFERENCE_NUMBER,
            Episciences_Volume::VOLUME_CONFERENCE_LOCATION,
            Episciences_Volume::VOLUME_CONFERENCE_START_DATE,
            Episciences_Volume::VOLUME_CONFERENCE_END_DATE,
            Episciences_Volume::VOLUME_CONFERENCE_DOI,
        ], array_keys($this->makeVolume([])->getProceedingInfo()));
    }

    public function testMissingSettingsAreReportedAsFalse(): void
    {
        $info = $this->makeVolume([])->getProceedingInfo();

        self::assertFalse($info[Episciences_Volume::VOLUME_CONFERENCE_NAME]);
        self::assertFalse($info[Episciences_Volume::VOLUME_IS_PROCEEDING]);
    }

    public function testIsProceedingReadsTheInMemorySetting(): void
    {
        self::assertSame(1, $this->makeVolume([Episciences_Volume::VOLUME_IS_PROCEEDING => '1'])->isProceeding());
        self::assertSame(0, $this->makeVolume([])->isProceeding());
    }
}
