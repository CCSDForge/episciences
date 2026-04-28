<?php

namespace unit\library\Episciences;

use Episciences_VolumesAndSectionsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_VolumesAndSectionsManager
 *
 * Tests the dataProcess() method which is pure (no DB, no session, no constants needed).
 * The sort() method requires DB and is not tested here.
 *
 * @covers Episciences_VolumesAndSectionsManager
 */
class Episciences_VolumesAndSectionsManagerTest extends TestCase
{
    // =========================================================================
    // dataProcess() — encode (default)
    // =========================================================================

    public function testDataProcessEncodesTitlesArrayToJson(): void
    {
        $data = ['titles' => ['en' => 'Hello', 'fr' => 'Bonjour']];
        Episciences_VolumesAndSectionsManager::dataProcess($data);

        $this->assertIsString($data['titles']);
        $decoded = json_decode($data['titles'], true);
        $this->assertSame('Hello', $decoded['en']);
        $this->assertSame('Bonjour', $decoded['fr']);
    }

    public function testDataProcessEncodesDescriptionsArrayToJson(): void
    {
        $data = ['descriptions' => ['en' => 'Desc EN', 'fr' => 'Desc FR']];
        Episciences_VolumesAndSectionsManager::dataProcess($data);

        $this->assertIsString($data['descriptions']);
        $decoded = json_decode($data['descriptions'], true);
        $this->assertSame('Desc EN', $decoded['en']);
    }

    public function testDataProcessEncodesBothKeysInOneCall(): void
    {
        $data = [
            'titles'       => ['en' => 'T'],
            'descriptions' => ['en' => 'D'],
        ];
        Episciences_VolumesAndSectionsManager::dataProcess($data);

        $this->assertIsString($data['titles']);
        $this->assertIsString($data['descriptions']);
    }

    public function testDataProcessEncodeSkipsKeyNotInData(): void
    {
        $data = ['titles' => ['en' => 'T']];
        Episciences_VolumesAndSectionsManager::dataProcess($data); // no 'descriptions' key

        $this->assertArrayNotHasKey('descriptions', $data);
    }

    // =========================================================================
    // dataProcess() — decode
    // =========================================================================

    public function testDataProcessDecodesTitlesJsonToArray(): void
    {
        $data = ['titles' => json_encode(['en' => 'Hello', 'fr' => 'Bonjour'])];
        Episciences_VolumesAndSectionsManager::dataProcess($data, 'decode');

        $this->assertIsArray($data['titles']);
        $this->assertSame('Hello', $data['titles']['en']);
        $this->assertSame('Bonjour', $data['titles']['fr']);
    }

    public function testDataProcessDecodesDescriptionsJsonToArray(): void
    {
        $data = ['descriptions' => json_encode(['en' => 'D'])];
        Episciences_VolumesAndSectionsManager::dataProcess($data, 'decode');

        $this->assertIsArray($data['descriptions']);
        $this->assertSame('D', $data['descriptions']['en']);
    }

    // =========================================================================
    // dataProcess() — encode/decode round-trip
    // =========================================================================

    public function testDataProcessRoundTripEncodeThenDecode(): void
    {
        $original = ['en' => 'Title', 'fr' => 'Titre'];
        $data = ['titles' => $original];

        Episciences_VolumesAndSectionsManager::dataProcess($data, 'encode');
        Episciences_VolumesAndSectionsManager::dataProcess($data, 'decode');

        $this->assertSame($original, $data['titles']);
    }

    // =========================================================================
    // dataProcess() — custom keys
    // =========================================================================

    public function testDataProcessEncodesCustomKeys(): void
    {
        $data = ['title' => ['en' => 'T'], 'content' => ['en' => 'C']];
        Episciences_VolumesAndSectionsManager::dataProcess($data, 'encode', ['title', 'content']);

        $this->assertIsString($data['title']);
        $this->assertIsString($data['content']);
    }

    public function testDataProcessDecodesCustomKeys(): void
    {
        $data = [
            'title'   => json_encode(['en' => 'T']),
            'content' => json_encode(['en' => 'C']),
        ];
        Episciences_VolumesAndSectionsManager::dataProcess($data, 'decode', ['title', 'content']);

        $this->assertSame('T', $data['title']['en']);
        $this->assertSame('C', $data['content']['en']);
    }

    // =========================================================================
    // dataProcess() — empty / missing data
    // =========================================================================

    public function testDataProcessWithEmptyDataDoesNothing(): void
    {
        $data = [];
        Episciences_VolumesAndSectionsManager::dataProcess($data);
        $this->assertSame([], $data);
    }

    public function testDataProcessKeyNotPresentIsSkipped(): void
    {
        $data = ['other_key' => 'value'];
        Episciences_VolumesAndSectionsManager::dataProcess($data);
        // 'titles' and 'descriptions' are not in $data — should remain unchanged
        $this->assertSame('value', $data['other_key']);
        $this->assertArrayNotHasKey('titles', $data);
    }

    public function testDataProcessEncodesEmptyArray(): void
    {
        $data = ['titles' => []];
        Episciences_VolumesAndSectionsManager::dataProcess($data);
        $this->assertSame('[]', $data['titles']);
    }
}
