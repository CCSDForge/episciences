<?php

namespace unit\library\Episciences\Review;

use Episciences_Review_Doi;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Review_Doi (deprecated, see DoiSettings).
 *
 * Pure-logic: constants, setters-getters, setOptions(), getDoiSettings(), __toArray().
 * No DB or filesystem required.
 *
 * @covers Episciences_Review_Doi
 */
class Episciences_Review_DoiTest extends TestCase
{
    private Episciences_Review_Doi $doi;

    protected function setUp(): void
    {
        $this->doi = new Episciences_Review_Doi();
    }

    // =========================================================================
    // Format constants
    // =========================================================================

    public function testFormatConstants(): void
    {
        self::assertSame('%R%', Episciences_Review_Doi::DOI_FORMAT_REVIEW_CODE);
        self::assertSame('%V%', Episciences_Review_Doi::DOI_FORMAT_PAPER_VOLUME);
        self::assertSame('%S%', Episciences_Review_Doi::DOI_FORMAT_PAPER_SECTION);
        self::assertSame('%VP%', Episciences_Review_Doi::DOI_FORMAT_PAPER_VOLUME_ORDER);
        self::assertSame('%P%', Episciences_Review_Doi::DOI_FORMAT_PAPER_ID);
        self::assertSame('%Y%', Episciences_Review_Doi::DOI_FORMAT_PAPER_YEAR);
        self::assertSame('%M%', Episciences_Review_Doi::DOI_FORMAT_PAPER_MONTH);
    }

    public function testSettingKeyConstants(): void
    {
        self::assertSame('doiPrefix', Episciences_Review_Doi::SETTING_DOI_PREFIX);
        self::assertSame('doiFormat', Episciences_Review_Doi::SETTING_DOI_FORMAT);
        self::assertSame('doiRegistrationAgency', Episciences_Review_Doi::SETTING_DOI_REGISTRATION_AGENCY);
    }

    // =========================================================================
    // Default values
    // =========================================================================

    public function testDefaultDoiPrefixIsEmpty(): void
    {
        self::assertSame('', $this->doi->getDoiPrefix());
    }

    public function testDefaultRegistrationAgencyIsDatacite(): void
    {
        self::assertSame('datacite', $this->doi->getDoiRegistrationAgency());
    }

    public function testDefaultDoiFormatContainsReviewCode(): void
    {
        $format = $this->doi->getDoiFormat();
        self::assertStringContainsString('%R%', $format);
    }

    // =========================================================================
    // Setters / getters
    // =========================================================================

    public function testSetAndGetDoiPrefix(): void
    {
        $this->doi->setDoiPrefix('10.12345');
        self::assertSame('10.12345', $this->doi->getDoiPrefix());
    }

    public function testSetAndGetDoiFormat(): void
    {
        $this->doi->setDoiFormat('%R%-%Y%-%P%');
        self::assertSame('%R%-%Y%-%P%', $this->doi->getDoiFormat());
    }

    public function testSetAndGetDoiRegistrationAgency(): void
    {
        $this->doi->setDoiRegistrationAgency('crossref');
        self::assertSame('crossref', $this->doi->getDoiRegistrationAgency());
    }

    // =========================================================================
    // getDoiSettings() static
    // =========================================================================

    public function testGetDoiSettingsReturnsThreeKeys(): void
    {
        $settings = Episciences_Review_Doi::getDoiSettings();
        self::assertCount(3, $settings);
        self::assertContains('doiPrefix', $settings);
        self::assertContains('doiFormat', $settings);
        self::assertContains('doiRegistrationAgency', $settings);
    }

    // =========================================================================
    // __toArray()
    // =========================================================================

    public function testToArrayContainsExpectedKeys(): void
    {
        $array = $this->doi->__toArray();

        self::assertArrayHasKey('doiPrefix', $array);
        self::assertArrayHasKey('doiFormat', $array);
        self::assertArrayHasKey('doiRegistrationAgency', $array);
    }

    public function testToArrayReflectsSetValues(): void
    {
        $this->doi->setDoiPrefix('10.9999');
        $this->doi->setDoiFormat('%R%-%P%');
        $this->doi->setDoiRegistrationAgency('crossref');

        $array = $this->doi->__toArray();

        self::assertSame('10.9999', $array['doiPrefix']);
        self::assertSame('%R%-%P%', $array['doiFormat']);
        self::assertSame('crossref', $array['doiRegistrationAgency']);
    }

    // =========================================================================
    // setOptions()
    // =========================================================================

    public function testSetOptionsAppliesValues(): void
    {
        $doi = new Episciences_Review_Doi([
            'doiPrefix'               => '10.1234',
            'doiFormat'               => '%P%',
            'doiRegistrationAgency'   => 'crossref',
        ]);

        self::assertSame('10.1234', $doi->getDoiPrefix());
        self::assertSame('%P%', $doi->getDoiFormat());
        self::assertSame('crossref', $doi->getDoiRegistrationAgency());
    }

    public function testConstructorWithEmptyOptionsDoesNotThrow(): void
    {
        $doi = new Episciences_Review_Doi([]);
        self::assertInstanceOf(Episciences_Review_Doi::class, $doi);
    }
}
