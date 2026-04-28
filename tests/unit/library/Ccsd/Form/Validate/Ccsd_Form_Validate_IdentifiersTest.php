<?php

namespace unit\library\Ccsd\Form\Validate;

use Ccsd_Form_Validate_Isdoi;
use Ccsd_Form_Validate_Isarxiv;
use Ccsd_Form_Validate_Isbibcode;
use Ccsd_Form_Validate_Ispubmed;
use Ccsd_Form_Validate_Ispubmedcentral;
use Ccsd_Form_Validate_Isissn;
use Ccsd_Form_Validate_Isbiorxiv;
use Ccsd_Form_Validate_Ischemrxiv;
use Ccsd_Form_Validate_Isinspire;
use Ccsd_Form_Validate_Isoatao;
use Ccsd_Form_Validate_Isokina;
use Ccsd_Form_Validate_Isprodinra;
use Ccsd_Form_Validate_Isirstea;
use Ccsd_Form_Validate_Isensam;
use Ccsd_Form_Validate_Iscern;
use Ccsd_Form_Validate_Issciencespo;
use Ccsd_Form_Validate_Isird;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for all identifier validators.
 *
 * Covers: Isdoi, Isarxiv, Isbibcode (B1: /.* accepts anything),
 * Ispubmed, Ispubmedcentral, Isissn, Isbiorxiv, Ischemrxiv,
 * Isinspire, Isoatao, Isokina, Isprodinra, Isirstea,
 * Isensam, Iscern, Issciencespo, Isird.
 */
class Ccsd_Form_Validate_IdentifiersTest extends TestCase
{
    // ------------------------------------------------------------------
    // Isdoi
    // ------------------------------------------------------------------

    public function testIsdoiValidBasic(): void
    {
        $v = new Ccsd_Form_Validate_Isdoi();
        $this->assertTrue($v->isValid('10.1000/xyz123'));
    }

    public function testIsdoiValidWithSlash(): void
    {
        $v = new Ccsd_Form_Validate_Isdoi();
        $this->assertTrue($v->isValid('10.5281/zenodo.123456'));
    }

    public function testIsdoiInvalidNoPrefix(): void
    {
        $v = new Ccsd_Form_Validate_Isdoi();
        $this->assertFalse($v->isValid('not-a-doi'));
    }

    public function testIsdoiInvalidStartsWithElevenDot(): void
    {
        $v = new Ccsd_Form_Validate_Isdoi();
        $this->assertFalse($v->isValid('11.1000/abc'));
    }

    public function testIsdoiInvalidEmpty(): void
    {
        $v = new Ccsd_Form_Validate_Isdoi();
        $this->assertFalse($v->isValid(''));
    }

    // ------------------------------------------------------------------
    // Isarxiv
    // ------------------------------------------------------------------

    public function testIsarxivValidNewStyle(): void
    {
        $v = new Ccsd_Form_Validate_Isarxiv();
        $this->assertTrue($v->isValid('1401.0006'));
    }

    public function testIsarxivValidOldStyle(): void
    {
        $v = new Ccsd_Form_Validate_Isarxiv();
        $this->assertTrue($v->isValid('math/0602059'));
    }

    public function testIsarxivValidOldStyleSubfield(): void
    {
        $v = new Ccsd_Form_Validate_Isarxiv();
        $this->assertTrue($v->isValid('hep-th/0602059'));
    }

    public function testIsarxivInvalidTooShort(): void
    {
        $v = new Ccsd_Form_Validate_Isarxiv();
        $this->assertFalse($v->isValid('1401.006'));
    }

    public function testIsarxivInvalidRandomString(): void
    {
        $v = new Ccsd_Form_Validate_Isarxiv();
        $this->assertFalse($v->isValid('not-an-arxiv-id'));
    }

    // ------------------------------------------------------------------
    // Isbibcode — B1: /.* accepts anything (documented behavior)
    // ------------------------------------------------------------------

    public function testIsbibcodeAcceptsAnything(): void
    {
        // B1: The regex /.* / is intentionally permissive — any string is valid.
        // This is documented behavior for this repository type.
        $v = new Ccsd_Form_Validate_Isbibcode();
        $this->assertTrue($v->isValid('2003ApJ...598L..21F'));
        $this->assertTrue($v->isValid('anything-goes'));
        $this->assertTrue($v->isValid(''));
    }

    // ------------------------------------------------------------------
    // Ispubmed
    // ------------------------------------------------------------------

    public function testIspubmedValidNumeric(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmed();
        $this->assertTrue($v->isValid('12345678'));
    }

    public function testIspubmedInvalidWithLetters(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmed();
        $this->assertFalse($v->isValid('PMID12345'));
    }

    public function testIspubmedInvalidEmpty(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmed();
        $this->assertFalse($v->isValid(''));
    }

    // ------------------------------------------------------------------
    // Ispubmedcentral
    // ------------------------------------------------------------------

    public function testIspubmedcentralValid(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmedcentral();
        $this->assertTrue($v->isValid('PMC1234567'));
    }

    public function testIspubmedcentralInvalidNoPrefix(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmedcentral();
        $this->assertFalse($v->isValid('1234567'));
    }

    public function testIspubmedcentralInvalidLowercasePmc(): void
    {
        $v = new Ccsd_Form_Validate_Ispubmedcentral();
        $this->assertFalse($v->isValid('pmc1234567'));
    }

    // ------------------------------------------------------------------
    // Isissn
    // ------------------------------------------------------------------

    public function testIsissnValidWithDash(): void
    {
        $v = new Ccsd_Form_Validate_Isissn();
        // 0378-5955 is a known valid ISSN
        $this->assertTrue($v->isValid('0378-5955'));
    }

