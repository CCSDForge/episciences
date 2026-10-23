<?php

namespace unit\library\Episciences\Paper\Import;

use Episciences\Paper\Import\PublicationDateResolver;
use PHPUnit\Framework\TestCase;

/**
 * Only the pure buildHalApiUrl() logic is unit-tested here; resolve() needs
 * a real Episciences_Paper and (for the HAL API step) network access.
 */
class PublicationDateResolverTest extends TestCase
{
    public function testBuildHalApiUrl(): void
    {
        $url = PublicationDateResolver::buildHalApiUrl('hal-01234567', '2');

        $this->assertSame(
            'https://api.archives-ouvertes.fr/search/?indent=true&q=halId_s:hal-01234567&fq=version_i:2&fl=publicationDate_tdate&wt=json',
            $url
        );
    }

    public function testBuildHalApiUrlEncodesSpecialCharacters(): void
    {
        $url = PublicationDateResolver::buildHalApiUrl('hal id/with space', '1');

        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringContainsString('hal%20id%2Fwith%20space', $url);
    }
}
