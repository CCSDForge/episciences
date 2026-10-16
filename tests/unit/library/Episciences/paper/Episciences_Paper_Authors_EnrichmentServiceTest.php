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

    /**
     * A wrong ROR (copied from another affiliation of the same author) is replaced by the TEI one
     */
    public function testMergeReplacesRorDuplicatedFromAnotherAffiliation(): void
    {
        $rorAmu = [['id' => 'https://ror.org/035xkbk20', 'id-type' => 'ROR', 'acronym' => 'AMU']];
        $dbAuthors = [
            [
                'fullname' => 'Nicolas Salvage',
                'given' => 'Nicolas',
                'family' => 'Salvage',
                'affiliation' => [
                    ['name' => 'Institut des Sciences du Mouvement', 'id' => $rorAmu],
                    ['name' => 'Aix Marseille Université', 'id' => $rorAmu],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Nicolas Salvage',
                'given_name' => 'Nicolas',
                'first_name' => 'Nicolas',
                'family' => 'Salvage',
                'affiliations' => [
                    ['name' => 'Institut des Sciences du Mouvement', 'ROR' => 'https://ror.org/03tncyc93', 'acronym' => 'ISM'],
                    ['name' => 'Aix Marseille Université', 'ROR' => 'https://ror.org/035xkbk20', 'acronym' => 'AMU'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertCount(2, $result[0]['affiliation']);
        self::assertSame(
            [['id' => 'https://ror.org/03tncyc93', 'id-type' => 'ROR', 'acronym' => 'ISM']],
            $result[0]['affiliation'][0]['id']
        );
        self::assertSame($rorAmu, $result[0]['affiliation'][1]['id']);
    }

    /**
     * A ROR copied from a sibling affiliation is removed when the HAL structure has no ROR
     */
    public function testMergeRemovesCopiedRorWhenTeiStructureHasNone(): void
    {
        $rorUdem = [['id' => 'https://ror.org/0161xgx34', 'id-type' => 'ROR', 'acronym' => 'UdeM']];
        $dbAuthors = [
            [
                'fullname' => 'Kevin Co',
                'given' => 'Kevin',
                'family' => 'Co',
                'affiliation' => [
                    ['name' => 'Laboratoire LS2M', 'id' => $rorUdem],
                    ['name' => 'Université de Montréal', 'id' => $rorUdem],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Kevin Co',
                'given_name' => 'Kevin',
                'first_name' => 'Kevin',
                'family' => 'Co',
                'affiliations' => [
                    ['name' => 'Laboratoire LS2M'],
                    ['name' => 'Université de Montréal', 'ROR' => 'https://ror.org/0161xgx34', 'acronym' => 'UdeM'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame(
            [['name' => 'Laboratoire LS2M'], ['name' => 'Université de Montréal', 'id' => $rorUdem]],
            $result[0]['affiliation']
        );
    }

    /**
     * A ROR carried by a single affiliation (e.g. set manually) is kept even if HAL disagrees
     */
    public function testMergeKeepsManualRorNotSharedWithSibling(): void
    {
        $dbAuthors = [
            [
                'fullname' => 'Kevin Co',
                'given' => 'Kevin',
                'family' => 'Co',
                'affiliation' => [
                    ['name' => 'Laboratoire LS2M', 'id' => [['id' => 'https://ror.org/0000manual', 'id-type' => 'ROR']]],
                    ['name' => 'Université de Montréal', 'id' => [['id' => 'https://ror.org/0161xgx34', 'id-type' => 'ROR']]],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Kevin Co',
                'given_name' => 'Kevin',
                'first_name' => 'Kevin',
                'family' => 'Co',
                'affiliations' => [
                    ['name' => 'Laboratoire LS2M'],
                    ['name' => 'Université de Montréal', 'ROR' => 'https://ror.org/0other0000'],
                ],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame($dbAuthors, $result);
    }

    /**
     * An affiliation already holding the TEI ROR is left untouched
     */
    public function testMergeKeepsAffiliationWithSameRor(): void
    {
        $dbAffiliation = ['name' => 'CNRS', 'id' => [['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'Custom']]];
        $dbAuthors = [
            ['fullname' => 'John Doe', 'given' => 'John', 'family' => 'Doe', 'affiliation' => [$dbAffiliation]],
        ];

        $teiAuthors = [
            [
                'fullname' => 'John Doe',
                'given_name' => 'John',
                'family' => 'Doe',
                'affiliations' => [['name' => 'CNRS', 'ROR' => 'https://ror.org/02feahw73', 'acronym' => 'CNRS']],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame([$dbAffiliation], $result[0]['affiliation']);
    }

    /**
     * A DB author without middle name (OAI-DC) is matched and completed with the TEI middle name
     */
    public function testMergeAddsMiddleNameFromTei(): void
    {
        $dbAuthors = [
            ['fullname' => 'Eva Herbst', 'given' => 'Eva', 'family' => 'Herbst'],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Eva C Herbst',
                'given_name' => 'Eva C',
                'first_name' => 'Eva',
                'family' => 'Herbst',
                'orcid' => '0000-0001-2345-6789',
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame('Eva C', $result[0]['given']);
        self::assertSame('Eva C Herbst', $result[0]['fullname']);
        self::assertSame('Herbst', $result[0]['family']);
        self::assertSame('0000-0001-2345-6789', $result[0]['orcid']);

        $secondPass = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($result, $teiAuthors);
        self::assertSame($result, $secondPass);
    }

    /**
     * A DB given name that differs from the TEI first forename is never overwritten
     */
    public function testMergeDoesNotOverwriteDifferentGivenName(): void
    {
        $dbAuthors = [
            ['fullname' => 'Eva Herbst', 'given' => 'E.', 'family' => 'Herbst'],
        ];

        $teiAuthors = [
            ['fullname' => 'Eva C Herbst', 'given_name' => 'Eva C', 'first_name' => 'Eva', 'family' => 'Herbst'],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame($dbAuthors, $result);
    }

    /**
     * HAL authors are built from the TEI: middle names, ORCID and affiliations with their own ROR
     */
    public function testBuildAuthorsFromHalTeiUsesTeiRecords(): void
    {
        $dcAuthors = [
            ['fullname' => 'Eva Herbst', 'given' => 'Eva', 'family' => 'Herbst'],
            ['fullname' => 'Philipp Moroder', 'given' => 'Philipp', 'family' => 'Moroder'],
        ];

        $teiAuthors = [
            [
                'given_name' => 'Eva C',
                'first_name' => 'Eva',
                'family' => 'Herbst',
                'fullname' => 'Eva C Herbst',
                'orcid' => '0000-0001-2345-6789',
                'affiliations' => [
                    ['name' => 'Institut ISM', 'ROR' => 'https://ror.org/03tncyc93', 'acronym' => 'ISM'],
                    ['name' => 'Aix Marseille Université', 'ROR' => 'https://ror.org/035xkbk20', 'acronym' => 'AMU'],
                ],
            ],
            [
                'given_name' => 'Philipp',
                'first_name' => 'Philipp',
                'family' => 'Moroder',
                'fullname' => 'Philipp Moroder',
                'affiliations' => [['name' => 'Schulthess Klinik']],
            ],
        ];

        $result = Episciences_Paper_Authors_EnrichmentService::buildAuthorsFromHalTei($dcAuthors, $teiAuthors);

        self::assertSame([
            [
                'fullname' => 'Eva C Herbst',
                'given' => 'Eva C',
                'family' => 'Herbst',
                'orcid' => '0000-0001-2345-6789',
                'affiliation' => [
                    ['name' => 'Institut ISM', 'id' => [['id' => 'https://ror.org/03tncyc93', 'id-type' => 'ROR', 'acronym' => 'ISM']]],
                    ['name' => 'Aix Marseille Université', 'id' => [['id' => 'https://ror.org/035xkbk20', 'id-type' => 'ROR', 'acronym' => 'AMU']]],
                ],
            ],
            [
                'fullname' => 'Philipp Moroder',
                'given' => 'Philipp',
                'family' => 'Moroder',
                'affiliation' => [['name' => 'Schulthess Klinik']],
            ],
        ], $result);
    }

    /**
     * DC authors are kept when the TEI does not list the same number of authors
     */
    public function testBuildAuthorsFromHalTeiFallsBackOnDcWhenAuthorCountDiffers(): void
    {
        $dcAuthors = [
            ['fullname' => 'Eva Herbst', 'given' => 'Eva', 'family' => 'Herbst'],
            ['fullname' => 'Philipp Moroder', 'given' => 'Philipp', 'family' => 'Moroder'],
        ];

        $teiAuthors = [
            ['given_name' => 'Eva C', 'first_name' => 'Eva', 'family' => 'Herbst', 'fullname' => 'Eva C Herbst'],
        ];

        self::assertSame($dcAuthors, Episciences_Paper_Authors_EnrichmentService::buildAuthorsFromHalTei($dcAuthors, $teiAuthors));
        self::assertSame($dcAuthors, Episciences_Paper_Authors_EnrichmentService::buildAuthorsFromHalTei($dcAuthors, []));
    }

    /**
     * A TEI author without forename is stored with a null given name, like DC authors
     */
    public function testBuildAuthorsFromHalTeiStoresNullGivenNameWhenMissing(): void
    {
        $dcAuthors = [['fullname' => ' Collective', 'given' => null, 'family' => 'Collective']];
        $teiAuthors = [['given_name' => '', 'first_name' => '', 'family' => 'Collective', 'fullname' => 'Collective']];

        $result = Episciences_Paper_Authors_EnrichmentService::buildAuthorsFromHalTei($dcAuthors, $teiAuthors);

        self::assertSame([['fullname' => 'Collective', 'given' => null, 'family' => 'Collective']], $result);
    }

    /**
     * Every correction applied during a merge is listed, and the list can be reset
     */
    public function testMergeListsAppliedCorrections(): void
    {
        $rorAmu = [['id' => 'https://ror.org/035xkbk20', 'id-type' => 'ROR', 'acronym' => 'AMU']];
        $dbAuthors = [
            [
                'fullname' => 'Eva Herbst',
                'given' => 'Eva',
                'family' => 'Herbst',
                'affiliation' => [
                    ['name' => 'ISM', 'id' => $rorAmu],
                    ['name' => 'AMU', 'id' => $rorAmu],
                ],
            ],
        ];

        $teiAuthors = [
            [
                'fullname' => 'Eva C Herbst',
                'given_name' => 'Eva C',
                'first_name' => 'Eva',
                'family' => 'Herbst',
                'orcid' => '0000-0001-2345-6789',
                'affiliations' => [
                    ['name' => 'ISM', 'ROR' => 'https://ror.org/03tncyc93'],
                    ['name' => 'AMU', 'ROR' => 'https://ror.org/035xkbk20'],
                    ['name' => 'CNRS'],
                ],
            ],
        ];

        Episciences_Paper_Authors_EnrichmentService::resetCorrections();
        Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        self::assertSame([
            'Middle name added: Eva Herbst -> Eva C Herbst',
            'ORCID 0000-0001-2345-6789 added for Eva C Herbst',
            'ROR https://ror.org/035xkbk20 replaced by https://ror.org/03tncyc93 for Eva C Herbst - ISM',
            'Affiliation added for Eva C Herbst - CNRS (no ROR)',
        ], Episciences_Paper_Authors_EnrichmentService::getCorrections());

        Episciences_Paper_Authors_EnrichmentService::resetCorrections();
        self::assertSame([], Episciences_Paper_Authors_EnrichmentService::getCorrections());
    }
}
