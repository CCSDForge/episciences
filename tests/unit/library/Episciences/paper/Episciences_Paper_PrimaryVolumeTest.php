<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for the primary volume memoisation added to Episciences_Paper.
 *
 * Episciences_VolumesManager::find() is a static DB call with no seam, so these
 * tests exercise the cache contract only: the no-VID short circuit, the memo hit,
 * and — the part that would silently export the wrong volume if it broke — the
 * invalidation performed by setVid().
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_PrimaryVolumeTest extends TestCase
{
    private function readLoadedFlag(Episciences_Paper $paper): bool
    {
        $property = new ReflectionProperty(Episciences_Paper::class, '_primaryVolumeLoaded');
        $property->setAccessible(true);

        return $property->getValue($paper);
    }

    private function seedMemo(Episciences_Paper $paper, ?Episciences_Volume $volume): void
    {
        foreach (['_primaryVolume' => $volume, '_primaryVolumeLoaded' => true] as $name => $value) {
            $property = new ReflectionProperty(Episciences_Paper::class, $name);
            $property->setAccessible(true);
            $property->setValue($paper, $value);
        }
    }

    public function testReturnsNullWithoutHittingTheDatabaseWhenThePaperHasNoVolume(): void
    {
        $paper = new Episciences_Paper();

        self::assertNull($paper->getPrimaryVolume());
    }

    public function testTheMemoIsMarkedLoadedEvenWithoutAVolume(): void
    {
        $paper = new Episciences_Paper();
        $paper->getPrimaryVolume();

        // a paper without a volume must not retry the lookup on every read
        self::assertTrue($this->readLoadedFlag($paper));
    }

    public function testReturnsTheMemoisedVolume(): void
    {
        $volume = new Episciences_Volume();
        $volume->setVid(15);

        $paper = new Episciences_Paper();
        $paper->setVid(15);
        $this->seedMemo($paper, $volume);

        self::assertSame($volume, $paper->getPrimaryVolume());
        // second read must come from the memo, not from a fresh lookup
        self::assertSame($volume, $paper->getPrimaryVolume());
    }

    public function testChangingTheVidDropsTheMemo(): void
    {
        $volume = new Episciences_Volume();
        $volume->setVid(15);

        $paper = new Episciences_Paper();
        $paper->setVid(15);
        $this->seedMemo($paper, $volume);

        $paper->setVid(20);

        self::assertFalse($this->readLoadedFlag($paper));
    }

    public function testSettingTheSameVidKeepsTheMemo(): void
    {
        $volume = new Episciences_Volume();
        $volume->setVid(15);

        $paper = new Episciences_Paper();
        $paper->setVid(15);
        $this->seedMemo($paper, $volume);

        $paper->setVid(15);

        self::assertTrue($this->readLoadedFlag($paper));
        self::assertSame($volume, $paper->getPrimaryVolume());
    }

    public function testResetPrimaryVolumeDropsTheMemo(): void
    {
        $paper = new Episciences_Paper();
        $paper->setVid(15);
        $this->seedMemo($paper, new Episciences_Volume());

        self::assertSame($paper, $paper->resetPrimaryVolume());
        self::assertFalse($this->readLoadedFlag($paper));
    }
}
