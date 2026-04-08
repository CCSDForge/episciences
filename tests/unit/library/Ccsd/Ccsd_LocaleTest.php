<?php

namespace unit\library\Ccsd;

use Ccsd_Locale;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_Locale
 *
 * Methods that require Zend_Registry / Zend_Locale (getCountry, getLanguage,
 * getFullLocale) are not tested here.
 * Pure methods: convertIso2ToIso1(), langExists(), isAcceptedLanguage().
 */
class Ccsd_LocaleTest extends TestCase
{
    private Ccsd_Locale $locale;

    protected function setUp(): void
    {
        $this->locale = new Ccsd_Locale();
    }

    // ------------------------------------------------------------------
    // convertIso2ToIso1 — ISO 639-2 → ISO 639-1
    // ------------------------------------------------------------------

    public function testConvertFraToFr(): void
    {
        $this->assertSame('fr', $this->locale->convertIso2ToIso1('fra'));
    }

    public function testConvertEngToEn(): void
    {
        $this->assertSame('en', $this->locale->convertIso2ToIso1('eng'));
    }

    public function testConvertDeuToDe(): void
    {
        $this->assertSame('de', $this->locale->convertIso2ToIso1('deu'));
    }

    public function testConvertAlternateCodeFre(): void
    {
        // ISO 639-2/B "fre" also maps to 'fr'
        $this->assertSame('fr', $this->locale->convertIso2ToIso1('fre'));
    }

    public function testConvertAlternateCodeGer(): void
    {
        // ISO 639-2/B "ger" also maps to 'de'
        $this->assertSame('de', $this->locale->convertIso2ToIso1('ger'));
    }

    public function testConvertUnknownCodeReturnsLowercaseInput(): void
    {
        // Unknown 3-letter codes are returned as-is (lowercased)
        $this->assertSame('zzz', $this->locale->convertIso2ToIso1('zzz'));
    }

    public function testConvertCaseInsensitive(): void
    {
        // mb_strtolower is applied before lookup
        $this->assertSame('fr', $this->locale->convertIso2ToIso1('FRA'));
        $this->assertSame('en', $this->locale->convertIso2ToIso1('ENG'));
    }

    // ------------------------------------------------------------------
    // langExists — checks if ISO 639-1 code is in the conversion table values
    // ------------------------------------------------------------------

    public function testLangExistsFr(): void
    {
        $this->assertTrue($this->locale->langExists('fr'));
    }

    public function testLangExistsEn(): void
    {
        $this->assertTrue($this->locale->langExists('en'));
    }

    public function testLangExistsDe(): void
    {
        $this->assertTrue($this->locale->langExists('de'));
    }

    public function testLangExistsCaseInsensitive(): void
    {
        $this->assertTrue($this->locale->langExists('FR'));
        $this->assertTrue($this->locale->langExists('EN'));
    }

    public function testLangNotExistsUnknown(): void
    {
        $this->assertFalse($this->locale->langExists('xx'));
        $this->assertFalse($this->locale->langExists('zzz'));
    }

    // ------------------------------------------------------------------
    // isAcceptedLanguage (static)
    // Note: "accepted" here means the code should be EXCLUDED from the
    // language picker list (returns true = will be unset by getLanguage()).
    // 2-letter codes return false (kept in list).
    // 3-letter codes return true (removed from list), except those in
    // $_accepted3lettersLanguage which also return true (still removed).
    // ------------------------------------------------------------------

    public function testTwoLetterCodeReturnsFalse(): void
    {
        // 2-letter codes: NOT accepted (→ kept in getLanguage() list)
        $this->assertFalse(Ccsd_Locale::isAcceptedLanguage('fr'));
        $this->assertFalse(Ccsd_Locale::isAcceptedLanguage('en'));
        $this->assertFalse(Ccsd_Locale::isAcceptedLanguage('de'));
    }

    public function testThreeLetterCodeReturnsTrue(): void
    {
        // 3-letter codes are returned as true (→ removed from getLanguage() list)
        $this->assertTrue(Ccsd_Locale::isAcceptedLanguage('fra'));
        $this->assertTrue(Ccsd_Locale::isAcceptedLanguage('eng'));
    }

    public function testAccepted3LetterCodeSahReturnsTrue(): void
    {
        // 'sah' (Yakut) is listed in $_accepted3lettersLanguage and also returns true
        $this->assertTrue(Ccsd_Locale::isAcceptedLanguage('sah'));
    }

    public function testFourLetterCodeReturnsTrue(): void
    {
        // Any code longer than 2 chars returns true
        $this->assertTrue(Ccsd_Locale::isAcceptedLanguage('zzzz'));
    }

    // ------------------------------------------------------------------
    // Conversion table consistency checks
    // ------------------------------------------------------------------

    public function testConversionTableCoversCommonLanguages(): void
    {
        $common = ['fra' => 'fr', 'eng' => 'en', 'spa' => 'es', 'por' => 'pt', 'zho' => 'zh'];
        foreach ($common as $iso2 => $expected) {
            $this->assertSame(
                $expected,
                $this->locale->convertIso2ToIso1($iso2),
                "Failed for iso2='$iso2'"
            );
        }
    }

    public function testConversionTableAllValuesAreValidIso1(): void
    {
        // All values in the conversion table should be 2-letter strings
        $prop = new \ReflectionProperty(Ccsd_Locale::class, '_conversionTable');
        $prop->setAccessible(true);
        $table = $prop->getValue($this->locale);

        foreach ($table as $iso2 => $iso1) {
            $this->assertSame(2, strlen($iso1),
                "iso2='$iso2' maps to '$iso1' which is not a 2-letter code");
        }
    }
}
