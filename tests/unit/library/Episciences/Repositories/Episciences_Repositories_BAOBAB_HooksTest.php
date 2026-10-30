<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories;
use Episciences_Repositories_BAOBAB_Hooks;
use Episciences_Repositories_Common;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Repositories_BAOBAB_Hooks.
 *
 * BAOBAB (WACREN, InvenioRDM 14)'s OAI-PMH endpoint was fixed on the WACREN
 * side (verified 2026-09-08), so the Dublin Core body is now compiled from the
 * DataCite serializer fetched over OAI-PMH (metadata_sources.base_url) rather
 * than from InvenioRDM's own oai_dc, which double-escapes HTML on this corpus.
 * The REST API remains the source for files, authors, access status and the
 * concept identifier, none of which OAI-PMH carries.
 *
 * All tests are DB-free and network-free: no Guzzle mock exists for the hooks
 * classes in this codebase, so hookApiRecords()/hookVersion()/hookLinkedDataProcessing()
 * are exercised only through the private extraction helpers (via ReflectionMethod)
 * or with a pre-built 'response' that bypasses the live API call. The ARK
 * resolution branch of hookCleanIdentifiers() needs a live search API call and is
 * therefore checked only at the regex-detection level.
 *
 * @covers Episciences_Repositories_BAOBAB_Hooks
 */
final class Episciences_Repositories_BAOBAB_HooksTest extends TestCase
{
    private const DATACITE_FIXTURE = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <identifier identifierType="DOI"></identifier>
    <titles>
        <title xml:lang="en">A preprint about baobab trees</title>
    </titles>
    <creators>
        <creator>
            <creatorName>Arogundade, Femi Qudus</creatorName>
        </creator>
    </creators>
    <subjects>
        <subject>botany</subject>
    </subjects>
    <descriptions>
        <description descriptionType="Abstract" xml:lang="en">&lt;p&gt;Simple abstract&lt;/p&gt;</description>
    </descriptions>
    <rightsList>
        <rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC-BY 4.0</rights>
    </rightsList>
    <resourceType resourceTypeGeneral="Preprint"></resourceType>
    <dates>
        <date dateType="Issued">2026-05-01</date>
    </dates>
    <alternateIdentifiers>
        <alternateIdentifier alternateIdentifierType="URL">https://baobab.wacren.net/records/xkpt8-rjr81</alternateIdentifier>
    </alternateIdentifiers>
    <relatedIdentifiers>
        <relatedIdentifier relatedIdentifierType="URL" relationType="IsPartOf">https://baobab.wacren.net/communities/community-coppha</relatedIdentifier>
        <relatedIdentifier relatedIdentifierType="DOI" relationType="IsDerivedFrom">10.5281/zenodo.15868466</relatedIdentifier>
    </relatedIdentifiers>
    <publisher>WACREN</publisher>
</resource>
XML;

    private function invokePrivate(string $method, array $args)
    {
        $rm = new ReflectionMethod(Episciences_Repositories_BAOBAB_Hooks::class, $method);
        $rm->setAccessible(true);
        return $rm->invokeArgs(null, $args);
    }

    // =========================================================================
    // hookCleanIdentifiers()
    // =========================================================================

    public function testHookCleanIdentifiersBareSlugPassesThrough(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers(['id' => 'xkpt8-rjr81']);
        self::assertSame(['identifier' => 'xkpt8-rjr81'], $result);
    }

    public function testHookCleanIdentifiersStripsRecordUrl(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers([
            'id' => 'https://baobab.wacren.net/records/xkpt8-rjr81',
        ]);
        self::assertSame(['identifier' => 'xkpt8-rjr81'], $result);
    }

    public function testHookCleanIdentifiersStripsPreviewQueryString(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers([
            'id' => 'https://baobab.wacren.net/records/xkpt8-rjr81?preview=1',
        ]);
        self::assertSame(['identifier' => 'xkpt8-rjr81'], $result);
    }

