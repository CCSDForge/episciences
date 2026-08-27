<?php

namespace unit\library\Episciences\Paper\Import;

use Episciences\Paper\Import\VolumeSectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Only the pure normalizeTitle()/findMatchingId() logic is unit-tested here;
 * resolveVolumeId()/resolveSectionId() need a DB and Episciences_Volume/Section.
 */
class VolumeSectionResolverTest extends TestCase
{
    public function testNormalizeTitleTrimsAndLowercases(): void
    {
        $this->assertSame('mon titre', VolumeSectionResolver::normalizeTitle('  Mon Titre  '));
    }

    public function testNormalizeTitleCollapsesInnerWhitespace(): void
    {
        $this->assertSame('mon titre', VolumeSectionResolver::normalizeTitle("Mon   \n Titre"));
    }

    public function testNormalizeTitleHandlesAccents(): void
    {
        $this->assertSame('numéro spécial', VolumeSectionResolver::normalizeTitle('Numéro Spécial'));
    }

    public function testFindMatchingIdExactMatch(): void
    {
        $existing = [1 => ['fr' => 'Volume special'], 2 => ['fr' => 'Autre volume']];

        $this->assertSame(1, VolumeSectionResolver::findMatchingId(['fr' => 'Volume special'], $existing));
    }

    public function testFindMatchingIdCaseAndWhitespaceInsensitive(): void
    {
        $existing = [5 => ['fr' => 'Volume Spécial']];

        $this->assertSame(5, VolumeSectionResolver::findMatchingId(['fr' => '  volume   spécial '], $existing));
    }

    public function testFindMatchingIdOnPartialLanguageOverlap(): void
    {
        $existing = [9 => ['en' => 'Special issue']];

        $this->assertSame(
            9,
            VolumeSectionResolver::findMatchingId(['fr' => 'Numero special', 'en' => 'Special issue'], $existing)
        );
    }

    public function testFindMatchingIdReturnsNullWhenNoOverlap(): void
    {
        $existing = [1 => ['fr' => 'Volume A']];

        $this->assertNull(VolumeSectionResolver::findMatchingId(['fr' => 'Volume B'], $existing));
    }

    public function testFindMatchingIdReturnsFirstMatchAmongCandidates(): void
    {
        $existing = [1 => ['fr' => 'Volume A'], 2 => ['fr' => 'Volume A']];

        $this->assertSame(1, VolumeSectionResolver::findMatchingId(['fr' => 'Volume A'], $existing));
    }

    public function testFindMatchingIdReturnsNullOnEmptyCandidates(): void
    {
        $this->assertNull(VolumeSectionResolver::findMatchingId(['fr' => 'Volume A'], []));
    }
}
