<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Volume;
use Episciences_Volume_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for the secondary volumes block of the JSON v2 paper export
 * (database.current.secondary_volumes), see issue #1140.
 *
 * Tested methods:
 *   - Episciences_Paper::getSecondaryVolumesToJson()
 *   - Episciences_Paper::formatSecondaryVolumeToJson()
 *
 * All tests are DB-free: the null paths return before any query, and the
 * populated paths stub resolveSecondaryVolumes() via createPartialMock().
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_SecondaryVolumesJsonTest extends TestCase
{
    /**
     * @param Episciences_Paper $paper
     * @return array<int, array<string, mixed>>|null
     */
    private function callGetSecondaryVolumesToJson(Episciences_Paper $paper): ?array
    {
        $method = new ReflectionMethod(Episciences_Paper::class, 'getSecondaryVolumesToJson');
        $method->setAccessible(true);

        return $method->invoke($paper);
    }

    /**
     * @return array<string, mixed>
     */
    private function callFormatSecondaryVolumeToJson(Episciences_Volume $volume): array
    {
        $method = new ReflectionMethod(Episciences_Paper::class, 'formatSecondaryVolumeToJson');
        $method->setAccessible(true);

        return $method->invoke(null, $volume);
    }

    /**
     * Builds a volume without ever touching the database: no loadSettings(),
     * no loadMetadatas(), and no array constructor (setOptions() would generate
     * an access code).
     *
     * @param array<string, string>|null $titles
     * @param array<string, string>|null $descriptions
     * @param array<string, string> $settings
     */
    private function makeVolume(
        int     $vid,
        int     $position = 1,
        ?string $num = '1',
        ?string $year = '2024',
        ?array  $titles = null,
        ?array  $descriptions = null,
        ?string $bibReference = null,
        array   $settings = []
    ): Episciences_Volume
    {
        $volume = new Episciences_Volume();
        $volume->setVid($vid);
        $volume->setPosition($position);
        $volume->setVol_num($num);
        $volume->setVol_year($year);
        $volume->setTitles($titles);
        $volume->setDescriptions($descriptions);
        $volume->setBib_reference($bibReference);
        $volume->setSettings($settings);

        return $volume;
    }

    public function testReturnsNullWhenNoSecondaryVolume(): void
    {
        $paper = new Episciences_Paper();
        $paper->setVid(12);
        $paper->setOtherVolumes([]);

        self::assertNull($this->callGetSecondaryVolumesToJson($paper));
    }

    public function testReturnsNullWhenOnlyThePrimaryVolumeIsAttached(): void
    {
        $paper = new Episciences_Paper();
        $paper->setVid(12);
        $paper->setOtherVolumes([
            new Episciences_Volume_Paper(['id' => 1, 'vid' => 12, 'docid' => 99]),
        ]);

        self::assertNull($this->callGetSecondaryVolumesToJson($paper));
    }

    public function testIgnoresInvalidVids(): void
    {
        $paper = new Episciences_Paper();
        $paper->setVid(12);
        $paper->setOtherVolumes([
            new Episciences_Volume_Paper(['id' => 1, 'vid' => 0, 'docid' => 99]),
        ]);

        self::assertNull($this->callGetSecondaryVolumesToJson($paper));
    }

    public function testReturnsOneFormattedSecondaryVolume(): void
    {
        $volume = $this->makeVolume(
            vid: 15,
            position: 3,
            num: '2',
            year: '2024',
            titles: ['en' => 'Special Track on AI', 'fr' => 'Session spéciale sur l\'IA'],
            descriptions: ['en' => 'Description...', 'fr' => 'Description...'],
        );

        $paper = $this->createPartialMock(Episciences_Paper::class, ['resolveSecondaryVolumes']);
        $paper->setVid(12);
        $paper->setOtherVolumes([
            new Episciences_Volume_Paper(['id' => 1, 'vid' => 15, 'docid' => 99]),
        ]);
        $paper->method('resolveSecondaryVolumes')->willReturn([15 => $volume]);

        $result = $this->callGetSecondaryVolumesToJson($paper);

        self::assertSame([
            [
                'id' => 15,
                'position' => 3,
                'number' => '2',
                'year' => '2024',
                'has_proceedings' => false,
                'titles' => ['en' => 'Special Track on AI', 'fr' => 'Session spéciale sur l\'IA'],
                'descriptions' => ['en' => 'Description...', 'fr' => 'Description...'],
                'bibliographical_references' => null,
            ],
        ], $result);
    }

    public function testDeduplicatesVidsAndKeepsResolvedOrder(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['resolveSecondaryVolumes']);
        $paper->setVid(12);
        $paper->setOtherVolumes([
            new Episciences_Volume_Paper(['id' => 1, 'vid' => 15, 'docid' => 99]),
            new Episciences_Volume_Paper(['id' => 2, 'vid' => 20, 'docid' => 99]),
            // duplicated row for the same volume
            new Episciences_Volume_Paper(['id' => 3, 'vid' => 15, 'docid' => 99]),
            // primary volume, must be filtered out
            new Episciences_Volume_Paper(['id' => 4, 'vid' => 12, 'docid' => 99]),
        ]);

        $paper
            ->expects(self::once())
            ->method('resolveSecondaryVolumes')
            ->with([15 => 15, 20 => 20])
            ->willReturn([
                20 => $this->makeVolume(vid: 20, position: 1),
                15 => $this->makeVolume(vid: 15, position: 3),
            ]);

        $result = $this->callGetSecondaryVolumesToJson($paper);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        // a JSON array, not an object: keys must be 0..n
        self::assertSame([0, 1], array_keys($result));
        // resolveSecondaryVolumes() orders by volume position
        self::assertSame([20, 15], array_column($result, 'id'));
    }

    public function testReturnsNullWhenNoVolumeCouldBeResolved(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['resolveSecondaryVolumes']);
        $paper->setVid(12);
        $paper->setOtherVolumes([
            new Episciences_Volume_Paper(['id' => 1, 'vid' => 4242, 'docid' => 99]),
        ]);
        $paper->method('resolveSecondaryVolumes')->willReturn([]);

        self::assertNull($this->callGetSecondaryVolumesToJson($paper));
    }

    public function testFormatExposesAllPublicFields(): void
    {
        $volume = $this->makeVolume(
            vid: 15,
            position: 3,
            num: '2',
            year: '2024',
            titles: ['en' => 'Main Volume Title'],
            descriptions: ['en' => 'Description in English'],
            bibReference: 'Some reference',
        );

        self::assertSame([
            'id' => 15,
            'position' => 3,
            'number' => '2',
            'year' => '2024',
            'has_proceedings' => false,
            'titles' => ['en' => 'Main Volume Title'],
            'descriptions' => ['en' => 'Description in English'],
            'bibliographical_references' => 'Some reference',
        ], $this->callFormatSecondaryVolumeToJson($volume));
    }

    public function testFormatReturnsNullIdForAnEmptyVid(): void
    {
        $result = $this->callFormatSecondaryVolumeToJson($this->makeVolume(vid: 0));

        self::assertNull($result['id']);
    }

    public function testFormatHasProceedingsIsTrueWhenTheVolumeIsAProceeding(): void
    {
        $volume = $this->makeVolume(vid: 15, settings: [Episciences_Volume::VOLUME_IS_PROCEEDING => '1']);

        self::assertTrue($this->callFormatSecondaryVolumeToJson($volume)['has_proceedings']);
    }

    public function testFormatHasProceedingsIsFalseWhenTheSettingIsAbsentOrOff(): void
    {
        $withoutSetting = $this->makeVolume(vid: 15);
        $withSettingOff = $this->makeVolume(vid: 16, settings: [Episciences_Volume::VOLUME_IS_PROCEEDING => '0']);

        self::assertFalse($this->callFormatSecondaryVolumeToJson($withoutSetting)['has_proceedings']);
        self::assertFalse($this->callFormatSecondaryVolumeToJson($withSettingOff)['has_proceedings']);
    }

    public function testFormatDoesNotLeakPrivateSettings(): void
    {
        $volume = $this->makeVolume(vid: 15, settings: [
            Episciences_Volume::SETTING_ACCESS_CODE => 'super-secret-code',
            Episciences_Volume::SETTING_STATUS => '1',
            Episciences_Volume::SETTING_SPECIAL_ISSUE => '1',
        ]);

        $result = $this->callFormatSecondaryVolumeToJson($volume);

        self::assertArrayNotHasKey('settings', $result);
        self::assertArrayNotHasKey(Episciences_Volume::SETTING_ACCESS_CODE, $result);
        self::assertStringNotContainsString('super-secret-code', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testFormatKeysMatchTheDocumentedPayload(): void
    {
        self::assertSame([
            'id',
            'position',
            'number',
            'year',
            'has_proceedings',
            'titles',
            'descriptions',
            'bibliographical_references',
        ], array_keys($this->callFormatSecondaryVolumeToJson($this->makeVolume(vid: 15))));
    }
}