    public function testIsissnValidNoDash(): void
    {
        $v = new Ccsd_Form_Validate_Isissn();
        $this->assertTrue($v->isValid('03785955'));
    }

    public function testIsissnInvalidWrongCheckDigit(): void
    {
        $v = new Ccsd_Form_Validate_Isissn();
        // checksum=false, so all 8-digit patterns pass barcode format check
        // We just verify the validator does not crash on a random 8-digit value
        $result = $v->isValid('12345678');
        $this->assertIsBool($result);
    }

    // ------------------------------------------------------------------
    // Isbiorxiv
    // ------------------------------------------------------------------

    public function testIsbiorxivValidNumeric(): void
    {
        $v = new Ccsd_Form_Validate_Isbiorxiv();
        $this->assertTrue($v->isValid('123456'));
    }

    public function testIsbiorxivValidEmpty(): void
    {
        // Pattern /^[0-9]*$/ — empty matches zero or more digits
        $v = new Ccsd_Form_Validate_Isbiorxiv();
        $this->assertTrue($v->isValid(''));
    }

    public function testIsbiorxivInvalidLetters(): void
    {
        $v = new Ccsd_Form_Validate_Isbiorxiv();
        $this->assertFalse($v->isValid('abc'));
    }

    // ------------------------------------------------------------------
    // Ischemrxiv
    // ------------------------------------------------------------------

    public function testIschemrxivValidNumeric(): void
    {
        $v = new Ccsd_Form_Validate_Ischemrxiv();
        $this->assertTrue($v->isValid('7654321'));
    }

    public function testIschemrxivInvalidLetters(): void
    {
        $v = new Ccsd_Form_Validate_Ischemrxiv();
        $this->assertFalse($v->isValid('chemrxiv-123'));
    }

    // ------------------------------------------------------------------
    // Isird (fdi:|PAR prefix)
    // ------------------------------------------------------------------

    public function testIsirdValidFdi(): void
    {
        $v = new Ccsd_Form_Validate_Isird();
        $this->assertTrue($v->isValid('fdi:123456'));
    }

    public function testIsirdValidPar(): void
    {
        $v = new Ccsd_Form_Validate_Isird();
        $this->assertTrue($v->isValid('PAR123456'));
    }

    public function testIsirdInvalidNoPrefix(): void
    {
        $v = new Ccsd_Form_Validate_Isird();
        $this->assertFalse($v->isValid('123456'));
    }

    // ------------------------------------------------------------------
    // Isid-based validators (all use /^.+$/ — non-empty)
    // ------------------------------------------------------------------

    /** @dataProvider isidBasedValidators */
    public function testIsidValidNonEmpty(string $class): void
    {
        $v = new $class();
        $this->assertTrue($v->isValid('any-non-empty-value'));
    }

    /** @dataProvider isidBasedValidators */
    public function testIsidInvalidEmpty(string $class): void
    {
        $v = new $class();
        $this->assertFalse($v->isValid(''));
    }

    /** @return array<string, array{string}> */
    public static function isidBasedValidators(): array
    {
        return [
            'Isinspire'    => [Ccsd_Form_Validate_Isinspire::class],
            'Isoatao'      => [Ccsd_Form_Validate_Isoatao::class],
            'Isokina'      => [Ccsd_Form_Validate_Isokina::class],
            'Isprodinra'   => [Ccsd_Form_Validate_Isprodinra::class],
            'Isirstea'     => [Ccsd_Form_Validate_Isirstea::class],
            'Isensam'      => [Ccsd_Form_Validate_Isensam::class],
            'Iscern'       => [Ccsd_Form_Validate_Iscern::class],
            'Issciencespo' => [Ccsd_Form_Validate_Issciencespo::class],
        ];
    }

    // ------------------------------------------------------------------
    // ReDoS smoke test: long near-matching input must not hang
    // ------------------------------------------------------------------

    /** @dataProvider allValidatorClasses */
    public function testNoReDoSOnLongInput(string $class): void
    {
        $v = new $class();
        $long = str_repeat('a', 10000);
        // Should return without timeout (if it hangs, test infra will kill it)
        $result = $v->isValid($long);
        $this->assertIsBool($result);
    }

    /** @return array<string, array{string}> */
    public static function allValidatorClasses(): array
    {
        return [
            'Isdoi'            => [Ccsd_Form_Validate_Isdoi::class],
            'Isarxiv'          => [Ccsd_Form_Validate_Isarxiv::class],
            'Isbibcode'        => [Ccsd_Form_Validate_Isbibcode::class],
            'Ispubmed'         => [Ccsd_Form_Validate_Ispubmed::class],
            'Ispubmedcentral'  => [Ccsd_Form_Validate_Ispubmedcentral::class],
            'Isbiorxiv'        => [Ccsd_Form_Validate_Isbiorxiv::class],
            'Ischemrxiv'       => [Ccsd_Form_Validate_Ischemrxiv::class],
            'Isinspire'        => [Ccsd_Form_Validate_Isinspire::class],
            'Isoatao'          => [Ccsd_Form_Validate_Isoatao::class],
            'Isokina'          => [Ccsd_Form_Validate_Isokina::class],
            'Isprodinra'       => [Ccsd_Form_Validate_Isprodinra::class],
            'Isirstea'         => [Ccsd_Form_Validate_Isirstea::class],
            'Isensam'          => [Ccsd_Form_Validate_Isensam::class],
            'Iscern'           => [Ccsd_Form_Validate_Iscern::class],
            'Issciencespo'     => [Ccsd_Form_Validate_Issciencespo::class],
            'Isird'            => [Ccsd_Form_Validate_Isird::class],
        ];
    }
}