    public function testHookCleanIdentifiersStripsFilesSegment(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers([
            'id' => 'https://baobab.wacren.net/records/xkpt8-rjr81/files/preview.pdf',
        ]);
        self::assertSame(['identifier' => 'xkpt8-rjr81'], $result);
    }

    /**
     * A trailing slash (e.g. copy-pasted straight from a browser's address bar)
     * must not survive, or it reaches the API as a bogus extra path segment.
     */
    public function testHookCleanIdentifiersStripsTrailingSlash(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers([
            'id' => 'https://baobab.wacren.net/records/xkpt8-rjr81/',
        ]);
        self::assertSame(['identifier' => 'xkpt8-rjr81'], $result);
    }

    public function testHookCleanIdentifiersEmptyEntry(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers(['id' => '']);
        self::assertSame(['identifier' => ''], $result);
    }

    /**
     * Bug: the submission form's JS helper strips the scheme+host from a pasted
     * URL but leaves the "records/" path segment untouched for a repository
     * outside its special cases (Dataverse, arXiv) — so hookCleanIdentifiers()
     * must strip a bare leading "records/"/"uploads/" too, not only right after
     * a full "https://host/" prefix.
     */
    public function testHookCleanIdentifiersStripsBareRecordsPrefixLeftByJs(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookCleanIdentifiers([
            'id' => 'records/s9g8r-v1011',
        ]);
        self::assertSame(['identifier' => 's9g8r-v1011'], $result);
    }

