<?php

namespace unit\library\Episciences;

use Episciences\Classification;
use Episciences\Classification\jel;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences\Classification (abstract).
 *
 * Uses the concrete jel subclass (single-line child) to exercise all
 * abstract-class logic: setOptions, setters/getters, jsonSerialize.
 *
 * @covers \Episciences\Classification
 */
class Episciences_ClassificationTest extends TestCase
{
    // =========================================================================
    // Instantiation
    // =========================================================================

    public function testJelExtendsClassification(): void
    {
        $jel = new jel([]);
        self::assertInstanceOf(Classification::class, $jel);
    }

    public function testClassificationNameConstant(): void
    {
        self::assertSame('jel', jel::$classificationName);
    }

    // =========================================================================
    // Default values
    // =========================================================================

    public function testDefaultDocidIsZero(): void
    {
        $jel = new jel([]);
        self::assertSame(0, $jel->getDocid());
    }

    public function testDefaultCodeIsEmpty(): void
    {
        $jel = new jel([]);
        self::assertSame('', $jel->getCode());
    }

    public function testDefaultLabelIsEmpty(): void
    {
        $jel = new jel([]);
        self::assertSame('', $jel->getLabel());
    }

    public function testDefaultSourceNameIsEmpty(): void
    {
        $jel = new jel([]);
        self::assertSame('', $jel->getSourceName());
    }

    // =========================================================================
    // Setters / getters
    // =========================================================================

    public function testSetAndGetCode(): void
    {
        $jel = new jel([]);
        $jel->setCode('A10');
        self::assertSame('A10', $jel->getCode());
    }

    public function testSetAndGetLabel(): void
    {
        $jel = new jel([]);
        $jel->setLabel('General Economics');
        self::assertSame('General Economics', $jel->getLabel());
    }

    public function testSetAndGetDocid(): void
    {
        $jel = new jel([]);
        $jel->setDocid(42);
        self::assertSame(42, $jel->getDocid());
    }

    public function testSetAndGetSourceName(): void
    {
        $jel = new jel([]);
        $jel->setSourceName('hal');
        self::assertSame('hal', $jel->getSourceName());
    }

    // =========================================================================
    // setOptions via constructor
    // =========================================================================

    public function testConstructorWithOptionsViaSetOptions(): void
    {
        $jel = new jel([
            'code'        => 'B20',
            'label'       => 'Financial Markets',
            'docid'       => 99,
            'source_name' => 'episciences',
        ]);

        self::assertSame('B20', $jel->getCode());
        self::assertSame('Financial Markets', $jel->getLabel());
        self::assertSame(99, $jel->getDocid());
        self::assertSame('episciences', $jel->getSourceName());
    }

    public function testConstructorIgnoresUnknownKeys(): void
    {
        $jel = new jel(['unknown_key' => 'some_value']);
        self::assertSame('', $jel->getCode());
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testJsonSerializeWithDocidIncluded(): void
    {
        $jel = new jel([]);
        $jel->setCode('C10');
        $jel->setLabel('Econometric Methods');
        $jel->setDocid(7);
        $jel->setSourceName('hal');

        $result = $jel->jsonSerialize(true);

        self::assertArrayHasKey('docid', $result);
        self::assertSame(7, $result['docid']);
        self::assertSame('C10', $result['code']);
        self::assertSame('Econometric Methods', $result['label']);
        self::assertSame('jel', $result['classificationName']);
        self::assertSame('hal', $result['sourceName']);
    }

    public function testJsonSerializeWithoutDocid(): void
    {
        $jel = new jel([]);
        $jel->setCode('D00');
        $jel->setLabel('Microeconomics');

        $result = $jel->jsonSerialize(false);

        self::assertArrayNotHasKey('docid', $result);
        self::assertSame('D00', $result['code']);
        self::assertSame('Microeconomics', $result['label']);
        self::assertSame('jel', $result['classificationName']);
    }

    public function testJsonEncodeUsesJsonSerialize(): void
    {
        $jel = new jel([]);
        $jel->setCode('E10');
        $jel->setLabel('Money');
        $jel->setDocid(3);
        $jel->setSourceName('test');

        $json = json_encode($jel);
        $decoded = json_decode($json, true);

        self::assertSame('E10', $decoded['code']);
        self::assertSame('Money', $decoded['label']);
        self::assertSame('jel', $decoded['classificationName']);
        self::assertSame('test', $decoded['sourceName']);
    }
}
