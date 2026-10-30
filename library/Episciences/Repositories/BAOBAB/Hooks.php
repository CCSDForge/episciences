<?php

use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\ConceptIdentifierInterface;
use Episciences\Repositories\FilesEnrichmentInterface;
use Episciences\Repositories\InputSanitizerInterface;
use Episciences\Repositories\LinkedDataEnrichmentInterface;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use GuzzleHttp\Exception\GuzzleException;

/**
 * BAOBAB (WACREN), an InvenioRDM 14 instance. Its OAI-PMH endpoint used to
 * return a 500 as soon as a record had to be emitted (GetRecord/ListIdentifiers/
 * ListRecords); this was fixed by an InvenioRDM upgrade on the WACREN side
 * (verified 2026-09-08), so the DataCite body compiled into PAPERS.RECORD is now
 * fetched over OAI-PMH (metadata_sources.base_url), not InvenioRDM's oai_dc,
 * which double-escapes HTML in dc:description on this corpus. When base_url is
 * not set or OAI-PMH fails, the same DataCite serializer is fetched through the
 * REST API's content negotiation. The REST API remains the source for files,
 * authors, access status and the concept identifier, none of which OAI-PMH
 * carries.
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
    public const DATACITE_NS = 'http://datacite.org/schema/kernel-4';
    public const COMMUNITY_URL_PREFIX = 'https://baobab.wacren.net/communities/';
    public const UPLOADS_URL_PREFIX = 'https://baobab.wacren.net/uploads/';
    public const RECORDS_URL_PREFIX = 'https://baobab.wacren.net/records/';
    public const DATACITE_MIME_TYPE = 'application/vnd.datacite.datacite+xml';
    public const ROR_URL_PREFIX = 'https://ror.org/';
    public const ROR_URL_PREFIX_PATTERN = '#^(?:https?://)?(?:www\.)?ror\.org/#i';
    // Seconds; every call runs inside an interactive request (submission, Paper::save())
    public const API_TIMEOUT = 10;
    // An InvenioRDM record id is a single URL path segment ("xkpt8-rjr81")
    public const RECORD_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';
    public const ROR_ID_PATTERN = '/^0[a-hj-km-np-tv-z0-9]{6}[0-9]{2}$/';
    public const ARK_PATTERN = '#^(?:https?://[^/]+/)?(ark:/?\d+/[^?\#\s]+)#';

    /**
     * Last record fetched by fetchRecord(): hookApiRecords() fetches it again,
     * then the hooks run without a response and the licence lookup that follow
     * in the same process (PapersManager::updateRecordData()) reuse it.
     * A single entry, so a batch over many records does not grow it.
     *
     * @var array{0: string, 1: array<string, mixed>}|null [record id, REST JSON]
     */
    private static ?array $lastRecord = null;

    /**
     * @param array<string, mixed> $hookParams
     * @param bool $freshRecord false to reuse the record kept from the previous fetch
     * @return array<string, mixed>
     * @throws Ccsd_Error
     */
    public static function hookApiRecords(array $hookParams, bool $freshRecord = true): array
    {
        if (!isset($hookParams[Episciences_Repositories_Common::META_IDENTIFIER])) {
            return [];
        }

        $identifier = (string)$hookParams[Episciences_Repositories_Common::META_IDENTIFIER];

        try {
            $json = self::fetchRecord($identifier, $freshRecord);
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage(), (int)$e->getCode());
        }

        if ($json === null) {
            throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        }

        $data = $json;

        $language = Episciences_Repositories_Common::convertTo2LetterCode(
            $json['metadata']['languages'][0]['id'] ?? null
        ) ?? 'en';

        $body = [];
        $relatedIdentifiers = [];

        $repoId = $hookParams['repoId'] ?? Episciences_Repositories::BAOBAB_REPO_ID;
        $dataCite = self::fetchDataCite($repoId, $identifier);

        if ($dataCite !== null) {
            [$body, $relatedIdentifiers] = self::extractFromDataCite($dataCite, $language);
        }

        if (empty($body['title'])) {
            // Degraded fallback: DataCite could not be fetched or parsed, or carried
            // no title. Take the title from the JSON rather than fail the submission.
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

        $license = self::extractLicenceUrl($json);

        if ($license !== '') {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::LICENSE_ENRICHMENT] = $license;
        }

        $data[Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY] = self::extractConceptIdentifier($json);

        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $authors,
            Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT => $dcType,
            Episciences_Repositories_Common::FILES => $json['files']['entries'] ?? [],
            Episciences_Repositories_Common::RELATED_IDENTIFIERS => self::filterOutCommunityLinks($relatedIdentifiers, (string)($json['id'] ?? $identifier)),
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

        // A bare ARK, or an ARK resolver URL (https://pidslink.ren.africa/ark:/50962/...)
        if (preg_match(self::ARK_PATTERN, $identifier, $matches) === 1) {
            $ark = rtrim($matches[1], '/');
            $resolved = self::resolveArk($ark);

            return [Episciences_Repositories_Common::META_IDENTIFIER => $resolved ?? $ark];
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

        // links.self is the file's JSON metadata on InvenioRDM 14, not its
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
     * REST JSON of a record, or null when the identifier is not a record id
     * (e.g. an ARK left unresolved by hookCleanIdentifiers()) or the record does
     * not exist.
     *
     * @param bool $fresh true to ignore the record kept from the previous call
     * @return array<string, mixed>|null
     * @throws GuzzleException
     */
    public static function fetchRecord(string $identifier, bool $fresh = false): ?array
    {
        if ($fresh) {
            // A failed reload must not leave the previous record behind
            self::$lastRecord = null;
        } elseif (self::$lastRecord !== null && self::$lastRecord[0] === $identifier) {
            return self::$lastRecord[1];
        }

        if (preg_match(self::RECORD_ID_PATTERN, $identifier) !== 1) {
            return null;
        }

        $json = Episciences_Tools::callApi(
            self::API_RECORDS_URL . '/' . rawurlencode($identifier),
            ['headers' => ['Content-type' => 'application/json'], 'timeout' => self::API_TIMEOUT]
        );

        if (!is_array($json)) {
            return null;
        }

        self::$lastRecord = [$identifier, $json];

        return $json;
    }

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     * @throws Ccsd_Error
     */
    private static function checkResponse(array $hookParams): array
    {
        // A hook run without a response follows hookApiRecords() in the same
        // process (PapersManager::updateRecordData()): reuse its record
        return Episciences_Repositories_Common::resolveResponse(
            $hookParams,
            static fn(array $params): array => self::hookApiRecords($params, false)
        );
    }

    /**
     * Licence URL of an InvenioRDM record, from its REST JSON. Shared with
     * Episciences_Paper_LicenceManager, which stores the paper licence.
     *
     * A custom licence's "link" is free text typed by the depositor and the
     * stored licence ends up in an href: anything but an http(s) URL is dropped.
     * The first valid URL wins, across all rights entries and, within an entry,
     * props.url before link.
     *
     * @param array<string, mixed> $json
     */
    public static function extractLicenceUrl(array $json): string
    {
        foreach ($json['metadata']['rights'] ?? [] as $rights) {
            if (!is_array($rights)) {
                continue;
            }

            foreach ([$rights['props']['url'] ?? null, $rights['link'] ?? null] as $candidate) {
                $url = trim((string)$candidate);

                if (Episciences_Tools::isHttpUrl($url)) {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * DataCite metadata of the record: over OAI-PMH when metadata_sources.base_url
     * is set, otherwise (or if OAI-PMH fails or returns no DataCite resource, e.g.
     * a deleted record) through the REST API's content negotiation, which serves
     * the same InvenioRDM serializer.
     */
    private static function fetchDataCite(int|string $repoId, string $identifier): ?SimpleXMLElement
    {
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);
        $oaiIdentifier = Episciences_Repositories::getIdentifier($repoId, $identifier);

        if ($baseUrl !== null && $baseUrl !== '' && $oaiIdentifier !== null) {
            try {
                $xml = Episciences_Repositories_Common::getRecord($baseUrl, $oaiIdentifier, 'datacite');
                $dataCite = is_string($xml) ? self::parseDataCite($xml) : null;

                if ($dataCite !== null) {
                    return $dataCite;
                }
            } catch (Ccsd_Error $e) {
                Episciences_View_Helper_Log::log($e->getMessage());
            }
        }

        try {
            $xml = Episciences_Tools::callApi(
                self::API_RECORDS_URL . '/' . rawurlencode($identifier),
                ['headers' => ['Accept' => self::DATACITE_MIME_TYPE], 'timeout' => self::API_TIMEOUT]
            );
        } catch (GuzzleException $e) {
            Episciences_View_Helper_Log::log($e->getMessage());
            return null;
        }

        return is_string($xml) ? self::parseDataCite($xml) : null;
    }

    /**
     * Parses an OAI-PMH answer or a bare DataCite document, with the "datacite"
     * prefix registered. Null when the XML is unusable or carries no DataCite
     * resource.
     */
    private static function parseDataCite(string $xml): ?SimpleXMLElement
    {
        if (trim($xml) === '') {
            return null;
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($document === false) {
            return null;
        }

        $document->registerXPathNamespace('datacite', self::DATACITE_NS);

        return empty($document->xpath('//datacite:resource')) ? null : $document;
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
            $response = Episciences_Tools::callApi(
                self::API_RECORDS_URL . '?q=' . urlencode($query),
                ['headers' => ['Content-type' => 'application/json'], 'timeout' => self::API_TIMEOUT]
            );
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
     * DataCite document returned by fetchDataCite(). It uses a default
     * namespace (no "datacite:" prefix at the source); registering the prefix on
     * our side is enough for the xpaths below to resolve regardless.
     *
     * @param SimpleXMLElement $metadata as returned by parseDataCite()
     * @return array{0: array<string, mixed>, 1: array<int, array<string, string>>}
     */
    private static function extractFromDataCite(SimpleXMLElement $metadata, string $language): array
    {
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
     * Abstracts first; an "Other" description is kept only when written in a
     * language no abstract covers. BAOBAB depositors put a translated abstract in
     * an "Other" additional description (xml:lang="eng" beside a French Abstract);
     * other types (TechnicalInfo, Methods...) are never promoted to an abstract.
     *
     * @return array<int, array<string, string>>
     */
    private static function extractDescriptions(SimpleXMLElement $metadata, string $language): array
    {
        $descriptions = Episciences_Repositories_Common::extractDescriptions($metadata, $language, false, true, 'Abstract');
        $coveredLanguages = array_column($descriptions, 'language');

        foreach (Episciences_Repositories_Common::extractDescriptions($metadata, $language, false, true, 'Other') as $description) {
            if (!in_array($description['language'], $coveredLanguages, true)) {
                $descriptions[] = $description;
                $coveredLanguages[] = $description['language'];
            }
        }

        return $descriptions;
    }

    /**
     * Authors come from the JSON, not from DataCite's creatorName: InvenioRDM
     * writes it as "Family, Given", which Common::extractPersons()/processPerson()
     * would mis-split (it assumes "Given Family" and pops the last word). The JSON
     * gives given_name/family_name apart, with no splitting heuristic needed.
     *
     * An organisational creator's ROR has no slot on an Episciences author: it is
     * kept as the creator's own affiliation (only when it has a ROR, the name
     * alone would just repeat the author's), and lent to any other author's
     * free-text affiliation bearing the same name.
     *
     * @param array<int, array<string, mixed>> $creators
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private static function extractAuthorsFromJson(array $creators): array
    {
        $creatorsDc = [];
        $authors = [];
        $organisationRors = self::collectOrganisationRors($creators);

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

            $affiliations = self::buildAffiliations($creatorEntry['affiliations'] ?? [], $organisationRors);

            if (
                $affiliations === []
                && ($person['type'] ?? '') === 'organizational'
                && self::extractAffiliationRor($person) !== null
            ) {
                $affiliations = self::buildAffiliations([$person], $organisationRors);
            }

            if (!empty($affiliations)) {
                $author['affiliation'] = $affiliations;
            }

            $authors[] = $author;
        }

        return [$creatorsDc, $authors];
    }

    /**
     * @param array<int, array<string, mixed>> $creators
     * @return array<string, string> lower-cased organisation name => full ROR URL
     */
    private static function collectOrganisationRors(array $creators): array
    {
        $rors = [];

        foreach ($creators as $creatorEntry) {
            $person = $creatorEntry['person_or_org'] ?? [];
            $name = mb_strtolower(trim((string)($person['name'] ?? '')));
            $ror = self::extractAffiliationRor($person);

            if (($person['type'] ?? '') === 'organizational' && $name !== '' && $ror !== null) {
                $rors[$name] = $ror;
            }
        }

        return $rors;
    }

    /**
     * @param array<int, array<string, mixed>> $rawAffiliations
     * @param array<string, string> $organisationRors
     * @return array<int, array<string, mixed>>
     */
    private static function buildAffiliations(array $rawAffiliations, array $organisationRors): array
    {
        $affiliations = [];

        foreach ($rawAffiliations as $affiliation) {
            $affiliationName = trim((string)($affiliation['name'] ?? ''));

            if ($affiliationName === '') {
                continue;
            }

            $ror = self::extractAffiliationRor($affiliation) ?? $organisationRors[mb_strtolower($affiliationName)] ?? null;

            $affiliations[] = $ror !== null
                ? Episciences_Paper_Authors_AffiliationHelper::buildWithRor(['name' => $affiliationName, 'ROR' => $ror])
                : Episciences_Paper_Authors_AffiliationHelper::buildNameOnly($affiliationName);
        }

        return $affiliations;
    }

    /**
     * An affiliation picked from InvenioRDM's affiliations vocabulary carries its
     * ROR id twice (bare "id" and identifiers[scheme=ror]); a free-text one has
     * neither.
     *
     * @param array<string, mixed> $affiliation
     * @return string|null the full ROR URL
     */
    private static function extractAffiliationRor(array $affiliation): ?string
    {
        $rorId = '';

        foreach ($affiliation['identifiers'] ?? [] as $identifier) {
            if (($identifier['scheme'] ?? '') === 'ror' && !empty($identifier['identifier'])) {
                $rorId = (string)$identifier['identifier'];
                break;
            }
        }

        if ($rorId === '') {
            $rorId = (string)($affiliation['id'] ?? '');
        }

        $rorId = (string)preg_replace(self::ROR_URL_PREFIX_PATTERN, '', trim($rorId));

        // A ROR id is "0" + 6 base32 characters + a 2-digit checksum.
        return preg_match(self::ROR_ID_PATTERN, $rorId) === 1 ? self::ROR_URL_PREFIX . $rorId : null;
    }

    /**
     * Two kinds of BAOBAB related identifiers are not related works and must never
     * reach PAPER_DATASETS: the communities a record belongs to (IsPartOf a
     * /communities/... URL), and the record's own deposit page (IsMetadataFor
     * /uploads/{id}, which redirects to the record itself).
     *
     * @param array<int, array<string, string>> $relatedIdentifiers
     * @return array<int, array<string, string>>
     */
    private static function filterOutCommunityLinks(array $relatedIdentifiers, string $recordId = ''): array
    {
        $selfLinks = $recordId !== '' ? [
            self::UPLOADS_URL_PREFIX . $recordId,
            self::RECORDS_URL_PREFIX . $recordId,
        ] : [];

        return array_values(array_filter(
            $relatedIdentifiers,
            static function (array $relatedIdentifier) use ($selfLinks): bool {
                $identifier = rtrim($relatedIdentifier['identifier'] ?? '', '/');

                return !str_starts_with($identifier, self::COMMUNITY_URL_PREFIX)
                    && !in_array($identifier, $selfLinks, true);
            }
        ));
    }
}
