<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for Episciences_Paper entity: getters/setters, DOI, identifier,
 * isTmp, type, flag, setOptions, metadata format constants.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_EntityTest extends TestCase
{
    private Episciences_Paper $paper;

    protected function setUp(): void
    {
        $this->paper = new Episciences_Paper();
    }

    // -----------------------------------------------------------------------
    // setPaperid / getPaperid
    // -----------------------------------------------------------------------

    public function testSetAndGetPaperid(): void
    {
        $this->paper->setPaperid(42);
        self::assertSame(42, $this->paper->getPaperid());
    }

    public function testSetPaperidCastsToInt(): void
    {
        $this->paper->setPaperid('99');
        self::assertSame(99, $this->paper->getPaperid());
    }

    public function testGetPaperidDefaultIsZero(): void
    {
        self::assertSame(0, $this->paper->getPaperid());
    }

    // -----------------------------------------------------------------------
    // setDocid / getDocid
    // -----------------------------------------------------------------------

    public function testSetAndGetDocid(): void
    {
        $this->paper->setDocid(100);
        self::assertSame(100, $this->paper->getDocid());
    }

    // -----------------------------------------------------------------------
    // setRvid / getRvid
    // -----------------------------------------------------------------------

    public function testSetAndGetRvid(): void
    {
        $this->paper->setRvid(7);
        self::assertSame(7, $this->paper->getRvid());
    }

    // -----------------------------------------------------------------------
    // setVid / getVid
    // -----------------------------------------------------------------------

    public function testSetAndGetVid(): void
    {
        $this->paper->setVid(3);
        self::assertSame(3, $this->paper->getVid());
    }

    // -----------------------------------------------------------------------
    // setStatus / getStatus
    // -----------------------------------------------------------------------

    public function testGetStatusDefaultIsSubmitted(): void
    {
        self::assertSame(Episciences_Paper::STATUS_SUBMITTED, $this->paper->getStatus());
    }

    public function testSetAndGetStatus(): void
    {
        $this->paper->setStatus(Episciences_Paper::STATUS_ACCEPTED);
        self::assertSame(Episciences_Paper::STATUS_ACCEPTED, $this->paper->getStatus());
    }

    public function testSetStatusCastsToInt(): void
    {
        $this->paper->setStatus('4');
        self::assertSame(4, $this->paper->getStatus());
    }

    // -----------------------------------------------------------------------
    // DOI
    // -----------------------------------------------------------------------

    public function testHasDoiReturnsFalseWhenEmpty(): void
    {
        self::assertFalse($this->paper->hasDoi());
    }

    public function testHasDoiReturnsTrueAfterSet(): void
    {
        $this->paper->setDoi('10.1234/test');
        self::assertTrue($this->paper->hasDoi());
    }

    public function testGetDoiWithoutPrefixReturnsBareValue(): void
    {
        $this->paper->setDoi('10.9999/abc');
        self::assertSame('10.9999/abc', $this->paper->getDoi());
    }

    public function testGetDoiWithPrefixAddsDoiOrg(): void
    {
        $this->paper->setDoi('10.9999/abc');
        $withPrefix = $this->paper->getDoi(true);
        self::assertStringContainsString('doi.org', $withPrefix);
        self::assertStringContainsString('10.9999/abc', $withPrefix);
    }

    public function testGetDoiWithPrefixWhenEmptyReturnsEmptyString(): void
    {
        // No DOI set: getDoi(true) should not prepend prefix to empty string
        self::assertSame('', $this->paper->getDoi(true));
    }

    public function testSetDoiNullClearsToEmptyString(): void
    {
        $this->paper->setDoi('10.9999/abc');
        $this->paper->setDoi(null);
        self::assertSame('', $this->paper->getDoi());
        self::assertFalse($this->paper->hasDoi());
    }

    // -----------------------------------------------------------------------
    // getIdentifier
    // -----------------------------------------------------------------------

    public function testGetIdentifierStripsRefusedSuffixWhenRefused(): void
    {
        $this->paper->setIdentifier('hal-123-REFUSED');
        $this->paper->setStatus(Episciences_Paper::STATUS_REFUSED);
        self::assertSame('hal-123', $this->paper->getIdentifier());
    }

    public function testGetIdentifierUnchangedWhenPublished(): void
    {
        $this->paper->setIdentifier('hal-456');
        $this->paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);
        self::assertSame('hal-456', $this->paper->getIdentifier());
    }

    public function testGetIdentifierUnchangedWhenSubmitted(): void
    {
        $this->paper->setIdentifier('hal-789');
        $this->paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);
        self::assertSame('hal-789', $this->paper->getIdentifier());
    }

    // -----------------------------------------------------------------------
    // isTmp
    // -----------------------------------------------------------------------

    public function testIsTmpReturnsTrueWhenRepoIdIsZero(): void
    {
        // Default _repoId is 0
        self::assertTrue($this->paper->isTmp());
    }

    public function testIsTmpReturnsFalseWhenRepoIdIsNonZero(): void
    {
        $ref = new ReflectionProperty(Episciences_Paper::class, '_repoId');
        $ref->setAccessible(true);
        $ref->setValue($this->paper, 5);
        self::assertFalse($this->paper->isTmp());
    }

    // -----------------------------------------------------------------------
    // getType / setType
    // -----------------------------------------------------------------------

    public function testGetTypeReturnsDefaultAfterConstruction(): void
    {
        $type = $this->paper->getType();
        self::assertIsArray($type);
        self::assertArrayHasKey(Episciences_Paper::TITLE_TYPE, $type);
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $type[Episciences_Paper::TITLE_TYPE]);
    }

    public function testSetTypeWithValidArrayStoresIt(): void
    {
        $custom = [Episciences_Paper::TITLE_TYPE => 'article'];
        $this->paper->setType($custom);
        self::assertSame($custom, $this->paper->getType());
    }

    public function testSetTypeWithNullFallsBackToDefault(): void
    {
        $this->paper->setType(null);
        $type = $this->paper->getType();
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $type[Episciences_Paper::TITLE_TYPE]);
    }

    public function testSetTypeWithEmptyArrayFallsBackToDefault(): void
    {
        $this->paper->setType([]);
        $type = $this->paper->getType();
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $type[Episciences_Paper::TITLE_TYPE]);
    }

    // -----------------------------------------------------------------------
    // getFlag / setFlag
    // -----------------------------------------------------------------------

    public function testGetFlagReturnsDefaultSubmitted(): void
    {
        self::assertSame('submitted', $this->paper->getFlag());
    }

    public function testSetAndGetFlag(): void
    {
        $this->paper->setFlag('imported');
        self::assertSame('imported', $this->paper->getFlag());
    }

    // -----------------------------------------------------------------------
    // isValidMetadataFormat
    // -----------------------------------------------------------------------

    /**
     * @dataProvider validMetadataFormatProvider
     */
    public function testIsValidMetadataFormatReturnsTrueForKnownFormats(string $format): void
    {
        self::assertTrue(Episciences_Paper::isValidMetadataFormat($format));
    }

    public static function validMetadataFormatProvider(): array
    {
        return [
            'tei'       => ['tei'],
            'json'      => ['json'],
            'bibtex'    => ['bibtex'],
            'dc'        => ['dc'],
            'datacite'  => ['datacite'],
            'openaire'  => ['openaire'],
        ];
    }

    public function testIsValidMetadataFormatReturnsFalseForUnknown(): void
    {
        self::assertFalse(Episciences_Paper::isValidMetadataFormat('unknown_format'));
    }

    public function testIsValidMetadataFormatReturnsFalseForEmptyString(): void
    {
        self::assertFalse(Episciences_Paper::isValidMetadataFormat(''));
    }

    // -----------------------------------------------------------------------
    // setOptions (via constructor)
    // -----------------------------------------------------------------------

    public function testSetOptionsSetsMultipleProperties(): void
    {
        $paper = new Episciences_Paper([
            'paperid' => 10,
            'rvid'    => 3,
            'status'  => Episciences_Paper::STATUS_PUBLISHED,
        ]);
        self::assertSame(10, $paper->getPaperid());
        self::assertSame(3, $paper->getRvid());
        self::assertSame(Episciences_Paper::STATUS_PUBLISHED, $paper->getStatus());
    }

    public function testSetOptionsDecodesTypeFromJsonString(): void
    {
        $typeJson = json_encode([Episciences_Paper::TITLE_TYPE => 'article']);
        $paper = new Episciences_Paper(['type' => $typeJson]);
        self::assertSame('article', $paper->getType()[Episciences_Paper::TITLE_TYPE]);
    }

    public function testSetOptionsWithNullTypeUsesDefault(): void
    {
        $paper = new Episciences_Paper(['type' => null]);
        $type = $paper->getType();
        self::assertSame(Episciences_Paper::DEFAULT_TYPE_TITLE, $type[Episciences_Paper::TITLE_TYPE]);
    }
}
