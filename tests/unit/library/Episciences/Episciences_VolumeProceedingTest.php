<?php

namespace unit\library\Episciences;

use Episciences_VolumeProceeding;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_VolumeProceeding
 *
 * Only pure-logic methods that need no DB are tested here.
 * save/delete methods require DB and are not covered.
 *
 * @covers Episciences_VolumeProceeding
 */
class Episciences_VolumeProceedingTest extends TestCase
{
    // =========================================================================
    // getSetting() / setSetting() / getSettings() / setSettings()
    // =========================================================================

    public function testGetSettingReturnsFalseForMissingSetting(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $this->assertFalse($vp->getSetting('nonexistent'));
    }

    public function testSetAndGetSetting(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $vp->setSetting('conference_name', 'My Conference');
        $this->assertSame('My Conference', $vp->getSetting('conference_name'));
    }

    public function testSetSettingCastsValueToString(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $vp->setSetting('conference_number', 42);
        $this->assertSame('42', $vp->getSetting('conference_number'));
    }

    public function testGetSettingsReturnsAllSettings(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $vp->setSetting('name', 'Conf A');
        $vp->setSetting('location', 'Paris');
        $settings = $vp->getSettings();
        $this->assertSame('Conf A', $settings['name']);
        $this->assertSame('Paris', $settings['location']);
    }

    public function testSetSettingsReplacesAllSettings(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $vp->setSetting('old_key', 'old_value');
        $vp->setSettings(['new_key' => 'new_value']);
        $this->assertFalse($vp->getSetting('old_key'));
        $this->assertSame('new_value', $vp->getSetting('new_key'));
    }

    public function testGetSettingsDefaultsToEmptyArray(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $this->assertSame([], $vp->getSettings());
    }

    // =========================================================================
    // setOptions() — mirrors the Volume / Section pattern
    // =========================================================================

    public function testSetOptionsDispatchesToSetters(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $vp->setOptions(['settings' => ['key' => 'val']]);
        $this->assertSame('val', $vp->getSetting('key'));
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $vp = new Episciences_VolumeProceeding();
        // 'unknownkey' has no setter → silently ignored
        $vp->setOptions(['unknownkey' => 'whatever']);
        $this->assertSame([], $vp->getSettings());
    }

    public function testSetOptionsReturnsInstance(): void
    {
        $vp = new Episciences_VolumeProceeding();
        $result = $vp->setOptions([]);
        $this->assertSame($vp, $result);
    }
}
