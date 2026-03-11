<?php

namespace unit\library\Episciences\Review;

use Episciences_Review_DoiSettings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Review_DoiSettings.
 *
 * Pure-logic: constants, setters-getters, setOptions(), __toArray(),
 * keepOnlyIntegersInTag() (private, tested via reflection).
 * DB-dependent methods (createDoiWithTemplate, computeNextDoiAutoIncrement) excluded.
 *
 * @covers Episciences_Review_DoiSettings
 */
class Episciences_Review_DoiSettingsTest extends TestCase
{
    private Episciences_Review_DoiSettings $settings;

    protected function setUp(): void
    {
        $this->settings = new Episciences_Review_DoiSettings();
    }

    // =========================================================================
    // Format-token constants
    // =========================================================================

    public function testFormatTokenConstants(): void
    {
        self::assertSame('%R%', Episciences_Review_DoiSettings::DOI_FORMAT_REVIEW_CODE);
        self::assertSame('%V%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME);
        self::assertSame('%S%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_SECTION);
        self::assertSame('%V_INT%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_INT);
        self::assertSame('%S_INT%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_SECTION_INT);
        self::assertSame('%VP%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_ORDER);
        self::assertSame('%P%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_ID);
        self::assertSame('%Y%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_YEAR);
        self::assertSame('%y%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_YEAR_SHORT);
        self::assertSame('%M%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_MONTH);
        self::assertSame('%AUTOINCREMENT%', Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_AUTOINCREMENT);
    }

    public function testAssignModeConstants(): void
    {
        self::assertSame('automatic', Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_AUTO);
        self::assertSame('manual', Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL);
        self::assertSame('disabled', Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_DISABLED);
    }

    public function testDefaultDoiFormat(): void
    {
        // Default: '%R%-%P%'
        $expected = Episciences_Review_DoiSettings::DOI_FORMAT_REVIEW_CODE
            . '-'
            . Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_ID;
        self::assertSame($expected, Episciences_Review_DoiSettings::SETTING_DOI_DEFAULT_DOI_FORMAT);
    }

    // =========================================================================
    // Default values on fresh instance
    // =========================================================================

    public function testDefaultPrefixIsEmpty(): void
    {
        self::assertSame('', $this->settings->getDoiPrefix());
    }

    public function testDefaultRegistrationAgencyIsCrossref(): void
    {
        self::assertSame('crossref', $this->settings->getDoiRegistrationAgency());
    }

    public function testDefaultAssignModeIsAutomatic(): void
    {
        self::assertSame(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_AUTO, $this->settings->getDoiAssignMode());
    }

    public function testDefaultFormatMatchesConstant(): void
    {
        self::assertSame(Episciences_Review_DoiSettings::SETTING_DOI_DEFAULT_DOI_FORMAT, $this->settings->getDoiFormat());
    }

    // =========================================================================
    // Setters / getters
    // =========================================================================

    public function testSetAndGetDoiPrefix(): void
    {
        $this->settings->setDoiPrefix('10.1234');
        self::assertSame('10.1234', $this->settings->getDoiPrefix());
    }

    public function testSetAndGetDoiFormat(): void
    {
        $this->settings->setDoiFormat('%R%-%Y%-%P%');
        self::assertSame('%R%-%Y%-%P%', $this->settings->getDoiFormat());
    }

    public function testSetAndGetDoiRegistrationAgency(): void
    {
        $this->settings->setDoiRegistrationAgency('datacite');
        self::assertSame('datacite', $this->settings->getDoiRegistrationAgency());
    }

    public function testSetAndGetDoiAssignMode(): void
    {
        $this->settings->setDoiAssignMode(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL);
        self::assertSame(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL, $this->settings->getDoiAssignMode());
    }

    // =========================================================================
    // getDoiSettings() static
    // =========================================================================

    public function testGetDoiSettingsReturnsFourKeys(): void
    {
        $keys = Episciences_Review_DoiSettings::getDoiSettings();
        self::assertCount(4, $keys);
        self::assertContains('doiPrefix', $keys);
        self::assertContains('doiFormat', $keys);
        self::assertContains('doiRegistrationAgency', $keys);
        self::assertContains('doiAssignMode', $keys);
    }

    // =========================================================================
    // __toArray()
    // =========================================================================

    public function testToArrayContainsAllKeys(): void
    {
        $array = $this->settings->__toArray();

        self::assertArrayHasKey('doiPrefix', $array);
        self::assertArrayHasKey('doiFormat', $array);
        self::assertArrayHasKey('doiRegistrationAgency', $array);
        self::assertArrayHasKey('doiAssignMode', $array);
    }

    public function testToArrayReflectsSetValues(): void
    {
        $this->settings->setDoiPrefix('10.9999');
        $this->settings->setDoiFormat('%P%');
        $this->settings->setDoiRegistrationAgency('datacite');
        $this->settings->setDoiAssignMode(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_DISABLED);

        $array = $this->settings->__toArray();

        self::assertSame('10.9999', $array['doiPrefix']);
        self::assertSame('%P%', $array['doiFormat']);
        self::assertSame('datacite', $array['doiRegistrationAgency']);
        self::assertSame(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_DISABLED, $array['doiAssignMode']);
    }

    // =========================================================================
    // keepOnlyIntegersInTag() — private, tested via reflection
    // =========================================================================

    private function callKeepOnlyIntegers(string $tag, string $char = '.'): mixed
    {
        $m = new ReflectionMethod(Episciences_Review_DoiSettings::class, 'keepOnlyIntegersInTag');
        $m->setAccessible(true);
        return $m->invoke(null, $tag, $char);
    }

    public function testKeepOnlyIntegersInTagExtractsDigits(): void
    {
        self::assertSame('2024', $this->callKeepOnlyIntegers('2024'));
    }

    public function testKeepOnlyIntegersInTagStripsNonDigits(): void
    {
        // 'vol-3' → '3'
        self::assertSame('3', $this->callKeepOnlyIntegers('vol-3'));
    }

    public function testKeepOnlyIntegersInTagWithMultipleNumbers(): void
    {
        // 'Volume 1 Issue 2' → '1.2'
        self::assertSame('1.2', $this->callKeepOnlyIntegers('Volume 1 Issue 2'));
    }

    public function testKeepOnlyIntegersInTagWithCustomReplacement(): void
    {
        // 'vol 3 num 1' with '-' → '3-1'
        self::assertSame('3-1', $this->callKeepOnlyIntegers('vol 3 num 1', '-'));
    }

    public function testKeepOnlyIntegersInTagPureDigitString(): void
    {
        self::assertSame('42', $this->callKeepOnlyIntegers('42'));
    }

    public function testKeepOnlyIntegersInTagEmptyStringReturnsEmpty(): void
    {
        self::assertSame('', $this->callKeepOnlyIntegers(''));
    }

    // =========================================================================
    // setOptions()
    // =========================================================================

    public function testSetOptionsConstructor(): void
    {
        $s = new Episciences_Review_DoiSettings([
            'doiPrefix'             => '10.5678',
            'doiAssignMode'         => Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL,
        ]);

        self::assertSame('10.5678', $s->getDoiPrefix());
        self::assertSame(Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL, $s->getDoiAssignMode());
    }

    // =========================================================================
    // createDoiWithTemplate — empty prefix guard (no DB needed)
    // =========================================================================

    public function testCreateDoiWithTemplateReturnsEmptyWhenNoPrefixSet(): void
    {
        // Default prefix is '' → method returns ''
        $paper = $this->createMock(\Episciences_Paper::class);

        $result = $this->settings->createDoiWithTemplate($paper, 'testjournal');
        self::assertSame('', $result);
    }
}
