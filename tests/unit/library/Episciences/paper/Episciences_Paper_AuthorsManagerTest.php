<?php

namespace unit\library\Episciences;

use Episciences_OpenAireResearchGraphTools;
use Episciences_Paper_AuthorsManager;
use PHPUnit\Framework\TestCase;


final class Episciences_Paper_AuthorsManagerTest extends TestCase
{
    /**
     * @return void
     */
    public function testOrcidValidator(): void
    {
        $orcid = '0000-2222-5555-444X';
        self::assertEquals($orcid,Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcid));
        $orcidLower =  '0000-2222-5555-444x';
        self::assertEquals( '0000-2222-5555-444X',Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcidLower));
        self::assertIsString(Episciences_Paper_AuthorsManager::cleanLowerCaseOrcid($orcidLower));
    }

    /**
     * @dataProvider sampleTeiAuthor
     */
    public function testFormatNameAuthorFromTei(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $infoSampleAuthor = [];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getAuthorInfoFromXmlTei($sampleTeiAuthor,$infoSampleAuthor);
        self::assertIsArray($infoSampleAuthor);
        self::assertNotEmpty($infoSampleAuthor);
        self::assertEquals("Don",$infoSampleAuthor[0]['given_name']);
        self::assertEquals("Zagier",$infoSampleAuthor[0]['family']);
        self::assertEquals("Don Zagier",$infoSampleAuthor[0]['fullname']);
    }

    /**
     * @dataProvider sampleTeiAuthorWithOrcidAndAffi
     */
    public function testFormatRawTeiAffiliation(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $arrayAuthor = ['0' => ['given_name'=>'Don','family'=>'Zagier','fullname'=>'Don Zagier']];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getAuthorStructureFromXmlTei($sampleTeiAuthor,$arrayAuthor);
        self::assertIsArray($infoSampleAuthor);
        self::assertArrayHasKey('affiliations',$infoSampleAuthor[0]);
        self::assertEquals("struct-59373",$infoSampleAuthor[0]['affiliations'][0]);
        self::assertEquals("struct-56878",$infoSampleAuthor[0]['affiliations'][1]);
    }

    /**
     * @dataProvider sampleTeiAuthorWithOrcidAndAffi
     */
    public function testFormatOrcidFromTeiHal(iterable $sampleTeiAuthor): void
    {
        self::assertIsObject($sampleTeiAuthor);
        $arrayAuthor = ['0' => ['given_name'=>'Don','family'=>'Zagier','fullname'=>'Don Zagier']];
        $infoSampleAuthor = Episciences_Paper_AuthorsManager::getOrcidAuthorFromXmlTei($sampleTeiAuthor,$arrayAuthor);
        self::assertIsArray($infoSampleAuthor[0]);
        self::assertArrayHasKey('orcid',$infoSampleAuthor[0]);
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
    public function testGetAuthorsFromHalTeiWithoutAuthor(iterable $halTeiEmptyProviders): void
    {
        $getAuthor = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($halTeiEmptyProviders);
        self::assertEmpty($getAuthor);
    }

    /**
     * @dataProvider halTeiWithSeveralsAuthorsProvider
     */
    public function testgetAffiInfoFromHal(iterable $halTeiWithSeveralsAuthorsProvider): void
    {
        $getStruct = Episciences_Paper_AuthorsManager::getAffiFromHalTei($halTeiWithSeveralsAuthorsProvider);
        self::assertIsArray($getStruct);
        self::assertCount(21,$getStruct);
        self::assertArrayHasKey('ROR',$getStruct['struct-7127']);
        self::assertEquals('Université de Caen Normandie',$getStruct['struct-7127']['name']);
        self::assertEquals('https://ror.org/051kpcy16',$getStruct['struct-7127']['ROR']);
    }

    /**
     * @dataProvider sampleArrayAuthorAndStructTei
     * @param array $author
     * @param array $affiliation
     * @return void
     */
    public function testMergeAuthorInfoAndAffiTei(array $author, array $affiliation): void
    {
        $transformIdStructToName = Episciences_Paper_AuthorsManager::mergeAuthorInfoAndAffiTei($author[0],$affiliation[0]);
        self::assertIsArray($transformIdStructToName);
        self::assertCount(6,$transformIdStructToName);
        self::assertArrayNotHasKey('affiliations',$transformIdStructToName[2]);
        self::assertEquals('Littoral, Environnement, Télédétection, Géomatique',$transformIdStructToName[0]['affiliations'][0]['name']);
        self::assertArrayHasKey('ROR',$transformIdStructToName[0]['affiliations'][0]);
        self::assertEquals('https://ror.org/00r8amq78',$transformIdStructToName[0]['affiliations'][0]['ROR']);
    }

    /**
     * Merge both Array
     * @dataProvider sampleArrayTeiAndDB
     * @param array $authorDb
     * @param array $arrayTeiAuthor
     * @return void
     */
    public function testMergeTeiAndDB(array $authorDb, array $arrayTeiAuthor): void {
        $mergingArray = Episciences_Paper_AuthorsManager::mergeInfoDbAndInfoTei($authorDb,$arrayTeiAuthor);
        self::assertIsArray($mergingArray);
        self::assertCount(6,$mergingArray);
        self::assertArrayHasKey('orcid',$mergingArray[0]);
        self::assertEquals('0000-0002-8029-850X',$mergingArray[0]['orcid']);
        self::assertEquals('Littoral, Environnement, Télédétection, Géomatique',$mergingArray[0]['affiliation'][0]['name']);
        self::assertArrayHasKey('id',$mergingArray[0]['affiliation'][0]);
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
        [$arrayForDB,$isNewOrcid] = Episciences_OpenAireResearchGraphTools::getOrcidApiForDb($sampleOACreatorDB[0]['fullname'],$sampleOACreatorAPI,$sampleOACreatorDB,0,0);
        self::assertIsArray($arrayForDB);
        self::assertIsInt($isNewOrcid);
        self::assertEquals(1, $isNewOrcid);
        self::assertEquals('0000-0002-5332-5437',$arrayForDB[0]['orcid']);

    }


    /**
     * @return iterable
     */
    public function halTeiProvider(): iterable
    {
        yield [simplexml_load_string('<?xml version="1.0"?>
<TEI
	xmlns="http://www.tei-c.org/ns/1.0"
	xmlns:tei="http://www.tei-c.org/ns/1.0">
	<teiHeader>
		<fileDesc>
			<titleStmt>
				<title>Search results from HAL API</title>
			</titleStmt>
			<publicationStmt>
				<distributor>CCSD</distributor>
				<availability status="restricted">
					<licence target="http://creativecommons.org/licenses/by/4.0/">Distributed under a Creative Commons Attribution 4.0 International License</licence>
				</availability>
				<date when="2022-11-21T10:00:25+01:00"/>
			</publicationStmt>
			<sourceDesc>
				<p part="N">HAL API platform</p>
			</sourceDesc>
		</fileDesc>
		<profileDesc>
			<creation>
				<measure quantity="1" unit="count" commodity="totalSearchResults"/>
				<measure quantity="1" unit="count" commodity="searchResultsInDocument"/>
				<ptr type="query" target="https://api.archives-ouvertes.fr/search/?q=((halId_s:hal-03494849%20OR%20halIdSameAs_s:hal-03494849)%20AND%20version_i:1)&amp;wt=xml-tei"/>
			</creation>
		</profileDesc>
	</teiHeader>
	<text>
		<body>
			<listBibl>
				<biblFull>
					<titleStmt>
						<title xml:lang="en">Power partitions and a generalized eta transformation property</title>
						<author role="aut">
							<persName>
								<forename type="first">Don</forename>
								<surname>Zagier</surname>
							</persName>
							<idno type="halauthorid">848818-0</idno>
							<idno type="ORCID">https://orcid.org/0000-0004-8099-840X</idno>
							<affiliation ref="#struct-426005"/>
						</author>
						<editor role="depositor">
							<persName>
								<forename>Srinivas</forename>
								<surname>Kotyada</surname>
							</persName>
							<email type="md5">fdeee94a7d0d5c041cfb7764a204a322</email>
							<email type="domain">gmail.com</email>
						</editor>
					</titleStmt>
					<editionStmt>
						<edition n="v1" type="current">
							<date type="whenSubmitted">2021-12-20 09:14:38</date>
							<date type="whenModified">2022-03-28 08:14:08</date>
							<date type="whenReleased">2022-01-06 09:05:04</date>
							<date type="whenProduced">2022-01-09</date>
							<date type="whenEndEmbargoed">2021-12-20</date>
							<ref type="file" target="https://hal.archives-ouvertes.fr/hal-03494849/document">
								<date notBefore="2021-12-20"/>
							</ref>
							<ref type="file" subtype="greenPublisher" n="1" target="https://hal.archives-ouvertes.fr/hal-03494849/file/44Article01.pdf">
								<date notBefore="2021-12-20"/>
							</ref>
						</edition>
						<respStmt>
							<resp>contributor</resp>
							<name key="432592">
								<persName>
									<forename>Srinivas</forename>
									<surname>Kotyada</surname>
								</persName>
								<email type="md5">fdeee94a7d0d5c041cfb7764a204a322</email>
								<email type="domain">gmail.com</email>
							</name>
						</respStmt>
					</editionStmt>
					<publicationStmt>
						<distributor>CCSD</distributor>
						<idno type="halId">hal-03494849</idno>
						<idno type="halUri">https://hal.archives-ouvertes.fr/hal-03494849</idno>
						<idno type="halBibtex">zagier:hal-03494849</idno>
						<idno type="halRefHtml">&lt;i&gt;Hardy-Ramanujan Journal&lt;/i&gt;, Hardy-Ramanujan Society, 2022, Special commemorative volume in honour of Srinivasa Ramanujan - 2021, Volume 44 - Special Commemorative volume in honour of Srinivasa Ramanujan - 2021, pp.1 -- 18. &lt;a target="_blank" href="https://dx.doi.org/10.46298/hrj.2022.8932"&gt;&amp;#x27E8;10.46298/hrj.2022.8932&amp;#x27E9;&lt;/a&gt;</idno>
						<idno type="halRef">Hardy-Ramanujan Journal, Hardy-Ramanujan Society, 2022, Special commemorative volume in honour of Srinivasa Ramanujan - 2021, Volume 44 - Special Commemorative volume in honour of Srinivasa Ramanujan - 2021, pp.1 -- 18. &amp;#x27E8;10.46298/hrj.2022.8932&amp;#x27E9;</idno>
					</publicationStmt>
					<seriesStmt>
						<idno type="stamp" n="INSMI">CNRS-INSMI - INstitut des Sciences Mathématiques et de leurs Interactions</idno>
					</seriesStmt>
					<notesStmt>
						<note type="audience" n="2">International</note>
						<note type="popular" n="0">No</note>
						<note type="peer" n="1">Yes</note>
					</notesStmt>
					<sourceDesc>
						<biblStruct>
							<analytic>
								<title xml:lang="en">Power partitions and a generalized eta transformation property</title>
								<author role="aut">
									<persName>
										<forename type="first">Don</forename>
										<surname>Zagier</surname>
									</persName>
									<idno type="halauthorid">848818-0</idno>
									<affiliation ref="#struct-426005"/>
								</author>
							</analytic>
							<monogr>
								<idno type="halJournalId" status="VALID">54861</idno>
								<idno type="eissn">2804-7370</idno>
								<title level="j">Hardy-Ramanujan Journal</title>
								<imprint>
									<publisher>Hardy-Ramanujan Society</publisher>
									<biblScope unit="serie">Special commemorative volume in honour of Srinivasa Ramanujan - 2021</biblScope>
									<biblScope unit="volume">Volume 44 - Special Commemorative volume in honour of Srinivasa Ramanujan - 2021</biblScope>
									<biblScope unit="pp">1 -- 18</biblScope>
									<date type="datePub">2022-01-09</date>
								</imprint>
							</monogr>
							<idno type="doi">10.46298/hrj.2022.8932</idno>
						</biblStruct>
					</sourceDesc>
					<profileDesc>
						<langUsage>
							<language ident="en">English</language>
						</langUsage>
						<textClass>
							<keywords scheme="author">
								<term xml:lang="en">partitions into powers</term>
								<term xml:lang="en">Hardy-Ramanujan partition formula</term>
								<term xml:lang="en">circle method</term>
							</keywords>
							<classCode scheme="classification">05A18;11P82</classCode>
							<classCode scheme="halDomain" n="math">Mathematics [math]</classCode>
							<classCode scheme="halTypology" n="ART">Journal articles</classCode>
							<classCode scheme="halOldTypology" n="ART">Journal articles</classCode>
							<classCode scheme="halTreeTypology" n="ART">Journal articles</classCode>
						</textClass>
						<abstract xml:lang="en">
							<p>In their famous paper on partitions, Hardy and Ramanujan also raised the question of the behaviour of the number $p_s(n)$ of partitions of a positive integer~$n$ into $s$-th powers and gave some preliminary results. We give first an asymptotic formula to all orders, and then an exact formula, describing the behaviour of the corresponding generating function $P_s(q) = \prod_{n=1}^\infty \bigl(1-q^{n^s}\bigr)^{-1}$ near any root of unity, generalizing the modular transformation behaviour of the Dedekind eta-function in the case $s=1$.  This is then combined with the Hardy-Ramanujan circle method to give a rather precise formula for $p_s(n)$ of the same general type of the one that they gave for~$s=1$.  There are several new features, the most striking being that the contributions coming from various roots of unity behave very erratically rather than decreasing uniformly as in their situation.  Thus in their famous calculation of $p(200)$ the contributions from arcs of the circle near roots of unity of order 1, 2, 3, 4 and 5 have 13, 5, 2, 1 and 1 digits, respectively, but in the corresponding calculation for $p_2(100000)$ these contributions have 60, 27, 4, 33, and 16  digits, respectively, of wildly varying sizes</p>
						</abstract>
					</profileDesc>
				</biblFull>
			</listBibl>
		</body>
		<back>
			<listOrg type="structures">
				<org type="laboratory" xml:id="struct-426005" status="VALID">
					<orgName>Max Planck Institute for Mathematics</orgName>
					<orgName type="acronym">MPIM</orgName>
					<desc>
						<address>
							<addrLine>Vivatsgasse 7, 53111 Bonn, Germany</addrLine>
							<country key="DE"/>
						</address>
						<ref type="url">https://www.mpim-bonn.mpg.de/</ref>
					</desc>
					<listRelation>
						<relation active="#struct-5247" type="direct"/>
					</listRelation>
				</org>
				<org type="institution" xml:id="struct-5247" status="VALID">
					<orgName>Max-Planck-Gesellschaft</orgName>
					<desc>
						<address>
							<addrLine>Jägerstrasse, 10-11, D-10117 Berlin</addrLine>
							<country key="DE"/>
						</address>
						<ref type="url">https://www.mpg.de/en</ref>
					</desc>
				</org>
			</listOrg>
		</back>
	</text>
</TEI>')];
    }

    public function halTeiEmptyProvider(): iterable
    {
        yield [simplexml_load_string('<?xml version="1.0"?>
<TEI
	xmlns="http://www.tei-c.org/ns/1.0"
	xmlns:tei="http://www.tei-c.org/ns/1.0">
	<teiHeader>
		<fileDesc>
			<titleStmt>
				<title>Search results from HAL API</title>
			</titleStmt>
			<publicationStmt>
				<distributor>CCSD</distributor>
				<availability status="restricted">
					<licence target="http://creativecommons.org/licenses/by/4.0/">Distributed under a Creative Commons Attribution 4.0 International License</licence>
				</availability>
				<date when="2022-11-21T10:00:25+01:00"/>
			</publicationStmt>
			<sourceDesc>
				<p part="N">HAL API platform</p>
			</sourceDesc>
		</fileDesc>
		<profileDesc>
			<creation>
				<measure quantity="1" unit="count" commodity="totalSearchResults"/>
				<measure quantity="1" unit="count" commodity="searchResultsInDocument"/>
				<ptr type="query" target="https://api.archives-ouvertes.fr/search/?q=((halId_s:hal-03494849%20OR%20halIdSameAs_s:hal-03494849)%20AND%20version_i:1)&amp;wt=xml-tei"/>
			</creation>
		</profileDesc>
	</teiHeader>
</TEI>')];
    }

    public function halTeiEmptyAuthorsProvider(): iterable
    {
        yield [simplexml_load_string('<?xml version="1.0"?>
<TEI
    xmlns="http://www.tei-c.org/ns/1.0"
    xmlns:tei="http://www.tei-c.org/ns/1.0">
    <teiHeader>
        <fileDesc>
            <titleStmt>
                <title>Search results from HAL API</title>
            </titleStmt>
            <publicationStmt>
                <distributor>CCSD</distributor>
                <availability status="restricted">
                    <licence target="http://creativecommons.org/licenses/by/4.0/">Distributed under a Creative Commons Attribution 4.0 International License</licence>
                </availability>
                <date when="2022-11-24T11:42:12+01:00"/>
            </publicationStmt>
            <sourceDesc>
                <p part="N">HAL API platform</p>
            </sourceDesc>
        </fileDesc>
        <profileDesc>
            <creation>
                <measure quantity="1" unit="count" commodity="totalSearchResults"/>
                <measure quantity="1" unit="count" commodity="searchResultsInDocument"/>
                <ptr type="query" target="https://api.archives-ouvertes.fr/search/?q=((halId_s:hal-02905692%20OR%20halIdSameAs_s:hal-02905692)%20AND%20version_i:1)&amp;wt=xml-tei"/>
            </creation>
        </profileDesc>
    </teiHeader>
    <text>
        <body>
        <listBibl>
            <biblFull>
                <titleStmt>
                    <title xml:lang="fr">Vers une démarche scientifique intégrative : l\'exemple de l\'Observatoire Hommes-milieux du Nunavik (Canada)</title>
                    <editor role="depositor">
                        <persName>
                            <forename>Armelle</forename>
                            <surname>Decaulne</surname>
                        </persName>
                        <email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
                        <email type="domain">univ-nantes.fr</email>
                    </editor>
                    <funder ref="#projanr-23477"/>
                </titleStmt>
                <editionStmt>
                    <edition n="v1" type="current">
                        <date type="whenSubmitted">2020-07-23 16:05:59</date>
                        <date type="whenModified">2022-11-14 03:00:08</date>
                        <date type="whenReleased">2020-07-24 09:26:51</date>
                        <date type="whenProduced">2020-07-26</date>
                        <date type="whenEndEmbargoed">2020-07-23</date>
                        <ref type="file" target="https://hal.archives-ouvertes.fr/hal-02905692/document">
                            <date notBefore="2020-07-23"/>
                        </ref>
                        <ref type="file" subtype="author" n="1" target="https://hal.archives-ouvertes.fr/hal-02905692/file/Vol6_art5_Armelle-Decaulne.pdf">
                            <date notBefore="2020-07-23"/>
                        </ref>
                    </edition>
                    <respStmt>
                        <resp>contributor</resp>
                        <name key="126900">
                            <persName>
                                <forename>Armelle</forename>
                                <surname>Decaulne</surname>
                            </persName>
                            <email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
                            <email type="domain">univ-nantes.fr</email>
                        </name>
                    </respStmt>
                </editionStmt>
                <publicationStmt>
                    <distributor>CCSD</distributor>
                    <idno type="halId">hal-02905692</idno>
                    <idno type="halUri">https://hal.archives-ouvertes.fr/hal-02905692</idno>
                    <idno type="halBibtex">decaulne:hal-02905692</idno>
                    <idno type="halRefHtml">&lt;i&gt;Journal of Interdisciplinary Methodologies and Issues in Science&lt;/i&gt;, Journal of Interdisciplinary Methodologies and Issues in Science, 2020, Scientific observatories Environments/Societies, new challenges, &lt;a target="_blank" href="https://dx.doi.org/10.18713/JIMIS-120620-6-5"&gt;&amp;#x27E8;10.18713/JIMIS-120620-6-5&amp;#x27E9;&lt;/a&gt;</idno>
                    <idno type="halRef">Journal of Interdisciplinary Methodologies and Issues in Science, Journal of Interdisciplinary Methodologies and Issues in Science, 2020, Scientific observatories Environments/Societies, new challenges, &amp;#x27E8;10.18713/JIMIS-120620-6-5&amp;#x27E9;</idno>
                </publicationStmt>
                <seriesStmt>
                    <idno type="stamp" n="UNIV-BREST">Université de Bretagne occidentale - Brest (UBO)</idno>
                    <idno type="stamp" n="UNIV-NANTES">Université de Nantes</idno>
                    <idno type="stamp" n="EPHE">Ecole Pratique des Hautes Etudes</idno>
                    <idno type="stamp" n="UR2-HB">Université Rennes 2 - Haute Bretagne</idno>
                    <idno type="stamp" n="CNRS">CNRS - Centre national de la recherche scientifique</idno>
                    <idno type="stamp" n="UNIV-PAU">Université de Pau et des Pays de l\'Adour - E2S UPPA</idno>
                    <idno type="stamp" n="UNIV-ANGERS">Université d\'Angers</idno>
                    <idno type="stamp" n="UNIV-LEMANS">Université du Mans</idno>
                    <idno type="stamp" n="LETG" corresp="UNIV-BREST">Littoral, Environnement, Télédétection, Géomatique</idno>
                    <idno type="stamp" n="LETG-GEOLITTOMER" corresp="LETG">LETG-Nantes</idno>
                    <idno type="stamp" n="GIP-BE">GIP Bretagne Environnement</idno>
                    <idno type="stamp" n="UNAM">l\'unam - université nantes angers le mans</idno>
                    <idno type="stamp" n="COMUE-NORMANDIE">Normandie Université</idno>
                    <idno type="stamp" n="PSL">Université Paris sciences et lettres</idno>
                    <idno type="stamp" n="AGREENIUM">Archive ouverte en agrobiosciences</idno>
                    <idno type="stamp" n="UNIV-RENNES2">Université Rennes 2</idno>
                    <idno type="stamp" n="ESO-ANGERS" corresp="UNIV-ANGERS">Espaces et Sociétés - Angers</idno>
                    <idno type="stamp" n="LABEX-DRIIHM">Laboratoire d\'Excellence Dispositif de Recherche Interdisciplinaire sur les Interactions Hommes-Milieux</idno>
                    <idno type="stamp" n="OHMI-NUNAVIK">Observatoire Hommes-Milieux international Nunavik</idno>
                    <idno type="stamp" n="UNIV-RENNES">Université de Rennes</idno>
                    <idno type="stamp" n="UNICAEN">Université de Caen Normandie</idno>
                    <idno type="stamp" n="LATEP" corresp="UNIV-PAU">Laboratoire de Thermique, Energétique et Procédés - IPRA</idno>
                    <idno type="stamp" n="TEST-DEV">TEST-DEV</idno>
                    <idno type="stamp" n="IGARUN" corresp="UNIV-NANTES">Institut de Géographie et d\'Aménagement de l\'Université de Nantes</idno>
                    <idno type="stamp" n="TEST-HALCNRS">Collection test HAL CNRS</idno>
                    <idno type="stamp" n="EPHE-PSL" corresp="PSL">École pratique des hautes études - PSL</idno>
                    <idno type="stamp" n="ANR">ANR</idno>
                    <idno type="stamp" n="UPPA-OA" corresp="UNIV-PAU">uppa-oa</idno>
                    <idno type="stamp" n="NANTES-UNIVERSITE">Nantes Université</idno>
                    <idno type="stamp" n="UNIV-NANTES-AV2022" corresp="NANTES-UNIVERSITE">Université de Nantes</idno>
                    <idno type="stamp" n="INSTITUT-AGRO-RENNES-ANGERS-UMR-ESO">Institut Agro Rennes-Angers - UMR ESO</idno>
                    <idno type="stamp" n="INSTITUT-AGRO-RENNES-ANGERS">Institut Agro Rennes-Angers</idno>
                    <idno type="stamp" n="INSTITUT-AGRO-ESO" corresp="INSTITUT-AGRO-RENNES-ANGERS">Institut Agro - ESO</idno>
                </seriesStmt>
                <notesStmt>
                    <note type="audience" n="2">International</note>
                    <note type="popular" n="0">No</note>
                    <note type="peer" n="1">Yes</note>
                </notesStmt>
                <sourceDesc>
                    <biblStruct>
                        <analytic>
                            <title xml:lang="fr">Vers une démarche scientifique intégrative : l\'exemple de l\'Observatoire Hommes-milieux du Nunavik (Canada)</title>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Armelle</forename>
                                    <surname>Decaulne</surname>
                                </persName>
                                <email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
                                <email type="domain">univ-nantes.fr</email>
                                <idno type="idhal" notation="string">armelle-decaulne</idno>
                                <idno type="idhal" notation="numeric">179996</idno>
                                <idno type="halauthorid" notation="string">17906-179996</idno>
                                <idno type="ORCID">https://orcid.org/0000-0002-8029-850X</idno>
                                <affiliation ref="#struct-2267"/>
                            </author>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Fabienne</forename>
                                    <surname>Joliet</surname>
                                </persName>
                                <email type="md5">612010d12abcc156e4835057dcaab522</email>
                                <email type="domain">agrocampus-ouest.fr</email>
                                <idno type="idhal" notation="numeric">932715</idno>
                                <idno type="halauthorid" notation="string">672149-932715</idno>
                                <idno type="ORCID">https://orcid.org/0000-0002-4707-3229</idno>
                                <orgName ref="#struct-108028"/>
                                <affiliation ref="#struct-59373"/>
                            </author>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Laine</forename>
                                    <surname>Chanteloup</surname>
                                </persName>
                                <email type="md5">d467ca8cd1f319ca1ec3b0df8a209c8f</email>
                                <email type="domain">univ-savoie.fr</email>
                                <idno type="idhal" notation="numeric">869121</idno>
                                <idno type="halauthorid" notation="string">34786-869121</idno>
                                <orgName ref="#struct-97916"/>
                            </author>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Thora</forename>
                                    <surname>Herrmann</surname>
                                </persName>
                                <email type="md5">5b91c50bc8713719c2d89c9b22c6b935</email>
                                <email type="domain">umontreal.ca</email>
                                <idno type="idhal" notation="numeric">1025242</idno>
                                <idno type="halauthorid" notation="string">1282105-1025242</idno>
                                <affiliation ref="#struct-60508"/>
                            </author>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Najat</forename>
                                    <surname>Bhiry</surname>
                                </persName>
                                <email type="md5">e531c02ba9cacf12d72fdd02a8e3648e</email>
                                <email type="domain">cen.ulaval.ca</email>
                                <idno type="idhal" notation="numeric">988112</idno>
                                <idno type="halauthorid" notation="string">910200-988112</idno>
                                <affiliation ref="#struct-194108"/>
                            </author>
                            <author role="aut">
                                <persName>
                                    <forename type="first">Didier</forename>
                                    <surname>Haillot</surname>
                                </persName>
                                <email type="md5">5e87eb249a6920d688f25ffd230d0b75</email>
                                <email type="domain">univ-pau.fr</email>
                                <idno type="idhal" notation="string">didier-haillot</idno>
                                <idno type="idhal" notation="numeric">174380</idno>
                                <idno type="halauthorid" notation="string">18533-174380</idno>
                                <idno type="IDREF">https://www.idref.fr/150128738</idno>
                                <idno type="ORCID">https://orcid.org/0000-0001-8676-034X</idno>
                                <orgName ref="#struct-301085"/>
                                <affiliation ref="#struct-33789"/>
                            </author>
                        </analytic>
                        <monogr>
                            <idno type="halJournalId" status="VALID">108022</idno>
                            <idno type="eissn">2430-3038</idno>
                            <title level="j">Journal of Interdisciplinary Methodologies and Issues in Science</title>
                            <imprint>
                                <publisher>Journal of Interdisciplinary Methodologies and Issues in Science</publisher>
                                <biblScope unit="volume">Scientific observatories Environments/Societies, new challenges</biblScope>
                                <date type="datePub">2020-07-26</date>
                                <date type="dateEpub">2020-07</date>
                            </imprint>
                        </monogr>
                        <idno type="doi">10.18713/JIMIS-120620-6-5</idno>
                    </biblStruct>
                </sourceDesc>
                <profileDesc>
                    <langUsage>
                        <language ident="en">English</language>
                    </langUsage>
                    <textClass>
                        <keywords scheme="author">
                            <term xml:lang="en">OHMi Nunavik</term>
                        </keywords>
                        <classCode scheme="halDomain" n="sdu.stu.gm">Sciences of the Universe [physics]/Earth Sciences/Geomorphology</classCode>
                        <classCode scheme="halTypology" n="ART">Journal articles</classCode>
                        <classCode scheme="halOldTypology" n="ART">Journal articles</classCode>
                        <classCode scheme="halTreeTypology" n="ART">Journal articles</classCode>
                    </textClass>
                    <abstract xml:lang="en">
                        <p>Les Observatoires Hommes-Milieux (OHM, dispositifs du LabEx DRIIHM créés à l’initiative duCNRS) étudient les interrelations société–environnement interrogées suite à un événementfondateur anthropique qui a produit une réorganisation de l’ensemble du socio-écosystème initial.Cet article s’intéresse à la mise en oeuvre, aux rôles et fonctionnement d’un de ces OHM, l’OHMiNUNAVIK, développé depuis 2014 en contexte autochtone dans l’Arctique canadien au Nord duQuébec. L’OHMi NUNAVIK tend à développer un cadre de recherche holistique et intégrateurfaisant tomber les barrières entre disciplines d’une part, et types de savoirs (scientifiques /autochtones) d’autre part. Il vise ainsi à créer une structure de recherche innovante en Arctiquepar le prisme (i) des représentations arctiques des Inuits et des Qallunaat, (ii) les collaborationsentre chercheurs, gestionnaires territoriaux et habitants. L’objectif de l’OHMi NUNAVIK portesur l’acquisition de connaissances et de nouvelles manières de les produire afin d’identifier lesimpacts cumulatifs des programmes de développements économiques du Nunavik et des PlansNord successifs du Québec depuis 2011, et des changements socio-environnementaux globauxsur cette aire d’étude. Au total, sept projets de recherche se déploient au sein de l’OHMiNUNAVIK ; quatre d’entre eux (Nuna, Kingaq, Niqiliriniq et Siqiniq) sont ici mobilisés pourillustrer le fonctionnement de l’OHMi NUNAVIK, les techniques d’observations utilisées, lescollaborations produites.</p>
                    </abstract>
                </profileDesc>
            </biblFull>
        </listBibl>
        </body>
        <back>
            <listOrg type="structures">
                <org type="researchteam" xml:id="struct-2267" status="OLD">
                    <idno type="IdRef">221296700</idno>
                    <orgName>Littoral, Environnement, Télédétection, Géomatique</orgName>
                    <orgName type="acronym">LETG - Nantes</orgName>
                    <date type="end">2021-12-20</date>
                    <desc>
                        <address>
                            <addrLine> Institut de Géographie et d\'Aménagement de l\'Université de Nantes Campus du Tertre BP 8122 44312 NANTES CEDEX 3</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://letg.cnrs.fr</ref>
                    </desc>
                    <listRelation>
                        <relation active="#struct-7127" type="direct"/>
                        <relation active="#struct-455934" type="indirect"/>
                        <relation active="#struct-110691" type="direct"/>
                        <relation active="#struct-564132" type="indirect"/>
                        <relation active="#struct-300314" type="direct"/>
                        <relation active="#struct-406201" type="direct"/>
                        <relation active="#struct-528860" type="indirect"/>
                        <relation name="UMR6554" active="#struct-441569" type="direct"/>
                        <relation active="#struct-530572" type="direct"/>
                        <relation name="93263" active="#struct-93263" type="indirect"/>
                    </listRelation>
                </org>
                <org type="laboratory" xml:id="struct-59373" status="OLD">
                    <orgName>Espaces et Sociétés</orgName>
                    <orgName type="acronym">ESO</orgName>
                    <date type="start">1996-01-01</date>
                    <date type="end">2021-12-31</date>
                    <desc>
                        <address>
                            <country key="FR"/>
                        </address>
                    </desc>
                    <listRelation>
                        <relation active="#struct-7127" type="direct"/>
                        <relation active="#struct-455934" type="indirect"/>
                        <relation active="#struct-7566" type="direct"/>
                        <relation active="#struct-74911" type="direct"/>
                        <relation active="#struct-108028" type="direct"/>
                        <relation active="#struct-406201" type="direct"/>
                        <relation active="#struct-528860" type="indirect"/>
                        <relation active="#struct-441569" type="direct"/>
                        <relation active="#struct-530572" type="direct"/>
                        <relation name="93263" active="#struct-93263" type="indirect"/>
                    </listRelation>
                </org>
                <org type="laboratory" xml:id="struct-60508" status="INCOMING">
                    <orgName>Département de géographie</orgName>
                    <desc>
                        <address>
                            <addrLine>Montréal</addrLine>
                            <country key="CA"/>
                        </address>
                    </desc>
                    <listRelation>
                        <relation active="#struct-302452" type="direct"/>
                    </listRelation>
                </org>
                <org type="laboratory" xml:id="struct-194108" status="INCOMING">
                    <orgName>Centre d\'études nordiques et Département de Géographie</orgName>
                    <desc>
                        <address>
                            <addrLine>Québec</addrLine>
                            <country key="CA"/>
                        </address>
                    </desc>
                    <listRelation>
                        <relation active="#struct-93488" type="direct"/>
                    </listRelation>
                </org>
                <org type="laboratory" xml:id="struct-33789" status="VALID">
                    <idno type="IdRef">227249224</idno>
                    <idno type="RNSR">199513639B</idno>
                    <orgName>Laboratoire de Génie Thermique Énergétique et Procédés (EA1932)</orgName>
                    <orgName type="acronym">LATEP</orgName>
                    <date type="start">1995-01-01</date>
                    <desc>
                        <address>
                            <addrLine>Université de Pau et des Pays de l\'Adour BP 1155 64013 PAU</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.univ-pau.fr/latep</ref>
                    </desc>
                    <listRelation>
                        <relation active="#struct-301085" type="direct"/>
                    </listRelation>
                </org>
                <org type="institution" xml:id="struct-7127" status="VALID">
                    <idno type="IdRef">026403064</idno>
                    <idno type="ISNI">0000000121864076</idno>
                    <idno type="ROR">051kpcy16</idno>
                    <orgName>Université de Caen Normandie</orgName>
                    <orgName type="acronym">UNICAEN</orgName>
                    <date type="start">1432-01-01</date>
                    <desc>
                        <address>
                            <addrLine>Esplanade de la Paix - CS 14032 - 14032 CAEN Cedex 5</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.unicaen.fr/</ref>
                    </desc>
                    <listRelation>
                        <relation active="#struct-455934" type="direct"/>
                    </listRelation>
                </org>
                <org type="regroupinstitution" xml:id="struct-455934" status="VALID">
                    <idno type="IdRef">190906332</idno>
                    <idno type="ISNI">0000000417859671 </idno>
                    <idno type="ROR">01k40cz91</idno>
                    <orgName>Normandie Université</orgName>
                    <orgName type="acronym">NU</orgName>
                    <date type="start">2015-01-01</date>
                    <desc>
                        <address>
                            <addrLine>Esplanade de la Paix - CS 14032 - 14032 Caen Cedex 5</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.normandie-univ.fr/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-110691" status="VALID">
                    <idno type="IdRef">026375478</idno>
                    <idno type="ISNI">0000000121955365</idno>
                    <idno type="ROR">046b3cj80</idno>
                    <orgName>École pratique des hautes études</orgName>
                    <orgName type="acronym">EPHE</orgName>
                    <desc>
                        <address>
                            <addrLine>4-14 Rue Ferrus, 75014 Paris</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.ephe.fr</ref>
                    </desc>
                    <listRelation>
                        <relation active="#struct-564132" type="direct"/>
                    </listRelation>
                </org>
                <org type="regroupinstitution" xml:id="struct-564132" status="VALID">
                    <idno type="IdRef">241597595</idno>
                    <idno type="ISNI">0000 0004 1784 3645</idno>
                    <orgName>Université Paris sciences et lettres</orgName>
                    <orgName type="acronym">PSL</orgName>
                    <desc>
                        <address>
                            <addrLine>60 rue Mazarine75006 Paris</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">https://www.psl.eu/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-300314" status="VALID">
                    <idno type="ROR">https://ror.org/01b8h3982</idno>
                    <orgName>Université de Brest</orgName>
                    <orgName type="acronym">UBO</orgName>
                    <desc>
                        <address>
                            <addrLine>Université de Bretagne Occidentale - 3 Rue des Archives 29238, Brest</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">https://www.univ-brest.fr/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-406201" status="VALID">
                    <idno type="IdRef">054447658</idno>
                    <idno type="ISNI">0000000121522279</idno>
                    <idno type="ROR">01m84wm78</idno>
                    <orgName>Université de Rennes 2</orgName>
                    <orgName type="acronym">UR2</orgName>
                    <desc>
                        <address>
                            <addrLine>Place du recteur Henri Le Moal - CS 24307 - 35043 Rennes cedex</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.univ-rennes2.fr/</ref>
                    </desc>
                    <listRelation>
                        <relation active="#struct-528860" type="direct"/>
                    </listRelation>
                </org>
                <org type="regroupinstitution" xml:id="struct-528860" status="VALID">
                    <orgName>Université de Rennes</orgName>
                    <orgName type="acronym">UNIV-RENNES</orgName>
                    <date type="start">2018-01-01</date>
                    <desc>
                        <address>
                            <country key="FR"/>
                        </address>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-441569" status="VALID">
                    <idno type="IdRef">02636817X</idno>
                    <idno type="ISNI">0000000122597504</idno>
                    <orgName>Centre National de la Recherche Scientifique</orgName>
                    <orgName type="acronym">CNRS</orgName>
                    <date type="start">1939-10-19</date>
                    <desc>
                        <address>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.cnrs.fr/</ref>
                    </desc>
                </org>
                <org type="regrouplaboratory" xml:id="struct-530572" status="OLD">
                    <idno type="IdRef">026568586</idno>
                    <orgName>Institut de Géographie et d\'Aménagement Régional de l\'Université de Nantes</orgName>
                    <orgName type="acronym">IGARUN</orgName>
                    <date type="end">2021-12-31</date>
                    <desc>
                        <address>
                            <addrLine>Campus du Tertre, BP 81 22744312 Nantes cedex 3</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.igarun.univ-nantes.fr</ref>
                    </desc>
                    <listRelation>
                        <relation name="93263" active="#struct-93263" type="direct"/>
                    </listRelation>
                </org>
                <org type="institution" xml:id="struct-93263" status="OLD">
                    <idno type="IdRef">026403447</idno>
                    <orgName>Université de Nantes</orgName>
                    <orgName type="acronym">UN</orgName>
                    <date type="end">2021-12-31</date>
                    <desc>
                        <address>
                            <addrLine>1, quai de Tourville - BP 13522 - 44035 Nantes cedex 1</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.univ-nantes.fr/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-7566" status="VALID">
                    <orgName>Le Mans Université</orgName>
                    <orgName type="acronym">UM</orgName>
                    <desc>
                        <address>
                            <addrLine>Avenue Olivier Messiaen - 72085 Le Mans cedex 9</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.univ-lemans.fr/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-74911" status="VALID">
                    <idno type="IdRef">026402920</idno>
                    <idno type="ISNI">0000000122483363</idno>
                    <idno type="ROR">04yrqp957</idno>
                    <orgName>Université d\'Angers</orgName>
                    <orgName type="acronym">UA</orgName>
                    <date type="start">1971-10-25</date>
                    <desc>
                        <address>
                            <addrLine>Université d\'Angers - 40 Rue de Rennes, BP 73532 - 49035 Angers CEDEX 01</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.univ-angers.fr/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-108028" status="OLD">
                    <orgName>AGROCAMPUS OUEST</orgName>
                    <date type="start">2008-01-01</date>
                    <date type="end">2019-12-31</date>
                    <desc>
                        <address>
                            <addrLine>Institut Supérieur des Sciences Agronomiques, Agroalimentaires, Horticoles et du Paysage - 65, rue de St Brieuc - CS 84215 - 35042 Rennes cedex</addrLine>
                            <country key="FR"/>
                        </address>
                        <ref type="url">http://www.agrocampus-ouest.fr/</ref>
                    </desc>
                </org>
                <org type="regroupinstitution" xml:id="struct-302452" status="VALID">
                    <orgName>Université de Montréal</orgName>
                    <orgName type="acronym">UdeM</orgName>
                    <desc>
                        <address>
                            <addrLine>2900 Boulevard Edouard-Montpetit, Montréal, QC H3T 1J4</addrLine>
                            <country key="CA"/>
                        </address>
                        <ref type="url">https://www.umontreal.ca/</ref>
                    </desc>
                </org>
                <org type="regroupinstitution" xml:id="struct-93488" status="VALID">
                    <orgName>Université Laval [Québec]</orgName>
                    <orgName type="acronym">ULaval</orgName>
                    <desc>
                        <address>
                            <addrLine>2325, rue de l\'Université Québec G1V 0A6</addrLine>
                            <country key="CA"/>
                        </address>
                        <ref type="url">https://www.ulaval.ca/</ref>
                    </desc>
                </org>
                <org type="institution" xml:id="struct-301085" status="VALID">
                    <orgName>Université de Pau et des Pays de l\'Adour</orgName>
                    <orgName type="acronym">UPPA</orgName>
                    <date type="start">1970-12-17</date>
                    <desc>
                        <address>
                            <addrLine>Avenue de l\'Université - BP 576 - 64012 Pau Cedex </addrLine>
                            <country key="FR"/>
                        </address>
                    </desc>
                </org>
            </listOrg>
            <listOrg type="projects">
                <org type="anrProject" xml:id="projanr-23477" status="VALID">
                    <idno type="anr">ANR-11-LABX-0010</idno>
                    <orgName>DRIIHM / IRDHEI</orgName>
                    <desc>Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux</desc>
                    <date type="start">2011</date>
                </org>
            </listOrg>
        </back>
    </text>
</TEI>')];
    }

    public function halTeiWithSeveralsAuthorsProvider(): iterable
    {
        yield [simplexml_load_string('<?xml version="1.0"?>
<TEI
	xmlns="http://www.tei-c.org/ns/1.0"
	xmlns:tei="http://www.tei-c.org/ns/1.0">
	<teiHeader>
		<fileDesc>
			<titleStmt>
				<title>Search results from HAL API</title>
			</titleStmt>
			<publicationStmt>
				<distributor>CCSD</distributor>
				<availability status="restricted">
					<licence target="http://creativecommons.org/licenses/by/4.0/">Distributed under a Creative Commons Attribution 4.0 International License</licence>
				</availability>
				<date when="2022-11-24T11:42:12+01:00"/>
			</publicationStmt>
			<sourceDesc>
				<p part="N">HAL API platform</p>
			</sourceDesc>
		</fileDesc>
		<profileDesc>
			<creation>
				<measure quantity="1" unit="count" commodity="totalSearchResults"/>
				<measure quantity="1" unit="count" commodity="searchResultsInDocument"/>
				<ptr type="query" target="https://api.archives-ouvertes.fr/search/?q=((halId_s:hal-02905692%20OR%20halIdSameAs_s:hal-02905692)%20AND%20version_i:1)&amp;wt=xml-tei"/>
			</creation>
		</profileDesc>
	</teiHeader>
	<text>
		<body>
			<listBibl>
				<biblFull>
					<titleStmt>
						<title xml:lang="fr">Vers une démarche scientifique intégrative : l\'exemple de l\'Observatoire Hommes-milieux du Nunavik (Canada)</title>
						<author role="aut">
							<persName>
								<forename type="first">Armelle</forename>
								<surname>Decaulne</surname>
							</persName>
							<email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
							<email type="domain">univ-nantes.fr</email>
							<idno type="idhal" notation="string">armelle-decaulne</idno>
							<idno type="idhal" notation="numeric">179996</idno>
							<idno type="halauthorid" notation="string">17906-179996</idno>
							<idno type="ORCID">https://orcid.org/0000-0002-8029-850X</idno>
							<affiliation ref="#struct-2267"/>
						</author>
						<author role="aut">
							<persName>
								<forename type="first">Fabienne</forename>
								<surname>Joliet</surname>
							</persName>
							<email type="md5">612010d12abcc156e4835057dcaab522</email>
							<email type="domain">agrocampus-ouest.fr</email>
							<idno type="idhal" notation="numeric">932715</idno>
							<idno type="halauthorid" notation="string">672149-932715</idno>
							<idno type="ORCID">https://orcid.org/0000-0002-4707-3229</idno>
							<orgName ref="#struct-108028"/>
							<affiliation ref="#struct-59373"/>
						</author>
						<author role="aut">
							<persName>
								<forename type="first">Laine</forename>
								<surname>Chanteloup</surname>
							</persName>
							<email type="md5">d467ca8cd1f319ca1ec3b0df8a209c8f</email>
							<email type="domain">univ-savoie.fr</email>
							<idno type="idhal" notation="numeric">869121</idno>
							<idno type="halauthorid" notation="string">34786-869121</idno>
							<orgName ref="#struct-97916"/>
						</author>
						<author role="aut">
							<persName>
								<forename type="first">Thora</forename>
								<surname>Herrmann</surname>
							</persName>
							<email type="md5">5b91c50bc8713719c2d89c9b22c6b935</email>
							<email type="domain">umontreal.ca</email>
							<idno type="idhal" notation="numeric">1025242</idno>
							<idno type="halauthorid" notation="string">1282105-1025242</idno>
							<affiliation ref="#struct-60508"/>
						</author>
						<author role="aut">
							<persName>
								<forename type="first">Najat</forename>
								<surname>Bhiry</surname>
							</persName>
							<email type="md5">e531c02ba9cacf12d72fdd02a8e3648e</email>
							<email type="domain">cen.ulaval.ca</email>
							<idno type="idhal" notation="numeric">988112</idno>
							<idno type="halauthorid" notation="string">910200-988112</idno>
							<affiliation ref="#struct-194108"/>
						</author>
						<author role="aut">
							<persName>
								<forename type="first">Didier</forename>
								<surname>Haillot</surname>
							</persName>
							<email type="md5">5e87eb249a6920d688f25ffd230d0b75</email>
							<email type="domain">univ-pau.fr</email>
							<idno type="idhal" notation="string">didier-haillot</idno>
							<idno type="idhal" notation="numeric">174380</idno>
							<idno type="halauthorid" notation="string">18533-174380</idno>
							<idno type="IDREF">https://www.idref.fr/150128738</idno>
							<idno type="ORCID">https://orcid.org/0000-0001-8676-034X</idno>
							<orgName ref="#struct-301085"/>
							<affiliation ref="#struct-33789"/>
						</author>
						<editor role="depositor">
							<persName>
								<forename>Armelle</forename>
								<surname>Decaulne</surname>
							</persName>
							<email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
							<email type="domain">univ-nantes.fr</email>
						</editor>
						<funder ref="#projanr-23477"/>
					</titleStmt>
					<editionStmt>
						<edition n="v1" type="current">
							<date type="whenSubmitted">2020-07-23 16:05:59</date>
							<date type="whenModified">2022-11-14 03:00:08</date>
							<date type="whenReleased">2020-07-24 09:26:51</date>
							<date type="whenProduced">2020-07-26</date>
							<date type="whenEndEmbargoed">2020-07-23</date>
							<ref type="file" target="https://hal.archives-ouvertes.fr/hal-02905692/document">
								<date notBefore="2020-07-23"/>
							</ref>
							<ref type="file" subtype="author" n="1" target="https://hal.archives-ouvertes.fr/hal-02905692/file/Vol6_art5_Armelle-Decaulne.pdf">
								<date notBefore="2020-07-23"/>
							</ref>
						</edition>
						<respStmt>
							<resp>contributor</resp>
							<name key="126900">
								<persName>
									<forename>Armelle</forename>
									<surname>Decaulne</surname>
								</persName>
								<email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
								<email type="domain">univ-nantes.fr</email>
							</name>
						</respStmt>
					</editionStmt>
					<publicationStmt>
						<distributor>CCSD</distributor>
						<idno type="halId">hal-02905692</idno>
						<idno type="halUri">https://hal.archives-ouvertes.fr/hal-02905692</idno>
						<idno type="halBibtex">decaulne:hal-02905692</idno>
						<idno type="halRefHtml">&lt;i&gt;Journal of Interdisciplinary Methodologies and Issues in Science&lt;/i&gt;, Journal of Interdisciplinary Methodologies and Issues in Science, 2020, Scientific observatories Environments/Societies, new challenges, &lt;a target="_blank" href="https://dx.doi.org/10.18713/JIMIS-120620-6-5"&gt;&amp;#x27E8;10.18713/JIMIS-120620-6-5&amp;#x27E9;&lt;/a&gt;</idno>
						<idno type="halRef">Journal of Interdisciplinary Methodologies and Issues in Science, Journal of Interdisciplinary Methodologies and Issues in Science, 2020, Scientific observatories Environments/Societies, new challenges, &amp;#x27E8;10.18713/JIMIS-120620-6-5&amp;#x27E9;</idno>
					</publicationStmt>
					<seriesStmt>
						<idno type="stamp" n="UNIV-BREST">Université de Bretagne occidentale - Brest (UBO)</idno>
						<idno type="stamp" n="UNIV-NANTES">Université de Nantes</idno>
						<idno type="stamp" n="EPHE">Ecole Pratique des Hautes Etudes</idno>
						<idno type="stamp" n="UR2-HB">Université Rennes 2 - Haute Bretagne</idno>
						<idno type="stamp" n="CNRS">CNRS - Centre national de la recherche scientifique</idno>
						<idno type="stamp" n="UNIV-PAU">Université de Pau et des Pays de l\'Adour - E2S UPPA</idno>
						<idno type="stamp" n="UNIV-ANGERS">Université d\'Angers</idno>
						<idno type="stamp" n="UNIV-LEMANS">Université du Mans</idno>
						<idno type="stamp" n="LETG" corresp="UNIV-BREST">Littoral, Environnement, Télédétection, Géomatique</idno>
						<idno type="stamp" n="LETG-GEOLITTOMER" corresp="LETG">LETG-Nantes</idno>
						<idno type="stamp" n="GIP-BE">GIP Bretagne Environnement</idno>
						<idno type="stamp" n="UNAM">l\'unam - université nantes angers le mans</idno>
						<idno type="stamp" n="COMUE-NORMANDIE">Normandie Université</idno>
						<idno type="stamp" n="PSL">Université Paris sciences et lettres</idno>
						<idno type="stamp" n="AGREENIUM">Archive ouverte en agrobiosciences</idno>
						<idno type="stamp" n="UNIV-RENNES2">Université Rennes 2</idno>
						<idno type="stamp" n="ESO-ANGERS" corresp="UNIV-ANGERS">Espaces et Sociétés - Angers</idno>
						<idno type="stamp" n="LABEX-DRIIHM">Laboratoire d\'Excellence Dispositif de Recherche Interdisciplinaire sur les Interactions Hommes-Milieux</idno>
						<idno type="stamp" n="OHMI-NUNAVIK">Observatoire Hommes-Milieux international Nunavik</idno>
						<idno type="stamp" n="UNIV-RENNES">Université de Rennes</idno>
						<idno type="stamp" n="UNICAEN">Université de Caen Normandie</idno>
						<idno type="stamp" n="LATEP" corresp="UNIV-PAU">Laboratoire de Thermique, Energétique et Procédés - IPRA</idno>
						<idno type="stamp" n="TEST-DEV">TEST-DEV</idno>
						<idno type="stamp" n="IGARUN" corresp="UNIV-NANTES">Institut de Géographie et d\'Aménagement de l\'Université de Nantes</idno>
						<idno type="stamp" n="TEST-HALCNRS">Collection test HAL CNRS</idno>
						<idno type="stamp" n="EPHE-PSL" corresp="PSL">École pratique des hautes études - PSL</idno>
						<idno type="stamp" n="ANR">ANR</idno>
						<idno type="stamp" n="UPPA-OA" corresp="UNIV-PAU">uppa-oa</idno>
						<idno type="stamp" n="NANTES-UNIVERSITE">Nantes Université</idno>
						<idno type="stamp" n="UNIV-NANTES-AV2022" corresp="NANTES-UNIVERSITE">Université de Nantes</idno>
						<idno type="stamp" n="INSTITUT-AGRO-RENNES-ANGERS-UMR-ESO">Institut Agro Rennes-Angers - UMR ESO</idno>
						<idno type="stamp" n="INSTITUT-AGRO-RENNES-ANGERS">Institut Agro Rennes-Angers</idno>
						<idno type="stamp" n="INSTITUT-AGRO-ESO" corresp="INSTITUT-AGRO-RENNES-ANGERS">Institut Agro - ESO</idno>
					</seriesStmt>
					<notesStmt>
						<note type="audience" n="2">International</note>
						<note type="popular" n="0">No</note>
						<note type="peer" n="1">Yes</note>
					</notesStmt>
					<sourceDesc>
						<biblStruct>
							<analytic>
								<title xml:lang="fr">Vers une démarche scientifique intégrative : l\'exemple de l\'Observatoire Hommes-milieux du Nunavik (Canada)</title>
								<author role="aut">
									<persName>
										<forename type="first">Armelle</forename>
										<surname>Decaulne</surname>
									</persName>
									<email type="md5">6f9b6ebca2f873b07459de5ee8adfe81</email>
									<email type="domain">univ-nantes.fr</email>
									<idno type="idhal" notation="string">armelle-decaulne</idno>
									<idno type="idhal" notation="numeric">179996</idno>
									<idno type="halauthorid" notation="string">17906-179996</idno>
									<idno type="ORCID">https://orcid.org/0000-0002-8029-850X</idno>
									<affiliation ref="#struct-2267"/>
								</author>
								<author role="aut">
									<persName>
										<forename type="first">Fabienne</forename>
										<surname>Joliet</surname>
									</persName>
									<email type="md5">612010d12abcc156e4835057dcaab522</email>
									<email type="domain">agrocampus-ouest.fr</email>
									<idno type="idhal" notation="numeric">932715</idno>
									<idno type="halauthorid" notation="string">672149-932715</idno>
									<idno type="ORCID">https://orcid.org/0000-0002-4707-3229</idno>
									<orgName ref="#struct-108028"/>
									<affiliation ref="#struct-59373"/>
								</author>
								<author role="aut">
									<persName>
										<forename type="first">Laine</forename>
										<surname>Chanteloup</surname>
									</persName>
									<email type="md5">d467ca8cd1f319ca1ec3b0df8a209c8f</email>
									<email type="domain">univ-savoie.fr</email>
									<idno type="idhal" notation="numeric">869121</idno>
									<idno type="halauthorid" notation="string">34786-869121</idno>
									<orgName ref="#struct-97916"/>
								</author>
								<author role="aut">
									<persName>
										<forename type="first">Thora</forename>
										<surname>Herrmann</surname>
									</persName>
									<email type="md5">5b91c50bc8713719c2d89c9b22c6b935</email>
									<email type="domain">umontreal.ca</email>
									<idno type="idhal" notation="numeric">1025242</idno>
									<idno type="halauthorid" notation="string">1282105-1025242</idno>
									<affiliation ref="#struct-60508"/>
								</author>
								<author role="aut">
									<persName>
										<forename type="first">Najat</forename>
										<surname>Bhiry</surname>
									</persName>
									<email type="md5">e531c02ba9cacf12d72fdd02a8e3648e</email>
									<email type="domain">cen.ulaval.ca</email>
									<idno type="idhal" notation="numeric">988112</idno>
									<idno type="halauthorid" notation="string">910200-988112</idno>
									<affiliation ref="#struct-194108"/>
								</author>
								<author role="aut">
									<persName>
										<forename type="first">Didier</forename>
										<surname>Haillot</surname>
									</persName>
									<email type="md5">5e87eb249a6920d688f25ffd230d0b75</email>
									<email type="domain">univ-pau.fr</email>
									<idno type="idhal" notation="string">didier-haillot</idno>
									<idno type="idhal" notation="numeric">174380</idno>
									<idno type="halauthorid" notation="string">18533-174380</idno>
									<idno type="IDREF">https://www.idref.fr/150128738</idno>
									<idno type="ORCID">https://orcid.org/0000-0001-8676-034X</idno>
									<orgName ref="#struct-301085"/>
									<affiliation ref="#struct-33789"/>
								</author>
							</analytic>
							<monogr>
								<idno type="halJournalId" status="VALID">108022</idno>
								<idno type="eissn">2430-3038</idno>
								<title level="j">Journal of Interdisciplinary Methodologies and Issues in Science</title>
								<imprint>
									<publisher>Journal of Interdisciplinary Methodologies and Issues in Science</publisher>
									<biblScope unit="volume">Scientific observatories Environments/Societies, new challenges</biblScope>
									<date type="datePub">2020-07-26</date>
									<date type="dateEpub">2020-07</date>
								</imprint>
							</monogr>
							<idno type="doi">10.18713/JIMIS-120620-6-5</idno>
						</biblStruct>
					</sourceDesc>
					<profileDesc>
						<langUsage>
							<language ident="en">English</language>
						</langUsage>
						<textClass>
							<keywords scheme="author">
								<term xml:lang="en">OHMi Nunavik</term>
							</keywords>
							<classCode scheme="halDomain" n="sdu.stu.gm">Sciences of the Universe [physics]/Earth Sciences/Geomorphology</classCode>
							<classCode scheme="halTypology" n="ART">Journal articles</classCode>
							<classCode scheme="halOldTypology" n="ART">Journal articles</classCode>
							<classCode scheme="halTreeTypology" n="ART">Journal articles</classCode>
						</textClass>
						<abstract xml:lang="en">
							<p>Les Observatoires Hommes-Milieux (OHM, dispositifs du LabEx DRIIHM créés à l’initiative duCNRS) étudient les interrelations société–environnement interrogées suite à un événementfondateur anthropique qui a produit une réorganisation de l’ensemble du socio-écosystème initial.Cet article s’intéresse à la mise en oeuvre, aux rôles et fonctionnement d’un de ces OHM, l’OHMiNUNAVIK, développé depuis 2014 en contexte autochtone dans l’Arctique canadien au Nord duQuébec. L’OHMi NUNAVIK tend à développer un cadre de recherche holistique et intégrateurfaisant tomber les barrières entre disciplines d’une part, et types de savoirs (scientifiques /autochtones) d’autre part. Il vise ainsi à créer une structure de recherche innovante en Arctiquepar le prisme (i) des représentations arctiques des Inuits et des Qallunaat, (ii) les collaborationsentre chercheurs, gestionnaires territoriaux et habitants. L’objectif de l’OHMi NUNAVIK portesur l’acquisition de connaissances et de nouvelles manières de les produire afin d’identifier lesimpacts cumulatifs des programmes de développements économiques du Nunavik et des PlansNord successifs du Québec depuis 2011, et des changements socio-environnementaux globauxsur cette aire d’étude. Au total, sept projets de recherche se déploient au sein de l’OHMiNUNAVIK ; quatre d’entre eux (Nuna, Kingaq, Niqiliriniq et Siqiniq) sont ici mobilisés pourillustrer le fonctionnement de l’OHMi NUNAVIK, les techniques d’observations utilisées, lescollaborations produites.</p>
						</abstract>
					</profileDesc>
				</biblFull>
			</listBibl>
		</body>
		<back>
			<listOrg type="structures">
				<org type="researchteam" xml:id="struct-2267" status="OLD">
					<idno type="IdRef">221296700</idno>
					<orgName>Littoral, Environnement, Télédétection, Géomatique</orgName>
					<orgName type="acronym">LETG - Nantes</orgName>
					<date type="end">2021-12-20</date>
					<desc>
						<address>
							<addrLine> Institut de Géographie et d\'Aménagement de l\'Université de Nantes Campus du Tertre BP 8122 44312 NANTES CEDEX 3</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://letg.cnrs.fr</ref>
					</desc>
					<listRelation>
						<relation active="#struct-7127" type="direct"/>
						<relation active="#struct-455934" type="indirect"/>
						<relation active="#struct-110691" type="direct"/>
						<relation active="#struct-564132" type="indirect"/>
						<relation active="#struct-300314" type="direct"/>
						<relation active="#struct-406201" type="direct"/>
						<relation active="#struct-528860" type="indirect"/>
						<relation name="UMR6554" active="#struct-441569" type="direct"/>
						<relation active="#struct-530572" type="direct"/>
						<relation name="93263" active="#struct-93263" type="indirect"/>
					</listRelation>
				</org>
				<org type="laboratory" xml:id="struct-59373" status="OLD">
					<orgName>Espaces et Sociétés</orgName>
					<orgName type="acronym">ESO</orgName>
					<date type="start">1996-01-01</date>
					<date type="end">2021-12-31</date>
					<desc>
						<address>
							<country key="FR"/>
						</address>
					</desc>
					<listRelation>
						<relation active="#struct-7127" type="direct"/>
						<relation active="#struct-455934" type="indirect"/>
						<relation active="#struct-7566" type="direct"/>
						<relation active="#struct-74911" type="direct"/>
						<relation active="#struct-108028" type="direct"/>
						<relation active="#struct-406201" type="direct"/>
						<relation active="#struct-528860" type="indirect"/>
						<relation active="#struct-441569" type="direct"/>
						<relation active="#struct-530572" type="direct"/>
						<relation name="93263" active="#struct-93263" type="indirect"/>
					</listRelation>
				</org>
				<org type="laboratory" xml:id="struct-60508" status="INCOMING">
					<orgName>Département de géographie</orgName>
					<desc>
						<address>
							<addrLine>Montréal</addrLine>
							<country key="CA"/>
						</address>
					</desc>
					<listRelation>
						<relation active="#struct-302452" type="direct"/>
					</listRelation>
				</org>
				<org type="laboratory" xml:id="struct-194108" status="INCOMING">
					<orgName>Centre d\'études nordiques et Département de Géographie</orgName>
					<desc>
						<address>
							<addrLine>Québec</addrLine>
							<country key="CA"/>
						</address>
					</desc>
					<listRelation>
						<relation active="#struct-93488" type="direct"/>
					</listRelation>
				</org>
				<org type="laboratory" xml:id="struct-33789" status="VALID">
					<idno type="IdRef">227249224</idno>
					<idno type="RNSR">199513639B</idno>
					<orgName>Laboratoire de Génie Thermique Énergétique et Procédés (EA1932)</orgName>
					<orgName type="acronym">LATEP</orgName>
					<date type="start">1995-01-01</date>
					<desc>
						<address>
							<addrLine>Université de Pau et des Pays de l\'Adour BP 1155 64013 PAU</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.univ-pau.fr/latep</ref>
					</desc>
					<listRelation>
						<relation active="#struct-301085" type="direct"/>
					</listRelation>
				</org>
				<org type="institution" xml:id="struct-7127" status="VALID">
					<idno type="IdRef">026403064</idno>
					<idno type="ISNI">0000000121864076</idno>
					<idno type="ROR">051kpcy16</idno>
					<orgName>Université de Caen Normandie</orgName>
					<orgName type="acronym">UNICAEN</orgName>
					<date type="start">1432-01-01</date>
					<desc>
						<address>
							<addrLine>Esplanade de la Paix - CS 14032 - 14032 CAEN Cedex 5</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.unicaen.fr/</ref>
					</desc>
					<listRelation>
						<relation active="#struct-455934" type="direct"/>
					</listRelation>
				</org>
				<org type="regroupinstitution" xml:id="struct-455934" status="VALID">
					<idno type="IdRef">190906332</idno>
					<idno type="ISNI">0000000417859671 </idno>
					<idno type="ROR">01k40cz91</idno>
					<orgName>Normandie Université</orgName>
					<orgName type="acronym">NU</orgName>
					<date type="start">2015-01-01</date>
					<desc>
						<address>
							<addrLine>Esplanade de la Paix - CS 14032 - 14032 Caen Cedex 5</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.normandie-univ.fr/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-110691" status="VALID">
					<idno type="IdRef">026375478</idno>
					<idno type="ISNI">0000000121955365</idno>
					<idno type="ROR">046b3cj80</idno>
					<orgName>École pratique des hautes études</orgName>
					<orgName type="acronym">EPHE</orgName>
					<desc>
						<address>
							<addrLine>4-14 Rue Ferrus, 75014 Paris</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.ephe.fr</ref>
					</desc>
					<listRelation>
						<relation active="#struct-564132" type="direct"/>
					</listRelation>
				</org>
				<org type="regroupinstitution" xml:id="struct-564132" status="VALID">
					<idno type="IdRef">241597595</idno>
					<idno type="ISNI">0000 0004 1784 3645</idno>
					<orgName>Université Paris sciences et lettres</orgName>
					<orgName type="acronym">PSL</orgName>
					<desc>
						<address>
							<addrLine>60 rue Mazarine75006 Paris</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">https://www.psl.eu/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-300314" status="VALID">
					<idno type="ROR">https://ror.org/01b8h3982</idno>
					<orgName>Université de Brest</orgName>
					<orgName type="acronym">UBO</orgName>
					<desc>
						<address>
							<addrLine>Université de Bretagne Occidentale - 3 Rue des Archives 29238, Brest</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">https://www.univ-brest.fr/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-406201" status="VALID">
					<idno type="IdRef">054447658</idno>
					<idno type="ISNI">0000000121522279</idno>
					<idno type="ROR">01m84wm78</idno>
					<orgName>Université de Rennes 2</orgName>
					<orgName type="acronym">UR2</orgName>
					<desc>
						<address>
							<addrLine>Place du recteur Henri Le Moal - CS 24307 - 35043 Rennes cedex</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.univ-rennes2.fr/</ref>
					</desc>
					<listRelation>
						<relation active="#struct-528860" type="direct"/>
					</listRelation>
				</org>
				<org type="regroupinstitution" xml:id="struct-528860" status="VALID">
					<orgName>Université de Rennes</orgName>
					<orgName type="acronym">UNIV-RENNES</orgName>
					<date type="start">2018-01-01</date>
					<desc>
						<address>
							<country key="FR"/>
						</address>
					</desc>
				</org>
				<org type="institution" xml:id="struct-441569" status="VALID">
					<idno type="IdRef">02636817X</idno>
					<idno type="ISNI">0000000122597504</idno>
					<orgName>Centre National de la Recherche Scientifique</orgName>
					<orgName type="acronym">CNRS</orgName>
					<date type="start">1939-10-19</date>
					<desc>
						<address>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.cnrs.fr/</ref>
					</desc>
				</org>
				<org type="regrouplaboratory" xml:id="struct-530572" status="OLD">
					<idno type="IdRef">026568586</idno>
					<orgName>Institut de Géographie et d\'Aménagement Régional de l\'Université de Nantes</orgName>
					<orgName type="acronym">IGARUN</orgName>
					<date type="end">2021-12-31</date>
					<desc>
						<address>
							<addrLine>Campus du Tertre, BP 81 22744312 Nantes cedex 3</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.igarun.univ-nantes.fr</ref>
					</desc>
					<listRelation>
						<relation name="93263" active="#struct-93263" type="direct"/>
					</listRelation>
				</org>
				<org type="institution" xml:id="struct-93263" status="OLD">
					<idno type="IdRef">026403447</idno>
					<orgName>Université de Nantes</orgName>
					<orgName type="acronym">UN</orgName>
					<date type="end">2021-12-31</date>
					<desc>
						<address>
							<addrLine>1, quai de Tourville - BP 13522 - 44035 Nantes cedex 1</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.univ-nantes.fr/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-7566" status="VALID">
					<orgName>Le Mans Université</orgName>
					<orgName type="acronym">UM</orgName>
					<desc>
						<address>
							<addrLine>Avenue Olivier Messiaen - 72085 Le Mans cedex 9</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.univ-lemans.fr/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-74911" status="VALID">
					<idno type="IdRef">026402920</idno>
					<idno type="ISNI">0000000122483363</idno>
					<idno type="ROR">04yrqp957</idno>
					<orgName>Université d\'Angers</orgName>
					<orgName type="acronym">UA</orgName>
					<date type="start">1971-10-25</date>
					<desc>
						<address>
							<addrLine>Université d\'Angers - 40 Rue de Rennes, BP 73532 - 49035 Angers CEDEX 01</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.univ-angers.fr/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-108028" status="OLD">
					<orgName>AGROCAMPUS OUEST</orgName>
					<date type="start">2008-01-01</date>
					<date type="end">2019-12-31</date>
					<desc>
						<address>
							<addrLine>Institut Supérieur des Sciences Agronomiques, Agroalimentaires, Horticoles et du Paysage - 65, rue de St Brieuc - CS 84215 - 35042 Rennes cedex</addrLine>
							<country key="FR"/>
						</address>
						<ref type="url">http://www.agrocampus-ouest.fr/</ref>
					</desc>
				</org>
				<org type="regroupinstitution" xml:id="struct-302452" status="VALID">
					<orgName>Université de Montréal</orgName>
					<orgName type="acronym">UdeM</orgName>
					<desc>
						<address>
							<addrLine>2900 Boulevard Edouard-Montpetit, Montréal, QC H3T 1J4</addrLine>
							<country key="CA"/>
						</address>
						<ref type="url">https://www.umontreal.ca/</ref>
					</desc>
				</org>
				<org type="regroupinstitution" xml:id="struct-93488" status="VALID">
					<orgName>Université Laval [Québec]</orgName>
					<orgName type="acronym">ULaval</orgName>
					<desc>
						<address>
							<addrLine>2325, rue de l\'Université Québec G1V 0A6</addrLine>
							<country key="CA"/>
						</address>
						<ref type="url">https://www.ulaval.ca/</ref>
					</desc>
				</org>
				<org type="institution" xml:id="struct-301085" status="VALID">
					<orgName>Université de Pau et des Pays de l\'Adour</orgName>
					<orgName type="acronym">UPPA</orgName>
					<date type="start">1970-12-17</date>
					<desc>
						<address>
							<addrLine>Avenue de l\'Université - BP 576 - 64012 Pau Cedex </addrLine>
							<country key="FR"/>
						</address>
					</desc>
				</org>
			</listOrg>
			<listOrg type="projects">
				<org type="anrProject" xml:id="projanr-23477" status="VALID">
					<idno type="anr">ANR-11-LABX-0010</idno>
					<orgName>DRIIHM / IRDHEI</orgName>
					<desc>Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux</desc>
					<date type="start">2011</date>
				</org>
			</listOrg>
		</back>
	</text>
</TEI>')];
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
     * Sample from dataProvider halTeiWithSeveralsAuthorsProvider
     * @return array
     */
    public function sampleArrayAuthorTei(): array {
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
    public function sampleArrayStructTei(): array {
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
     * @return array
     */
    public function sampleArrayAuthorAndStructTei(): array {
        return [[$this->sampleArrayAuthorTei(),$this->sampleArrayStructTei()]];
    }

    /**
     * sample array after mergeAuthorInfoAndAffiTei method
     * @return array
     */
    public function sampleArrayMergedAllInfoTei(): array {
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
     * regroup sample before merging TEI and DB
     * @return array
     */
    public function sampleArrayTeiAndDB(): array {
        return [[$this->sampleArrayAuthorInDB(),$this->sampleArrayMergedAllInfoTei()]];
    }

    /**
     * @return array
     */
    public function sampleOACreator() : array
    {
        return [

                '@rank' => '1',
                '@orcid' => '0000-0002-5332-5437',
                '@URL' => 'https://academic.microsoft.com/#/detail/2304801209',
                '$' => 'Svjetlan Feretić',

        ];
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
     * sample merge db and api information for one author
     * @return array
     */
    public function sampleOaDbAndApi(): array {
     return [[$this->sampleDbForOACreator(),$this->sampleOACreator()]];
    }
}

