<?php

namespace unit\library\Episciences;

use Episciences_Paper_Authors_AffiliationHelper;
use PHPUnit\Framework\TestCase;

final class Episciences_Paper_Authors_AffiliationHelperTest extends TestCase
{
    public function testBuildWithRorWithAcronym(): void
    {
        $input = [
            'name' => 'Centre National de la Recherche Scientifique',
            'ROR' => 'https://ror.org/02feahw73',
            'acronym' => 'CNRS',
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::buildWithRor($input);

        self::assertEquals('Centre National de la Recherche Scientifique', $result['name']);
        self::assertArrayHasKey('id', $result);
        self::assertCount(1, $result['id']);
        self::assertEquals('https://ror.org/02feahw73', $result['id'][0]['id']);
        self::assertEquals('ROR', $result['id'][0]['id-type']);
        self::assertEquals('CNRS', $result['id'][0]['acronym']);
    }

    public function testBuildWithRorWithoutAcronym(): void
    {
        $input = [
            'name' => 'Université de Paris',
            'ROR' => 'https://ror.org/05f82e368',
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::buildWithRor($input);

        self::assertEquals('Université de Paris', $result['name']);
        self::assertEquals('https://ror.org/05f82e368', $result['id'][0]['id']);
        self::assertEquals('ROR', $result['id'][0]['id-type']);
        self::assertArrayNotHasKey('acronym', $result['id'][0]);
    }

    public function testBuildNameOnly(): void
    {
        $result = Episciences_Paper_Authors_AffiliationHelper::buildNameOnly('Some University');

        self::assertEquals(['name' => 'Some University'], $result);
    }

    public function testBuildRorOnlyWithAcronym(): void
    {
        $result = Episciences_Paper_Authors_AffiliationHelper::buildRorOnly('https://ror.org/02feahw73', 'CNRS');

        self::assertCount(1, $result);
        self::assertEquals('https://ror.org/02feahw73', $result[0]['id']);
        self::assertEquals('ROR', $result[0]['id-type']);
        self::assertEquals('CNRS', $result[0]['acronym']);
    }

    public function testBuildRorOnlyWithoutAcronym(): void
    {
        $result = Episciences_Paper_Authors_AffiliationHelper::buildRorOnly('https://ror.org/02feahw73', '');

        self::assertCount(1, $result);
        self::assertEquals('https://ror.org/02feahw73', $result[0]['id']);
        self::assertEquals('ROR', $result[0]['id-type']);
        self::assertArrayNotHasKey('acronym', $result[0]);
    }

    public function testBuildRorOnlyWithNullAcronym(): void
    {
        $result = Episciences_Paper_Authors_AffiliationHelper::buildRorOnly('https://ror.org/02feahw73', null);

        self::assertArrayNotHasKey('acronym', $result[0]);
    }

    public function testHasRorReturnsTrueWhenPresent(): void
    {
        $affiliation = [
            'name' => 'CNRS',
            'id' => [
                ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR']
            ]
        ];

        self::assertTrue(Episciences_Paper_Authors_AffiliationHelper::hasRor($affiliation));
    }

    public function testHasRorReturnsFalseWhenMissing(): void
    {
        $affiliation = ['name' => 'Some Lab'];

        self::assertFalse(Episciences_Paper_Authors_AffiliationHelper::hasRor($affiliation));
    }

    public function testHasRorReturnsFalseForNonRorIdentifier(): void
    {
        $affiliation = [
            'name' => 'Some Lab',
            'id' => [
                ['id' => 'https://isni.org/isni/0000000121663029', 'id-type' => 'ISNI']
            ]
        ];

        self::assertFalse(Episciences_Paper_Authors_AffiliationHelper::hasRor($affiliation));
    }

    public function testHasAcronymReturnsTrueWhenPresent(): void
    {
        $affiliation = [
            'name' => 'CNRS',
            'id' => [
                ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']
            ]
        ];

        self::assertTrue(Episciences_Paper_Authors_AffiliationHelper::hasAcronym($affiliation));
    }

    public function testHasAcronymReturnsFalseWhenMissing(): void
    {
        $affiliation = [
            'name' => 'Some Lab',
            'id' => [
                ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR']
            ]
        ];

        self::assertFalse(Episciences_Paper_Authors_AffiliationHelper::hasAcronym($affiliation));
    }

    public function testIsAcronymDuplicateReturnsTrue(): void
    {
        $identifiers = [
            ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']
        ];

        self::assertTrue(Episciences_Paper_Authors_AffiliationHelper::isAcronymDuplicate($identifiers, 'CNRS'));
    }

    public function testIsAcronymDuplicateReturnsFalse(): void
    {
        $identifiers = [
            ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']
        ];

        self::assertFalse(Episciences_Paper_Authors_AffiliationHelper::isAcronymDuplicate($identifiers, 'INRIA'));
    }

    public function testGetExistingAcronymsWithMultiple(): void
    {
        $affiliations = [
            [
                'name' => 'CNRS',
                'id' => [['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']]
            ],
            [
                'name' => 'INRIA',
                'id' => [['id' => 'https://ror.org/02vjkv261', 'id-type' => 'ROR', 'acronym' => 'INRIA']]
            ],
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::getExistingAcronyms($affiliations);

        self::assertEquals('[CNRS]||[INRIA]', $result);
    }

    public function testGetExistingAcronymsEmpty(): void
    {
        $affiliations = [
            ['name' => 'Some Lab'],
        ];

        self::assertEquals('', Episciences_Paper_Authors_AffiliationHelper::getExistingAcronyms($affiliations));
    }

    public function testGetExistingAcronymsDeduplicates(): void
    {
        $affiliations = [
            [
                'name' => 'CNRS Lab 1',
                'id' => [['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']]
            ],
            [
                'name' => 'CNRS Lab 2',
                'id' => [['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']]
            ],
        ];

        self::assertEquals('[CNRS]', Episciences_Paper_Authors_AffiliationHelper::getExistingAcronyms($affiliations));
    }

    public function testFormatAcronymList(): void
    {
        self::assertEquals('[A]||[B]||[C]', Episciences_Paper_Authors_AffiliationHelper::formatAcronymList(['[A]', '[B]', '[C]']));
    }

    public function testSetOrUpdateRorAcronymFindsMatch(): void
    {
        $acronyms = ['CNRS', 'INRIA'];
        $haystack = 'Centre National de la Recherche Scientifique CNRS Paris';

        self::assertEquals('CNRS', Episciences_Paper_Authors_AffiliationHelper::setOrUpdateRorAcronym($acronyms, $haystack));
    }

    public function testSetOrUpdateRorAcronymNoMatch(): void
    {
        $acronyms = ['INRIA', 'INSERM'];
        $haystack = 'Some random university name';

        self::assertEquals('', Episciences_Paper_Authors_AffiliationHelper::setOrUpdateRorAcronym($acronyms, $haystack));
    }

    public function testEraseAcronymInName(): void
    {
        $result = Episciences_Paper_Authors_AffiliationHelper::eraseAcronymInName('CNRS - Paris [CNRS]', '[CNRS]');

        self::assertEquals('CNRS - Paris', $result);
    }

    public function testCleanAcronym(): void
    {
        self::assertEquals('CNRS', Episciences_Paper_Authors_AffiliationHelper::cleanAcronym('[CNRS]'));
        self::assertEquals('INRIA', Episciences_Paper_Authors_AffiliationHelper::cleanAcronym(' [INRIA] '));
    }

    public function testFormatAffiliationForInputRorWithRorAndAcronym(): void
    {
        $affiliations = [
            [
                'name' => 'Centre National de la Recherche Scientifique',
                'id' => [
                    ['id' => 'https://ror.org/02feahw73', 'id-type' => 'ROR', 'acronym' => 'CNRS']
                ]
            ]
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::formatAffiliationForInputRor($affiliations);

        self::assertCount(1, $result);
        self::assertEquals(
            'Centre National de la Recherche Scientifique [CNRS] #https://ror.org/02feahw73',
            $result[0]
        );
    }

    public function testFormatAffiliationForInputRorWithRorNoAcronym(): void
    {
        $affiliations = [
            [
                'name' => 'Université de Paris',
                'id' => [
                    ['id' => 'https://ror.org/05f82e368', 'id-type' => 'ROR']
                ]
            ]
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::formatAffiliationForInputRor($affiliations);

        self::assertEquals('Université de Paris #https://ror.org/05f82e368', $result[0]);
    }

    public function testFormatAffiliationForInputRorNameOnly(): void
    {
        $affiliations = [
            ['name' => 'Some Lab']
        ];

        $result = Episciences_Paper_Authors_AffiliationHelper::formatAffiliationForInputRor($affiliations);

        self::assertEquals('Some Lab', $result[0]);
    }
}
