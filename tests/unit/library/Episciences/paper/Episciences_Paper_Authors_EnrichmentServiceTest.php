<?php

namespace unit\library\Episciences;

use Episciences_Paper_Authors_EnrichmentService;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_Authors_EnrichmentServiceTest extends TestCase
{
    /**
     * ORCID should be added from TEI when DB author has none
     */
    public function testMergeAddsOrcidWhenMissing(): void
    {
        $dbAuthors = [
            ['fullname' => 'John Doe', 'given' => 'John', 'family' => 'Doe'],
        ];

        $teiAuthors = [
            ['fullname' => 'John Doe', 'given_name' => 'John', 'family' => 'Doe', 'orcid' => '0000-0001-2345-6789'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertArrayHasKey('orcid', $result[0]);
        self::assertEquals('0000-0001-2345-6789', $result[0]['orcid']);
    }

    /**
     * ORCID should NOT be overwritten when DB author already has one
     */
    public function testMergeDoesNotOverwriteExistingOrcid(): void
    {
        $dbAuthors = [
            ['fullname' => 'John Doe', 'given' => 'John', 'family' => 'Doe', 'orcid' => '0000-0001-1111-1111'],
        ];

        $teiAuthors = [
            ['fullname' => 'John Doe', 'given_name' => 'John', 'family' => 'Doe', 'orcid' => '0000-0001-2222-2222'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertEquals('0000-0001-1111-1111', $result[0]['orcid']);
    }

    /**
     * Affiliation with ROR should be added when DB author has no affiliations
     */
    public function testMergeAddsNewAffiliationWithRor(): void
    {
        $dbAuthors = [
            ['fullname' => 'Jane Smith', 'given' => 'Jane', 'family' => 'Smith'],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Jane Smith',
                'given_name' => 'Jane',
                'family' => 'Smith',
                'affiliations' => [
                    ['name' => 'CNRS', 'ROR' => 'https://ror.org/02feahw73', 'acronym' => 'CNRS'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertArrayHasKey('affiliation', $result[0]);
        self::assertCount(1, $result[0]['affiliation']);
        self::assertEquals('CNRS', $result[0]['affiliation'][0]['name']);
        self::assertArrayHasKey('id', $result[0]['affiliation'][0]);
        self::assertEquals('https://ror.org/02feahw73', $result[0]['affiliation'][0]['id'][0]['id']);
        self::assertEquals('ROR', $result[0]['affiliation'][0]['id'][0]['id-type']);
    }

    /**
     * Affiliation without ROR should be added as name-only
     */
    public function testMergeAddsNewAffiliationWithoutRor(): void
    {
        $dbAuthors = [
            ['fullname' => 'Jane Smith', 'given' => 'Jane', 'family' => 'Smith'],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Jane Smith',
                'given_name' => 'Jane',
                'family' => 'Smith',
                'affiliations' => [
                    ['name' => 'Some University'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertArrayHasKey('affiliation', $result[0]);
        self::assertEquals('Some University', $result[0]['affiliation'][0]['name']);
        self::assertArrayNotHasKey('id', $result[0]['affiliation'][0]);
    }

    /**
     * Non-matching authors should not be enriched
     */
    public function testMergeDoesNotEnrichNonMatchingAuthors(): void
    {
        $dbAuthors = [
            ['fullname' => 'Alice Martin', 'given' => 'Alice', 'family' => 'Martin'],
        ];

        $teiAuthors = [
            ['fullname' => 'Bob Dupont', 'given_name' => 'Bob', 'family' => 'Dupont', 'orcid' => '0000-0001-9999-9999'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertArrayNotHasKey('orcid', $result[0]);
        self::assertArrayNotHasKey('affiliation', $result[0]);
    }

    /**
     * Accent-insensitive name matching should work (e.g. é vs e)
     */
    public function testMergeMatchesAccentInsensitiveNames(): void
    {
        $dbAuthors = [
            ['fullname' => 'Hélène Müller', 'given' => 'Hélène', 'family' => 'Müller'],
        ];

        $teiAuthors = [
            ['fullname' => 'Helene Muller', 'given_name' => 'Helene', 'family' => 'Muller', 'orcid' => '0000-0003-1111-2222'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertArrayHasKey('orcid', $result[0]);
        self::assertEquals('0000-0003-1111-2222', $result[0]['orcid']);
    }

    /**
     * An already-existing affiliation should not be duplicated
     */
    public function testMergeDoesNotDuplicateExistingAffiliation(): void
    {
        $dbAuthors = [
            [
                'fullname' => 'John Doe',
                'given' => 'John',
                'family' => 'Doe',
                'affiliation' => [
                    ['name' => 'Existing Lab'],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'John Doe',
                'given_name' => 'John',
                'family' => 'Doe',
                'affiliations' => [
                    ['name' => 'Existing Lab'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertCount(1, $result[0]['affiliation']);
    }

    /**
     * A new affiliation should be appended alongside existing ones
     */
    public function testMergeAppendsNewAffiliationToExisting(): void
    {
        $dbAuthors = [
            [
                'fullname' => 'John Doe',
                'given' => 'John',
                'family' => 'Doe',
                'affiliation' => [
                    ['name' => 'Lab Alpha'],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'John Doe',
                'given_name' => 'John',
                'family' => 'Doe',
                'affiliations' => [
                    ['name' => 'Lab Beta', 'ROR' => 'https://ror.org/00000001'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertCount(2, $result[0]['affiliation']);
        self::assertEquals('Lab Alpha', $result[0]['affiliation'][0]['name']);
        self::assertEquals('Lab Beta', $result[0]['affiliation'][1]['name']);
    }

    /**
     * Empty TEI authors array should return DB authors unchanged
     */
    public function testMergeWithEmptyTeiReturnsDbUnchanged(): void
    {
        $dbAuthors = [
            ['fullname' => 'John Doe', 'given' => 'John', 'family' => 'Doe'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, []);

        self::assertEquals($dbAuthors, $result);
    }

    /**
     * Empty DB authors array should return empty
     */
    public function testMergeWithEmptyDbReturnsEmpty(): void
    {
        $teiAuthors = [
            ['fullname' => 'John Doe', 'given_name' => 'John', 'family' => 'Doe', 'orcid' => '0000-0001-0000-0000'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei([], $teiAuthors);

        self::assertEmpty($result);
    }

    /**
     * ROR should be added to an existing affiliation that lacks one
     */
    public function testMergeAddsRorToExistingAffiliationWithoutRor(): void
    {
        $dbAuthors = [
            [
                'fullname' => 'John Doe',
                'given' => 'John',
                'family' => 'Doe',
                'affiliation' => [
                    ['name' => 'CNRS'],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'John Doe',
                'given_name' => 'John',
                'family' => 'Doe',
                'affiliations' => [
                    ['name' => 'CNRS', 'ROR' => 'https://ror.org/02feahw73', 'acronym' => 'CNRS'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertCount(1, $result[0]['affiliation']);
        self::assertArrayHasKey('id', $result[0]['affiliation'][0]);
        self::assertEquals('https://ror.org/02feahw73', $result[0]['affiliation'][0]['id'][0]['id']);
    }

    /**
     * Multiple authors should be enriched independently
     */
    public function testMergeEnrichesMultipleAuthorsIndependently(): void
    {
        $dbAuthors = [
            ['fullname' => 'Alice Martin', 'given' => 'Alice', 'family' => 'Martin'],
            ['fullname' => 'Bob Dupont', 'given' => 'Bob', 'family' => 'Dupont'],
        ];

        $teiAuthors = [
            ['fullname' => 'Alice Martin', 'given_name' => 'Alice', 'family' => 'Martin', 'orcid' => '0000-0001-1111-1111'],
            ['fullname' => 'Bob Dupont', 'given_name' => 'Bob', 'family' => 'Dupont', 'orcid' => '0000-0002-2222-2222'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertEquals('0000-0001-1111-1111', $result[0]['orcid']);
        self::assertEquals('0000-0002-2222-2222', $result[1]['orcid']);
    }
}
