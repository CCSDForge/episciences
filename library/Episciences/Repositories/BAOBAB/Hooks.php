<?php

use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\ConceptIdentifierInterface;
use Episciences\Repositories\FilesEnrichmentInterface;
use Episciences\Repositories\InputSanitizerInterface;
use Episciences\Repositories\LinkedDataEnrichmentInterface;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use GuzzleHttp\Exception\GuzzleException;

/**
 * BAOBAB (WACREN), an InvenioRDM 13.1 instance whose OAI-PMH endpoint returns
 * a 500 as soon as a record must be emitted (GetRecord/ListIdentifiers/ListRecords).
 * Records are therefore fetched over the REST API instead; the Dublin Core body
 * compiled into PAPERS.RECORD is built from the DataCite serializer (obtained by
 * content negotiation on the same endpoint), not from InvenioRDM's oai_dc, which
 * double-escapes HTML in dc:description on this corpus.
 *
 * @see https://baobab.wacren.net/
 */
class Episciences_Repositories_BAOBAB_Hooks implements
    CommonHooksInterface,
    InputSanitizerInterface,
    FilesEnrichmentInterface,
    LinkedDataEnrichmentInterface,
    ConceptIdentifierInterface
{
    public const API_RECORDS_URL = 'https://baobab.wacren.net/api/records';
    public const DATACITE_MIME = 'application/vnd.datacite.datacite+xml';
    public const DATACITE_NS = 'http://datacite.org/schema/kernel-4';
    public const COMMUNITY_URL_PREFIX = 'https://baobab.wacren.net/communities/';

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     * @throws Ccsd_Error
     */
    public static function hookApiRecords(array $hookParams): array
    {
        if (!isset($hookParams[Episciences_Repositories_Common::META_IDENTIFIER])) {
            return [];
        }

        $identifier = $hookParams[Episciences_Repositories_Common::META_IDENTIFIER];

        try {
            $json = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $identifier);
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage(), (int)$e->getCode());
        }

        if ($json === false) {
            throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        }

        if (!is_array($json)) {
            throw new Ccsd_Error('Unexpected API response format');
        }

        $data = $json;

        $language = Episciences_Repositories_Common::convertTo2LetterCode(
            $json['metadata']['languages'][0]['id'] ?? null
        ) ?? 'en';

        $body = [];
        $relatedIdentifiers = [];

        try {
            $dataciteXml = Episciences_Tools::callApi(
                self::API_RECORDS_URL . '/' . $identifier,
                ['headers' => ['Accept' => self::DATACITE_MIME]]
            );

            if (is_string($dataciteXml) && trim($dataciteXml) !== '') {
                [$body, $relatedIdentifiers] = self::extractFromDataCite($dataciteXml, $language);
            }
        } catch (GuzzleException|InvalidArgumentException $e) {
            Episciences_View_Helper_Log::log($e->getMessage());
        }

        if ($body === []) {
            // Degraded fallback: DataCite could not be fetched or parsed. Build a
            // minimal body from the JSON alone rather than fail the submission.
            $jsonTitle = trim((string)($json['metadata']['title'] ?? ''));
            $body['title'] = $jsonTitle !== '' ? [['value' => $jsonTitle, 'language' => $language]] : [];
        }

        $dcType = $body['type'] ?? '';
        $body['language'] = $language;

        if (($json['access']['status'] ?? '') === 'open') {
            $body['rights'][] = 'info:eu-repo/semantics/openAccess';
        }

        $headers = [
            'identifier' => $json['links']['self_html'] ?? '',
            'datestamp' => Episciences_Repositories_Common::safeDateFormat($json['updated'] ?? $json['created'] ?? ''),
        ];

        [$creatorsDc, $authors] = self::extractAuthorsFromJson($json['metadata']['creators'] ?? []);

        if (!empty($creatorsDc)) {
            $body['creator'] = $creatorsDc;
        }

        $license = $json['metadata']['rights'][0]['props']['url'] ?? '';

        if ($license !== '') {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::LICENSE_ENRICHMENT] = $license;
        }

        $data[Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY] = self::extractConceptIdentifier($json);

        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $authors,
            Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT => $dcType,
            Episciences_Repositories_Common::FILES => $json['files']['entries'] ?? [],
            Episciences_Repositories_Common::RELATED_IDENTIFIERS => self::filterOutCommunityLinks($relatedIdentifiers),
        ];

        Episciences_Repositories_Common::assembleData(['headers' => $headers, 'body' => $body], $enrichment, $data);

        $data['record'] = Episciences_Repositories_Common::toDublinCore($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);

        return $data;
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = trim((string)($hookParams['id'] ?? ''));

        if ($identifier !== '' && preg_match('#^ark:/?\d+/#', $identifier) === 1) {
            $resolved = self::resolveArk($identifier);

            return [Episciences_Repositories_Common::META_IDENTIFIER => $resolved ?? $identifier];
        }

        // The submission form's JS helper (public/js/submit/index.js) strips the
        // scheme and host from a pasted URL but, for a repository outside its
        // known special cases (Dataverse, arXiv), leaves the first path segment
        // ("records/...") in the field it hands over — so this must strip a bare
        // leading "records/"/"uploads/" too, not only after a full "https://host/".
        $identifier = preg_replace('#^https?://[^/]+/#', '', $identifier);
        $identifier = preg_replace('#^(?:records|uploads)/#', '', (string)$identifier);
        // Drop a trailing query string (?preview=1) or a /files/... segment left
        // over from a notice URL; a bare slug has neither and passes through as is.
        $identifier = preg_replace('#\?.*$#', '', (string)$identifier);
        $identifier = preg_replace('#/files/.*$#', '', (string)$identifier);
        // A trailing slash left over from a browser address bar (copy-pasted with it)
        // would otherwise reach the API as an extra path segment and fail to resolve.
        $identifier = rtrim((string)$identifier, '/');

        return [Episciences_Repositories_Common::META_IDENTIFIER => $identifier];
    }

    /**
     * @param array<string, mixed> $hookParams ['identifier' => '...', 'response' => []]
     * @return array<string, mixed>
     * @throws Ccsd_Error
     */
    public static function hookVersion(array $hookParams): array
    {
        // metadata.version is always null on BAOBAB: versions.index is the only
        // source, with the same previousVersion+1 fallback as Zenodo.
        $response = self::checkResponse($hookParams);

        $previousVersion = $hookParams['context']['previousVersion'] ?? null;
        $version = $response['versions']['index'] ?? ($previousVersion !== null ? $previousVersion + 1 : null);

        if (!$version) {
            return [];
        }

        return ['version' => $version];
    }

    /**
     * @return array<string, bool>
     */
    public static function hookIsRequiredVersion(): array
    {
        return ['result' => false];
    }

    /**
     * @return array<string, bool>
     */
    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => false];
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function hookFilesProcessing(array $hookParams): array
    {
        $entries = $hookParams['files'] ?? [];

        if (empty($entries)) {
            $response = self::checkResponse($hookParams);
            $entries = $response['files']['entries'] ?? [];
        }

        $data = [];

        foreach ($entries as $entry) {
            $data[] = self::buildFileRow($entry, $hookParams['docId'], $hookParams['repoId']);
        }

        $hookParams['affectedRows'] = Episciences_Paper_FilesManager::insert($data);

        return $hookParams;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function buildFileRow(array $entry, int $docId, int $repoId): array
    {
        $explodedChecksum = explode(':', (string)($entry['checksum'] ?? ''));

        // links.self is the file's JSON metadata on InvenioRDM 13.1, not its
        // content, unlike the legacy Zenodo API: links.content is mandatory here.
        $selfLink = $entry['links']['content'] ?? null;

        if ($selfLink === null && isset($entry['links']['self'])) {
            Episciences_View_Helper_Log::log(sprintf(
                'BAOBAB file "%s" has no links.content; falling back to links.self, which points to file metadata, not content',
                (string)($entry['key'] ?? '')
            ));
            $selfLink = $entry['links']['self'];
        }

        return [
            'doc_id' => $docId,
            'source' => $repoId,
            'file_name' => $entry['key'],
            'file_type' => $entry['ext'] ?? pathinfo($entry['key'], PATHINFO_EXTENSION),
            'file_size' => $entry['size'] ?? 0,
            'checksum' => $explodedChecksum[array_key_last($explodedChecksum)] ?? null,
            'checksum_type' => $explodedChecksum[array_key_first($explodedChecksum)] ?? null,
            'self_link' => $selfLink,
        ];
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        $response = self::checkResponse($hookParams);
        $relatedIdentifiers = $response[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RELATED_IDENTIFIERS] ?? [];

        $affectedRows = Episciences_Submit::processDatasets($hookParams['docId'], $relatedIdentifiers);

        if ($affectedRows > 0) {
            $paper = Episciences_PapersManager::get((int)$hookParams['docId'], false);
            if ($paper && $paper->isPublished()) {
                try {
                    SolrIndexing::enqueueIndex($paper->getDocid());
                } catch (Exception $e) {
                    trigger_error($e->getMessage());
                }
            }
        }

        $response['affectedRows'] = $affectedRows;
        return $response;
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     * @throws Ccsd_Error
     */
    private static function checkResponse(array $hookParams): array
    {
        return Episciences_Repositories_Common::resolveResponse($hookParams, [self::class, 'hookApiRecords']);
    }

    /**
     * @param array<string, mixed> $json
     */
    private static function extractConceptIdentifier(array $json): ?string
    {
        return $json['parent']['id'] ?? null;
    }

    /**
     * Resolves an ARK to its current InvenioRDM slug. Neither /api/records/{ark}
     * nor a plain-text search matches it; only a search on the PID field does.
     *
     * @return string|null the resolved slug, or null if resolution found anything
     *                      other than exactly one record (so the caller falls back
     *                      to the unresolved input and hookApiRecords() fails cleanly
     *                      with ID_DOES_NOT_EXIST_CODE, instead of guessing).
     */
    private static function resolveArk(string $ark): ?string
    {
        $normalizedArk = preg_replace('#^ark:/?#', 'ark:/', $ark);
        $query = sprintf('pids.ark.identifier:"%s"', $normalizedArk);

        try {
            $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '?q=' . urlencode($query));
        } catch (GuzzleException $e) {
            return null;
        }

        if (!is_array($response)) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        if (count($hits) !== 1) {
            return null;
        }

        return $hits[0]['id'] ?? null;
    }

    /**
     * Compiles the Dublin Core body and the raw related identifiers from the
     * DataCite XML obtained by content negotiation. The document uses a default
     * namespace (no "datacite:" prefix at the source); registering the prefix on
     * our side is enough for the xpaths below to resolve regardless.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, string>>}
     * @throws InvalidArgumentException if the XML cannot be parsed
     */
    private static function extractFromDataCite(string $xmlString, string $language): array
    {
        libxml_use_internal_errors(true);
        $metadata = simplexml_load_string($xmlString);
        libxml_clear_errors();

        if ($metadata === false) {
            throw new InvalidArgumentException('Invalid DataCite XML');
        }

        $metadata->registerXPathNamespace('datacite', self::DATACITE_NS);

        $titles = Episciences_Repositories_Common::extractMultilingualContent($metadata, '//datacite:titles/datacite:title', $language);
        $subjects = Episciences_Repositories_Common::extractMultilingualContent($metadata, '//datacite:subjects/datacite:subject', $language);
        $descriptions = self::extractDescriptions($metadata, $language);

        $typeNodes = $metadata->xpath('//datacite:resourceType');
        $type = '';

        if (!empty($typeNodes)) {
            // On a preprint, the node's text is empty and only the attribute carries
            // the value ("Preprint"): resourceTypeGeneral is the required fallback.
            $type = trim((string)$typeNodes[0]);

            if ($type === '') {
                $type = (string)($typeNodes[0]['resourceTypeGeneral'] ?? '');
            }
        }

        $dateNodes = $metadata->xpath('//datacite:dates/datacite:date[@dateType="Issued"]');

        if (empty($dateNodes)) {
            $dateNodes = $metadata->xpath('//datacite:dates/datacite:date');
        }

        $date = !empty($dateNodes) ? (string)$dateNodes[0] : '';

        $identifiers = [];

        foreach ($metadata->xpath('//datacite:alternateIdentifiers/datacite:alternateIdentifier') as $node) {
            $identifiers[] = (string)$node;
        }

        $rightsNodes = $metadata->xpath('//datacite:rightsList/datacite:rights');

        if (empty($rightsNodes)) {
            $rightsNodes = $metadata->xpath('//datacite:rights');
        }

        $rights = [];

        foreach ($rightsNodes as $node) {
            $uri = (string)($node['rightsURI'] ?? '');
            $rights[] = $uri !== '' ? $uri : trim((string)$node);
        }

        $publisherNodes = $metadata->xpath('//datacite:publisher');
        $publisher = !empty($publisherNodes) ? (string)$publisherNodes[0] : '';

        $body = [
            'title' => $titles,
            'subject' => $subjects,
            Episciences_Repositories_Common::META_DESCRIPTION => $descriptions,
            'type' => $type,
            'date' => $date,
            Episciences_Repositories_Common::META_IDENTIFIER => $identifiers,
            'rights' => $rights,
            'publisher' => $publisher,
        ];

        $relatedIdentifiers = Episciences_Repositories_Common::extractRelatedIdentifiersFromMetadata($metadata);

        return [$body, $relatedIdentifiers];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function extractDescriptions(SimpleXMLElement $metadata, string $language): array
    {
        return Episciences_Repositories_Common::extractDescriptions($metadata, $language, true);
    }

    /**
     * Authors come from the JSON, not from DataCite's creatorName: InvenioRDM
     * writes it as "Family, Given", which Common::extractPersons()/processPerson()
     * would mis-split (it assumes "Given Family" and pops the last word). The JSON
     * gives given_name/family_name apart, with no splitting heuristic needed.
     *
     * @param array<int, array<string, mixed>> $creators
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private static function extractAuthorsFromJson(array $creators): array
    {
        $creatorsDc = [];
        $authors = [];

        foreach ($creators as $creatorEntry) {
            $person = $creatorEntry['person_or_org'] ?? [];
            $given = trim((string)($person['given_name'] ?? ''));
            $family = trim((string)($person['family_name'] ?? ''));
            $fullname = trim($given . ' ' . $family);

            if ($fullname === '') {
                $fullname = trim((string)($person['name'] ?? ''));
            }

            if ($fullname === '') {
                continue;
            }

            $creatorsDc[] = $fullname;

            $author = [
                'fullname' => $fullname,
                'given' => $given,
                'family' => $family !== '' ? $family : $fullname,
            ];

            foreach ($person['identifiers'] ?? [] as $identifier) {
                if (($identifier['scheme'] ?? '') === 'orcid' && !empty($identifier['identifier'])) {
                    $author['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($identifier['identifier']);
                }
            }

            $affiliations = [];

            foreach ($creatorEntry['affiliations'] ?? [] as $affiliation) {
                if (!empty($affiliation['name'])) {
                    $affiliations[] = ['name' => $affiliation['name']];
                }
            }

            if (!empty($affiliations)) {
                $author['affiliation'] = $affiliations;
            }

            $authors[] = $author;
        }

        return [$creatorsDc, $authors];
    }

    /**
     * Most of the 19 BAOBAB records carrying related_identifiers only point to a
     * community the record belongs to (IsPartOf a /communities/... URL); those are
     * not datasets and must never reach PAPER_DATASETS.
     *
     * @param array<int, array<string, string>> $relatedIdentifiers
     * @return array<int, array<string, string>>
     */
    private static function filterOutCommunityLinks(array $relatedIdentifiers): array
    {
        return array_values(array_filter(
            $relatedIdentifiers,
            static fn(array $relatedIdentifier): bool => !str_starts_with($relatedIdentifier['identifier'] ?? '', self::COMMUNITY_URL_PREFIX)
        ));
    }
}
