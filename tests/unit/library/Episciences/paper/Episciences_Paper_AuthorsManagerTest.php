<?php

namespace unit\library\Episciences;

use Episciences_OpenAireResearchGraphTools;
use Episciences_Paper_AuthorsManager;
use PHPUnit\Framework\TestCase;


final class Episciences_Paper_AuthorsManagerTest extends TestCase
{
    const TESTS_UNIT_LIBRARY_EPISCIENCES_PAPER_DATA = '/data/';

    /**
     * @return void
     */
    public function testOrcidValidator(): void
    {
        $orcid = '0000-2222-5555-444X';
        self::assertEquals($orcid, Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcid));
        $orcidLower = '0000-2222-5555-444x';
        self::assertEquals('0000-2222-5555-444X', Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcidLower));
        self::assertIsString(Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcidLower));
    }

    /**
     * @dataProvider sampleTeiAuthor
     */
    public function testFormatNameAuthorFromTei(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $infoSampleAuthor = [];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getAuthorInfoFromXmlTei($sampleTeiAuthor, $infoSampleAuthor);
        self::assertIsArray($infoSampleAuthor);
        self::assertNotEmpty($infoSampleAuthor);
        self::assertEquals("Don", $infoSampleAuthor[0]['given_name']);
        self::assertEquals("Zagier", $infoSampleAuthor[0]['family']);
        self::assertEquals("Don Zagier", $infoSampleAuthor[0]['fullname']);
    }

    /**
     * @dataProvider sampleTeiAuthorWithOrcidAndAffi
     */
    public function testFormatRawTeiAffiliation(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $arrayAuthor = ['0' => ['given_name' => 'Don', 'family' => 'Zagier', 'fullname' => 'Don Zagier']];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getAuthorStructureFromXmlTei($sampleTeiAuthor, $arrayAuthor);
        self::assertIsArray($infoSampleAuthor);
        self::assertArrayHasKey('affiliations', $infoSampleAuthor[0]);
        self::assertEquals("struct-59373", $infoSampleAuthor[0]['affiliations'][0]);
        self::assertEquals("struct-56878", $infoSampleAuthor[0]['affiliations'][1]);
    }

    /**
     * @dataProvider sampleTeiAuthorWithOrcidAndAffi
     */
    public function testFormatOrcidFromTeiHal(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $arrayAuthor = ['0' => ['given_name' => 'Don', 'family' => 'Zagier', 'fullname' => 'Don Zagier']];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getOrcidAuthorFromXmlTei($sampleTeiAuthor, $arrayAuthor);
        self::assertIsArray($infoSampleAuthor[0]);
        self::assertArrayHasKey('orcid', $infoSampleAuthor[0]);
        self::assertEquals('0000-0002-4707-3229', $infoSampleAuthor[0]['orcid']);

    }


    /**
     * @dataProvider halTeiProvider
     */
    public function testGetAuthorsFromHalTeiWithAuthor(iterable $halTeiProvider): void
    {
        self::assertIsObject($halTeiProvider);
        $getAuthor = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($halTeiProvider);
        self::assertIsArray($getAuthor);
        self::assertNotEmpty($getAuthor);
        self::assertEquals("Don", $getAuthor[0]['given_name']);
        self::assertEquals("Zagier", $getAuthor[0]['family']);
        self::assertEquals("Don Zagier", $getAuthor[0]['fullname']);
        self::assertArrayHasKey('affiliations', $getAuthor[0]);
        self::assertEquals('struct-426005', $getAuthor[0]['affiliations'][0]);
        self::assertArrayHasKey('orcid', $getAuthor[0]);
        self::assertEquals('0000-0004-8099-840X', $getAuthor[0]['orcid']);
    }

    /**
     * @dataProvider halTeiEmptyProvider
     * @dataProvider halTeiEmptyAuthorsProvider
     */
    public function testGetAuthorsFromHalTeiWithoutAuthor(iterable $halTeiEmptyProvider): void
    {
        $getAuthor = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($halTeiEmptyProvider);
        self::assertEmpty($getAuthor);
    }

    /**
     * @dataProvider halTeiWithSeveralsAuthorsProvider
     */
    public function testgetAffiInfoFromHal(iterable $halTeiWithSeveralsAuthorsProvider): void
    {
        $getStruct = Episciences_Paper_AuthorsManager::getAffiFromHalTei($halTeiWithSeveralsAuthorsProvider);
        self::assertIsArray($getStruct);
        self::assertCount(21, $getStruct);
        self::assertArrayHasKey('ROR', $getStruct['struct-7127']);
        self::assertEquals('Université de Caen Normandie', $getStruct['struct-7127']['name']);
        self::assertEquals('https://ror.org/051kpcy16', $getStruct['struct-7127']['ROR']);
    }

    /**
     * @dataProvider sampleArrayAuthorAndStructTei
     * @param array $author
     * @param array $affiliation
     * @return void
     */
    public function testMergeAuthorInfoAndAffiTei(array $author, array $affiliation): void
    {
        $transformIdStructToName = Episciences_Paper_AuthorsManager::mergeAuthorInfoAndAffiTei($author[0], $affiliation[0]);
        self::assertIsArray($transformIdStructToName);
        self::assertCount(6, $transformIdStructToName);
        self::assertArrayNotHasKey('affiliations', $transformIdStructToName[2]);
        self::assertEquals('Littoral, Environnement, Télédétection, Géomatique', $transformIdStructToName[0]['affiliations'][0]['name']);
        self::assertArrayHasKey('ROR', $transformIdStructToName[0]['affiliations'][0]);
        self::assertEquals('https://ror.org/00r8amq78', $transformIdStructToName[0]['affiliations'][0]['ROR']);
    }

    /**
     * Merge both Array
     * @dataProvider sampleArrayTeiAndDB
     * @param array $authorDb
     * @param array $arrayTeiAuthor
     * @return void
     */
    public function testMergeTeiAndDB(array $authorDb, array $arrayTeiAuthor): void
    {
        $mergingArray = Episciences_Paper_AuthorsManager::mergeInfoDbAndInfoTei($authorDb, $arrayTeiAuthor);
        self::assertIsArray($mergingArray);
        self::assertCount(6, $mergingArray);
        self::assertArrayHasKey('orcid', $mergingArray[0]);
        self::assertEquals('0000-0002-8029-850X', $mergingArray[0]['orcid']);
        self::assertEquals('Littoral, Environnement, Télédétection, Géomatique', $mergingArray[0]['affiliation'][0]['name']);
        self::assertArrayHasKey('id', $mergingArray[0]['affiliation'][0]);
        self::assertIsArray($mergingArray[0]['affiliation'][0]['id']);
        self::assertEquals('https://ror.org/00r8amq78', $mergingArray[0]['affiliation'][0]['id'][0]['id']);
        self::assertEquals('ROR', $mergingArray[0]['affiliation'][0]['id'][0]['id-type']);
    }

    /**
     * @dataProvider sampleOaDbAndApi
     * @param array $sampleOACreatorDB
     * @param array $sampleOACreatorAPI
     * @return void
     */
    public function testGetAuthorOrcidFromOA(array $sampleOACreatorDB, array $sampleOACreatorAPI): void
    {
        [$arrayForDB, $isNewOrcid] = Episciences_OpenAireResearchGraphTools::getOrcidApiForDb($sampleOACreatorDB[0]['fullname'], $sampleOACreatorAPI, $sampleOACreatorDB, 0, 0);
        self::assertIsArray($arrayForDB);
        self::assertIsInt($isNewOrcid);
        self::assertEquals(1, $isNewOrcid);
        self::assertEquals('0000-0002-5332-5437', $arrayForDB[0]['orcid']);

    }


    /**
     * @return iterable
     */
    public function halTeiProvider(): iterable
    {
        yield [simplexml_load_file(__DIR__ . self::TESTS_UNIT_LIBRARY_EPISCIENCES_PAPER_DATA . 'halTeiProvider.xml')];
    }

    public function halTeiEmptyProvider(): iterable
    {

        yield [simplexml_load_file(__DIR__ . self::TESTS_UNIT_LIBRARY_EPISCIENCES_PAPER_DATA . 'halTeiEmptyProvider.xml')];
    }

    public function halTeiEmptyAuthorsProvider(): iterable
    {
        yield [simplexml_load_file(__DIR__ . self::TESTS_UNIT_LIBRARY_EPISCIENCES_PAPER_DATA . 'halTeiEmptyAuthorsProvider.xml')];
    }

    public function halTeiWithSeveralsAuthorsProvider(): iterable
    {
        yield [simplexml_load_file(__DIR__ . self::TESTS_UNIT_LIBRARY_EPISCIENCES_PAPER_DATA . 'halTeiWithSeveralsAuthorsProvider.xml')];
    }

    /**
     * info author name
     * @return iterable
     */
    public function sampleTeiAuthor(): iterable
    {
        yield [simplexml_load_string('<persName>
								<forename type="first">Don</forename>
								<surname>Zagier</surname>
							</persName>')];
    }

    public function sampleTeiAuthorWithOrcidAndAffi(): iterable
    {
        yield [simplexml_load_string('<author role="aut">
							<persName>
								<forename type="first">Don</forename>
								<surname>Zagier</surname>
							</persName>
							<email type="md5">612010d12abcc156e4835057dcaab522</email>
							<email type="domain">agrocampus-ouest.fr</email>
							<idno type="idhal" notation="numeric">932715</idno>
							<idno type="halauthorid" notation="string">672149-932715</idno>
							<idno type="ORCID">https://orcid.org/0000-0002-4707-3229</idno>
							<orgName ref="#struct-108028"/>
							<affiliation ref="#struct-59373"/>
                            <affiliation ref="#struct-56878"/>
						</author>')];
    }

    /**
     * @return array
     */
    public function sampleArrayAuthorAndStructTei(): array
    {
        return [[$this->sampleArrayAuthorTei(), $this->sampleArrayStructTei()]];
    }

    /**
     * Sample from dataProvider halTeiWithSeveralsAuthorsProvider
     * @return array
     */
    public function sampleArrayAuthorTei(): array
    {
        return [
            [
                0 =>
                    [
                        'given_name' => 'Armelle',
                        'family' => 'Decaulne',
                        'fullname' => 'Armelle Decaulne',
                        'affiliations' =>
                            [
                                0 => 'struct-2267',
                            ],
                        'orcid' => '0000-0002-8029-850X',
                    ],
                1 =>
                    [
                        'given_name' => 'Fabienne',
                        'family' => 'Joliet',
                        'fullname' => 'Fabienne Joliet',
                        'affiliations' =>
                            [
                                0 => 'struct-59373',
                            ],
                        'orcid' => '0000-0002-4707-3229',
                    ],
                2 =>
                    [
                        'given_name' => 'Laine',
                        'family' => 'Chanteloup',
                        'fullname' => 'Laine Chanteloup',
                    ],
                3 =>
                    [
                        'given_name' => 'Thora',
                        'family' => 'Herrmann',
                        'fullname' => 'Thora Herrmann',
                        'affiliations' =>
                            [
                                0 => 'struct-60508',
                            ],
                    ],
                4 =>
                    [
                        'given_name' => 'Najat',
                        'family' => 'Bhiry',
                        'fullname' => 'Najat Bhiry',
                        'affiliations' =>
                            [
                                0 => 'struct-194108',
                            ],
                    ],
                5 =>
                    [
                        'given_name' => 'Didier',
                        'family' => 'Haillot',
                        'fullname' => 'Didier Haillot',
                        'affiliations' =>
                            [
                                0 => 'struct-33789',
                            ],
                        'orcid' => '0000-0001-8676-034X',
                    ],
            ]
        ];
    }

    /**
     * Sample from dataProvider halTeiWithSeveralsAuthorsProvider
     * @return array
     */
    public function sampleArrayStructTei(): array
    {
        return [
            [
                'struct-2267' =>
                    [
                        'name' => 'Littoral, Environnement, Télédétection, Géomatique',
                        'ROR' => 'https://ror.org/00r8amq78' //fake ROR for the sample
                    ],
                'struct-59373' =>
                    [
                        'name' => 'Espaces et Sociétés',
                    ],
                'struct-60508' =>
                    [
                        'name' => 'Département de géographie',
                    ],
                'struct-194108' =>
                    [
                        'name' => 'Centre d\'études nordiques et Département de Géographie',
                    ],
                'struct-33789' =>
                    [
                        'name' => 'Laboratoire de Génie Thermique Énergétique et Procédés (EA1932)',
                    ],
                'struct-7127' =>
                    [
                        'name' => 'Université de Caen Normandie',
                        'ROR' => 'https://ror.org/051kpcy16',
                    ],
                'struct-455934' =>
                    [
                        'name' => 'Normandie Université',
                        'ROR' => 'https://ror.org/01k40cz91',
                    ],
                'struct-110691' =>
                    [
                        'name' => 'École pratique des hautes études',
                        'ROR' => 'https://ror.org/046b3cj80',
                    ],
                'struct-564132' =>
                    [
                        'name' => 'Université Paris sciences et lettres',
                    ],
                'struct-300314' =>
                    [
                        'name' => 'Université de Brest',
                        'ROR' => 'https://ror.org/https://ror.org/01b8h3982',
                    ],
                'struct-406201' =>
                    [
                        'name' => 'Université de Rennes 2',
                        'ROR' => 'https://ror.org/01m84wm78',
                    ],
                'struct-528860' =>
                    [
                        'name' => 'Université de Rennes',
                    ],
                'struct-441569' =>
                    [
                        'name' => 'Centre National de la Recherche Scientifique',
                    ],
                'struct-530572' =>
                    [
                        'name' => 'Institut de Géographie et d\'Aménagement Régional de l\'Université de Nantes',
                    ],
                'struct-93263' =>
                    [
                        'name' => 'Université de Nantes',
                    ],
                'struct-7566' =>
                    [
                        'name' => 'Le Mans Université',
                    ],
                'struct-74911' =>
                    [
                        'name' => 'Université d\'Angers',
                        'ROR' => 'https://ror.org/04yrqp957',
                    ],
                'struct-108028' =>
                    [
                        'name' => 'AGROCAMPUS OUEST',
                    ],
                'struct-302452' =>
                    [
                        'name' => 'Université de Montréal',
                    ],
                'struct-93488' =>
                    [
                        'name' => 'Université Laval [Québec]',
                    ],
                'struct-301085' =>
                    [
                        'name' => 'Université de Pau et des Pays de l\'Adour',
                    ]
            ]
        ];
    }

    /**
     * regroup sample before merging TEI and DB
     * @return array
     */
    public function sampleArrayTeiAndDB(): array
    {
        return [[$this->sampleArrayAuthorInDB(), $this->sampleArrayMergedAllInfoTei()]];
    }

    /**
     * sample array from DB before add new or already info from TEI
     * @return array
     */
    public function sampleArrayAuthorInDB(): array
    {
        return [
            [
                'fullname' => 'Armelle Decaulne',
                'given' => 'Armelle',
                'family' => 'Decaulne',
            ],
            [
                'fullname' => 'Fabienne Joliet',
                'given' => 'Fabienne',
                'family' => 'Joliet',
                'affiliation' =>
                    [
                        0 =>
                            [
                                'name' => 'Espaces et Sociétés',
                            ],
                    ],
            ],
            [
                'fullname' => 'Laine Chanteloup',
                'given' => 'Laine',
                'family' => 'Chanteloup',
            ],
            [
                'fullname' => 'Thora Herrmann',
                'given' => 'Thora',
                'family' => 'Herrmann',
                'affiliation' =>
                    [
                        0 =>
                            [
                                'name' => 'Département de géographie',
                            ],
                    ],
            ],
            [
                'fullname' => 'Najat Bhiry',
                'given' => 'Najat',
                'family' => 'Bhiry',
                'affiliation' =>
                    [
                        0 =>
                            [
                                'name' => 'Centre d\'études nordiques et Département de Géographie',
                            ],
                    ],
            ],
            [
                'fullname' => 'Didier Haillot',
                'given' => 'Didier',
                'family' => 'Haillot',
            ]
        ];
    }

    /**
     * sample array after mergeAuthorInfoAndAffiTei method
     * @return array
     */
    public function sampleArrayMergedAllInfoTei(): array
    {
        return [
            [
                'given_name' => 'Armelle',
                'family' => 'Decaulne',
                'fullname' => 'Armelle Decaulne',
                'affiliations' => [
                    [
                        'name' => 'Littoral, Environnement, Télédétection, Géomatique',
                        'ROR' => 'https://ror.org/00r8amq78',
                    ],
                ],
                'orcid' => '0000-0002-8029-850X',
            ],
            [
                'given_name' => 'Fabienne',
                'family' => 'Joliet',
                'fullname' => 'Fabienne Joliet',
                'affiliations' => [
                    [
                        'name' => 'Espaces et Sociétés',
                    ],
                ],
                'orcid' => '0000-0002-4707-3229',
            ],
            [
                'given_name' => 'Laine',
                'family' => 'Chanteloup',
                'fullname' => 'Laine Chanteloup',
            ],
            [
                'given_name' => 'Thora',
                'family' => 'Herrmann',
                'fullname' => 'Thora Herrmann',
                'affiliations' => [
                    [
                        'name' => 'Département de géographie',
                    ],
                ],
            ],
            [
                'given_name' => 'Najat',
                'family' => 'Bhiry',
                'fullname' => 'Najat Bhiry',
                'affiliations' => [
                    [
                        'name' => 'Centre d\'études nordiques et Département de Géographie',
                    ],
                ],
            ],
            [
                'given_name' => 'Didier',
                'family' => 'Haillot',
                'fullname' => 'Didier Haillot',
                'affiliations' => [
                    [
                        'name' => 'Laboratoire de Génie Thermique Énergétique et Procédés (EA1932)',
                    ],
                ],
                'orcid' => '0000-0001-8676-034X',
            ],
        ];
    }

    /**
     * sample merge db and api information for one author
     * @return array
     */
    public function sampleOaDbAndApi(): array
    {
        return [[$this->sampleDbForOACreator(), $this->sampleOACreator()]];
    }

    public function sampleDbForOACreator(): array
    {
        return [[
            'fullname' => 'Svjetlan Feretić',
            'given' => 'Svjetlan',
            'family' => 'Feretić',
        ]];
    }

    /**
     * @return array
     */
    public function sampleOACreator(): array
    {
        return [

            '@rank' => '1',
            '@orcid' => '0000-0002-5332-5437',
            '@URL' => 'https://academic.microsoft.com/#/detail/2304801209',
            '$' => 'Svjetlan Feretić',

        ];
    }
}

