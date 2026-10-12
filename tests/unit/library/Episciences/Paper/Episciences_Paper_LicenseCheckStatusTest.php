<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::getLicenseCheckStatus()
 *
 * DB-dependent accessors (repository, acceptance date, license, SPDX code) are stubbed.
 *
 * @covers Episciences_Paper::getLicenseCheckStatus
 * @covers Episciences_Paper::ignoreLicenseCheck
 */
class Episciences_Paper_LicenseCheckStatusTest extends TestCase
{
    private function paper(
        ?string $licence,
        ?string $spdxCode,
        bool $isTmp = false,
        ?string $acceptanceDate = '2026-01-01'
    ): Episciences_Paper {
        return new class ($licence, $spdxCode, $isTmp, $acceptanceDate) extends Episciences_Paper {
            public function __construct(
                private ?string $stubLicence,
                private ?string $stubSpdxCode,
                private bool $stubIsTmp,
                private ?string $stubAcceptanceDate
            ) {
            }

            public function isTmp(): bool
            {
                return $this->stubIsTmp;
            }

            public function getAcceptanceDate(): ?string
            {
                return $this->stubAcceptanceDate;
            }

            public function getLicence(): string
            {
                return (string)$this->stubLicence;
            }

            public function getSpdxLicenseCode(): ?string
            {
                return $this->stubSpdxCode;
            }
        };
    }

    /**
     * @param string[] $allowedLicenses
     */
    private function review(?string $defaultLicense, array $allowedLicenses = []): Episciences_Review
    {
        $rows = [];

        if ($defaultLicense !== null) {
            $rows[] = ['SETTING' => Episciences_Review::SETTING_LICENSE_DEFAULT, 'VALUE' => $defaultLicense];
        }

        if ($allowedLicenses !== []) {
            $rows[] = ['SETTING' => Episciences_Review::SETTING_LICENSES_ALLOWED, 'VALUE' => json_encode($allowedLicenses)];
        }

        $review = new Episciences_Review();
        $review->applySettingsFromRows($rows);

        return $review;
    }

    private const CC_BY_URL = 'https://creativecommons.org/licenses/by/4.0/';

    public function testNoCheckForTemporaryVersion(): void
    {
        self::assertNull($this->paper(null, null, true)->getLicenseCheckStatus($this->review('MIT')));
    }

    public function testNoCheckBeforeAcceptance(): void
    {
        self::assertNull($this->paper(null, null, false, null)->getLicenseCheckStatus($this->review('MIT')));
    }

    public function testMissingLicense(): void
    {
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_MISSING,
            $this->paper('', null)->getLicenseCheckStatus($this->review('CC-BY-4.0'))
        );
    }

    public function testSavedSpdxCodeWithoutLicenseUrlIsNotMissing(): void
    {
        self::assertNull($this->paper('', 'CC-BY-4.0')->getLicenseCheckStatus($this->review('CC-BY-4.0')));
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_MISMATCH,
            $this->paper('', 'MIT')->getLicenseCheckStatus($this->review('CC-BY-4.0'))
        );
    }

    public function testSameLicenseAsJournalDefaultIsCompliant(): void
    {
        $paper = $this->paper(self::CC_BY_URL, 'CC-BY-4.0');

        self::assertNull($paper->getLicenseCheckStatus($this->review('CC-BY-4.0')));
        self::assertTrue($paper->ignoreLicenseCheck($this->review('CC-BY-4.0')));
    }

    public function testComparisonIsCaseInsensitive(): void
    {
        self::assertNull($this->paper(self::CC_BY_URL, 'cc-by-4.0')->getLicenseCheckStatus($this->review('CC-BY-4.0')));
    }

    public function testDifferentLicenseFromJournalDefaultIsMismatch(): void
    {
        $paper = $this->paper(self::CC_BY_URL, 'CC-BY-4.0');

        self::assertSame(Episciences_Paper::LICENSE_CHECK_MISMATCH, $paper->getLicenseCheckStatus($this->review('MIT')));
        self::assertFalse($paper->ignoreLicenseCheck($this->review('MIT')));
    }

    public function testUnrecognizedLicenseWithJournalDefaultIsMismatch(): void
    {
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_MISMATCH,
            $this->paper('https://example.org/custom-license', null)->getLicenseCheckStatus($this->review('MIT'))
        );
    }

    public function testRecognizedLicenseWithoutJournalDefaultIsCompliant(): void
    {
        self::assertNull($this->paper(self::CC_BY_URL, 'CC-BY-4.0')->getLicenseCheckStatus($this->review(null)));
    }

    public function testUnrecognizedLicenseWithoutJournalDefault(): void
    {
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_UNRECOGNIZED,
            $this->paper('https://example.org/custom-license', null)->getLicenseCheckStatus($this->review(null))
        );
    }

    public function testAllowedNonDefaultLicenseIsCompliant(): void
    {
        $review = $this->review('CC-BY-4.0', ['CC-BY-4.0', 'CC-BY-SA-4.0']);

        self::assertNull($this->paper('', 'CC-BY-SA-4.0')->getLicenseCheckStatus($review));
        self::assertNull($this->paper('', 'cc-by-sa-4.0')->getLicenseCheckStatus($review));
    }

    public function testLicenseOutsideAllowedLicensesIsMismatch(): void
    {
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_MISMATCH,
            $this->paper('', 'MIT')->getLicenseCheckStatus($this->review('CC-BY-4.0', ['CC-BY-4.0', 'CC-BY-SA-4.0']))
        );
    }

    public function testAllowedLicensesWithoutDefaultAreChecked(): void
    {
        $review = $this->review(null, ['CC-BY-4.0']);

        self::assertNull($this->paper(self::CC_BY_URL, 'CC-BY-4.0')->getLicenseCheckStatus($review));
        self::assertSame(Episciences_Paper::LICENSE_CHECK_MISMATCH, $this->paper('', 'MIT')->getLicenseCheckStatus($review));
        self::assertSame(
            Episciences_Paper::LICENSE_CHECK_MISMATCH,
            $this->paper('https://example.org/custom-license', null)->getLicenseCheckStatus($review)
        );
    }
}