    /**
     * hookCleanIdentifiers() resolves an ARK through a live search API call
     * (Episciences_Tools::callApi() has no mock in this test suite: see
     * docs/adding-a-new-repository.md, "Testing"). Only the detection regex —
     * matched with or without the slash after "ark:", bare or behind a resolver
     * URL — is exercised here.
     *
     * @dataProvider arkPatternProvider
     */
    public function testArkPatternDetectsArkIdentifiersWithoutNetworkCall(string $input, ?string $expectedArk): void
    {
        $matched = preg_match(Episciences_Repositories_BAOBAB_Hooks::ARK_PATTERN, $input, $matches) === 1;

        self::assertSame($expectedArk, $matched ? $matches[1] : null);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function arkPatternProvider(): array
    {
        return [
            'bare ARK' => ['ark:/50962/bb67854x81qwd65d', 'ark:/50962/bb67854x81qwd65d'],
            'bare ARK without slash' => ['ark:50962/bb67854x81qwd65d', 'ark:50962/bb67854x81qwd65d'],
            'resolver URL' => ['https://pidslink.ren.africa/ark:/50962/bb678546fr6tnrn5', 'ark:/50962/bb678546fr6tnrn5'],
            'resolver URL with query' => ['https://pidslink.ren.africa/ark:/50962/bb678546fr6tnrn5?info', 'ark:/50962/bb678546fr6tnrn5'],
            'InvenioRDM slug' => ['xkpt8-rjr81', null],
            'record URL' => ['https://baobab.wacren.net/records/0btwb-n7z35', null],
        ];
    }

    // =========================================================================
    // hookIsRequiredVersion() / hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    public function testHookIsRequiredVersion(): void
    {
        self::assertSame(['result' => false], Episciences_Repositories_BAOBAB_Hooks::hookIsRequiredVersion());
    }

    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        self::assertSame(['result' => false], Episciences_Repositories_BAOBAB_Hooks::hookIsIdentifierCommonToAllVersions());
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    public function testHookVersionFromVersionsIndex(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookVersion([
            'response' => ['versions' => ['index' => 2], 'metadata' => ['version' => null]],
        ]);

        self::assertSame(['version' => 2], $result);
    }

    public function testHookVersionFallsBackToPreviousVersionPlusOne(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookVersion([
            'response' => ['metadata' => ['version' => null]],
            'context' => ['previousVersion' => 1],
        ]);

        self::assertSame(['version' => 2], $result);
    }

    public function testHookVersionReturnsEmptyWhenNoVersionInfo(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookVersion([
            'response' => ['metadata' => ['version' => null]],
        ]);

        self::assertSame([], $result);
    }

    // =========================================================================
    // hookFilesProcessing() — metadata-only records (files.entries empty)
    // =========================================================================

    public function testHookFilesProcessingWithEmptyFilesEntriesDoesNotInsert(): void
    {
        $result = Episciences_Repositories_BAOBAB_Hooks::hookFilesProcessing([
            'docId' => 999999,
            'repoId' => (int)Episciences_Repositories::BAOBAB_REPO_ID,
            'files' => [],
        ]);

        self::assertSame(0, $result['affectedRows']);
    }

    /**
     * links.self points to a file's JSON metadata on InvenioRDM 14, not to its
     * binary content, unlike the legacy Zenodo API: links.content is mandatory.
     */
    public function testHookFilesProcessingPrefersLinksContentOverSelf(): void
    {
        $entry = [
            'key' => 'preprint.pdf',
            'checksum' => 'md5:abc123',
            'size' => 42,
            'ext' => 'pdf',
            'links' => [
                'content' => 'https://baobab.wacren.net/api/records/xkpt8-rjr81/files/preprint.pdf/content',
                'self' => 'https://baobab.wacren.net/api/records/xkpt8-rjr81/files/preprint.pdf',
            ],
        ];

        $row = $this->invokePrivate('buildFileRow', [$entry, 999999, (int)Episciences_Repositories::BAOBAB_REPO_ID]);

        self::assertSame(
            'https://baobab.wacren.net/api/records/xkpt8-rjr81/files/preprint.pdf/content',
            $row['self_link']
        );
    }

    /**
     * When links.content is absent (contrary to the InvenioRDM 14 contract),
     * the file is still registered rather than dropped, falling back to
     * links.self even though it points to file metadata, not content.
     */
    public function testHookFilesProcessingFallsBackToSelfLinkWhenContentMissing(): void
    {
        $entry = [
            'key' => 'preprint.pdf',
            'checksum' => 'md5:abc123',
            'size' => 42,
            'ext' => 'pdf',
            'links' => [
                'self' => 'https://baobab.wacren.net/api/records/xkpt8-rjr81/files/preprint.pdf',
            ],
        ];

        $row = $this->invokePrivate('buildFileRow', [$entry, 999999, (int)Episciences_Repositories::BAOBAB_REPO_ID]);

        self::assertSame(
            'https://baobab.wacren.net/api/records/xkpt8-rjr81/files/preprint.pdf',
            $row['self_link']
        );
    }

    public function testHookFilesProcessingSelfLinkNullWhenNeitherLinkPresent(): void
    {
        $entry = ['key' => 'preprint.pdf', 'checksum' => 'md5:abc123', 'size' => 42, 'ext' => 'pdf', 'links' => []];

        $row = $this->invokePrivate('buildFileRow', [$entry, 999999, (int)Episciences_Repositories::BAOBAB_REPO_ID]);

        self::assertNull($row['self_link']);
    }

    // =========================================================================
    // Concept identifier — set directly from parent.id in hookApiRecords()
    // =========================================================================

    public function testConceptIdentifierSourcedFromParentId(): void
    {
        $result = $this->invokePrivate('extractConceptIdentifier', [['parent' => ['id' => 'xkpt8-rjr81']]]);
        self::assertSame('xkpt8-rjr81', $result);
    }

    public function testConceptIdentifierNullWhenNoParent(): void
    {
        $result = $this->invokePrivate('extractConceptIdentifier', [[]]);
        self::assertNull($result);
    }

    // =========================================================================
    // extractFromDataCite() — private, tested via ReflectionMethod
    // =========================================================================

    public function testExtractFromDataCiteBuildsExpectedBody(): void
    {
        $dataCite = $this->invokePrivate('parseDataCite', [self::DATACITE_FIXTURE]);
        self::assertInstanceOf(\SimpleXMLElement::class, $dataCite);

        [$body, $relatedIdentifiers] = $this->invokePrivate('extractFromDataCite', [$dataCite, 'en']);

        self::assertSame('A preprint about baobab trees', $body['title'][0]['value']);
        self::assertSame('en', $body['title'][0]['language']);

        self::assertSame('Simple abstract', $body[Episciences_Repositories_Common::META_DESCRIPTION][0]['value']);

        self::assertSame(['botany'], array_column($body['subject'], 'value'));

        // resourceType has empty text on a preprint; only the attribute carries "Preprint".
        self::assertSame('Preprint', $body['type']);

        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $body['rights'][0]);

        self::assertSame(
            ['https://baobab.wacren.net/records/xkpt8-rjr81'],
            $body[Episciences_Repositories_Common::META_IDENTIFIER]
        );

        self::assertSame('WACREN', $body['publisher']);
        self::assertSame('2026-05-01', $body['date']);

        self::assertCount(2, $relatedIdentifiers);
        self::assertSame('https://baobab.wacren.net/communities/community-coppha', $relatedIdentifiers[0]['identifier']);
        self::assertSame('10.5281/zenodo.15868466', $relatedIdentifiers[1]['identifier']);
    }

