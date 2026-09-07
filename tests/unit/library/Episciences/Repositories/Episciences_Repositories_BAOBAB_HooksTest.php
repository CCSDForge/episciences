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
 * BAOBAB (WACREN, InvenioRDM 13.1) has a broken OAI-PMH endpoint: every verb that
 * must emit at least one record returns a 500. Records are fetched over the REST
 * API instead, and the Dublin Core body is compiled from the DataCite serializer
 * obtained by content negotiation (Accept: application/vnd.datacite.datacite+xml)
 * rather than from InvenioRDM's own oai_dc, which double-escapes HTML on this
 * corpus.
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
     * matched with or without the slash after "ark:" — is exercised here.
     */
    public function testArkPatternDetectsArkIdentifiersWithoutNetworkCall(): void
    {
        self::assertSame(1, preg_match('#^ark:/?\d+/#', 'ark:/50962/bb67854x81qwd65d'));
        self::assertSame(1, preg_match('#^ark:/?\d+/#', 'ark:50962/bb67854x81qwd65d'));
        self::assertSame(0, preg_match('#^ark:/?\d+/#', 'xkpt8-rjr81'));
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
     * links.self points to a file's JSON metadata on InvenioRDM 13.1, not to its
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
     * When links.content is absent (contrary to the InvenioRDM 13.1 contract),
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
        [$body, $relatedIdentifiers] = $this->invokePrivate('extractFromDataCite', [self::DATACITE_FIXTURE, 'en']);

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

    public function testExtractFromDataCiteThrowsOnInvalidXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        set_error_handler(static function () {
            return true; // silence the libxml warning
        });

        try {
            $this->invokePrivate('extractFromDataCite', ['not valid xml', 'en']);
        } finally {
            restore_error_handler();
        }
    }

    // =========================================================================
    // extractDescriptions() — Abstract preferred, falls back to any description
    // =========================================================================

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
}