    public function testParseDataCiteRestoresLibxmlErrorMode(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            self::assertNull($this->invokePrivate('parseDataCite', ['not valid xml']));
            self::assertFalse(libxml_use_internal_errors(), 'libxml error mode must be restored');
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    // =========================================================================
    // extractDescriptions() — Abstract preferred, falls back to any description
    // =========================================================================

    public function testExtractDescriptionsKeepsTranslatedAbstractFromOtherDescription(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <descriptions>
        <description descriptionType="Abstract">Résumé en français</description>
        <description descriptionType="Other" xml:lang="eng">English abstract</description>
        <description descriptionType="TechnicalInfo">Notes techniques</description>
    </descriptions>
</resource>
XML;
        $metadata = simplexml_load_string($xml);
        $metadata->registerXPathNamespace('datacite', Episciences_Repositories_BAOBAB_Hooks::DATACITE_NS);

        $result = $this->invokePrivate('extractDescriptions', [$metadata, 'fr']);

        self::assertSame([
            ['value' => 'Résumé en français', 'language' => 'fr'],
            ['value' => 'English abstract', 'language' => 'en'],
        ], $result);
    }

    public function testExtractDescriptionsFallsBackToDocumentLanguage(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <descriptions>
        <description descriptionType="Abstract">No lang attribute here</description>
    </descriptions>
</resource>
XML;
        $metadata = simplexml_load_string($xml);
        $metadata->registerXPathNamespace('datacite', Episciences_Repositories_BAOBAB_Hooks::DATACITE_NS);

        $result = $this->invokePrivate('extractDescriptions', [$metadata, 'fr']);

        self::assertSame('No lang attribute here', $result[0]['value']);
        self::assertSame('fr', $result[0]['language']);
    }

    // =========================================================================
    // filterOutCommunityLinks() — community memberships must not become datasets
    // =========================================================================

    public function testFilterOutCommunityLinksRemovesOnlyCommunityUrls(): void
    {
        $relatedIdentifiers = [
            ['identifier' => 'https://baobab.wacren.net/communities/community-coppha', 'relation' => 'IsPartOf', 'resource_type' => 'dataset', 'scheme' => 'url'],
            ['identifier' => '10.5281/zenodo.15868466', 'relation' => 'IsDerivedFrom', 'resource_type' => 'dataset', 'scheme' => 'doi'],
        ];

        $result = $this->invokePrivate('filterOutCommunityLinks', [$relatedIdentifiers]);

        self::assertCount(1, $result);
        self::assertSame('10.5281/zenodo.15868466', $result[0]['identifier']);
    }

    // =========================================================================
    // extractAuthorsFromJson() — from InvenioRDM's person_or_org, not creatorName
    // =========================================================================

    public function testExtractAuthorsFromJsonWithGivenAndFamilyName(): void
    {
        $creators = [
            [
                'person_or_org' => [
                    'given_name' => 'Femi Qudus',
                    'family_name' => 'Arogundade',
                    'identifiers' => [
                        ['scheme' => 'orcid', 'identifier' => '0000-0001-2345-6789'],
                    ],
                ],
                'affiliations' => [
                    ['name' => 'WACREN'],
                ],
            ],
        ];

        [$creatorsDc, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame(['Femi Qudus Arogundade'], $creatorsDc);
        self::assertSame('Femi Qudus', $authors[0]['given']);
        self::assertSame('Arogundade', $authors[0]['family']);
        self::assertSame('0000-0001-2345-6789', $authors[0]['orcid']);
        self::assertSame([['name' => 'WACREN']], $authors[0]['affiliation']);
    }

    public function testExtractAuthorsFromJsonKeepsRorOfVocabularyAffiliation(): void
    {
        $creators = [
            [
                'person_or_org' => ['given_name' => 'Omo', 'family_name' => 'Oaiya'],
                'affiliations' => [
                    [
                        'id' => '006rqpt26',
                        'name' => 'West and Central African Research and Education Network',
                        'identifiers' => [
                            ['identifier' => '006rqpt26', 'scheme' => 'ror'],
                            ['identifier' => 'grid.463700.6', 'scheme' => 'grid'],
                        ],
                    ],
                    ['name' => 'UbuntuNet Alliance'],
                ],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame([
            [
                'name' => 'West and Central African Research and Education Network',
                'id' => [['id' => 'https://ror.org/006rqpt26', 'id-type' => 'ROR']],
            ],
            ['name' => 'UbuntuNet Alliance'],
        ], $authors[0]['affiliation']);
    }

    public function testExtractAuthorsFromJsonFallsBackToBareAffiliationIdForRor(): void
    {
        $creators = [
            [
                'person_or_org' => ['given_name' => 'Jane', 'family_name' => 'Doe'],
                'affiliations' => [['id' => '006rqpt26', 'name' => 'WACREN']],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame('https://ror.org/006rqpt26', $authors[0]['affiliation'][0]['id'][0]['id']);
    }

    public function testExtractAuthorsFromJsonKeepsOrganisationalCreatorName(): void
    {
        $creators = [
            [
                'person_or_org' => [
                    'type' => 'organizational',
                    'name' => 'West and Central African Research and Education Network',
                    'identifiers' => [['identifier' => '006rqpt26', 'scheme' => 'ror']],
                ],
            ],
        ];

        [$creatorsDc, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame(['West and Central African Research and Education Network'], $creatorsDc);
        self::assertSame('West and Central African Research and Education Network', $authors[0]['family']);
        self::assertArrayNotHasKey('orcid', $authors[0]);
    }

    public function testExtractAuthorsFromJsonKeepsOrganisationalCreatorRorAsAffiliation(): void
    {
        $creators = [
            [
                'person_or_org' => ['given_name' => 'Belhamel', 'family_name' => 'Kamel'],
                'affiliations' => [
                    ['name' => 'Directory of Open Access Journals'],
                    ['name' => 'University of Bejaia'],
                ],
            ],
            [
                'person_or_org' => [
                    'type' => 'organizational',
                    'name' => 'Directory of Open Access Journals',
                    'identifiers' => [['identifier' => '05amyt365', 'scheme' => 'ror']],
                ],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        $doaj = [
            'name' => 'Directory of Open Access Journals',
            'id' => [['id' => 'https://ror.org/05amyt365', 'id-type' => 'ROR']],
        ];

        self::assertSame([$doaj, ['name' => 'University of Bejaia']], $authors[0]['affiliation']);
        self::assertSame([$doaj], $authors[1]['affiliation']);
    }

    public function testExtractAuthorsFromJsonWithoutOrcidNorAffiliation(): void
    {
        $creators = [
            [
                'person_or_org' => [
                    'given_name' => 'Jane',
                    'family_name' => 'Doe',
                ],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertArrayNotHasKey('orcid', $authors[0]);
        self::assertArrayNotHasKey('affiliation', $authors[0]);
    }

    public function testExtractAuthorsFromJsonSkipsEmptyNames(): void
    {
        $creators = [
            ['person_or_org' => []],
        ];

        [$creatorsDc, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame([], $creatorsDc);
        self::assertSame([], $authors);
    }

    public function testFilterOutCommunityLinksRemovesSelfDepositLink(): void
    {
        $relatedIdentifiers = [
            ['identifier' => 'https://baobab.wacren.net/uploads/zpsm4-76246', 'relation' => 'IsMetadataFor', 'resource_type' => 'dataset', 'scheme' => 'url'],
            ['identifier' => 'https://baobab.wacren.net/uploads/other-record', 'relation' => 'IsMetadataFor', 'resource_type' => 'dataset', 'scheme' => 'url'],
        ];

        $result = $this->invokePrivate('filterOutCommunityLinks', [$relatedIdentifiers, 'zpsm4-76246']);

        self::assertCount(1, $result);
        self::assertSame('https://baobab.wacren.net/uploads/other-record', $result[0]['identifier']);
    }

    public function testFilterOutCommunityLinksRemovesSelfRecordLinkWithTrailingSlash(): void
    {
        $relatedIdentifiers = [
            ['identifier' => 'https://baobab.wacren.net/records/zpsm4-76246/', 'relation' => 'IsIdenticalTo', 'resource_type' => 'dataset', 'scheme' => 'url'],
            ['identifier' => 'https://hal.science/hal-05478157', 'relation' => 'Describes', 'resource_type' => 'dataset', 'scheme' => 'url'],
        ];

        $result = $this->invokePrivate('filterOutCommunityLinks', [$relatedIdentifiers, 'zpsm4-76246']);

        self::assertSame(['https://hal.science/hal-05478157'], array_column($result, 'identifier'));
    }

    public function testExtractDescriptionsKeepsOnlyAbstractsWhenAllLanguagesCovered(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <descriptions>
        <description descriptionType="Abstract" xml:lang="en">Abstract</description>
        <description descriptionType="Methods" xml:lang="en">Methods</description>
    </descriptions>
</resource>
XML;
        $metadata = simplexml_load_string($xml);
        $metadata->registerXPathNamespace('datacite', Episciences_Repositories_BAOBAB_Hooks::DATACITE_NS);

        $result = $this->invokePrivate('extractDescriptions', [$metadata, 'en']);

        self::assertSame([['value' => 'Abstract', 'language' => 'en']], $result);
    }

    // =========================================================================
    // extractLicenceUrl()
    // =========================================================================

    public function testExtractLicenceUrlFromVocabularyLicence(): void
    {
        $json = ['metadata' => ['rights' => [[
            'id' => 'cc-by-4.0',
            'props' => ['url' => 'https://creativecommons.org/licenses/by/4.0/', 'scheme' => 'spdx'],
        ]]]];

        self::assertSame('https://creativecommons.org/licenses/by/4.0/', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl($json));
    }

    public function testExtractLicenceUrlFallsBackToCustomLicenceLink(): void
    {
        $json = ['metadata' => ['rights' => [[
            'title' => ['en' => 'Custom licence'],
            'link' => 'https://example.org/licence',
        ]]]];

        self::assertSame('https://example.org/licence', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl($json));
    }

    /**
     * @dataProvider unsafeLicenceLinkProvider
     */
    public function testExtractLicenceUrlDropsNonHttpLinks(string $link): void
    {
        $json = ['metadata' => ['rights' => [['title' => ['en' => 'Custom'], 'link' => $link]]]];

        self::assertSame('', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl($json));
    }

    public static function unsafeLicenceLinkProvider(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(document.cookie)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'not a URL' => ['see the licence file'],
            'http with injected quote' => ['https://example.org/"onmouseover="alert(1)'],
        ];
    }

    public function testExtractAuthorsFromJsonIgnoresMalformedRor(): void
    {
        $creators = [
            [
                'person_or_org' => ['given_name' => 'Jane', 'family_name' => 'Doe'],
                'affiliations' => [[
                    'name' => 'WACREN',
                    'identifiers' => [['identifier' => '"><script>alert(1)</script>', 'scheme' => 'ror']],
                ]],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame([['name' => 'WACREN']], $authors[0]['affiliation']);
    }

    public function testExtractLicenceUrlEmptyWithoutRights(): void
    {
        self::assertSame('', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl(['metadata' => []]));
    }

    public function testExtractDescriptionsNeverPromotesNonOtherDescriptions(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <descriptions>
        <description descriptionType="Abstract" xml:lang="fr">Résumé</description>
        <description descriptionType="TechnicalInfo" xml:lang="en">Software requirements</description>
        <description descriptionType="Other" xml:lang="eng">English abstract</description>
    </descriptions>
</resource>
XML;
        $metadata = simplexml_load_string($xml);
        $metadata->registerXPathNamespace('datacite', Episciences_Repositories_BAOBAB_Hooks::DATACITE_NS);

        $result = $this->invokePrivate('extractDescriptions', [$metadata, 'fr']);

        self::assertSame([
            ['value' => 'Résumé', 'language' => 'fr'],
            ['value' => 'English abstract', 'language' => 'en'],
        ], $result);
    }

    public function testExtractDescriptionsWithoutAbstractNeverPromotesNonOtherDescriptions(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<resource xmlns="http://datacite.org/schema/kernel-4">
    <descriptions>
        <description descriptionType="TechnicalInfo" xml:lang="en">Software requirements</description>
        <description descriptionType="Methods" xml:lang="en">Survey protocol</description>
        <description descriptionType="Other" xml:lang="fra">Résumé</description>
    </descriptions>
</resource>
XML;
        $metadata = simplexml_load_string($xml);
        $metadata->registerXPathNamespace('datacite', Episciences_Repositories_BAOBAB_Hooks::DATACITE_NS);

        $result = $this->invokePrivate('extractDescriptions', [$metadata, 'en']);

        self::assertSame([['value' => 'Résumé', 'language' => 'fr']], $result);
    }

    public function testExtractLicenceUrlLooksBeyondFirstRightsEntry(): void
    {
        $json = ['metadata' => ['rights' => [
            ['title' => ['en' => 'Custom'], 'link' => 'see the LICENSE file'],
            ['id' => 'cc-by-4.0', 'props' => ['url' => 'https://creativecommons.org/licenses/by/4.0/']],
        ]]];

        self::assertSame('https://creativecommons.org/licenses/by/4.0/', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl($json));
    }

    public function testExtractLicenceUrlFallsBackToLinkWhenPropsUrlEmpty(): void
    {
        $json = ['metadata' => ['rights' => [
            ['props' => ['url' => ''], 'link' => 'https://example.org/licence'],
        ]]];

        self::assertSame('https://example.org/licence', Episciences_Repositories_BAOBAB_Hooks::extractLicenceUrl($json));
    }

    /**
     * @dataProvider rorVariantProvider
     */
    public function testExtractAuthorsFromJsonAcceptsRorUrlVariants(string $ror): void
    {
        $creators = [
            [
                'person_or_org' => ['given_name' => 'Jane', 'family_name' => 'Doe'],
                'affiliations' => [['name' => 'WACREN', 'identifiers' => [['identifier' => $ror, 'scheme' => 'ror']]]],
            ],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertSame('https://ror.org/006rqpt26', $authors[0]['affiliation'][0]['id'][0]['id']);
    }

    public static function rorVariantProvider(): array
    {
        return [
            'https URL' => ['https://ror.org/006rqpt26'],
            'http URL' => ['http://ror.org/006rqpt26'],
            'no scheme' => ['ror.org/006rqpt26'],
            'bare id' => ['006rqpt26'],
        ];
    }

    public function testExtractAuthorsFromJsonOrganisationalCreatorWithoutRorHasNoAffiliation(): void
    {
        $creators = [
            ['person_or_org' => ['type' => 'organizational', 'name' => 'WACREN']],
        ];

        [, $authors] = $this->invokePrivate('extractAuthorsFromJson', [$creators]);

        self::assertArrayNotHasKey('affiliation', $authors[0]);
    }

    // =========================================================================
    // parseDataCite() — an unusable OAI-PMH answer triggers the REST fallback
    // =========================================================================

    public function testParseDataCiteAcceptsOaiRecord(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">
    <GetRecord><record><metadata>
        <resource xmlns="http://datacite.org/schema/kernel-4"><titles><title>T</title></titles></resource>
    </metadata></record></GetRecord>
</OAI-PMH>
XML;

        self::assertInstanceOf(\SimpleXMLElement::class, $this->invokePrivate('parseDataCite', [$xml]));
    }

    /**
     * @dataProvider unusableDataCiteProvider
     */
    public function testParseDataCiteRejectsUnusableAnswers(string $xml): void
    {
        self::assertNull($this->invokePrivate('parseDataCite', [$xml]));
    }

    public static function unusableDataCiteProvider(): array
    {
        return [
            'empty' => [''],
            'not XML' => ['<html>Server error'],
            'deleted record' => ['<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><GetRecord><record><header status="deleted"><identifier>oai:x</identifier></header></record></GetRecord></OAI-PMH>'],
        ];
    }

    // =========================================================================
    // fetchRecord() — only a record id reaches the API
    // =========================================================================

    /**
     * @dataProvider notARecordIdProvider
     */
    public function testFetchRecordRejectsNonRecordIdWithoutCallingTheApi(string $identifier): void
    {
        self::assertNull(Episciences_Repositories_BAOBAB_Hooks::fetchRecord($identifier));
    }

    public static function notARecordIdProvider(): array
    {
        return [
            'unresolved ARK' => ['ark:/50962/abc123'],
            'path traversal' => ['../communities/x'],
            'query string' => ['abc?q=1'],
            'empty' => [''],
        ];
    }

    // =========================================================================
    // fetchRecord() — the kept record is reused unless a fresh one is asked for
    // =========================================================================

    /**
     * The tests keep an identifier that is not a record id, so a call that
     * ignores the kept record returns null without reaching the API.
     *
     * @param array<string, mixed> $json
     */
    private function keepRecord(string $identifier, array $json): void
    {
        $property = new \ReflectionProperty(Episciences_Repositories_BAOBAB_Hooks::class, 'lastRecord');
        $property->setValue(null, [$identifier, $json]);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Episciences_Repositories_BAOBAB_Hooks::class, 'lastRecord'))->setValue(null, null);
    }

    public function testFetchRecordReusesTheKeptRecord(): void
    {
        $this->keepRecord('ark:/50962/kept', ['id' => 'kept']);

        self::assertSame(['id' => 'kept'], Episciences_Repositories_BAOBAB_Hooks::fetchRecord('ark:/50962/kept'));
    }

    public function testFetchRecordIgnoresTheKeptRecordWhenFreshIsAsked(): void
    {
        $this->keepRecord('ark:/50962/kept', ['id' => 'kept']);

        self::assertNull(Episciences_Repositories_BAOBAB_Hooks::fetchRecord('ark:/50962/kept', true));
    }

    public function testHookApiRecordsFetchesTheRecordAgainByDefault(): void
    {
        $this->keepRecord('ark:/50962/kept', ['id' => 'kept']);

        $this->expectException(\Ccsd_Error::class);
        Episciences_Repositories_BAOBAB_Hooks::hookApiRecords([Episciences_Repositories_Common::META_IDENTIFIER => 'ark:/50962/kept']);
    }
}
